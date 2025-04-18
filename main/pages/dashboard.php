<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session and check if user is logged in
session_start();
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: auth.php");
    exit;
}

// Include database configuration
require_once "../../config.php";

// Get current user ID from session
$userId = $_SESSION["id"];

// Get total recipe count for the user
$recipe_count = 0;
$sql_recipes = "SELECT COUNT(*) as count FROM recipes WHERE user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql_recipes)) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $recipe_count = $row['count'];
        }
    } else {
        echo "Error (recipes): " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Get total meal plan count for the user
$meal_plan_count = 0;
$sql_meal_plans = "SELECT COUNT(*) as count FROM meal_plans WHERE user_id = ?";
if ($stmt = mysqli_prepare($conn, $sql_meal_plans)) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $meal_plan_count = $row['count'];
        }
    } else {
        echo "Error (meal plans): " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Get active shopping list items count
$shopping_list_count = 0;
$sql_shopping = "SELECT COUNT(*) as count FROM shopping_list_items WHERE user_id = ? AND completed = 0";
if ($stmt = mysqli_prepare($conn, $sql_shopping)) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            $shopping_list_count = $row['count'];
        }
    } else {
        echo "Error (shopping list): " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}

// Get current active meal plan
$current_plan = null;
$today = date('Y-m-d');

// Query to get current meal plan with slot counts
$sql_current_plan = "SELECT mp.*, 
    (SELECT COUNT(*) FROM meal_plan_slots mps WHERE mps.plan_id = mp.id) as total_slots,
    (SELECT COUNT(*) FROM meal_plan_slots mps JOIN meal_plan_items mpi ON mps.id = mpi.slot_id WHERE mps.plan_id = mp.id) as filled_slots
    FROM meal_plans mp 
    WHERE mp.user_id = ? AND mp.end_date >= ? 
    ORDER BY mp.start_date ASC
    LIMIT 1";

if ($stmt = mysqli_prepare($conn, $sql_current_plan)) {
    mysqli_stmt_bind_param($stmt, "is", $userId, $today);
    
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) == 1) {
            $current_plan = mysqli_fetch_assoc($result);
            
            // Get detailed meal plan slots with recipe information
            $sql_slots = "SELECT mps.*, mpi.recipe_id, r.name as recipe_name, r.image_path, r.calories 
                FROM meal_plan_slots mps 
                LEFT JOIN meal_plan_items mpi ON mps.id = mpi.slot_id 
                LEFT JOIN recipes r ON mpi.recipe_id = r.id 
                WHERE mps.plan_id = ? 
                ORDER BY mps.date ASC, FIELD(mps.meal_type, 'breakfast', 'lunch', 'dinner', 'snacks')";

            if ($stmt_slots = mysqli_prepare($conn, $sql_slots)) {
                mysqli_stmt_bind_param($stmt_slots, "i", $current_plan['id']);
                if (mysqli_stmt_execute($stmt_slots)) {
                    $result_slots = mysqli_stmt_get_result($stmt_slots);
                    
                    $current_plan['slots'] = array();
                    $current_plan['days'] = array();
                    
                    // Organize meal plan slots by day and meal type
                    while ($slot = mysqli_fetch_assoc($result_slots)) {
                        $date = $slot['date'];
                        $day_name = date('l', strtotime($date));
                        $formatted_date = date('M d', strtotime($date));
                        
                        if (!isset($current_plan['days'][$date])) {
                            $current_plan['days'][$date] = array(
                                'date' => $date,
                                'day_name' => $day_name,
                                'formatted_date' => $formatted_date,
                                'meals' => array(),
                                'total_calories' => 0
                            );
                        }
                        
                        $current_plan['days'][$date]['meals'][$slot['meal_type']] = $slot;
                        
                        if (!empty($slot['calories']) && !empty($slot['recipe_id'])) {
                            $current_plan['days'][$date]['total_calories'] += $slot['calories'];
                        }

                        $current_plan['slots'][] = $slot;
                    }
                } else {
                    echo "Error (slots): " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt_slots);
            }
        }
    } else {
        echo "Error (current plan): " . mysqli_error($conn);
    }

    mysqli_stmt_close($stmt);
}

