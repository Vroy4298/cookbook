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

$item_added = false;
$error_message = "";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["item-name"])) {
    $item_name = trim($_POST["item-name"]);
    $quantity = !empty($_POST["item-quantity"]) ? intval($_POST["item-quantity"]) : 1;
    $unit = !empty($_POST["item-unit"]) ? trim($_POST["item-unit"]) : "";
    $category = !empty($_POST["item-category"]) ? trim($_POST["item-category"]) : "other";
    $notes = !empty($_POST["item-notes"]) ? trim($_POST["item-notes"]) : NULL;
    
    if(empty($item_name)) {
        $error_message = "Please enter an item name.";
    } else {
        $sql = "INSERT INTO shopping_list_items (user_id, name, quantity, unit, category, notes, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "isdsss", 
                $param_user_id, 
                $param_name, 
                $param_quantity, 
                $param_unit, 
                $param_category, 
                $param_notes
            );
            
            $param_user_id = $_SESSION["id"];
            $param_name = $item_name;
            $param_quantity = $quantity;
            $param_unit = $unit;
            $param_category = $category;
            $param_notes = $notes;
            
            if(mysqli_stmt_execute($stmt)) {
                $item_added = true;
            } else {
                $error_message = "Error adding item: " . mysqli_error($conn);
            }
            
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "Error preparing statement: " . mysqli_error($conn);
        }
    }
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_item"])) {
    $item_id = intval($_POST["item_id"]);
    $completed = intval($_POST["completed"]);
    
    $sql_verify = "SELECT id FROM shopping_list_items WHERE id = ? AND user_id = ?";
    
    if($stmt_verify = mysqli_prepare($conn, $sql_verify)) {
        mysqli_stmt_bind_param($stmt_verify, "ii", $item_id, $_SESSION["id"]);
        
        if(mysqli_stmt_execute($stmt_verify)) {
            $result_verify = mysqli_stmt_get_result($stmt_verify);
            
            if(mysqli_num_rows($result_verify) == 1) {
                $sql_update = "UPDATE shopping_list_items SET completed = ?, updated_at = NOW() WHERE id = ?";
                
                if($stmt_update = mysqli_prepare($conn, $sql_update)) {
                    mysqli_stmt_bind_param($stmt_update, "ii", $completed, $item_id);
                    
                    if(mysqli_stmt_execute($stmt_update)) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit;
                    }
                }
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error updating item']);
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["delete_item"])) {
    $item_id = intval($_POST["item_id"]);
    
    $sql_verify = "SELECT id FROM shopping_list_items WHERE id = ? AND user_id = ?";
    
    if($stmt_verify = mysqli_prepare($conn, $sql_verify)) {
        mysqli_stmt_bind_param($stmt_verify, "ii", $item_id, $_SESSION["id"]);
        
        if(mysqli_stmt_execute($stmt_verify)) {
            $result_verify = mysqli_stmt_get_result($stmt_verify);
            
            if(mysqli_num_rows($result_verify) == 1) {
                $sql_delete = "DELETE FROM shopping_list_items WHERE id = ?";
                
                if($stmt_delete = mysqli_prepare($conn, $sql_delete)) {
                    mysqli_stmt_bind_param($stmt_delete, "i", $item_id);
                    
                    if(mysqli_stmt_execute($stmt_delete)) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit;
                    }
                }
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error deleting item']);
    exit;
}

$shopping_items = array();
$sql = "SELECT * FROM shopping_list_items WHERE user_id = ? ORDER BY category, completed, created_at DESC";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        
        while($row = mysqli_fetch_assoc($result)) {
            $shopping_items[] = $row;
        }
    }
}

$items_by_category = array();
foreach($shopping_items as $item) {
    $category = $item['category'];
    if(!isset($items_by_category[$category])) {
        $items_by_category[$category] = array();
    }
    $items_by_category[$category][] = $item;
}

$total_items = count($shopping_items);
$completed_items = 0;
foreach($shopping_items as $item) {
    if($item['completed']) {
        $completed_items++;
    }
}
$remaining_items = $total_items - $completed_items;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/logo.png" sizes="62x62">
    <title>CookBook | Shopping List</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../src/output.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@300;400;500;600;700&display=swap');

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

        .shopping-item {
            transition: all 0.3s ease;
        }

        .shopping-item:hover {
            background-color: rgba(255, 215, 0, 0.05);
        }

        .shopping-item.completed {
            background-color: #f9f9f9;
        }

        .shopping-item.completed .item-name {
            text-decoration: line-through;
            color: #9ca3af;
        }

        /* Modal Animation */
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
        
        /* Toast Animation */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
            }
            to {
                transform: translateX(0);
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
            }
            to {
                transform: translateX(100%);
            }
        }
        
        .toast-slide-in {
            animation: slideIn 0.3s forwards;
        }
        
        .toast-slide-out {
            animation: slideOut 0.3s forwards;
        }
    </style>
