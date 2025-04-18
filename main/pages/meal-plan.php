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

$plan_added = false;
$error_message = "";
$success_message = "";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["plan-name"])) {
    $plan_name = trim($_POST["plan-name"]);
    $start_date = trim($_POST["start-date"]);
    $end_date = trim($_POST["end-date"]);
    $plan_type = trim($_POST["plan-type"]);
    $daily_calories = !empty($_POST["daily-calories"]) ? intval($_POST["daily-calories"]) : NULL;
    $daily_protein = !empty($_POST["daily-protein"]) ? intval($_POST["daily-protein"]) : NULL;
    $daily_carbs = !empty($_POST["daily-carbs"]) ? intval($_POST["daily-carbs"]) : NULL;
    $daily_fiber = !empty($_POST["daily-fiber"]) ? intval($_POST["daily-fiber"]) : NULL;
    
    $meal_types = isset($_POST["meal-types"]) ? $_POST["meal-types"] : array();
    
    $auto_generate = isset($_POST["auto-generate"]) ? 1 : 0;
    $use_favorites = isset($_POST["use-favorites"]) ? 1 : 0;
    $generate_shopping_list = isset($_POST["shopping-list"]) ? 1 : 0;
    
    if(empty($plan_name) || empty($start_date) || empty($end_date) || empty($plan_type)) {
        $error_message = "Please fill in all required fields.";
    } else {
        $start_date_obj = new DateTime($start_date);
        $end_date_obj = new DateTime($end_date);
        
        if($end_date_obj < $start_date_obj) {
            $error_message = "End date cannot be before start date.";
        } else {
            $sql = "INSERT INTO meal_plans (user_id, name, start_date, end_date, plan_type, daily_calories, daily_protein, daily_carbs, daily_fiber, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            if($stmt = mysqli_prepare($conn, $sql)) {
                mysqli_stmt_bind_param($stmt, "issssiiii",  
                    $param_user_id, 
                    $param_name, 
                    $param_start_date, 
                    $param_end_date, 
                    $param_plan_type, 
                    $param_daily_calories,
                    $param_daily_protein,
                    $param_daily_carbs,
                    $param_daily_fiber
                );
                
                $param_user_id = $_SESSION["id"];
                $param_name = $plan_name;
                $param_start_date = $start_date;
                $param_end_date = $end_date;
                $param_plan_type = $plan_type;
                $param_daily_calories = $daily_calories;
                $param_daily_protein = $daily_protein;
                $param_daily_carbs = $daily_carbs;
                $param_daily_fiber = $daily_fiber;
                
                if(mysqli_stmt_execute($stmt)) {
                    $plan_id = mysqli_insert_id($conn);
                    
                    if($auto_generate) {
                        $interval = $start_date_obj->diff($end_date_obj);
                        $days = $interval->days + 1;
                        
                        $current_date = clone $start_date_obj;
                        
                        for($i = 0; $i < $days; $i++) {
                            $date_str = $current_date->format('Y-m-d');
                            
                            foreach($meal_types as $meal_type) {
                                $sql_slot = "INSERT INTO meal_plan_slots (plan_id, date, meal_type) VALUES (?, ?, ?)";
                                $stmt_slot = mysqli_prepare($conn, $sql_slot);
                                mysqli_stmt_bind_param($stmt_slot, "iss", $plan_id, $date_str, $meal_type);
                                mysqli_stmt_execute($stmt_slot);
                            }
                            
                            $current_date->modify('+1 day');
                        }
                    }
                    
                    if($generate_shopping_list) {
                        $sql_list = "INSERT INTO shopping_lists (user_id, plan_id, name, created_at) VALUES (?, ?, ?, NOW())";
                        $stmt_list = mysqli_prepare($conn, $sql_list);
                        $list_name = $plan_name . " Shopping List";
                        mysqli_stmt_bind_param($stmt_list, "iis", $_SESSION["id"], $plan_id, $list_name);
                        mysqli_stmt_execute($stmt_list);
                    }
                    
                    $plan_added = true;
                    $success_message = "Meal plan created successfully!";
                } else {
                    $error_message = "Error creating meal plan: " . mysqli_error($conn);
                }
                
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Error preparing statement: " . mysqli_error($conn);
            }
        }
    }
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_plan"]) && isset($_POST["plan_id"])) {
    $plan_id = intval($_POST["plan_id"]);
    
    $sql_verify = "SELECT id FROM meal_plans WHERE id = ? AND user_id = ?";
    
    if($stmt_verify = mysqli_prepare($conn, $sql_verify)) {
        mysqli_stmt_bind_param($stmt_verify, "ii", $plan_id, $_SESSION["id"]);
        
        if(mysqli_stmt_execute($stmt_verify)) {
            $result_verify = mysqli_stmt_get_result($stmt_verify);
            
            if(mysqli_num_rows($result_verify) == 1) {
                $sql_delete_items = "DELETE mpi FROM meal_plan_items mpi 
                                    JOIN meal_plan_slots mps ON mpi.slot_id = mps.id 
                                    WHERE mps.plan_id = ?";
                
                if($stmt_delete_items = mysqli_prepare($conn, $sql_delete_items)) {
                    mysqli_stmt_bind_param($stmt_delete_items, "i", $plan_id);
                    mysqli_stmt_execute($stmt_delete_items);
                }
                
                $sql_delete_slots = "DELETE FROM meal_plan_slots WHERE plan_id = ?";
                
                if($stmt_delete_slots = mysqli_prepare($conn, $sql_delete_slots)) {
                    mysqli_stmt_bind_param($stmt_delete_slots, "i", $plan_id);
                    mysqli_stmt_execute($stmt_delete_slots);
                }
                
                $sql_delete_plan = "DELETE FROM meal_plans WHERE id = ?";
                
                if($stmt_delete_plan = mysqli_prepare($conn, $sql_delete_plan)) {
                    mysqli_stmt_bind_param($stmt_delete_plan, "i", $plan_id);
                    
                    if(mysqli_stmt_execute($stmt_delete_plan)) {
                        // Success
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Meal plan deleted successfully']);
                        exit;
                    }
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting meal plan']);
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add-meal"]) && isset($_POST["recipe_id"]) && isset($_POST["slot_id"])) {
    $recipe_id = intval($_POST["recipe_id"]);
    $slot_id = intval($_POST["slot_id"]);
    
    $sql_verify = "SELECT mp.id FROM meal_plan_slots mps 
                  JOIN meal_plans mp ON mps.plan_id = mp.id 
                  WHERE mps.id = ? AND mp.user_id = ?";
    
    if($stmt_verify = mysqli_prepare($conn, $sql_verify)) {
        mysqli_stmt_bind_param($stmt_verify, "ii", $slot_id, $_SESSION["id"]);
        
        if(mysqli_stmt_execute($stmt_verify)) {
            $result_verify = mysqli_stmt_get_result($stmt_verify);
            
            if(mysqli_num_rows($result_verify) == 1) {
                $sql_add_meal = "INSERT INTO meal_plan_items (slot_id, recipe_id) VALUES (?, ?)";
                
                if($stmt_add_meal = mysqli_prepare($conn, $sql_add_meal)) {
                    mysqli_stmt_bind_param($stmt_add_meal, "ii", $slot_id, $recipe_id);
                    
                    if(mysqli_stmt_execute($stmt_add_meal)) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Meal added to plan']);
                        exit;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error adding meal to plan']);
                        exit;
                    }
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
        }
    }
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["remove-meal"]) && isset($_POST["slot_id"])) {
    $slot_id = intval($_POST["slot_id"]);
    
    $sql_verify = "SELECT mp.id FROM meal_plan_slots mps 
                  JOIN meal_plans mp ON mps.plan_id = mp.id 
                  WHERE mps.id = ? AND mp.user_id = ?";
    
    if($stmt_verify = mysqli_prepare($conn, $sql_verify)) {
        mysqli_stmt_bind_param($stmt_verify, "ii", $slot_id, $_SESSION["id"]);
        
        if(mysqli_stmt_execute($stmt_verify)) {
            $result_verify = mysqli_stmt_get_result($stmt_verify);
            
            if(mysqli_num_rows($result_verify) == 1) {
                $sql_remove_meal = "DELETE FROM meal_plan_items WHERE slot_id = ?";
                
                if($stmt_remove_meal = mysqli_prepare($conn, $sql_remove_meal)) {
                    mysqli_stmt_bind_param($stmt_remove_meal, "i", $slot_id);
                    
                    if(mysqli_stmt_execute($stmt_remove_meal)) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Meal removed from plan']);
                        exit;
                    } else {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error removing meal from plan']);
                        exit;
                    }
                }
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
        }
    }
}

$current_plan = null;
$user_plans = array();

$today = date('Y-m-d');
$sql_current = "SELECT mp.*, 
               (SELECT COUNT(*) FROM meal_plan_slots mps WHERE mps.plan_id = mp.id) as total_slots,
               (SELECT COUNT(*) FROM meal_plan_slots mps JOIN meal_plan_items mpi ON mps.id = mpi.slot_id WHERE mps.plan_id = mp.id) as filled_slots
               FROM meal_plans mp 
               WHERE mp.user_id = ? AND mp.end_date >= ? 
               ORDER BY mp.start_date ASC 
               LIMIT 1";

if($stmt_current = mysqli_prepare($conn, $sql_current)) {
    mysqli_stmt_bind_param($stmt_current, "is", $_SESSION["id"], $today);
    
    if(mysqli_stmt_execute($stmt_current)) {
        $result_current = mysqli_stmt_get_result($stmt_current);
        
        if(mysqli_num_rows($result_current) == 1) {
            $current_plan = mysqli_fetch_assoc($result_current);
            
            $sql_slots = "SELECT mps.*, mpi.recipe_id, r.name as recipe_name, r.image_path, r.calories, r.protein, r.carbs, r.fiber 
                         FROM meal_plan_slots mps 
                         LEFT JOIN meal_plan_items mpi ON mps.id = mpi.slot_id 
                         LEFT JOIN recipes r ON mpi.recipe_id = r.id 
                         WHERE mps.plan_id = ? 
                         ORDER BY mps.date ASC, FIELD(mps.meal_type, 'breakfast', 'lunch', 'dinner', 'snacks')";
            
            if($stmt_slots = mysqli_prepare($conn, $sql_slots)) {
                mysqli_stmt_bind_param($stmt_slots, "i", $current_plan['id']);
                
                if(mysqli_stmt_execute($stmt_slots)) {
                    $result_slots = mysqli_stmt_get_result($stmt_slots);
                    
                    $current_plan['slots'] = array();
                    $current_plan['days'] = array();
                    
                    while($slot = mysqli_fetch_assoc($result_slots)) {
                        $date = $slot['date'];
                        $day_name = date('l', strtotime($date));
                        $formatted_date = date('M d', strtotime($date));
                        
                        if(!isset($current_plan['days'][$date])) {
                            $current_plan['days'][$date] = array(
                                'date' => $date,
                                'day_name' => $day_name,
                                'formatted_date' => $formatted_date,
                                'meals' => array(),
                                'total_calories' => 0,
                                'total_protein' => 0,
                                'total_carbs' => 0,
                                'total_fiber' => 0
                            );
                        }
                        
                        $current_plan['days'][$date]['meals'][$slot['meal_type']] = $slot;
                        
                        if(!empty($slot['recipe_id'])) {
                            if(!empty($slot['calories'])) {
                                $current_plan['days'][$date]['total_calories'] += $slot['calories'];
                            }
                            if(!empty($slot['protein'])) {
                                $current_plan['days'][$date]['total_protein'] += $slot['protein'];
                            }
                            if(!empty($slot['carbs'])) {
                                $current_plan['days'][$date]['total_carbs'] += $slot['carbs'];
                            }
                            if(!empty($slot['fiber'])) {
                                $current_plan['days'][$date]['total_fiber'] += $slot['fiber'];
                            }
                        }
                        
                        $current_plan['slots'][] = $slot;
                    }
                }
            }
        }
    }
}

$sql_plans = "SELECT mp.*, 
             (SELECT COUNT(*) FROM meal_plan_slots mps WHERE mps.plan_id = mp.id) as total_slots,
             (SELECT COUNT(*) FROM meal_plan_slots mps JOIN meal_plan_items mpi ON mps.id = mpi.slot_id WHERE mps.plan_id = mp.id) as filled_slots
             FROM meal_plans mp 
             WHERE mp.user_id = ? 
             ORDER BY mp.start_date DESC";

if($stmt_plans = mysqli_prepare($conn, $sql_plans)) {
    mysqli_stmt_bind_param($stmt_plans, "i", $_SESSION["id"]);
    
    if(mysqli_stmt_execute($stmt_plans)) {
        $result_plans = mysqli_stmt_get_result($stmt_plans);
        
        while($plan = mysqli_fetch_assoc($result_plans)) {
            if($current_plan && $plan['id'] == $current_plan['id']) {
                continue;
            }
            
            $user_plans[] = $plan;
        }
    }
}

$user_recipes = array();
$sql_recipes = "SELECT id, name, category, prep_time, calories, protein, carbs, fiber, diet_type, image_path 
               FROM recipes 
               WHERE user_id = ? 
               ORDER BY name ASC";

if($stmt_recipes = mysqli_prepare($conn, $sql_recipes)) {
    mysqli_stmt_bind_param($stmt_recipes, "i", $_SESSION["id"]);
    
    if(mysqli_stmt_execute($stmt_recipes)) {
        $result_recipes = mysqli_stmt_get_result($stmt_recipes);
        
        while($recipe = mysqli_fetch_assoc($result_recipes)) {
            $user_recipes[] = $recipe;
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
    <title>CookBook | Meal Plans</title>
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
        
        .plan-card {
            transition: all 0.3s ease;
        }
        
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Modal Animation */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-animation {
            animation: modalFadeIn 0.3s ease forwards;
        }
        
        .meal-cell:hover {
            background-color: rgba(255, 215, 0, 0.1);
        }
        
        .meal-item {
            transition: all 0.2s ease;
        }
        
        .meal-item:hover {
            background-color: rgba(255, 215, 0, 0.2);
        }

        .day-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .day-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .meal-slot {
            transition: all 0.2s ease;
            border-radius: 8px;
        }
        
        .meal-slot:hover {
            background-color: rgba(255, 215, 0, 0.1);
        }

        .meal-slot.empty:hover {
            background-color: rgba(255, 215, 0, 0.05);
        }

        .remove-meal-btn {
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .meal-slot:hover .remove-meal-btn {
            opacity: 1;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            transform: translateY(-10px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            color: #166534;
        }
        
        .toast.error {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        /* Table styles */
        .meal-plan-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .meal-plan-table th {
            background-color: #f9fafb;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .meal-plan-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
        }

        .meal-plan-table tr:hover {
            background-color: rgba(255, 215, 0, 0.05);
        }

        .meal-plan-table tr:last-child td {
            border-bottom: none;
        }

        .action-btn {
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .view-btn {
            background-color: #f3f4f6;
            color: #111827;
        }

        .view-btn:hover {
            background-color: #e5e7eb;
        }

        .delete-btn {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .delete-btn:hover {
            background-color: #fecaca;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-yellow {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-green {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-blue {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-purple {
            background-color: #ede9fe;
            color: #5b21b6;
        }

        .badge-red {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        /* Nutrition info styles */
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
                        <a href="add-recipe.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-book mr-3 text-yellow-300"></i>
                            <span>My Recipes</span>
                        </a>
                    </li>
                    <li>
                        <a href="meal-plan.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
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
                        <a href="add-recipe.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-book mr-3 text-yellow-300"></i>
                            <span>My Recipes</span>
                        </a>
                    </li>
                    <li>
                        <a href="meal-plan.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
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
                    <h1 class="text-xl font-medium text-black hidden md:block">Meal Plans</h1>
                </div>
                
                <div class="flex items-center">
                    <span class="mr-4 text-text">Welcome, <span class="font-medium"><?php echo htmlspecialchars($_SESSION["name"]); ?></span>!</span>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center focus:outline-none">
                            <div class="w-10 h-10 rounded-full bg-yellow-300 text-black flex items-center justify-center font-medium mr-2">
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
            
            <!-- Meal Plans Content -->
            <main class="flex-grow p-6 overflow-auto">
                <!-- Toast Notification (Hidden by default) -->
                <div id="toast" class="toast" role="alert"></div>
                <?php if(!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Meal Plans Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                    <div>
                        <h2 class="text-2xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-black to-gray-700 mb-2">Your Meal Plans</h2>
                    </div>
                    <button id="create-plan-btn" class="mt-4 md:mt-0 bg-black text-white px-6 py-2 rounded-lg font-medium flex items-center hover:bg-gray-800 transition-colors duration-200 cursor-pointer">
                        <i class="fas fa-plus mr-2"></i>
                        Create New Plan
                    </button>
                </div>

                <?php if($current_plan): ?>
                <!-- Current Meal Plan -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-serif font-bold text-black"><?php echo htmlspecialchars($current_plan['name']); ?></h2>
                            <p class="text-text mt-1">
                                <?php 
                                    $start_date = new DateTime($current_plan['start_date']);
                                    $end_date = new DateTime($current_plan['end_date']);
                                    echo $start_date->format('M d') . ' - ' . $end_date->format('M d, Y'); 
                                ?>
                                <span class="ml-2 px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">
                                    <?php echo ucfirst(htmlspecialchars($current_plan['plan_type'])); ?>
                                </span>
                            </p>
                        </div>
                        <div class="flex items-center mt-4 md:mt-0 gap-3">
                            <div class="flex flex-wrap gap-2">
                                <?php if(!empty($current_plan['daily_calories'])): ?>
                                <span class="nutrition-badge nutrition-calories">
                                    <i class="fas fa-fire nutrition-icon"></i>
                                    <?php echo htmlspecialchars($current_plan['daily_calories']); ?> cal/day
                                </span>
                                <?php endif; ?>
                                
                                <?php if(!empty($current_plan['daily_protein'])): ?>
                                <span class="nutrition-badge nutrition-protein">
                                    <i class="fas fa-drumstick-bite nutrition-icon"></i>
                                    <?php echo htmlspecialchars($current_plan['daily_protein']); ?>g protein
                                </span>
                                <?php endif; ?>
                                
                                <?php if(!empty($current_plan['daily_carbs'])): ?>
                                <span class="nutrition-badge nutrition-carbs">
                                    <i class="fas fa-bread-slice nutrition-icon"></i>
                                    <?php echo htmlspecialchars($current_plan['daily_carbs']); ?>g carbs
                                </span>
                                <?php endif; ?>
                                
                                <?php if(!empty($current_plan['daily_fiber'])): ?>
                                <span class="nutrition-badge nutrition-fiber">
                                    <i class="fas fa-seedling nutrition-icon"></i>
                                    <?php echo htmlspecialchars($current_plan['daily_fiber']); ?>g fiber
                                </span>
                                <?php endif; ?>
                            </div>
                            <button class="delete-plan-btn bg-red-100 hover:bg-red-200 text-red-700 px-4 py-2 rounded-lg text-sm font-medium flex items-center cursor-pointer" data-plan-id="<?php echo $current_plan['id']; ?>">
                                <i class="fas fa-trash-alt mr-2"></i>
                                <span>Delete Plan</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Table-Based Layout for Current Plan -->
                    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                        <div class="overflow-x-auto">
                            <table class="meal-plan-table">
                                <thead>
                                    <tr>
                                        <th class="w-1/6">Day</th>
                                        <th class="w-1/5">Breakfast</th>
                                        <th class="w-1/5">Lunch</th>
                                        <th class="w-1/5">Dinner</th>
                                        <th class="w-1/5">Snacks</th>
                                        <th class="w-1/6 text-right">Daily Nutrition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($current_plan['days'] as $date => $day): ?>
                                    <tr>
                                        <td class="font-medium">
                                            <div><?php echo $day['day_name']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $day['formatted_date']; ?></div>
                                        </td>
                                        
                                        <!-- Breakfast -->
                                        <td>
                                            <?php if(isset($day['meals']['breakfast']) && !empty($day['meals']['breakfast']['recipe_id'])): ?>
                                            <div class="meal-slot flex items-center" data-slot-id="<?php echo $day['meals']['breakfast']['id']; ?>">
                                                <div class="w-10 h-10 rounded-md overflow-hidden mr-2 bg-gray-100 flex-shrink-0">
                                                    <?php if(!empty($day['meals']['breakfast']['image_path'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($day['meals']['breakfast']['image_path']); ?>" alt="<?php echo htmlspecialchars($day['meals']['breakfast']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                    <img src="../img/recipe-placeholder.jpg" alt="<?php echo htmlspecialchars($day['meals']['breakfast']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow">
                                                    <p class="font-medium text-black"><?php echo htmlspecialchars($day['meals']['breakfast']['recipe_name']); ?></p>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        <?php if(!empty($day['meals']['breakfast']['calories'])): ?>
                                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($day['meals']['breakfast']['calories']); ?> cal</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if(!empty($day['meals']['breakfast']['protein'])): ?>
                                                        <span class="text-xs text-gray-500 ml-1"><?php echo htmlspecialchars($day['meals']['breakfast']['protein']); ?>g protein</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <button class="remove-meal-btn text-red-500 hover:text-red-700 ml-2" data-slot-id="<?php echo $day['meals']['breakfast']['id']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <div class="meal-slot empty flex items-center justify-center h-10 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 add-meal-btn" data-day="<?php echo $date; ?>" data-meal="breakfast" data-slot-id="<?php echo isset($day['meals']['breakfast']) ? $day['meals']['breakfast']['id'] : ''; ?>">
                                                <div class="flex items-center text-gray-400">
                                                    <i class="fas fa-plus mr-2"></i>
                                                    <span>Add</span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Lunch -->
                                        <td>
                                            <?php if(isset($day['meals']['lunch']) && !empty($day['meals']['lunch']['recipe_id'])): ?>
                                            <div class="meal-slot flex items-center" data-slot-id="<?php echo $day['meals']['lunch']['id']; ?>">
                                                <div class="w-10 h-10 rounded-md overflow-hidden mr-2 bg-gray-100 flex-shrink-0">
                                                    <?php if(!empty($day['meals']['lunch']['image_path'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($day['meals']['lunch']['image_path']); ?>" alt="<?php echo htmlspecialchars($day['meals']['lunch']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                    <img src="../img/recipe-placeholder.jpg" alt="<?php echo htmlspecialchars($day['meals']['lunch']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow">
                                                    <p class="font-medium text-black"><?php echo htmlspecialchars($day['meals']['lunch']['recipe_name']); ?></p>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        <?php if(!empty($day['meals']['lunch']['calories'])): ?>
                                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($day['meals']['lunch']['calories']); ?> cal</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if(!empty($day['meals']['lunch']['protein'])): ?>
                                                        <span class="text-xs text-gray-500 ml-1"><?php echo htmlspecialchars($day['meals']['lunch']['protein']); ?>g protein</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <button class="remove-meal-btn text-red-500 hover:text-red-700 ml-2" data-slot-id="<?php echo $day['meals']['lunch']['id']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <div class="meal-slot empty flex items-center justify-center h-10 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 add-meal-btn" data-day="<?php echo $date; ?>" data-meal="lunch" data-slot-id="<?php echo isset($day['meals']['lunch']) ? $day['meals']['lunch']['id'] : ''; ?>">
                                                <div class="flex items-center text-gray-400">
                                                    <i class="fas fa-plus mr-2"></i>
                                                    <span>Add</span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Dinner -->
                                        <td>
                                            <?php if(isset($day['meals']['dinner']) && !empty($day['meals']['dinner']['recipe_id'])): ?>
                                            <div class="meal-slot flex items-center" data-slot-id="<?php echo $day['meals']['dinner']['id']; ?>">
                                                <div class="w-10 h-10 rounded-md overflow-hidden mr-2 bg-gray-100 flex-shrink-0">
                                                    <?php if(!empty($day['meals']['dinner']['image_path'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($day['meals']['dinner']['image_path']); ?>" alt="<?php echo htmlspecialchars($day['meals']['dinner']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                    <img src="../img/recipe-placeholder.jpg" alt="<?php echo htmlspecialchars($day['meals']['dinner']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow">
                                                    <p class="font-medium text-black"><?php echo htmlspecialchars($day['meals']['dinner']['recipe_name']); ?></p>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        <?php if(!empty($day['meals']['dinner']['calories'])): ?>
                                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($day['meals']['dinner']['calories']); ?> cal</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if(!empty($day['meals']['dinner']['protein'])): ?>
                                                        <span class="text-xs text-gray-500 ml-1"><?php echo htmlspecialchars($day['meals']['dinner']['protein']); ?>g protein</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <button class="remove-meal-btn text-red-500 hover:text-red-700 ml-2" data-slot-id="<?php echo $day['meals']['dinner']['id']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <div class="meal-slot empty flex items-center justify-center h-10 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 add-meal-btn" data-day="<?php echo $date; ?>" data-meal="dinner" data-slot-id="<?php echo isset($day['meals']['dinner']) ? $day['meals']['dinner']['id'] : ''; ?>">
                                                <div class="flex items-center text-gray-400">
                                                    <i class="fas fa-plus mr-2"></i>
                                                    <span>Add</span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Snacks -->
                                        <td>
                                            <?php if(isset($day['meals']['snacks']) && !empty($day['meals']['snacks']['recipe_id'])): ?>
                                            <div class="meal-slot flex items-center" data-slot-id="<?php echo $day['meals']['snacks']['id']; ?>">
                                                <div class="w-10 h-10 rounded-md overflow-hidden mr-2 bg-gray-100 flex-shrink-0">
                                                    <?php if(!empty($day['meals']['snacks']['image_path'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($day['meals']['snacks']['image_path']); ?>" alt="<?php echo htmlspecialchars($day['meals']['snacks']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                    <img src="../img/recipe-placeholder.jpg" alt="<?php echo htmlspecialchars($day['meals']['snacks']['recipe_name']); ?>" class="w-full h-full object-cover">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow">
                                                    <p class="font-medium text-black"><?php echo htmlspecialchars($day['meals']['snacks']['recipe_name']); ?></p>
                                                    <div class="flex flex-wrap gap-1 mt-1">
                                                        <?php if(!empty($day['meals']['snacks']['calories'])): ?>
                                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($day['meals']['snacks']['calories']); ?> cal</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if(!empty($day['meals']['snacks']['protein'])): ?>
                                                        <span class="text-xs text-gray-500 ml-1"><?php echo htmlspecialchars($day['meals']['snacks']['protein']); ?>g protein</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <button class="remove-meal-btn text-red-500 hover:text-red-700 ml-2" data-slot-id="<?php echo $day['meals']['snacks']['id']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <?php else: ?>
                                            <div class="meal-slot empty flex items-center justify-center h-10 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 add-meal-btn" data-day="<?php echo $date; ?>" data-meal="snacks" data-slot-id="<?php echo isset($day['meals']['snacks']) ? $day['meals']['snacks']['id'] : ''; ?>">
                                                <div class="flex items-center text-gray-400">
                                                    <i class="fas fa-plus mr-2"></i>
                                                    <span>Add</span>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Daily Nutrition -->
                                        <td class="text-right">
                                            <div class="flex flex-col items-end gap-1">
                                                <span class="font-medium <?php echo $day['total_calories'] > 0 ? 'text-black' : 'text-gray-400'; ?>">
                                                    <?php echo $day['total_calories'] > 0 ? $day['total_calories'] . ' cal' : 'No meals added'; ?>
                                                </span>
                                                
                                                <?php if($day['total_protein'] > 0): ?>
                                                <span class="text-xs text-gray-500">
                                                    <?php echo $day['total_protein']; ?>g protein
                                                </span>
                                                <?php endif; ?>
                                                
                                                <?php if($day['total_carbs'] > 0): ?>
                                                <span class="text-xs text-gray-500">
                                                    <?php echo $day['total_carbs']; ?>g carbs
                                                </span>
                                                <?php endif; ?>
                                                
                                                <?php if($day['total_fiber'] > 0): ?>
                                                <span class="text-xs text-gray-500">
                                                    <?php echo $day['total_fiber']; ?>g fiber
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-right font-medium">Weekly Totals:</td>
                                        <td class="text-right">
                                            <?php 
                                                $total_calories = 0;
                                                $total_protein = 0;
                                                $total_carbs = 0;
                                                $total_fiber = 0;
                                                
                                                foreach($current_plan['days'] as $day) {
                                                    $total_calories += $day['total_calories'];
                                                    $total_protein += $day['total_protein'];
                                                    $total_carbs += $day['total_carbs'];
                                                    $total_fiber += $day['total_fiber'];
                                                }
                                            ?>
                                            <div class="flex flex-col items-end gap-1">
                                                <span class="font-medium text-black"><?php echo $total_calories; ?> calories</span>
                                                
                                                <?php if($total_protein > 0): ?>
                                                <span class="text-xs text-gray-500"><?php echo $total_protein; ?>g protein</span>
                                                <?php endif; ?>
                                                
                                                <?php if($total_carbs > 0): ?>
                                                <span class="text-xs text-gray-500"><?php echo $total_carbs; ?>g carbs</span>
                                                <?php endif; ?>
                                                
                                                <?php if($total_fiber > 0): ?>
                                                <span class="text-xs text-gray-500"><?php echo $total_fiber; ?>g fiber</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-right font-medium">Daily Average:</td>
                                        <td class="text-right">
                                            <?php 
                                                $days_count = count($current_plan['days']);
                                                $avg_calories = $days_count > 0 ? round($total_calories / $days_count) : 0;
                                                $avg_protein = $days_count > 0 ? round($total_protein / $days_count) : 0;
                                                $avg_carbs = $days_count > 0 ? round($total_carbs / $days_count) : 0;
                                                $avg_fiber = $days_count > 0 ? round($total_fiber / $days_count) : 0;
                                            ?>
                                            <div class="flex flex-col items-end gap-1">
                                                <span class="font-medium text-black"><?php echo $avg_calories; ?> calories</span>
                                                
                                                <?php if($avg_protein > 0): ?>
                                                <span class="text-xs text-gray-500"><?php echo $avg_protein; ?>g protein</span>
                                                <?php endif; ?>
                                                
                                                <?php if($avg_carbs > 0): ?>
                                                <span class="text-xs text-gray-500"><?php echo $avg_carbs; ?>g carbs</span>
                                                <?php endif; ?>
                                                
                                                <?php if($avg_fiber > 0): ?>
                                                <span class="text-xs text-gray-500"><?php echo $avg_fiber; ?>g fiber</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- No meal plans yet -->
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-300 bg-opacity-20 mb-4">
                        <i class="fas fa-calendar-alt text-2xl text-black"></i>
                    </div>
                    <h3 class="text-lg font-medium text-black mb-2">No meal plans yet</h3>
                    <p class="text-text mb-4">Create your first meal plan to get started</p>
                    <button id="empty-create-plan-btn" class="bg-black text-white px-6 py-2 rounded-lg font-medium hover:bg-gray-800 transition-colors duration-200 cursor-pointer">
                        Create Your First Plan
                    </button>
                </div>
                <?php endif; ?>
                <!-- All Meal Plans Section -->
                <div class="mt-8">
                    <h2 class="text-2xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-black to-gray-700 mb-6">All Meal Plans</h2>
                    
                    <?php if(empty($user_plans)): ?>
                    <p class="text-text mb-4">You don't have any other meal plans.</p>
                    <?php else: ?>
                    <div class="bg-white rounded-xl shadow-md overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="meal-plan-table">
                                <thead>
                                    <tr>
                                        <th>Plan Name</th>
                                        <th>Date Range</th>
                                        <th>Plan Type</th>
                                        <th>Nutrition Targets</th>
                                        <th>Progress</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($user_plans as $plan): ?>
                                    <tr>
                                        <td class="font-medium"><?php echo htmlspecialchars($plan['name']); ?></td>
                                        <td>
                                            <?php 
                                                $start_date = new DateTime($plan['start_date']);
                                                $end_date = new DateTime($plan['end_date']);
                                                echo $start_date->format('M d') . ' - ' . $end_date->format('M d, Y'); 
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $badge_class = 'badge-yellow';
                                                switch($plan['plan_type']) {
                                                    case 'balanced':
                                                        $badge_class = 'badge-green';
                                                        break;
                                                    case 'low-carb':
                                                        $badge_class = 'badge-blue';
                                                        break;
                                                    case 'high-protein':
                                                        $badge_class = 'badge-purple';
                                                        break;
                                                    case 'vegetarian':
                                                    case 'vegan':
                                                        $badge_class = 'badge-green';
                                                        break;
                                                    case 'keto':
                                                        $badge_class = 'badge-red';
                                                        break;
                                                }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo ucfirst(htmlspecialchars($plan['plan_type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="flex flex-wrap gap-1">
                                                <?php if(!empty($plan['daily_calories'])): ?>
                                                <span class="nutrition-badge nutrition-calories">
                                                    <i class="fas fa-fire nutrition-icon"></i>
                                                    <?php echo htmlspecialchars($plan['daily_calories']); ?> cal
                                                </span>
                                                <?php endif; ?>
                                                
                                                <?php if(!empty($plan['daily_protein'])): ?>
                                                <span class="nutrition-badge nutrition-protein">
                                                    <i class="fas fa-drumstick-bite nutrition-icon"></i>
                                                    <?php echo htmlspecialchars($plan['daily_protein']); ?>g
                                                </span>
                                                <?php endif; ?>
                                                
                                                <?php if(!empty($plan['daily_carbs'])): ?>
                                                <span class="nutrition-badge nutrition-carbs">
                                                    <i class="fas fa-bread-slice nutrition-icon"></i>
                                                    <?php echo htmlspecialchars($plan['daily_carbs']); ?>g
                                                </span>
                                                <?php endif; ?>
                                                
                                                <?php if(!empty($plan['daily_fiber'])): ?>
                                                <span class="nutrition-badge nutrition-fiber">
                                                    <i class="fas fa-seedling nutrition-icon"></i>
                                                    <?php echo htmlspecialchars($plan['daily_fiber']); ?>g
                                                </span>
                                                <?php endif; ?>
                                                
                                                <?php if(empty($plan['daily_calories']) && empty($plan['daily_protein']) && empty($plan['daily_carbs']) && empty($plan['daily_fiber'])): ?>
                                                <span class="text-gray-400">Not set</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2">
                                                    <?php 
                                                        $progress = $plan['total_slots'] > 0 ? ($plan['filled_slots'] / $plan['total_slots']) * 100 : 0;
                                                    ?>
                                                    <div class="bg-yellow-300 h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <span class="text-xs font-medium"><?php echo $plan['filled_slots']; ?>/<?php echo $plan['total_slots']; ?></span>
                                            </div>
                                        </td>
                                        <td class="text-right">
                                            <div class="flex justify-end space-x-2">
                                                <a href="meal-plan-details.php?id=<?php echo $plan['id']; ?>" class="action-btn view-btn cursor-pointer">
                                                    <i class="fas fa-eye mr-1"></i> View
                                                </a>
                                                <button class="action-btn delete-btn delete-plan-btn cursor-pointer" data-plan-id="<?php echo $plan['id']; ?>">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Create Meal Plan Modal (Hidden by default) -->
    <div id="create-plan-modal" class="fixed inset-0 bg-gray-200 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto modal-animation p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-black to-gray-700">Create New Meal Plan</h2>
                <button id="close-create-modal" class="text-gray-400 hover:text-black focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="create-plan-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-6">
                    <label for="plan-name" class="block text-sm font-medium text-gray-700 mb-1">Plan Name</label>
                    <input type="text" id="plan-name" name="plan-name" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. Weekly Plan (Mar 18 - Mar 24)" required>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="start-date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="start-date" name="start-date" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" required>
                    </div>
                    
                    <div>
                        <label for="end-date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" id="end-date" name="end-date" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" required>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label for="plan-type" class="block text-sm font-medium text-gray-700 mb-1">Plan Type</label>
                    <select id="plan-type" name="plan-type" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" required>
                        <option value="">Select a plan type</option>
                        <option value="balanced">Balanced</option>
                        <option value="low-carb">Low Carb</option>
                        <option value="high-protein">High Protein</option>
                        <option value="vegetarian">Vegetarian</option>
                        <option value="vegan">Vegan</option>
                        <option value="keto">Keto</option>
                    </select>
                </div>
                
                <div class="mb-6">
                    <h3 class="text-sm font-medium text-gray-700 mb-3">Nutrition Targets</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="daily-calories" class="block text-sm text-gray-700 mb-1">Daily Calories</label>
                            <div class="relative">
                                <input type="number" id="daily-calories" name="daily-calories" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 1800">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">cal</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="daily-protein" class="block text-sm text-gray-700 mb-1">Daily Protein</label>
                            <div class="relative">
                                <input type="number" id="daily-protein" name="daily-protein" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 120">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">g</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="daily-carbs" class="block text-sm text-gray-700 mb-1">Daily Carbs</label>
                            <div class="relative">
                                <input type="number" id="daily-carbs" name="daily-carbs" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 200">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">g</span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <label for="daily-fiber" class="block text-sm text-gray-700 mb-1">Daily Fiber</label>
                            <div class="relative">
                                <input type="number" id="daily-fiber" name="daily-fiber" class="w-full px-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300" placeholder="e.g. 25">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                    <span class="text-gray-500">g</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Meal Types to Include</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="meal-types[]" value="breakfast" class="mr-2" checked>
                            <span>Breakfast</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="meal-types[]" value="lunch" class="mr-2" checked>
                            <span>Lunch</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="meal-types[]" value="dinner" class="mr-2" checked>
                            <span>Dinner</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="meal-types[]" value="snacks" class="mr-2">
                            <span>Snacks</span>
                        </label>
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Options</label>
                    <div class="flex flex-col gap-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="auto-generate" class="mr-2" checked>
                            <span>Auto-generate meal plan structure</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="use-favorites" class="mr-2">
                            <span>Include favorite recipes</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="shopping-list" class="mr-2" checked>
                            <span>Generate shopping list</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancel-create-plan" class="bg-white border border-gray-200 text-text px-6 py-2 rounded-lg font-medium hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="bg-black text-white px-6 py-2 rounded-lg font-medium">
                        Create Plan
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Meal Modal (Hidden by default) -->
    <div id="add-meal-modal" class="fixed inset-0 bg-gray-200 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto modal-animation p-6">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-serif font-bold text-transparent bg-clip-text bg-gradient-to-r from-black to-gray-700">Add Meal</h2>
                <button id="close-add-meal-modal" class="text-gray-400 hover:text-black focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="mb-6">
                <div class="relative">
                    <input type="text" id="recipe-search" placeholder="Search recipes..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-yellow-300">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-lg font-medium text-black mb-3">Your Recipes</h3>
                <div id="recipe-list" class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-64 overflow-y-auto p-1">
                    <?php if(empty($user_recipes)): ?>
                    <p class="col-span-2 text-center text-gray-500 py-4">No recipes found. <a href="add-recipe.php" class="text-yellow-300 hover:underline">Add some recipes</a> first.</p>
                    <?php else: ?>
                        <?php foreach($user_recipes as $recipe): ?>
                        <div class="meal-item flex items-center p-2 rounded-lg cursor-pointer border border-transparent hover:border-yellow-300" data-recipe-id="<?php echo $recipe['id']; ?>">
                            <div class="w-16 h-16 rounded-md overflow-hidden mr-3 bg-gray-100">
                                <?php if(!empty($recipe['image_path'])): ?>
                                <img src="../<?php echo htmlspecialchars($recipe['image_path']); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                <img src="../img/recipe-placeholder.jpg" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="w-full h-full object-cover">
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow">
                                <p class="font-medium text-black"><?php echo htmlspecialchars($recipe['name']); ?></p>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <?php if(!empty($recipe['calories'])): ?>
                                    <span class="text-xs text-gray-500"><?php echo htmlspecialchars($recipe['calories']); ?> cal</span>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($recipe['protein'])): ?>
                                    <span class="text-xs text-gray-500 ml-1"><?php echo htmlspecialchars($recipe['protein']); ?>g protein</span>
                                    <?php endif; ?>
                                    
                                    <?php if(!empty($recipe['diet_type'])): ?>
                                    <span class="text-xs px-2 py-0.5 bg-yellow-300 bg-opacity-20 text-black rounded-full"><?php echo htmlspecialchars($recipe['diet_type']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <form id="add-meal-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="add-meal" value="1">
                <input type="hidden" id="selected-recipe-id" name="recipe_id" value="">
                <input type="hidden" id="selected-slot-id" name="slot_id" value="">
                
                <div class="flex justify-end gap-3">
                    <button type="button" id="cancel-add-meal" class="bg-white border border-gray-200 text-text px-6 py-2 rounded-lg font-medium hover:bg-gray-50 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" id="confirm-add-meal" class="bg-black text-white px-6 py-2 rounded-lg font-medium cursor-pointer" disabled>
                        Add to Plan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/meal-plan.js"></script>
</body>
</html>