// Set default empty plan if none exists
if (!$current_plan) {
    $current_plan = [
        'slots' => [],
        'days' => []
    ];
}

// Get user's most recent recipes
$recent_recipes = array();
$sql_recent = "SELECT id, name, category, prep_time, servings, calories, protein, carbs, fiber, diet_type, image_path, created_at 
        FROM recipes 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 4";

if ($stmt = mysqli_prepare($conn, $sql_recent)) {
    mysqli_stmt_bind_param($stmt, "i", $userId);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        while ($recipe = mysqli_fetch_assoc($result)) {
            $recent_recipes[] = $recipe;
        }
    } else {
        echo "Error (recent recipes): " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/logo.png" sizes="62x62">
    <title>CookBook | Dashboard</title>
    <!-- Include Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Include Tailwind CSS -->
    <link rel="stylesheet" href="../src/output.css">
    <style>
        /* Custom font imports */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@300;400;500;600;700&display=swap');

        /* Sidebar link hover and active states */
        .sidebar-link {
            transition: all 0.3s ease;
        }

        .sidebar-link:hover {
            background-color: rgba(255, 215, 0, 0.1);
        }

        .sidebar-link.active {
            border-left: 3px solid #ffd700;
            background-color: rgba(255, 215, 0, 0.1);
        }

        /* Card hover effects */
        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
            0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Recipe card styling */
        .recipe-card {
            transition: all 0.3s ease;
        }

        .recipe-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
            0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .recipe-card:hover .recipe-overlay {
            opacity: 1;
        }

        .recipe-overlay {
            opacity: 0;
            transition: opacity 0.3s ease;
            background: rgba(28, 28, 28, 0.7);
        }

        /* Modal animation */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-animation {
            animation: modalFadeIn 0.3s ease forwards;
        }
    </style>
</head>
<body class="font-sans bg-white text-text min-h-screen flex flex-col">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Left Sidebar Navigation -->
        <aside class="bg-black text-white w-full md:w-64 flex-shrink-0 md:flex flex-col hidden">
            <div class="p-4 border-b border-gray-800">
                <h2 class="font-serif text-2xl font-bold text-yellow-300">
                    <a href="../index.php">CookBook</a>
                </h2>
            </div>

            <nav class="flex-grow py-4">
                <ul class="space-y-1">
                    <li>
                        <a href="dashboard.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-home mr-3 text-yellow-300"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="add-recipe.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-book mr-3 text-yellow-300"></i>
                            <span>My Recipes</span>
                        </a>
                    </li>
                    <li>
                        <a href="meal-plan.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3 text-yellow-300"></i>
                            <span>Meal Plans</span>
                        </a>
                    </li>
                    <li>
                        <a href="shopping-list.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-shopping-basket mr-3 text-yellow-300"></i>
                            <span>Shopping List</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="p-4 border-t border-gray-800">
                <a href="../auth/logout.php" class="logout-button flex items-center text-gray-300 hover:text-white">
                    <i class="fas fa-sign-out-alt mr-3 text-yellow-300"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Mobile Sidebar Toggle -->
        <div class="md:hidden bg-black text-white p-4 flex justify-between items-center">
            <h2 class="font-serif text-xl font-bold text-yellow-300">CookBook</h2>
            <button id="mobile-menu-button" class="text-white focus:outline-none">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>

        <!-- Mobile Sidebar Menu (Hidden by default) -->
        <div id="mobile-menu" class="md:hidden hidden bg-black text-white w-full absolute z-50 top-16 left-0 shadow-lg">
            <nav class="py-2">
                <ul>
                    <li>
                        <a href="dashboard.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-home mr-3 text-yellow-300"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="add-recipe.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-book mr-3 text-yellow-300"></i>
                            <span>My Recipes</span>
                        </a>
                    </li>
                    <li>
                        <a href="meal-plan.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3 text-yellow-300"></i>
                            <span>Meal Plans</span>
                        </a>
                    </li>
                    <li>
                        <a href="shopping-list.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-shopping-basket mr-3 text-yellow-300"></i>
                            <span>Shopping List</span>
                        </a>
                    </li>
                    <li>
                        <a href="../auth/logout.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-sign-out-alt mr-3 text-yellow-300"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content Area -->
        <div class="flex-grow flex flex-col">
            <!-- Top Navigation Bar -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center">
                <div class="flex items-center">
                    <h1 class="text-xl font-medium text-black hidden md:block">
                        Dashboard
                    </h1>
                </div>

                <div class="flex items-center">
                    <span class="mr-4 text-text">Welcome, <span id="user-name" class="font-medium"><?php echo htmlspecialchars($_SESSION["name"]); ?></span>!</span>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center focus:outline-none cursor-pointer">
                            <div id="user-initials" class="w-10 h-10 rounded-full bg-yellow-300 text-black flex items-center justify-center font-medium mr-2">
                                <?php 
                                    $initials = '';
                                    $name_parts = explode(' ', $_SESSION["name"]);
                                    foreach ($name_parts as $part) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                    }
                                    echo htmlspecialchars($initials);
                                ?>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-text"></i>
                        </button>

                        <!-- User Dropdown Menu (Hidden by default) -->
                        <div id="user-menu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-text hover:bg-white">Your Profile</a>
                            <div class="border-t border-gray-200"></div>
                            <a href="../auth/logout.php" class="block px-4 py-2 text-sm text-text hover:bg-white">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="flex-grow p-6 overflow-auto">
                <div class="mb-8">
                    <h2 class="text-2xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-black to-gray-700 mb-6 tracking-tight">
                        Overview
                    </h2>

                    <!-- Overview Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-10">
                        <!-- Total Recipes Card -->
                        <div class="bg-white rounded-xl shadow-md p-6 w-full card-hover">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                    <i class="fas fa-book text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-text">Total Recipes</h3>
                                    <p class="text-2xl font-bold text-black"><?php echo $recipe_count; ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="add-recipe.php" class="text-black hover:underline text-sm flex items-center">
                                    <span>View all recipes</span>
                                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Meal Plans Card -->
                        <div class="bg-white rounded-xl shadow-md p-6 w-full card-hover">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                    <i class="fas fa-calendar-alt text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-text">Meal Plans</h3>
                                    <p class="text-2xl font-bold text-black"><?php echo $meal_plan_count; ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="meal-plan.php" class="text-black hover:underline text-sm flex items-center">
                                    <span>View meal plans</span>
                                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Shopping List Card -->
                        <div class="bg-white rounded-xl shadow-md p-6 w-full card-hover">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                    <i class="fas fa-shopping-basket text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-text">Shopping List</h3>
                                    <p class="text-2xl font-bold text-black"><?php echo $shopping_list_count; ?></p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="shopping-list.php" class="text-black hover:underline text-sm flex items-center">
                                    <span>View shopping list</span>
                                    <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if($current_plan): ?>
                <!-- Weekly Meal Plan Section -->
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-black to-gray-700 tracking-tight">
                            This Week's Meal Plan
                        </h2>
                        <a href="meal-plan.php" class="text-black hover:underline text-sm flex items-center">
                            <span>Edit meal plan</span>
                            <i class="fas fa-edit ml-1"></i>
                        </a>
                    </div>

                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-text uppercase tracking-wider">
                                            Day
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-text uppercase tracking-wider">
                                            Breakfast
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-text uppercase tracking-wider">
                                            Lunch
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-text uppercase tracking-wider">
                                            Dinner
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-text uppercase tracking-wider">
                                            Snacks
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-text uppercase tracking-wider">
                                            Calories
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach($current_plan['days'] as $date => $day): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-black">
                                            <?php echo $day['day_name']; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text">
                                            <?php if(isset($day['meals']['breakfast']) && !empty($day['meals']['breakfast']['recipe_id'])): ?>
                                                <?php echo htmlspecialchars($day['meals']['breakfast']['recipe_name']); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Not planned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text">
                                            <?php if(isset($day['meals']['lunch']) && !empty($day['meals']['lunch']['recipe_id'])): ?>
                                                <?php echo htmlspecialchars($day['meals']['lunch']['recipe_name']); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Not planned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text">
                                            <?php if(isset($day['meals']['dinner']) && !empty($day['meals']['dinner']['recipe_id'])): ?>
                                                <?php echo htmlspecialchars($day['meals']['dinner']['recipe_name']); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Not planned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text">
                                            <?php if(isset($day['meals']['snacks']) && !empty($day['meals']['snacks']['recipe_id'])): ?>
                                                <?php echo htmlspecialchars($day['meals']['snacks']['recipe_name']); ?>
                                            <?php else: ?>
                                                <span class="text-gray-400">Not planned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-text">
                                            <?php echo $day['total_calories']; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Explore New Recipes Section -->
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-black to-gray-700 tracking-tight">
                            Your Recent Recipes
                        </h2>
                        <a href="add-recipe.php" class="text-black hover:underline text-sm flex items-center">
                            <span>View all</span>
                            <i class="fas fa-arrow-right ml-1 text-xs"></i>
                        </a>
                    </div>

                    <?php if(empty($recent_recipes)): ?>
                    <div class="bg-white rounded-xl shadow-md p-8 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-300 bg-opacity-20 mb-4">
                            <i class="fas fa-book text-2xl text-black"></i>
                        </div>
                        <h3 class="text-lg font-medium text-black mb-2">No recipes yet</h3>
                        <p class="text-text mb-4">Start adding your favorite recipes to your collection</p>
                        <a href="add-recipe.php" class="bg-yellow-300 hover:bg-yellow-400 text-black font-medium py-2 px-4 rounded-lg shadow-sm inline-block">
                            Add Your First Recipe
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach($recent_recipes as $recipe): ?>
                        <!-- Recipe Card -->
                        <div class="recipe-card bg-white rounded-xl shadow-md overflow-hidden cursor-pointer" data-recipe="<?php echo $recipe['id']; ?>">
                            <div class="relative h-48">
                                <?php if(!empty($recipe['image_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($recipe['image_path']); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                <img src="../img/recipe-placeholder.jpg" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                                <div class="recipe-overlay absolute inset-0 flex items-center justify-center">
                                    <button class="bg-yellow-300 text-black px-4 py-2 rounded-lg font-medium view-recipe" data-id="<?php echo $recipe['id']; ?>">
                                        View Recipe
                                    </button>
                                </div>
                            </div>
                            <div class="p-4">
                                <h3 class="font-bold text-black"><?php echo htmlspecialchars($recipe['name']); ?></h3>
                                <div class="flex items-center justify-between mt-3">
                                    <div class="flex items-center text-xs text-text">
                                        <i class="far fa-clock mr-1"></i>
                                        <span><?php echo htmlspecialchars($recipe['prep_time']); ?> mins</span>
                                    </div>
                                    <div class="flex items-center text-xs text-text">
                                        <i class="fas fa-fire mr-1"></i>
                                        <span><?php echo !empty($recipe['calories']) ? htmlspecialchars($recipe['calories']) . ' cal' : 'N/A'; ?></span>
                                    </div>
                                    <span class="text-xs px-2 py-1 bg-yellow-300 bg-opacity-20 text-black rounded-full">
                                        <?php echo !empty($recipe['diet_type']) ? htmlspecialchars($recipe['diet_type']) : 'General'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- View Recipe Modal (Hidden by default) -->
<div id="view-recipe-modal" class="fixed inset-0 bg-gray-100 bg-opacity-60 flex items-center justify-center z-50 hidden transition-all duration-300">
    <div class="bg-gradient-to-t from-yellow-100 via-yellow-200 to-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto modal-animation transform scale-95 transition-transform duration-500 ease-in-out">
        <div class="relative">
            <div class="h-64 md:h-80 w-full">
                <img id="modal-image" src="../img/Alfredo.png" alt="Recipe" class="w-full h-full object-cover rounded-t-xl">
            </div>
            <button id="close-view-modal" class="absolute top-4 right-4 bg-white rounded-full p-2 shadow-lg text-black hover:text-yellow-500 focus:outline-none transition-transform transform hover:scale-110">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                <div>
                    <h2 id="modal-title" class="text-3xl font-semibold text-gray-800 tracking-tight"></h2>
                    </div>
                </div>
                <div>
                    <span id="modal-category" class="inline-block px-4 py-2 bg-yellow-300 text-black rounded-full text-sm font-medium"></span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 flex flex-col items-center justify-center shadow-lg transition-transform transform hover:scale-105">
                    <div class="text-yellow-400 mb-2">
                        <i class="far fa-clock text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-600">Prep Time</p>
                    <p id="modal-prep-time" class="font-bold text-gray-800"></p>
                </div>
                
                <div class="bg-white rounded-lg p-6 flex flex-col items-center justify-center shadow-lg transition-transform transform hover:scale-105">
                    <div class="text-yellow-400 mb-2">
                        <i class="fas fa-fire text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-600">Calories</p>
                    <p id="modal-calories" class="font-bold text-gray-800"></p>
                </div>
                
                <div class="bg-white rounded-lg p-6 flex flex-col items-center justify-center shadow-lg transition-transform transform hover:scale-105">
                    <div class="text-yellow-400 mb-2">
                        <i class="fas fa-drumstick-bite text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-600">Protein</p>
                    <p id="modal-protein" class="font-bold text-gray-800"></p>
                </div>
                
                <div class="bg-white rounded-lg p-6 flex flex-col items-center justify-center shadow-lg transition-transform transform hover:scale-105">
                    <div class="text-yellow-400 mb-2">
                        <i class="fas fa-utensils text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-600">Servings</p>
                    <p id="modal-servings" class="font-bold text-gray-800"></p>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg p-6 shadow-lg transition-transform transform hover:scale-105">
                    <div class="flex items-center mb-3">
                        <div class="text-yellow-400 mr-3">
                            <i class="fas fa-bread-slice text-lg"></i>
                        </div>
                        <p class="text-sm text-gray-600">Carbohydrates</p>
                    </div>
                    <p id="modal-carbs" class="font-bold text-gray-800"></p>
                </div>
                
                <div class="bg-white rounded-lg p-6 shadow-lg transition-transform transform hover:scale-105">
                    <div class="flex items-center mb-3">
                        <div class="text-yellow-400 mr-3">
                            <i class="fas fa-seedling text-lg"></i>
                        </div>
                        <p class="text-sm text-gray-600">Fiber</p>
                    </div>
                    <p id="modal-fiber" class="font-bold text-gray-800"></p>
                </div>
            </div>
            
            <div class="mb-8">
                <h3 class="text-2xl font-semibold text-gray-800 mb-4">Ingredients</h3>
                <ul id="modal-ingredients" class="space-y-2 pl-5 list-disc text-gray-700">
                    <!-- Ingredients will be populated by JavaScript -->
                </ul>
            </div>
            
            <div class="mb-8">
                <h3 class="text-2xl font-semibold text-gray-800 mb-4">Instructions</h3>
                <ol id="modal-instructions" class="space-y-4 pl-5 list-decimal text-gray-700">
                    <!-- Instructions will be populated by JavaScript -->
                </ol>
            </div>
            
            <div id="modal-notes-container" class="mb-8 hidden">
                <h3 class="text-2xl font-semibold text-gray-800 mb-4">Notes</h3>
                <div id="modal-notes" class="bg-yellow-50 p-6 rounded-lg text-gray-700">
                    <!-- Notes will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
</div>
    <script src= "../js/dashboard.js"></script>
</body>
</html>