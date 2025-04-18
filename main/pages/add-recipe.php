<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: auth.php");
    exit;
}

require_once "../../config.php";

if(isset($_GET['id']) && is_numeric($_GET['id'])) {
    $recipe_id = $_GET['id'];
    $user_id = $_SESSION["id"];
    
    $recipe_data = array();
    $sql = "SELECT * FROM recipes WHERE id = ? AND user_id = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "ii", $recipe_id, $user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if($row = mysqli_fetch_assoc($result)) {
                $recipe_data = $row;
                
                $ingredients = array();
                $sql_ingredients = "SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY id";
                if($stmt_ingredients = mysqli_prepare($conn, $sql_ingredients)) {
                    mysqli_stmt_bind_param($stmt_ingredients, "i", $recipe_id);
                    
                    if(mysqli_stmt_execute($stmt_ingredients)) {
                        $result_ingredients = mysqli_stmt_get_result($stmt_ingredients);
                        
                        while($row_ingredient = mysqli_fetch_assoc($result_ingredients)) {
                            $ingredients[] = $row_ingredient;
                        }
                    }
                    mysqli_stmt_close($stmt_ingredients);
                }
                
                $instructions = array();
                $sql_instructions = "SELECT * FROM recipe_instructions WHERE recipe_id = ? ORDER BY step_number";
                if($stmt_instructions = mysqli_prepare($conn, $sql_instructions)) {
                    mysqli_stmt_bind_param($stmt_instructions, "i", $recipe_id);
                    
                    if(mysqli_stmt_execute($stmt_instructions)) {
                        $result_instructions = mysqli_stmt_get_result($stmt_instructions);
                        
                        while($row_instruction = mysqli_fetch_assoc($result_instructions)) {
                            $instructions[] = $row_instruction;
                        }
                    }
                    mysqli_stmt_close($stmt_instructions);
                }
                
                $recipe_data['ingredients'] = $ingredients;
                $recipe_data['instructions'] = $instructions;
                
                echo json_encode(array('success' => true, 'recipe' => $recipe_data));
                exit;
            } else {
                echo json_encode(array('success' => false, 'message' => 'Recipe not found.'));
                exit;
            }
        } else {
            echo json_encode(array('success' => false, 'message' => 'Error executing statement.'));
            exit;
        }
        
        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(array('success' => false, 'message' => 'Error preparing statement.'));
        exit;
    }
}