</head>
<body class="font-sans bg-white text-text min-h-screen flex flex-col">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Left Sidebar Navigation -->
        <aside class="bg-black text-white w-full md:w-64 flex-shrink-0 md:flex flex-col hidden">
            <div class="p-4 border-b border-gray-800">
                <h2 class="font-serif text-2xl font-bold text-yellow-300">
                    <a href="dashboard.php">CookBook</a>
                </h2>
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
                        <a href="meal-plan.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3 text-yellow-300"></i>
                            <span>Meal Plans</span>
                        </a>
                    </li>
                    <li>
                        <a href="shopping-list.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
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

        <!-- Mobile Sidebar Menu (Hidden) -->
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
                        <a href="meal-plan.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-calendar-alt mr-3 text-yellow-300"></i>
                            <span>Meal Plans</span>
                        </a>
                    </li>
                    <li>
                        <a href="shopping-list.php" class="sidebar-link active flex items-center px-6 py-3 text-gray-300 hover:text-white">
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
                        Shopping List
                    </h1>
                </div>

                <div class="flex items-center">
                    <span class="mr-4 text-text">Welcome, <span id="user-name" class="font-medium"><?php echo htmlspecialchars($_SESSION["name"]); ?></span>!</span>
                    <div class="relative">
                        <button id="user-menu-button" class="flex items-center focus:outline-none">
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

            <!-- Shopping List Content -->
            <main class="flex-grow p-6 overflow-auto">
                
                <?php if(!empty($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                    <span class="block sm:inline"><?php echo $error_message; ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Shopping List Header -->
                <div class="mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <h2 class="text-2xl font-serif font-bold text-black mb-4 md:mb-0">
                            My Shopping List
                        </h2>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <button id="add-item-btn" class="bg-yellow-300 hover:bg-yellow-400 text-black font-medium py-2 px-4 rounded-lg shadow-sm flex items-center justify-center cursor-pointer">
                                <i class="fas fa-plus mr-2"></i>
                                Add Item
                            </button>
                        </div>
                    </div>
                </div>   
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-xl shadow-md p-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                <i class="fas fa-list-ul text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray">Total Items</h3>
                                <p id="total-items" class="text-2xl font-bold text-black"><?php echo $total_items; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-md p-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-text">Completed</h3>
                                <p id="completed-items" class="text-2xl font-bold text-black"><?php echo $completed_items; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl shadow-md p-4">
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                <i class="fas fa-shopping-basket text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-text">Remaining</h3>
                                <p id="remaining-items" class="text-2xl font-bold text-black"><?php echo $remaining_items; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shopping List -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-4 border-b border-gray-200 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <h3 class="font-medium text-black">Shopping Items</h3>
                            <div class="flex items-center">
                                <span id="list-stats" class="text-sm text-text">Showing <?php echo $total_items; ?> items</span>
                            </div>
                        </div>
                    </div>
                    
                    <div id="shopping-list-container" class="divide-y divide-gray-200">
                        <?php if(empty($shopping_items)): ?>
                        <div id="empty-state" class="p-8 text-center">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-yellow-300 bg-opacity-20 mb-4">
                                <i class="fas fa-shopping-basket text-2xl text-black"></i>
                            </div>
                            <h3 class="text-lg font-medium text-black mb-2">Your shopping list is empty</h3>
                            <p class="text-text mb-4">Add items to your shopping list to get started</p>
                            <button id="empty-add-item-btn" class="bg-yellow-300 hover:bg-yellow-400 text-black font-medium py-2 px-4 rounded-lg shadow-sm cursor-pointer">
                                Add Your First Item
                            </button>
                        </div>
                        <?php else: ?>
                            <?php foreach($items_by_category as $category => $items): ?>
                            <div class="category-section">
                                <div class="p-3 bg-gray-50 border-b border-gray-200">
                                    <h4 class="font-medium text-black capitalize"><?php echo htmlspecialchars($category); ?></h4>
                                </div>
                                <div class="divide-y divide-gray-100">
                                    <?php foreach($items as $item): ?>
                                    <div class="shopping-item <?php echo $item['completed'] ? 'completed' : ''; ?> p-4 flex items-center" data-item-id="<?php echo $item['id']; ?>">
                                        <input type="checkbox" class="item-checkbox w-5 h-5 mr-3 rounded border-gray-300 text-yellow-300 focus:ring-yellow-300" <?php echo $item['completed'] ? 'checked' : ''; ?>>
                                        <span class="item-name flex-grow">
                                            <?php 
                                                $display_text = htmlspecialchars($item['name']);
                                                if(!empty($item['quantity']) && !empty($item['unit'])) {
                                                    $display_text .= ' (' . htmlspecialchars($item['quantity']) . ' ' . htmlspecialchars($item['unit']) . ')';
                                                } elseif(!empty($item['quantity'])) {
                                                    $display_text .= ' (' . htmlspecialchars($item['quantity']) . ')';
                                                }
                                                echo $display_text;
                                            ?>
                                        </span>
                                        <div class="flex items-center">
                                            <?php 
                                                $category_colors = [
                                                    'produce' => 'bg-green-100 text-green-800',
                                                    'dairy' => 'bg-blue-100 text-blue-800',
                                                    'meat' => 'bg-red-100 text-red-800',
                                                    'bakery' => 'bg-orange-100 text-orange-800',
                                                    'pantry' => 'bg-yellow-100 text-yellow-800',
                                                    'frozen' => 'bg-indigo-100 text-indigo-800',
                                                    'beverages' => 'bg-purple-100 text-purple-800',
                                                    'other' => 'bg-gray-100 text-gray-800'
                                                ];
                                                $color_class = isset($category_colors[$item['category']]) ? $category_colors[$item['category']] : $category_colors['other'];
                                            ?>
                                            <span class="text-xs <?php echo $color_class; ?> px-2 py-1 rounded-full mr-3 capitalize"><?php echo htmlspecialchars($item['category']); ?></span>
                                            <button class="delete-item text-gray-400 hover:text-red-500 cursor-pointer">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div id="add-item-modal" class="fixed inset-0 bg-gray-200 bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-xl shadow-lg max-w-md w-full modal-animation p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-black" id="modal-title">Add New Item</h3>
                <button id="close-add-modal" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="add-item-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-4">
                    <label for="item-name" class="block text-sm font-medium text-text mb-1">Item Name</label>
                    <input type="text" id="item-name" name="item-name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300" placeholder="Enter item name" required>
                </div>
                
                <div class="mb-4">
                    <label for="item-quantity" class="block text-sm font-medium text-text mb-1">Quantity</label>
                    <div class="flex">
                        <input type="number" id="item-quantity" name="item-quantity" class="w-1/3 px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300" value="1" min="1">
                        <input type="text" id="item-unit" name="item-unit" class="w-2/3 px-3 py-2 border border-gray-300 border-l-0 rounded-r-md focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300" placeholder="Unit (e.g., lbs, oz, pieces)">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="item-category" class="block text-sm font-medium text-text mb-1">Category</label>
                    <select id="item-category" name="item-category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300">
                        <option value="produce">Produce</option>
                        <option value="dairy">Dairy</option>
                        <option value="meat">Meat & Seafood</option>
                        <option value="bakery">Bakery</option>
                        <option value="pantry">Pantry</option>
                        <option value="frozen">Frozen</option>
                        <option value="beverages">Beverages</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="item-notes" class="block text-sm font-medium text-text mb-1">Notes (Optional)</label>
                    <textarea id="item-notes" name="item-notes" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300" rows="2" placeholder="Add any notes about this item"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-add-item" class="px-4 py-2 border border-gray-300 rounded-md text-text hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-yellow-300 text-black rounded-md hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-300 focus:border-yellow-300 cursor-pointer">
                        Add Item
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="fixed top-4 right-4 bg-white shadow-lg rounded-lg p-4 max-w-md transform translate-x-full transition-transform duration-300 z-50">
        <div class="flex items-center">
            <div id="toast-icon" class="flex-shrink-0 w-6 h-6 mr-3 text-green-500">
                <i class="fas fa-check-circle"></i>
            </div>
            <div>
                <h4 id="toast-title" class="text-sm font-medium text-gray-900">Success</h4>
                <p id="toast-message" class="text-sm text-gray-500">Your action was successful!</p>
            </div>
            <button id="close-toast" class="ml-auto text-gray-400 hover:text-gray-500">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <script src="../js/shopping-list.js"></script>
</body>
</html>