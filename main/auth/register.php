<?php
require_once"../../config.php";

$name = $email = $password = $confirm_password = $dietary_preference = "";
$name_err = $email_err = $password_err = $confirm_password_err = "";

if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["form_type"]) && $_POST["form_type"] == "register"){

    if(empty(trim($_POST["name"]))){
        $name_err = "Please enter your name.";
    } else{
        $name = trim($_POST["name"]);
    }
    
    if(empty(trim($_POST["email"]))){
        $email_err = "Please enter an email.";
    } else{
        if(!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)){
            $email_err = "Please enter a valid email.";
        } else{
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
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
    
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
    
    if(empty(trim($_POST["confirm_password"]))){
        $confirm_password_err = "Please confirm password.";     
    } else{
        $confirm_password = trim($_POST["confirm_password"]);
        if(empty($password_err) && ($password != $confirm_password)){
            $confirm_password_err = "Password did not match.";
        }
    }
    
    $dietary_preference = trim($_POST["dietary_preference"]);
    
    if(empty($name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err)){
        
        $sql = "INSERT INTO users (name, email, password, dietary_preference) VALUES (?, ?, ?, ?)";
         
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "ssss", $param_name, $param_email, $param_password, $param_dietary_preference);
            
            $param_name = $name;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_dietary_preference = $dietary_preference;
            
            if(mysqli_stmt_execute($stmt)){
                header("location: auth.php");
                exit;
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>