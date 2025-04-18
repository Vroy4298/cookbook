<?php
session_start();
 
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: auth.php");
    exit;
}

require_once "../../config.php";

$name_err = $current_password_err = $new_password_err = $confirm_password_err = "";
$success_message = $error_message = "";

if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if(isset($_POST["update_name"])) {
        if(empty(trim($_POST["name"]))){
            $name_err = "Please enter your name.";
        } else {
            $name = trim($_POST["name"]);
            
            $sql = "UPDATE users SET name = ? WHERE id = ?";
            
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "si", $name, $_SESSION["id"]);
                
                if(mysqli_stmt_execute($stmt)){
                    $_SESSION["name"] = $name;
                    $success_message = "Your name has been updated successfully.";
                } else{
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    } 
    elseif(isset($_POST["change_password"])) {
        if(empty(trim($_POST["current_password"]))){
            $current_password_err = "Please enter your current password.";     
        } else{
            $sql = "SELECT password FROM users WHERE id = ?";
            
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
                
                if(mysqli_stmt_execute($stmt)){
                    mysqli_stmt_store_result($stmt);
                    
                    if(mysqli_stmt_num_rows($stmt) == 1){                    
                        mysqli_stmt_bind_result($stmt, $hashed_password);
                        if(mysqli_stmt_fetch($stmt)){
                            if(!password_verify($_POST["current_password"], $hashed_password)){
                                $current_password_err = "The password you entered is not valid.";
                            }
                        }
                    }
                } else{
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        }
        
        if(empty(trim($_POST["new_password"]))){
            $new_password_err = "Please enter the new password.";     
        } elseif(strlen(trim($_POST["new_password"])) < 6){
            $new_password_err = "Password must have at least 6 characters.";
        } else{
            $new_password = trim($_POST["new_password"]);
        }
        
        if(empty(trim($_POST["confirm_password"]))){
            $confirm_password_err = "Please confirm the password.";
        } else{
            $confirm_password = trim($_POST["confirm_password"]);
            if(empty($new_password_err) && ($new_password != $confirm_password)){
                $confirm_password_err = "Password did not match.";
            }
        }
        
        if(empty($current_password_err) && empty($new_password_err) && empty($confirm_password_err)){
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            
            if($stmt = mysqli_prepare($conn, $sql)){
                $param_password = password_hash($new_password, PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($stmt, "si", $param_password, $_SESSION["id"]);
                
                if(mysqli_stmt_execute($stmt)){
                    $success_message = "Your password has been updated successfully.";
                } else{
                    $error_message = "Oops! Something went wrong. Please try again later.";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}

$user_email = "";
$sql = "SELECT email FROM users WHERE id = ?";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    if(mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        if($row = mysqli_fetch_assoc($result)) {
            $user_email = $row['email'];
        }
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/logo.png" sizes="62x62">
    <title>CookBook | Profile</title>
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

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1),
            0 4px 6px -2px rgba(0, 0, 0, 0.05);
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
                        <a href="shopping-list.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
                            <i class="fas fa-shopping-basket mr-3 text-yellow-300"></i>
                            <span>Shopping List</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="p-4 border-t border-gray-800">
                <a href="logout.php" class="logout-button flex items-center text-gray-300 hover:text-white">
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
                        <a href="logout.php" class="sidebar-link flex items-center px-6 py-3 text-gray-300 hover:text-white">
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
                        Your Profile
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
                            <a href="settings.php" class="block px-4 py-2 text-sm text-text hover:bg-white">Settings</a>
                            <div class="border-t border-gray-200"></div>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-text hover:bg-white">Logout</a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Profile Content -->
            <main class="flex-grow p-6 overflow-auto">
                <!-- Success/Error Messages -->
                <?php if(!empty($error_message)): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?php echo $error_message; ?></p>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                    <div class="flex flex-col items-center justify-center text-center">
                        <div class="w-24 h-24 rounded-full bg-yellow-300 text-black flex items-center justify-center text-3xl font-medium mb-4">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
                        <h2 class="text-2xl font-bold text-black mb-1">
                            <?php echo htmlspecialchars($_SESSION["name"]); ?>
                        </h2>
                        <p class="text-black mb-3">
                            <?php echo htmlspecialchars($user_email); ?>
                        </p>
                    </div>
                </div>


                <!-- Profile Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Update Name -->
                    <div class="bg-white rounded-xl shadow-md p-6 mb-0">
                        <h3 class="text-xl font-bold text-black mb-4">
                            <i class="fas fa-user text-yellow-300 mr-2"></i> Update Your Name
                        </h3>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-4">
                                <label for="name" class="block text-text mb-1">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_SESSION["name"]); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-300 <?php echo (!empty($name_err)) ? 'border-red-500' : ''; ?>">
                                <?php if(!empty($name_err)): ?>
                                    <p class="text-red-500 text-sm mt-1"><?php echo $name_err; ?></p>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="update_name" class="w-full bg-yellow-300 hover:bg-yellow-400 text-black font-medium py-2 px-4 rounded-lg shadow-sm">
                                Update Name
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-white rounded-xl shadow-md p-6">
                        <h3 class="text-xl font-bold text-black mb-4">
                            <i class="fas fa-lock text-yellow-300 mr-2"></i> Change Your Password
                        </h3>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                            <div class="mb-4">
                                <label for="current_password" class="block text-text mb-1">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-300 <?php echo (!empty($current_password_err)) ? 'border-red-500' : ''; ?>">
                                <?php if(!empty($current_password_err)): ?>
                                    <p class="text-red-500 text-sm mt-1"><?php echo $current_password_err; ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="mb-4">
                                <label for="new_password" class="block text-text mb-1">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-300 <?php echo (!empty($new_password_err)) ? 'border-red-500' : ''; ?>">
                                <?php if(!empty($new_password_err)): ?>
                                    <p class="text-red-500 text-sm mt-1"><?php echo $new_password_err; ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="block text-text mb-1">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-300 <?php echo (!empty($confirm_password_err)) ? 'border-red-500' : ''; ?>">
                                <?php if(!empty($confirm_password_err)): ?>
                                    <p class="text-red-500 text-sm mt-1"><?php echo $confirm_password_err; ?></p>
                                <?php endif; ?>
                            </div>
                            <button type="submit" name="change_password" class="w-full bg-yellow-300 hover:bg-yellow-400 text-black font-medium py-2 px-4 rounded-lg shadow-sm">
                                Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        // User menu toggle
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');

        userMenuButton.addEventListener('click', () => {
            userMenu.classList.toggle('hidden');
        });

        // Close user menu when clicking outside
        document.addEventListener('click', (event) => {
            if (!userMenuButton.contains(event.target) && !userMenu.contains(event.target)) {
                userMenu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>