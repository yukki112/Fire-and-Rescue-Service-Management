<?php
// Start session and include database connection
session_start();
require_once 'config/db_connection.php';

require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';
require 'vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$errors = [];
$success = false;
$verificationSent = false;
$firstName = $lastName = $middleName = $username = $email = '';
$verificationMethod = ''; // Will be 'email' or 'sms'
$emailError = false; // Flag to track email errors

// Process form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a verification code submission
    if (isset($_POST['verify_code'])) {
        $submittedCode = implode('', $_POST['verification_code']);
        $storedCode = $_SESSION['verification_code'];
        $verificationMethod = $_SESSION['verification_method'];
        
        if ($submittedCode === $storedCode) {
            // Code matches - complete registration
            $firstName = $_SESSION['reg_data']['firstName'];
            $lastName = $_SESSION['reg_data']['lastName'];
            $middleName = $_SESSION['reg_data']['middleName'];
            $username = $_SESSION['reg_data']['username'];
            $email = $_SESSION['reg_data']['email'];
            $password = $_SESSION['reg_data']['password'];
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user into database
            $sql = "INSERT INTO users (
                first_name, 
                last_name,
                middle_name,
                username,
                email, 
                password_hash, 
                is_verified
            ) VALUES (
                '$firstName',
                '$lastName',
                " . ($middleName ? "'$middleName'" : "NULL") . ",
                '$username',
                '$email',
                '$passwordHash',
                1
            )";
            
            if (mysqli_query($conn, $sql)) {
                $success = true;
                // Clear session data
                unset($_SESSION['verification_code']);
                unset($_SESSION['verification_method']);
                unset($_SESSION['reg_data']);
                unset($_SESSION['verification_pending']);
                
                // Redirect to login after 3 seconds
                header("Refresh: 3; url=login.php");
            } else {
                $errors[] = "Registration failed: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['verification_error'] = "Invalid verification code. Please try again.";
            header("Location: ".$_SERVER['PHP_SELF']);
            exit();
        }
    }
    // Check if this is a verification method selection
    elseif (isset($_POST['verification_method'])) {
        // Get all the registration data from session
        $regData = $_SESSION['reg_data'];
        $verificationMethod = $_POST['verification_method'];
        
        // Generate 6-digit verification code
        $verificationCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['verification_code'] = $verificationCode;
        $_SESSION['verification_method'] = $verificationMethod;
        
        if ($verificationMethod === 'email') {
            // Send email verification
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'Stephenviray12@gmail.com'; // Replace with your email
                $mail->Password = 'bubr nckn tgqf lvus'; // Replace with your app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                
                $mail->setFrom('noreply@frsm.com', 'FRSM Registration');
                $mail->addAddress($regData['email'], $regData['firstName'] . ' ' . $regData['lastName']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your FRSM Account';
                $mail->Body = "
                    <div style='background: linear-gradient(135deg, #DC143C 0%, #B91C3C 100%); padding: 32px; border-radius: 18px; color: #fff; font-family: Inter, Arial, sans-serif; box-shadow: 0 4px 24px rgba(220,20,60,0.12); max-width: 480px; margin: 0 auto;'>
                        <div style='text-align: center; margin-bottom: 24px;'>
                            <img src='img/frsm1.png' alt='FRSM Logo' style='width: 80px; height: 80px; border-radius: 50%; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 12px;'>
                            <h2 style='margin: 0; font-size: 28px; font-weight: 700; letter-spacing: 1px;'>Welcome to FRSM!</h2>
                        </div>
                        <div style='background: #fff; color: #991B1B; border-radius: 12px; padding: 24px 18px; margin-bottom: 18px; box-shadow: 0 2px 8px rgba(220,20,60,0.08);'>
                            <p style='font-size: 16px; margin-bottom: 10px;'>Hello <strong>{$regData['firstName']}</strong>,</p>
                            <p style='font-size: 15px; margin-bottom: 18px;'>Your verification code is:</p>
                            <div style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #DC143C; background: #FEE2E2; border-radius: 8px; padding: 12px 0; margin-bottom: 10px; text-align: center;'>
                                $verificationCode
                            </div>
                            <p style='font-size: 14px; color: #991B1B; margin-bottom: 0;'>Enter this code to complete your registration.</p>
                        </div>
                        <p style='font-size: 13px; color: #FEE2E2; text-align: center; margin-bottom: 0;'>This code will expire in <strong>10 minutes</strong>.</p>
                        <hr style='border: none; border-top: 1px solid #FEE2E2; margin: 24px 0;'>
                        <p style='font-size: 12px; color: #FEE2E2; text-align: center; margin-bottom: 0;'>If you did not request this, please ignore this email.</p>
                    </div>
                ";
                
                $mail->send();
                $verificationSent = true;
            } catch (Exception $e) {
                $emailError = true;
                $errors[] = "Failed to send verification email: " . $mail->ErrorInfo;
            }
        } else {
            // SMS verification would go here
            // For now, we'll just set it as sent
            $verificationSent = true;
        }
    }
    // Check if user wants to go back to registration
    elseif (isset($_POST['back_to_register'])) {
        unset($_SESSION['verification_pending']);
        unset($_SESSION['reg_data']);
        unset($_SESSION['verification_code']);
        unset($_SESSION['verification_method']);
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    // Otherwise process the initial registration form
    else {
        // Sanitize and validate inputs
        $firstName = mysqli_real_escape_string($conn, trim($_POST['firstName']));
        $lastName = mysqli_real_escape_string($conn, trim($_POST['lastName']));
        $middleName = isset($_POST['middleName']) ? mysqli_real_escape_string($conn, trim($_POST['middleName'])) : '';
        $username = mysqli_real_escape_string($conn, trim($_POST['username']));
        $email = mysqli_real_escape_string($conn, trim($_POST['email']));
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirmPassword'];
        
        // Validate inputs
        if (empty($firstName)) $errors[] = "First name is required";
        if (empty($lastName)) $errors[] = "Last name is required";
        if (empty($username)) $errors[] = "Username is required";
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = "Username can only contain letters, numbers and underscores";
        if (strlen($username) < 4) $errors[] = "Username must be at least 4 characters";
        if (empty($email)) $errors[] = "Email is required";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";
        if (empty($password)) $errors[] = "Password is required";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
        if ($password !== $confirmPassword) $errors[] = "Passwords do not match";
        
        // Check if email already exists
        $emailCheck = mysqli_query($conn, "SELECT email FROM users WHERE email = '$email' UNION SELECT email FROM employees WHERE email = '$email'");
        if (mysqli_num_rows($emailCheck) > 0) {
            $errors[] = "Email already registered";
        }
        
        // Check if username already exists
        $usernameCheck = mysqli_query($conn, "SELECT username FROM users WHERE username = '$username' UNION SELECT username FROM employees WHERE username = '$username'");
        if (mysqli_num_rows($usernameCheck) > 0) {
            $errors[] = "Username already taken";
        }
        
        // If no errors, store data in session and proceed to verification
        if (empty($errors)) {
            // Store all registration data in session
            $_SESSION['reg_data'] = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'middleName' => $middleName,
                'username' => $username,
                'email' => $email,
                'password' => $password
            ];
            
            $_SESSION['verification_pending'] = true;
            $verificationSent = false; // User needs to choose verification method
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Fire and Rescue Service Management</title>
    <link rel="icon" type="image/png" href="img/frsm-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-red: #DC143C;
            --secondary-red: #B91C3C;
            --accent-red: #EF4444;
            --dark-red: #991B1B;
            --light-red: #FEE2E2;
            --gradient-primary: linear-gradient(135deg, #DC143C 0%, #B91C3C 50%, #991B1B 100%);
            --gradient-secondary: linear-gradient(45deg, #EF4444 0%, #DC143C 100%);
            --dark: #1F2937;
            --light: #F9FAFB;
            --white: #FFFFFF;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gradient-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--gray-800);
            line-height: 1.6;
        }
        
        .login-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            background: var(--white);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-left {
            flex: 1;
            background: var(--gradient-primary);
            color: var(--white);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .login-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
        }
        
        .logo img {
            width: 120px;
            height: auto;
        }
        
        .welcome-text h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 16px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-text p {
            font-size: 16px;
            font-weight: 400;
            opacity: 0.9;
            max-width: 300px;
            margin: 0 auto;
        }
        
        .features {
            margin-top: 40px;
        }
        
        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .feature-text {
            font-size: 14px;
            font-weight: 500;
        }
        
        .login-right {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
            overflow-y: auto;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: var(--gray-500);
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 24px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-700);
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            z-index: 1;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--gray-300);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: var(--gray-50);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            cursor: pointer;
            z-index: 2;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .form-hint {
            font-size: 11px;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--gradient-secondary);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: var(--shadow-md);
        }
        
        .btn-login:hover {
            background: var(--dark-red);
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .register-link {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: var(--gray-600);
        }
        
        .register-link a {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
        }
        
        .alert-error {
            background-color: var(--light-red);
            color: var(--dark-red);
            border: 1px solid #FECACA;
        }
        
        .alert-success {
            background-color: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .alert-icon {
            margin-right: 12px;
            font-size: 18px;
        }
        
        .verification-container {
            text-align: center;
        }
        
        .verification-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--dark);
        }
        
        .verification-subtitle {
            color: var(--gray-500);
            margin-bottom: 32px;
            font-size: 14px;
        }
        
        .verification-method {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .method-card {
            flex: 1;
            padding: 20px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .method-card:hover {
            border-color: var(--primary-red);
            box-shadow: var(--shadow-md);
        }
        
        .method-card.selected {
            border-color: var(--primary-red);
            background-color: rgba(220, 20, 60, 0.05);
        }
        
        .method-icon {
            font-size: 32px;
            margin-bottom: 12px;
            color: var(--primary-red);
        }
        
        .method-name {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .method-description {
            font-size: 12px;
            color: var(--gray-500);
        }
        
        .verification-inputs {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        
        .verification-input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            border: 2px solid var(--gray-300);
            border-radius: 10px;
            background: var(--gray-50);
            transition: all 0.3s;
        }
        
        .verification-input:focus {
            border-color: var(--primary-red);
            outline: none;
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
        }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            border-radius: 8px;
            border: 1px solid var(--gray-300);
            transition: all 0.3s;
            margin-top: 20px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .back-button i {
            margin-right: 8px;
        }
        
        .back-button:hover {
            background: var(--gray-200);
            border-color: var(--gray-400);
        }
        
        /* Enhanced Loading screen styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .fire-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 25px;
        }
        
        .fire-back {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80px;
            height: 80px;
            background: radial-gradient(circle, #ff8c00 0%, #ff4500 70%, #dc143c 100%);
            border-radius: 50%;
            box-shadow: 0 0 40px 15px #ff4500, 0 0 80px 20px #dc143c;
            animation: pulse 1.5s infinite alternate;
        }
        
        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 0.8; }
            100% { transform: translate(-50%, -50%) scale(1.1); opacity: 1; }
        }
        
        .fire-front {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, #ffff00 0%, #ff8c00 70%, #ff4500 100%);
            border-radius: 50%;
            box-shadow: 0 0 30px 10px #ffff00;
        }
        
        .flame {
            position: absolute;
            background: linear-gradient(to top, transparent 0%, #ffff00 30%, #ff8c00 70%, #ff4500 100%);
            border-radius: 50% 50% 20% 20%;
            opacity: 0.8;
            animation: flicker 1s infinite alternate;
        }
        
        .flame-1 {
            width: 25px;
            height: 45px;
            top: 15px;
            left: 30px;
            transform: translateX(-50%) rotate(-5deg);
            animation-delay: 0.1s;
        }
        
        .flame-2 {
            width: 20px;
            height: 40px;
            top: 20px;
            left: 50px;
            transform: translateX(-50%) rotate(5deg);
            animation-delay: 0.3s;
        }
        
        .flame-3 {
            width: 22px;
            height: 42px;
            top: 18px;
            left: 70px;
            transform: translateX(-50%) rotate(-3deg);
            animation-delay: 0.2s;
        }
        
        .flame-4 {
            width: 18px;
            height: 38px;
            top: 22px;
            left: 90px;
            transform: translateX(-50%) rotate(2deg);
            animation-delay: 0.4s;
        }
        
        @keyframes flicker {
            0% { height: 40px; opacity: 0.8; }
            25% { height: 42px; opacity: 0.9; }
            50% { height: 38px; opacity: 0.7; }
            75% { height: 43px; opacity: 0.9; }
            100% { height: 41px; opacity: 0.8; }
        }
        
        .spark {
            position: absolute;
            background: #ffff00;
            border-radius: 50%;
            opacity: 0;
            animation: spark 1.5s infinite;
        }
        
        @keyframes spark {
            0% { transform: translate(0, 0) scale(0); opacity: 0; }
            20% { opacity: 1; }
            100% { transform: translate(var(--tx), var(--ty)) scale(0.5); opacity: 0; }
        }
        
        .loading-text {
            color: white;
            font-size: 18px;
            font-weight: 500;
            text-align: center;
            text-shadow: 0 0 10px rgba(255, 140, 0, 0.8);
            margin-top: 20px;
        }
        
        .loading-subtext {
            color: #ffa500;
            font-size: 14px;
            margin-top: 10px;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 100%;
                min-height: auto;
            }
            
            .login-left {
                padding: 30px 20px;
                order: 2;
            }
            
            .login-right {
                padding: 30px 20px;
                order: 1;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .verification-method {
                flex-direction: column;
            }
            
            .fire-container {
                width: 100px;
                height: 100px;
            }
            
            .flame {
                transform-origin: bottom center;
            }
        }
    </style>
</head>
<body>
    <!-- Enhanced Loading Overlay with Fire Spinner -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="fire-container">
            <div class="fire-back"></div>
            <div class="fire-front"></div>
            <div class="flame flame-1"></div>
            <div class="flame flame-2"></div>
            <div class="flame flame-3"></div>
            <div class="flame flame-4"></div>
            
            <!-- Spark elements will be generated by JavaScript -->
        </div>
        <div class="loading-text">Igniting Your Account</div>
        <div class="loading-subtext">Please wait while we process your request...</div>
    </div>
    
    <div class="login-container">
        <!-- Left side with branding -->
        <div class="login-left">
            <div class="logo-container">
                <div class="logo">
                    <img src="img/frsm1.png" alt="FRSM Logo">
                </div>
                <div class="welcome-text">
                    <h1>Create Your Account</h1>
                    <p>Join the Fire and Rescue Service Management System</p>
                </div>
            </div>
            
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-text">
                        Secure account management with advanced encryption
                    </div>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="feature-text">
                        Instant alerts and notifications for emergency responses
                    </div>
                </div>
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="feature-text">
                        Real-time incident tracking and management
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right side with form -->
        <div class="login-right">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    Registration successful! Redirecting to login page...
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <?php echo $errors[0]; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['verification_error'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <?php echo $_SESSION['verification_error']; unset($_SESSION['verification_error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($emailError): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    Failed to send verification email. Please try SMS verification instead.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['verification_pending']) && !$verificationSent): ?>
                <!-- Verification method selection -->
                <div class="verification-container">
                    <h2 class="verification-title">Verify Your Account</h2>
                    <p class="verification-subtitle">Choose how you'd like to receive your verification code</p>
                    
                    <form method="POST" id="verificationMethodForm">
                        <div class="verification-method">
                            <label class="method-card <?php echo $verificationMethod === 'email' ? 'selected' : ''; ?>">
                                <input type="radio" name="verification_method" value="email" style="display: none;" <?php echo $verificationMethod === 'email' ? 'checked' : ''; ?> required>
                                <div class="method-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="method-name">Email</div>
                                <div class="method-description">Send code to your email address</div>
                            </label>
                            
                            <label class="method-card <?php echo $verificationMethod === 'sms' ? 'selected' : ''; ?>">
                                <input type="radio" name="verification_method" value="sms" style="display: none;" <?php echo $verificationMethod === 'sms' ? 'checked' : ''; ?>>
                                <div class="method-icon">
                                    <i class="fas fa-mobile-alt"></i>
                                </div>
                                <div class="method-name">SMS</div>
                                <div class="method-description">Text code to your phone</div>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-login" onclick="showLoading()">Send Verification Code</button>
                    </form>
                    
                    <form method="POST">
                        <button type="submit" name="back_to_register" class="back-button">
                            <i class="fas fa-arrow-left"></i> Back to registration
                        </button>
                    </form>
                </div>
            <?php elseif (isset($_SESSION['verification_pending']) && $verificationSent): ?>
                <!-- Verification code input -->
                <div class="verification-container">
                    <h2 class="verification-title">Enter Verification Code</h2>
                    <p class="verification-subtitle">We sent a 6-digit code to your <?php echo $verificationMethod; ?></p>
                    
                    <form method="POST" id="verificationCodeForm">
                        <div class="verification-inputs">
                            <?php for ($i = 0; $i < 6; $i++): ?>
                                <input type="text" class="verification-input" name="verification_code[]" maxlength="1" required 
                                    oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(this.value.length) this.nextElementSibling.focus();"
                                    onkeyup="if(event.key === 'Backspace' && !this.value) this.previousElementSibling.focus()">
                            <?php endfor; ?>
                        </div>
                        
                        <button type="submit" name="verify_code" class="btn-login" onclick="showLoading()">Verify & Create Account</button>
                    </form>
                    
                    <form method="POST">
                        <button type="submit" name="back_to_register" class="back-button">
                            <i class="fas fa-arrow-left"></i> Back to registration
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Registration form -->
                <div class="login-header">
                    <h2>Create Account</h2>
                    <p>Fill in your details to register for an account</p>
                </div>
                
                <form method="POST" id="registrationForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control" id="firstName" name="firstName" value="<?php echo htmlspecialchars($firstName); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <div class="input-with-icon">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control" id="lastName" name="lastName" value="<?php echo htmlspecialchars($lastName); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="middleName">Middle Name (Optional) </label>
                        <div class="input-with-icon">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" class="form-control" id="middleName" name="middleName" value="<?php echo htmlspecialchars($middleName); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <div class="input-with-icon">
                            <i class="fas fa-at input-icon"></i>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                        </div>
                        <p class="form-hint">4+ characters, letters, numbers and underscores only</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <span class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                        <p class="form-hint">Password must be at least 8 characters and include uppercase, lowercase, number, and symbol</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirmPassword">Confirm Password *</label>
                        <div class="input-with-icon">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                            <span class="password-toggle" onclick="togglePassword('confirmPassword')">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login" onclick="showLoading()">Register</button>
                </form>
                
                <div class="register-link">
                    Already have an account? <a href="login.php">Sign in here</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = passwordInput.nextElementSibling.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
        
        // Show loading screen
        function showLoading() {
            const overlay = document.getElementById('loadingOverlay');
            overlay.classList.add('active');
            createSparks();
        }
        
        // Hide loading screen when page is fully loaded
        window.addEventListener('load', function() {
            document.getElementById('loadingOverlay').classList.remove('active');
        });
        
        // Create spark elements for the fire animation
        function createSparks() {
            const fireContainer = document.querySelector('.fire-container');
            // Remove existing sparks
            document.querySelectorAll('.spark').forEach(spark => spark.remove());
            
            // Create new sparks
            for (let i = 0; i < 15; i++) {
                const spark = document.createElement('div');
                spark.className = 'spark';
                
                // Random position and animation values
                const tx = (Math.random() * 100 - 50) + 'px';
                const ty = (Math.random() * -80 - 20) + 'px';
                const size = Math.random() * 6 + 2;
                const delay = Math.random() * 1.5;
                
                spark.style.cssText = `
                    width: ${size}px;
                    height: ${size}px;
                    left: ${Math.random() * 100 + 10}px;
                    top: ${Math.random() * 40 + 60}px;
                    --tx: ${tx};
                    --ty: ${ty};
                    animation-delay: ${delay}s;
                `;
                
                fireContainer.appendChild(spark);
            }
        }
        
        // Auto-advance verification code inputs
        document.addEventListener('DOMContentLoaded', function() {
            const verificationInputs = document.querySelectorAll('.verification-input');
            
            if (verificationInputs.length > 0) {
                verificationInputs[0].focus();
                
                verificationInputs.forEach((input, index) => {
                    input.addEventListener('input', function() {
                        if (this.value.length === 1 && index < verificationInputs.length - 1) {
                            verificationInputs[index + 1].focus();
                        }
                    });
                    
                    input.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && this.value === '' && index > 0) {
                            verificationInputs[index - 1].focus();
                        }
                    });
                });
            }
            
            // Method selection
            const methodCards = document.querySelectorAll('.method-card');
            methodCards.forEach(card => {
                card.addEventListener('click', function() {
                    methodCards.forEach(c => c.classList.remove('selected'));
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });
            
            // Add loading screen to form submissions
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    showLoading();
                });
            });
        });
    </script>
</body>
</html>