$recipe_added = false;
$error_message = "";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['delete_recipe']) && $_POST['delete_recipe'] == 'true' && isset($_POST['recipe_id'])) {
        $recipe_id = trim($_POST['recipe_id']);
        
        if (!is_numeric($recipe_id)) {
            echo json_encode(['success' => false, 'message' => 'Invalid recipe ID.']);
            exit;
        }
        
        $sql = "DELETE FROM recipes WHERE id = ? AND user_id = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "ii", $param_recipe_id, $param_user_id);
            
            $param_recipe_id = $recipe_id;
            $param_user_id = $_SESSION["id"];
            
            if(mysqli_stmt_execute($stmt)){
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting recipe.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error preparing statement.']);
        }
        
        mysqli_stmt_close($stmt);
        exit;
    }
    
    if(isset($_POST["recipe-name"])) {
        $recipe_name = trim($_POST["recipe-name"]);
        $recipe_category = trim($_POST["recipe-category"]);
        $prep_time = trim($_POST["prep-time"]);
        $servings = trim($_POST["servings"]);
        $calories = !empty($_POST["calories"]) ? trim($_POST["calories"]) : NULL;
        $protein = !empty($_POST["protein"]) ? trim($_POST["protein"]) : NULL;
        $carbs = !empty($_POST["carbs"]) ? trim($_POST["carbs"]) : NULL;
        $fiber = !empty($_POST["fiber"]) ? trim($_POST["fiber"]) : NULL;
        $diet_type = !empty($_POST["diet-type"]) ? trim($_POST["diet-type"]) : NULL;
        $notes = !empty($_POST["recipe-notes"]) ? trim($_POST["recipe-notes"]) : NULL;
        
        $ingredients = isset($_POST["ingredient"]) ? $_POST["ingredient"] : array();
        $instructions = isset($_POST["instruction"]) ? $_POST["instruction"] : array();
        
        if(empty($recipe_name) || empty($recipe_category) || empty($prep_time) || empty($servings) || empty($ingredients) || empty($instructions)) {
            $error_message = "Please fill in all required fields.";
        } else {
            $image_path = NULL;
            if(isset($_FILES["recipe-image"]) && $_FILES["recipe-image"]["error"] == 0) {
                $allowed = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "png" => "image/png");
                $filename = $_FILES["recipe-image"]["name"];
                $filetype = $_FILES["recipe-image"]["type"];
                $filesize = $_FILES["recipe-image"]["size"];
                
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                if(!array_key_exists($ext, $allowed)) {
                    $error_message = "Error: Please select a valid file format (JPG, JPEG, PNG).";
                }
                
                $maxsize = 5 * 1024 * 1024;
                if($filesize > $maxsize) {
                    $error_message = "Error: File size is larger than the allowed limit (5MB).";
                }
                
                if(in_array($filetype, $allowed)) {
                    $new_filename = uniqid() . "." . $ext;
                    $upload_dir = "../img/recipes/";
                    
                    if(!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $image_path = $upload_dir . $new_filename;
                    
                    if(move_uploaded_file($_FILES["recipe-image"]["tmp_name"], $image_path)) {
                        $image_path = "img/recipes/" . $new_filename; // Store relative path in database
                    } else {
                        $error_message = "Error: There was a problem uploading your file. Please try again.";
                        $image_path = NULL;
                    }
                } else {
                    $error_message = "Error: There was a problem with your file. Please try again.";
                }
            }
            
            if(empty($error_message)) {
                mysqli_begin_transaction($conn);
                
                try {
                    $sql = "INSERT INTO recipes (user_id, name, category, prep_time, servings, calories, protein, carbs, fiber, diet_type, image_path, notes, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    if($stmt = mysqli_prepare($conn, $sql)) {
                        mysqli_stmt_bind_param($stmt, "issiiiiiisss", 
                            $param_user_id, 
                            $param_name, 
                            $param_category, 
                            $param_prep_time, 
                            $param_servings, 
                            $param_calories,
                            $param_protein,
                            $param_carbs,
                            $param_fiber,
                            $param_diet_type, 
                            $param_image_path, 
                            $param_notes
                        );
                        
                        $param_user_id = $_SESSION["id"];
                        $param_name = $recipe_name;
                        $param_category = $recipe_category;
                        $param_prep_time = $prep_time;
                        $param_servings = $servings;
                        $param_calories = $calories;
                        $param_protein = $protein;
                        $param_carbs = $carbs;
                        $param_fiber = $fiber;
                        $param_diet_type = $diet_type;
                        $param_image_path = $image_path;
                        $param_notes = $notes;
                        
                        if(mysqli_stmt_execute($stmt)) {
                            $recipe_id = mysqli_insert_id($conn);
                            
                            $sql_ingredient = "INSERT INTO recipe_ingredients (recipe_id, ingredient) VALUES (?, ?)";
                            $stmt_ingredient = mysqli_prepare($conn, $sql_ingredient);
                            
                            foreach($ingredients as $ingredient) {
                                if(!empty(trim($ingredient))) {
                                    mysqli_stmt_bind_param($stmt_ingredient, "is", $recipe_id, $ingredient);
                                    mysqli_stmt_execute($stmt_ingredient);
                                }
                            }
                            
                            $sql_instruction = "INSERT INTO recipe_instructions (recipe_id, step_number, instruction) VALUES (?, ?, ?)";
                            $stmt_instruction = mysqli_prepare($conn, $sql_instruction);
                            
                            foreach($instructions as $key => $instruction) {
                                if(!empty(trim($instruction))) {
                                    $step_number = $key + 1;
                                    mysqli_stmt_bind_param($stmt_instruction, "iis", $recipe_id, $step_number, $instruction);
                                    mysqli_stmt_execute($stmt_instruction);
                                }
                            }
                            
                            mysqli_commit($conn);
                            $recipe_added = true;
                        } else {
                            throw new Exception("Error executing recipe insert: " . mysqli_error($conn));
                        }
                    } else {
                        throw new Exception("Error preparing recipe statement: " . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error_message = $e->getMessage();
                    
                    if($image_path && file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
            }
        }
    }
}

$user_recipes = array();
$sql = "SELECT r.id, r.name, r.category, r.prep_time, r.servings, r.calories, r.protein, r.carbs, r.fiber, r.diet_type, r.image_path, r.created_at 
        FROM recipes r 
        WHERE r.user_id = ? 
        ORDER BY r.created_at DESC";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while($row = mysqli_fetch_assoc($result)) {
            $user_recipes[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/logo.png" sizes="62x62">
    <title>CookBook | My Recipes</title>
    <link rel="stylesheet" href="../src/output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@300;400;500;600;700&display=swap');
        
        .sidebar-link {
            transition: all 0.3s ease;
        }
        
        .sidebar-link:hover {
            background-color: rgba(255, 215, 0, 0.1);
        }
        
        .sidebar-link.active {
            border-left: 3px solid #FFD700;
            background-color: rgba(255, 215, 0, 0.1);
        }
        
        .recipe-card {
            transition: all 0.3s ease;
        }
        
        .recipe-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .recipe-card:hover .recipe-overlay {
            opacity: 1;
        }
        
        .recipe-overlay {
            opacity: 0;
            transition: opacity 0.3s ease;
            background: rgba(28, 28, 28, 0.7);
        }
        
        /* Modal Animation */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-animation {
            animation: modalFadeIn 0.3s ease forwards;
        }
        
        /* Nutrition badge styles */
        .nutrition-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            margin-right: 4px;
        }
        
        .nutrition-protein {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        
        .nutrition-carbs {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .nutrition-fiber {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .nutrition-calories {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .nutrition-icon {
            margin-right: 4px;
            font-size: 10px;
        }
    </style>
</head>
<body class="font-sans bg-white text-text min-h-screen flex flex-col">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Left Sidebar Navigation -->
        <aside class="bg-black text-white w-full md:w-64 flex-shrink-0 md:flex flex-col hidden">
            <div class="p-4 border-b border-gray-800">
                <h2 class="font-serif text-2xl font-bold text-yellow-300"><a href="dashboard.php">CookBook</a></h2>
            </div>
            
            <nav class="flex-grow py-4">
                <ul class="space-y-1">
                    <li>
                        <a href="dashboard.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-home mr-3 text-yellow-300"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="add-recipe.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
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
                <a href="../auth/logout.php" class="flex items-center text-gray-300 hover:text-white">
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
                        <a href="dashboard.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-home mr-3 text-yellow-300"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="add-recipe.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
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
                    <h1 class="text-xl font-medium text-black hidden md:block">My Recipes</h1>
                </div>
                
                <div class="flex items-center">
                    <span class="mr-4 text-text">Welcome, <span class="font-medium"><?php echo htmlspecialchars($_SESSION["name"]); ?></span>!</span>
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
                            <i class="fas fa-chevron-down text-xs text-text cursor-pointer"></i>
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
            
            <!-- My Recipes Content -->
            <main class="flex-grow p-6 overflow-auto">
                <?php if(!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Recipe Categories and Add Recipe Button -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                    <div class="flex flex-wrap gap-2 mb-4 md:mb-0">
                        <button class="bg-gray-200 text-black px-4 py-2 rounded-lg font-medium">All Recipes</button>
                    </div>
                    <button id="add-recipe-btn" class="bg-black hover:bg-yellow-400 text-white hover:text-black px-6 py-2 rounded-lg font-medium flex items-center cursor-pointer">
                        <i class="fas fa-plus mr-2"></i>
                        Add Recipe
                    </button>
                </div>
                
                <!-- My Recipes Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="recipes-container">
                    <?php if(empty($user_recipes)): ?>
                    <div class="col-span-full text-center py-10">
                        <div class="text-4xl text-gray-300 mb-4">
                            <i class="fas fa-book"></i>
                        </div>
                        <h3 class="text-xl font-medium text-gray-500 mb-2">No recipes yet</h3>
                        <p class="text-gray-400 mb-6">Start adding your favorite recipes to your collection</p>
                        <button id="empty-add-recipe-btn" class="bg-black hover:bg-yellow-400 text-white hover:text-black px-6 py-2 rounded-lg font-medium cursor-pointer">
                            Add Your First Recipe
                        </button>
                    </div>
                    <?php else: ?>
                        <?php foreach($user_recipes as $recipe): ?>
                        <div class="recipe-card bg-white rounded-xl shadow-md overflow-hidden" data-recipe="<?php echo $recipe['id']; ?>">
                            <div class="relative h-48">
                                <?php if(!empty($recipe['image_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($recipe['image_path']); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                <img src="../img/recipe-placeholder.jpg" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                                <div class="recipe-overlay absolute inset-0 flex items-center justify-center">
                                    <div class="flex space-x-2">
                                        <button class="delete-recipe bg-white text-black px-3 py-1 rounded-lg font-medium cursor-pointer">Delete</button>
                                        <button class="view-recipe bg-yellow-300 text-black px-3 py-1 rounded-lg font-medium cursor-pointer">View</button>
                                    </div>
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
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Recipe Modal (Hidden by default) -->
    <div id="add-recipe-modal" class="fixed inset-0 bg-gray-200 bg-opacity-200 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto modal-animation p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-serif font-bold text-black">Add New Recipe</h2>
                <button id="close-add-modal" class="text-gray-400 hover:text-black focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-recipe-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="recipe-name" class="block text-sm font-medium text-gray-700 mb-1">Recipe Name</label>
                        <input type="text" id="recipe-name" name="recipe-name" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="Enter recipe name" required>
                    </div>
                    
                    <div>
                        <label for="recipe-category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select id="recipe-category" name="recipe-category" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" required>
                            <option value="">Select a category</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="dessert">Dessert</option>
                            <option value="snack">Snack</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="prep-time" class="block text-sm font-medium text-gray-700 mb-1">Preparation Time (minutes)</label>
                        <input type="number" id="prep-time" name="prep-time" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 30" required>
                    </div>
                    
                    <div>
                        <label for="servings" class="block text-sm font-medium text-gray-700 mb-1">Servings</label>
                        <input type="number" id="servings" name="servings" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 4" required>
                    </div>
                </div>
                
                <!-- Nutrition Information Section -->
                <div class="mb-6">
                    <h3 class="text-lg font-medium text-black mb-3">Nutrition Information (per serving)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label for="calories" class="block text-sm font-medium text-gray-700 mb-1">Calories</label>
                            <div class="relative">
                                <input type="number" id="calories" name="calories" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 350">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">cal</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="protein" class="block text-sm font-medium text-gray-700 mb-1">Protein</label>
                            <div class="relative">
                                <input type="number" id="protein" name="protein" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 20">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">g</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="carbs" class="block text-sm font-medium text-gray-700 mb-1">Carbs</label>
                            <div class="relative">
                                <input type="number" id="carbs" name="carbs" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 40">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">g</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="fiber" class="block text-sm font-medium text-gray-700 mb-1">Fiber</label>
                            <div class="relative">
                                <input type="number" id="fiber" name="fiber" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 5">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">g</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="diet-type" class="block text-sm font-medium text-gray-700 mb-1">Diet Type</label>
                        <select id="diet-type" name="diet-type" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300">
                            <option value="">Select diet type</option>
                            <option value="Vegetarian">Vegetarian</option>
                            <option value="Vegan">Vegan</option>
                            <option value="Pescatarian">Pescatarian</option>
                            <option value="Keto">Keto</option>
                            <option value="High Protein">High Protein</option>
                            <option value="Low Carb">Low Carb</option>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="recipe-image" class="block text-sm font-medium text-gray-700 mb-1">Recipe Image</label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                        <div class="mb-3">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-sm text-gray-500 mb-2">Drag and drop an image here, or click to select a file</p>
                        <p class="text-xs text-gray-400">PNG, JPG or JPEG (max. 5MB)</p>
                        <input type="file" id="recipe-image" name="recipe-image" class="hidden" accept="image/*">
                        <button type="button" id="upload-trigger" class="mt-4 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm hover:bg-gray-200">Select Image</button>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Ingredients</label>
                    <div id="ingredients-container">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" name="ingredient[]" class="flex-grow px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 2 cups flour" required>
                            <button type="button" class="remove-ingredient bg-red-100 text-red-500 p-2 rounded-lg hover:bg-red-200 hidden">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" id="add-ingredient" class="mt-2 text-yellow-300 hover:text-yellow-400 text-sm flex items-center">
                        <i class="fas fa-plus mr-1"></i> Add Another Ingredient
                    </button>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Instructions</label>
                    <div id="instructions-container">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="bg-yellow-300 text-black w-6 h-6 rounded-full flex items-center justify-center font-medium">1</span>
                            <textarea name="instruction[]" rows="2" class="flex-grow px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. Preheat oven to 350°F" required></textarea>
                            <button type="button" class="remove-instruction bg-red-100 text-red-500 p-2 rounded-lg hover:bg-red-200 hidden">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" id="add-instruction" class="mt-2 text-yellow-300 hover:text-yellow-400 text-sm flex items-center">
                        <i class="fas fa-plus mr-1"></i> Add Another Instruction
                    </button>
                </div>
                
                <div class="mb-6">
                    <label for="recipe-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                    <textarea id="recipe-notes" name="recipe-notes" rows="3" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="Any additional notes or tips for this recipe"></textarea>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancel-add-recipe" class="bg-white border border-gray-200 text-text px-6 py-2 rounded-lg font-medium hover:bg-gray-50 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="bg-yellow-300 text-black px-6 py-2 rounded-lg font-medium hover:bg-yellow-400 cursor-pointer">
                        Save Recipe
                    </button>
                </div>
            </form>
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


    <script src="../js/add-recipe.js"></script>
</body>
</html>