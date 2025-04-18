<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session to manage user authentication state
session_start();

// Include database configuration
require_once "../../config.php";

// Check if user is already logged in, redirect to dashboard if true
if(isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true){
    header("location: dashboard.php");
    exit;
}

// Initialize form variables and error messages
$name = $email = $password = $confirm_password = $dietary_preference = "";
$name_err = $email_err = $password_err = $confirm_password_err = $login_err = "";

// Handle login form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST["form_type"])){
    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter email.";
    } else{
        $email = trim($_POST["email"]);
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter your password.";
    } else{
        $password = trim($_POST["password"]);
    }

    // If no validation errors, attempt login
    if(empty($email_err) && empty($password_err)){
        $sql = "SELECT id, name, email, password FROM users WHERE email = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "s", $param_email);
            
            $param_email = $email;
            
            if(mysqli_stmt_execute($stmt)){
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1){                    
                    mysqli_stmt_bind_result($stmt, $id, $name, $email, $hashed_password);
                    if(mysqli_stmt_fetch($stmt)){
                        // Verify password and set session variables if successful
                        if(password_verify($password, $hashed_password)){
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["name"] = $name;
                            $_SESSION["email"] = $email;                            

                            header("location: dashboard.php");
                            exit;
                        } else{
                            $login_err = "Invalid email or password.";
                        }
                    }
                } else{
                    $login_err = "Invalid email or password.";
                }
            } else{
                $login_err = "Oops! Something went wrong. Please try again later.";
                error_log("Login error: " . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
        }
    }
}

