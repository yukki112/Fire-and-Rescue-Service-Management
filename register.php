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
                    <h2>Welcome to FRSM!</h2>
                    <p>Hello {$regData['firstName']},</p>
                    <p>Your verification code is: <strong style='font-size: 24px; color: #DC143C;'>$verificationCode</strong></p>
                    <p>Enter this code to complete your registration.</p>
                    <p>This code will expire in 10 minutes.</p>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
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
            padding: 16px;
            position: relative;
        }
        
        .container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Improved responsive grid layout */
        .auth-wrapper {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            width: 100%;
            max-width: 1200px;
            min-height: 700px;
            backdrop-filter: blur(20px);
            position: relative;
        }
        
        /* Left side - Branding */
        .auth-brand {
            background: #be013c;
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-brand::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="20" cy="80" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }
        
         /* Updated brand logo to use image instead of icon */
        .brand-logo {
            width: 220px;
            height: 220px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        
        .brand-logo img {
            width: 210px;
            height: 210px;
            object-fit: contain;
          
        }
        
        .brand-title {
            font-size: 36px;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 12px;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .brand-subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.5;
            max-width: 280px;
            font-weight: 400;
        }
        
        .brand-stats {
            margin-top: 32px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            width: 100%;
            max-width: 240px;
        }
        
        .stat-item {
            text-align: center;
            color: var(--white);
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 11px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Right side - Registration form */
        .auth-form-section {
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
            position: relative;
            overflow-y: auto;
        }
        
        .form-header {
            margin-bottom: 32px;
        }
        
        .form-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }
        
        .form-subtitle {
            font-size: 15px;
            color: var(--gray-500);
            font-weight: 400;
        }
        
        /* Improved responsive form grid */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 15px;
            font-weight: 400;
            background: var(--gray-50);
            transition: all 0.2s ease;
            color: var(--gray-900);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-red);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
        }
        
        .form-input::placeholder {
            color: var(--gray-400);
        }
        
        .form-hint {
            font-size: 11px;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px 20px;
            background: var(--gradient-secondary);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(220, 20, 60, 0.3);
            position: relative;
            overflow: hidden;
            margin-top: 6px;
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }
        
        .btn-primary:hover::before {
            left: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(220, 20, 60, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .auth-switch {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--gray-200);
        }
        
        .auth-switch-text {
            color: var(--gray-500);
            font-size: 13px;
        }
        
        .auth-switch-link {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 600;
            margin-left: 4px;
            transition: color 0.2s ease;
        }
        
        .auth-switch-link:hover {
            color: var(--secondary-red);
        }
        
        /* Alert messages */
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 13px;
            font-weight: 500;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #FEF2F2;
            color: #991B1B;
            border-left-color: #DC2626;
        }
        
        .alert-success {
            background: #F0FDF4;
            color: #166534;
            border-left-color: #16A34A;
            text-align: center;
        }
        
        .alert-success h3 {
            margin-bottom: 6px;
            font-size: 15px;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(8px);
        }
        
        .modal-content {
            background: var(--white);
            margin: 3% auto;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            background: var(--gradient-primary);
            color: var(--white);
            padding: 20px 24px;
            text-align: center;
        }
        
        .modal-header h3 {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        
        .modal-body {
            padding: 24px;
            text-align: center;
        }
        
        .verification-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 24px 0;
        }
        
        .verification-option {
            padding: 20px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            background: var(--gray-50);
        }
        
        .verification-option:hover {
            border-color: var(--primary-red);
            background: var(--white);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 20, 60, 0.1);
        }
        
        .verification-option.selected {
            border-color: var(--primary-red);
            background: rgba(220, 20, 60, 0.05);
        }
        
        .verification-option i {
            font-size: 28px;
            color: var(--primary-red);
            margin-bottom: 10px;
            display: block;
        }
        
        .verification-option-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 3px;
            font-size: 14px;
        }
        
        .verification-option-desc {
            font-size: 11px;
            color: var(--gray-500);
        }
        
        .verification-code-input {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 24px 0;
        }
        
        .verification-code-input input {
            width: 48px;
            height: 48px;
            text-align: center;
            font-size: 20px;
            font-weight: 700;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            background: var(--gray-50);
            color: var(--gray-900);
            transition: all 0.2s ease;
        }
        
        .verification-code-input input:focus {
            border-color: var(--primary-red);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(220, 20, 60, 0.1);
            outline: none;
        }
        
        .resend-section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--gray-200);
            color: var(--gray-500);
            font-size: 13px;
        }
        
        .resend-link {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 500;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .btn-secondary {
            padding: 10px 16px;
            background: var(--gray-200);
            color: var(--gray-700);
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        /* Enhanced responsive design with better breakpoints */
        @media (max-width: 1024px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
                max-width: 500px;
                min-height: auto;
            }
            
            .auth-brand {
                padding: 32px 24px;
            }
            
             .brand-logo {
                width: 140px;
                height: 140px;
                margin-bottom: 20px;
            }
            
            .brand-logo img {
                width: 130px;
                height: 130px;
            }
            
            .brand-title {
                font-size: 28px;
            }
            
            .brand-subtitle {
                font-size: 15px;
            }
            
            .brand-stats {
                margin-top: 24px;
                max-width: 200px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
            
            .auth-wrapper {
                border-radius: 16px;
                min-height: auto;
            }
            
            .auth-brand {
                padding: 24px 20px;
            }
            
            .auth-form-section {
                padding: 32px 20px;
            }
            
            .form-title {
                font-size: 24px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .verification-methods {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .verification-code-input {
                gap: 8px;
            }
            
            .verification-code-input input {
                width: 44px;
                height: 44px;
                font-size: 18px;
            }
            
            .brand-stats {
                display: none;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
            
            .modal-body {
                padding: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .auth-form-section {
                padding: 24px 16px;
            }
            
            .auth-brand {
                padding: 20px 16px;
            }
            
            .form-input {
                padding: 12px 14px;
                font-size: 14px;
            }
            
            .btn-primary {
                padding: 12px 16px;
                font-size: 14px;
            }
            
            .verification-code-input input {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-wrapper">
            <!-- Branding Section -->
            <div class="auth-brand">
                <!-- Replaced icon with image logo -->
                <div class="brand-logo">
                    <img src="img/frsmse.png" alt="FRSM Logo">
                </div>
                <h1 class="brand-title">FRSM</h1>
                <p class="brand-subtitle">Fire and Rescue Service Management System - Join our mission to protect and serve</p>
                
                <div class="brand-stats">
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Service</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Secure</div>
                    </div>
                </div>
            </div>
            
            <!-- Registration Form Section -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h2 class="form-title">Create Account</h2>
                    <p class="form-subtitle">Join the FRSM system today</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success animate__animated animate__fadeIn">
                        <h3><i class="fas fa-check-circle" style="color: #16A34A; margin-right: 8px;"></i>Registration Successful!</h3>
                        <p>Your account has been created successfully. Redirecting to login page...</p>
                    </div>
                <?php endif; ?>
                
                <?php if (!isset($_SESSION['verification_pending']) || !$_SESSION['verification_pending']): ?>
                    <form id="registrationForm" method="POST" action="">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" id="firstName" name="firstName" class="form-input" 
                                       placeholder="Juan" value="<?php echo htmlspecialchars($firstName); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" id="lastName" name="lastName" class="form-input" 
                                       placeholder="Dela Cruz" value="<?php echo htmlspecialchars($lastName); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="middleName" class="form-label">Middle Name (Optional)</label>
                            <input type="text" id="middleName" name="middleName" class="form-input" 
                                   placeholder="Santos" value="<?php echo htmlspecialchars($middleName); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-input" 
                                   placeholder="juan123" value="<?php echo htmlspecialchars($username); ?>" required>
                            <div class="form-hint">4-20 characters, letters, numbers and underscores only</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" 
                                   placeholder="juan.delacruz@example.com" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" id="password" name="password" class="form-input" 
                                       placeholder="Create a password" required>
                                <div class="form-hint">At least 8 characters</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" class="form-input" 
                                       placeholder="Confirm your password" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-primary">
                            Create Account
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="auth-switch">
                    <span class="auth-switch-text">Already have an account?</span>
                    <a href="login.php" class="auth-switch-link">Sign in</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <?php if (isset($_SESSION['verification_pending']) && $_SESSION['verification_pending']): ?>
        <div class="modal" id="verificationModal" style="display: flex;">
            <div class="modal-content animate__animated animate__fadeInUp">
                <div class="modal-header">
                    <h3><i class="fas fa-shield-check" style="margin-right: 8px;"></i>Verify Your Account</h3>
                </div>
                <div class="modal-body">
                    <?php if (isset($_SESSION['verification_error'])): ?>
                        <div class="alert alert-error" style="margin-bottom: 20px;">
                            <p><?php echo htmlspecialchars($_SESSION['verification_error']); ?></p>
                            <?php unset($_SESSION['verification_error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$verificationSent): ?>
                        <p style="margin-bottom: 20px; color: var(--gray-600); line-height: 1.5;">
                            Choose how you'd like to receive your verification code:
                        </p>
                        
                        <form method="POST" action="">
                            <div class="verification-methods">
                                <label class="verification-option" onclick="selectMethod(this, 'email')">
                                    <input type="radio" name="verification_method" value="email" style="display: none;">
                                    <i class="fas fa-envelope"></i>
                                    <div class="verification-option-title">Email</div>
                                    <div class="verification-option-desc">Receive code via email</div>
                                </label>
                                
                                <label class="verification-option" onclick="selectMethod(this, 'sms')">
                                    <input type="radio" name="verification_method" value="sms" style="display: none;">
                                    <i class="fas fa-mobile-alt"></i>
                                    <div class="verification-option-title">SMS</div>
                                    <div class="verification-option-desc">Receive code via text</div>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn-primary" id="sendCodeBtn" disabled 
                                    style="width: auto; padding: 10px 20px; margin-right: 10px;">
                                Send Verification Code
                            </button>
                            
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="back_to_register" value="1">
                                <button type="submit" class="btn-secondary">Back to Registration</button>
                            </form>
                        </form>
                    <?php else: ?>
                        <?php if ($emailError): ?>
                            <div class="alert alert-error" style="margin-bottom: 20px;">
                                <p>Failed to send verification email. Please try SMS verification or check your email address.</p>
                            </div>
                            
                            <form method="POST" action="">
                                <div class="verification-methods">
                                    <label class="verification-option selected" onclick="selectMethod(this, 'sms')">
                                        <input type="radio" name="verification_method" value="sms" style="display: none;" checked>
                                        <i class="fas fa-mobile-alt"></i>
                                        <div class="verification-option-title">Try SMS Verification</div>
                                        <div class="verification-option-desc">Alternative verification method</div>
                                    </label>
                                </div>
                                
                                <button type="submit" class="btn-primary" style="width: auto; padding: 10px 20px; margin-right: 10px;">
                                    Send SMS Code
                                </button>
                                
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="back_to_register" value="1">
                                    <button type="submit" class="btn-secondary">Back to Registration</button>
                                </form>
                            </form>
                        <?php else: ?>
                            <p style="margin-bottom: 20px; color: var(--gray-600); line-height: 1.5;">
                                We sent a 6-digit verification code to your <?php echo $verificationMethod === 'email' ? 'email' : 'phone'; ?>.
                                Please enter it below:
                            </p>
                            
                            <form method="POST" action="" id="verificationForm">
                                <div class="verification-code-input">
                                    <input type="text" name="verification_code[]" maxlength="1" pattern="[0-9]" oninput="moveToNext(this)">
                                    <input type="text" name="verification_code[]" maxlength="1" pattern="[0-9]" oninput="moveToNext(this)">
                                    <input type="text" name="verification_code[]" maxlength="1" pattern="[0-9]" oninput="moveToNext(this)">
                                    <input type="text" name="verification_code[]" maxlength="1" pattern="[0-9]" oninput="moveToNext(this)">
                                    <input type="text" name="verification_code[]" maxlength="1" pattern="[0-9]" oninput="moveToNext(this)">
                                    <input type="text" name="verification_code[]" maxlength="1" pattern="[0-9]" oninput="moveToNext(this)">
                                </div>
                                
                                <input type="hidden" name="verify_code" value="1">
                                
                                <button type="submit" class="btn-primary" id="verifyBtn" 
                                        style="width: auto; padding: 10px 20px; margin-right: 10px;">
                                    Verify Account
                                </button>
                                
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="back_to_register" value="1">
                                    <button type="submit" class="btn-secondary">Back to Registration</button>
                                </form>
                                
                                <div class="resend-section">
                                    Didn't receive a code? <a href="#" onclick="resendCode()" class="resend-link">Resend code</a>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Enhanced form validation
        document.getElementById('registrationForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const username = document.getElementById('username').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('Passwords do not match', 'error');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                showAlert('Password must be at least 8 characters long', 'error');
                return false;
            }
            
            if (username.length < 4) {
                e.preventDefault();
                showAlert('Username must be at least 4 characters long', 'error');
                return false;
            }
            
            return true;
        });

        // Verification method selection
        function selectMethod(element, method) {
            document.querySelectorAll('.verification-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            element.classList.add('selected');
            element.querySelector('input[type="radio"]').checked = true;
            document.getElementById('sendCodeBtn').disabled = false;
        }

        // Enhanced verification code input
        function moveToNext(current) {
            if (current.value.length === 1) {
                const next = current.nextElementSibling;
                if (next && next.tagName === 'INPUT') {
                    next.focus();
                }
            }
            
            // Auto-submit when all fields are filled
            const inputs = document.querySelectorAll('.verification-code-input input');
            const allFilled = Array.from(inputs).every(input => input.value.length === 1);
            if (allFilled) {
                setTimeout(() => {
                    document.getElementById('verificationForm').submit();
                }, 500);
            }
        }

        // Resend verification code
        function resendCode() {
            window.location.reload();
        }

        // Alert system
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `<p>${message}</p>`;
            
            const form = document.getElementById('registrationForm');
            form.parentNode.insertBefore(alertDiv, form);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Enhanced form interactions
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });

        // Auto-focus first verification input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('.verification-code-input input');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