// Handle registration form submission
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"]) && $_POST["form_type"] == "register"){
    // Validate name
    if(empty(trim($_POST["name"]))){
        $name_err = "Please enter your name.";
    } else{
        $name = trim($_POST["name"]);
    }
    
    // Validate email
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter an email.";
    } else{
        if(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)){
            $email_err = "Please enter a valid email.";
        } else{
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = ?";
            
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "s", $param_email);
                
                $param_email = trim($_POST["email"]);
                
                if(mysqli_stmt_execute($stmt)){
                    mysqli_stmt_store_result($stmt);
                    
                    if(mysqli_stmt_num_rows($stmt) == 1){
                        $email_err = "This email is already taken.";
                    } else{
                        $email = trim($_POST["email"]);
                    }
                } else{
                    echo "Oops! Something went wrong. Please try again later.";
                    error_log("Registration email check error: " . mysqli_error($conn));
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))){
        $password_err = "Please enter a password.";     
    } elseif(strlen(trim($_POST["password"])) < 8){
        $password_err = "Password must have at least 8 characters.";
    } elseif(!preg_match('/[A-Z]/', trim($_POST["password"]))){
        $password_err = "Password must contain at least one uppercase letter.";
    } elseif(!preg_match('/[0-9]/', trim($_POST["password"]))){
        $password_err = "Password must contain at least one number.";
    } elseif(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', trim($_POST["password"]))){
        $password_err = "Password must contain at least one special character.";
    } else{
        $password = trim($_POST["password"]);
    }
    
    // Validate password confirmation
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Get dietary preference if provided
    $dietary_preference = isset($_POST["dietary_preference"]) ? trim($_POST["dietary_preference"]) : "";
    
    // If no validation errors, proceed with registration
    if(empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)){
        
        $sql = "INSERT INTO users (name, email, password, dietary_preference) VALUES (?, ?, ?, ?)";
         
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "ssss", $param_name, $param_email, $param_password, $param_dietary_preference);
            
            $param_name = $name;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Hash password for security
            $param_dietary_preference = $dietary_preference;
            
            if(mysqli_stmt_execute($stmt)){
                $register_success = "Registration successful! You can now log in.";
                
                // Clear form fields
                $name = $email = $password = $confirm_password = $dietary_preference = "";
                
                // Show success message and redirect to login
                echo "<script>
                    window.onload = function() {
                        showAlert('Registration successful! You can now log in.', 'success');
                        setTimeout(function() {
                            document.getElementById('login-tab').click();
                        }, 2000);
                    }
                </script>";
            } else{
                echo "Oops! Something went wrong. Please try again later.";
                error_log("Registration insert error: " . mysqli_error($conn));
            }

            mysqli_stmt_close($stmt);
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
    <title>CookBook | Authentication</title>
    <!-- External CSS and Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../src/output.css">
    
    <!-- Custom CSS Styles -->
    <style>
        /* Import Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@300;400;500;600;700&display=swap');
        
        /* Background image container with overlay */
        .auth-container {
            background-image: url('https://source.unsplash.com/random/1200x800/?food,cooking');
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        /* Dark overlay for better text readability */
        .auth-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1;
        }
        
        /* Content positioning */
        .auth-content {
            position: relative;
            z-index: 2;
        }
        
        /* Form container styling */
        .form-container {
            backdrop-filter: blur(8px);
            background-color: rgba(255, 255, 255, 0.9);
        }
        
        /* Tab styling */
        .tab-active {
            color: #1C1C1C;
            border-bottom: 2px solid #FFD700;
        }
        
        .tab-inactive {
            color: #5E6472;
            border-bottom: 2px solid transparent;
        }
        
        /* Input focus states */
        input:focus, select:focus {
            border-color: #FFD700;
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        
        /* Social button animations */
        .social-btn {
            transition: all 0.3s ease;
        }
        
        .social-btn:hover {
            transform: translateY(-2px);
        }
        
        /* Button click effects */
        button {
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        button:active {
            transform: scale(0.95);
        }
        
        /* Ripple effect for buttons */
        button::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        button:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        
        /* Ripple animation keyframes */
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            20% {
                transform: scale(25, 25);
                opacity: 0.3;
            }
            100% {
                opacity: 0;
                transform: scale(40, 40);
            }
        }
        
        /* Error animation for wrong password */
        .shake-error {
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
            transform: translate3d(0, 0, 0);
            backface-visibility: hidden;
            perspective: 1000px;
        }
        
        /* Shake animation keyframes */
        @keyframes shake {
            10%, 90% {
                transform: translate3d(-1px, 0, 0);
            }
            
            20%, 80% {
                transform: translate3d(2px, 0, 0);
            }
            
            30%, 50%, 70% {
                transform: translate3d(-4px, 0, 0);
            }
            
            40%, 60% {
                transform: translate3d(4px, 0, 0);
            }
        }
        
        /* Error highlight styling */
        .error-highlight {
            border-color: #FF4136 !important;
            box-shadow: 0 0 0 3px rgba(255, 65, 54, 0.2) !important;
        }
    </style>
</head>
<body class="font-sans bg-white text-text min-h-screen">
    <div class="flex flex-col min-h-screen">
        <!-- Navigation Bar -->
        <nav class="bg-white shadow-sm w-full z-10">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 flex items-center">
                            <a href="../index.php" class="text-black font-serif font-bold text-2xl">CookBook</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        
        <!-- Authentication Section -->
        <div class="flex-grow flex items-center justify-center p-4">
            <div class="w-full max-w-6xl bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="flex flex-col md:flex-row">
                    <!-- Left Side - Image and CTA -->
                    <div class="md:w-1/2 auth-container relative bg-cover bg-center" style="background-image: url('../img/auth.jpg');">
                        <div class="absolute inset-0  bg-opacity-50"></div> <!-- Overlay for readability -->
                        <div class="auth-content h-full flex flex-col justify-center p-8 text-white relative">
                            <h2 class="text-3xl md:text-4xl font-serif font-bold mb-4 text-yellow-300 animate-fade-in" style="animation-delay: 0.1s;">
                                CookBook Journey Awaits
                            </h2>
                            <p class="text-lg mb-6 animate-fade-in" style="animation-delay: 0.2s;">
                                Start your journey towards smart meal planning, recipe organization, and healthier eating habits.
                            </p>
                            <div class="space-y-4 animate-fade-in" style="animation-delay: 0.3s;">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                        <i class="fas fa-book text-black"></i>
                                    </div>
                                    <p>Store and organize all your favorite recipes</p>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                        <i class="fas fa-calendar-alt text-black"></i>
                                    </div>
                                    <p>Create personalized weekly meal plans</p>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                        <i class="fas fa-shopping-basket text-black"></i>
                                    </div>
                                    <p>Generate smart shopping lists automatically</p>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-yellow-300 bg-opacity-20 flex items-center justify-center mr-4">
                                        <i class="fas fa-heartbeat text-black"></i>
                                    </div>
                                    <p>Track nutritional information for healthier meals</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    
                    <!-- Right Side - Authentication Forms -->
                    <div class="md:w-1/2 form-container p-8">
                        <div class="mb-8 flex justify-center">
                            <div class="flex space-x-8">
                                <button id="login-tab" class="text-lg font-medium py-2 px-1 tab-active">Login</button>
                                <button id="signup-tab" class="text-lg font-medium py-2 px-1 tab-inactive">Sign Up</button>
                            </div>
                        </div>
                        
                        <!-- Login Form -->
                        <div id="login-form" class="animate-fade-in">
                            <h3 class="text-2xl font-bold text-black mb-6 text-center">Welcome Back</h3>
                            
                            <?php if(!empty($login_err)): ?>
                                <div id="login-error-alert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 shake-error">
                                    <strong class="font-bold">Error!</strong>
                                    <span class="block sm:inline"> <?php echo $login_err; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <form id="login-form-element" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-6">
                                <div>
                                    <label for="login-email" class="block text-sm font-medium text-text mb-1">Email Address</label>
                                    <input type="email" id="login-email" name="email" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none <?php echo (!empty($email_err) || !empty($login_err)) ? 'error-highlight' : ''; ?>" value="<?php echo $email; ?>" placeholder="your@email.com" required>
                                    <p id="login-email-error" class="text-red-500 text-sm mt-1 <?php echo (!empty($email_err)) ? '' : 'hidden'; ?>"><?php echo $email_err; ?></p>
                                </div>
                                
                                <div>
                                <div class="flex items-center justify-between mb-1">
                                        <label for="login-password" class="block text-sm font-medium text-text">Password</label>
                                        <a href="#" class="text-sm text-yellow-300 hover:underline">Forgot Password?</a>
                                    </div>
                                    <div class="relative">
                                        <input type="password" id="login-password" name="password" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>" placeholder="••••••••" required>
                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-text toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p id="login-password-error" class="text-red-500 text-sm mt-1 <?php echo (!empty($password_err)) ? '' : 'hidden'; ?>"><?php echo $password_err; ?></p>
                                </div>
                                
                                <div>
                                    <button type="submit" class="w-full bg-black hover:bg-opacity-90 text-white font-medium py-3 px-4 rounded-lg shadow-md transition duration-300 ease-in-out btn-with-effect">
                                        <span class="relative z-10">Login</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Sign Up Form (Hidden by default) -->
                        <div id="signup-form" class="hidden animate-fade-in">
                            <h3 class="text-2xl font-bold text-black mb-6 text-center">Create Your Account</h3>
                            
                            <form id="signup-form-element" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="space-y-5">
                                <input type="hidden" name="form_type" value="register">
                                <div>
                                    <label for="signup-name" class="block text-sm font-medium text-text mb-1">Full Name</label>
                                    <input type="text" id="signup-name" name="name" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none <?php echo (!empty($name_err)) ? 'error-highlight' : ''; ?>" value="<?php echo $name; ?>" placeholder="John Doe" required>
                                    <p id="signup-name-error" class="text-red-500 text-sm mt-1 <?php echo (!empty($name_err)) ? '' : 'hidden'; ?>"><?php echo $name_err; ?></p>
                                </div>
                                
                                <div>
                                    <label for="signup-email" class="block text-sm font-medium text-text mb-1">Email Address</label>
                                    <input type="email" id="signup-email" name="email" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none <?php echo (!empty($email_err)) ? 'error-highlight' : ''; ?>" value="<?php echo $email; ?>" placeholder="your@email.com" required>
                                    <p id="signup-email-error" class="text-red-500 text-sm mt-1 <?php echo (!empty($email_err)) ? '' : 'hidden'; ?>"><?php echo $email_err; ?></p>
                                </div>
                                
                                <div>
                                    <label for="signup-password" class="block text-sm font-medium text-text mb-1">Password</label>
                                    <div class="relative">
                                        <input type="password" id="signup-password" name="password" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none <?php echo (!empty($password_err)) ? 'error-highlight' : ''; ?>" placeholder="••••••••" required>
                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-text toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p id="signup-password-error" class="text-red-500 text-sm mt-1 <?php echo (!empty($password_err)) ? '' : 'hidden'; ?>"><?php echo $password_err; ?></p>
                                    <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters with 1 uppercase, 1 number, and 1 special character</p>
                                </div>
                                
                                <div>
                                    <label for="signup-confirm-password" class="block text-sm font-medium text-text mb-1">Confirm Password</label>
                                    <div class="relative">
                                        <input type="password" id="signup-confirm-password" name="confirm_password" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none <?php echo (!empty($confirm_password_err)) ? 'error-highlight' : ''; ?>" placeholder="••••••••" required>
                                        <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-text toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p id="signup-confirm-password-error" class="text-red-500 text-sm mt-1 <?php echo (!empty($confirm_password_err)) ? '' : 'hidden'; ?>"><?php echo $confirm_password_err; ?></p>
                                </div>
                                
                                <div>
                                    <label for="dietary-preference" class="block text-sm font-medium text-text mb-1">Dietary Preference (Optional)</label>
                                    <select id="dietary-preference" name="dietary_preference" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none">
                                        <option value="">Select a preference</option>
                                        <option value="none">No Preference</option>
                                        <option value="vegetarian">Vegetarian</option>
                                        <option value="non-vegetarian">Non-Vegetarian</option>
                                    </select>
                                </div>
                                
                                <div class="flex items-start">
                                    <input id="terms" name="terms" type="checkbox" class="h-4 w-4 text-yellow-300 border-gray-300 rounded mt-1" required>
                                    <label for="terms" class="ml-2 block text-sm text-text">
                                        I agree to the <a href="#" class="text-yellow-300 hover:underline">Terms of Service</a> and <a href="#" class="text-yellow-300 hover:underline">Privacy Policy</a>
                                    </label>
                                </div>
                                <p id="terms-error" class="text-red-500 text-sm mt-1 hidden"></p>
                                
                                <div>
                                    <button type="submit" class="w-full bg-yellow-300 hover:bg-opacity-90 text-black font-medium py-3 px-4 rounded-lg shadow-md transition duration-300 ease-in-out btn-with-effect">
                                        <span class="relative z-10">Create Account</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="bg-white py-6 border-t">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-500 mb-4 md:mb-0">&copy; 2025 CookBook. All rights reserved.</p>
                    <div class="flex space-x-6">
                        <a href="#" class="text-gray-500 hover:text-yellow-400 transition-colors">Privacy Policy</a>
                        <a href="#" class="text-gray-500 hover:text-yellow-400 transition-colors">Terms of Service</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <script src = "../js/auth.js">    </script>
</body>
</html>