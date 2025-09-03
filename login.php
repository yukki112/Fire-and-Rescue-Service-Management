<?php
// Start session with secure settings
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true
]);
require_once 'config/database.php';
require_once 'vendor/autoload.php'; // Make sure you have PHPMailer installed via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$errors = [];
$loginInput = '';
$forgotPasswordMessage = '';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } else {
            // Check if email exists in users or employees table
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, 'user' as type FROM users WHERE email = ? 
                                   UNION 
                                   SELECT id, first_name, last_name, 'employee' as type FROM employees WHERE email = ?");
            $stmt->execute([$email, $email]);
            
            if ($stmt->rowCount() === 1) {
                $user = $stmt->fetch();
                
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiration
                
                // Store token in database
                $table = ($user['type'] === 'user') ? 'users' : 'employees';
                $updateStmt = $pdo->prepare("UPDATE $table SET reset_token = ?, reset_token_expires = ? WHERE email = ?");
                $updateStmt->execute([$token, $expires, $email]);
                
                // Send reset email using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'Stephenviray12@gmail.com'; // Replace with your email
                    $mail->Password = 'bubr nckn tgqf lvus'; // Replace with your app password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    $mail->setFrom('noreply@frsm.com', 'FRSM System');
                    $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);
                    
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - FRSM';
                    $resetLink = "http://localhost/lgulogin/reset_password.php?token=" . $token;
                    $mail->Body = "
                        <h2>Password Reset Request</h2>
                        <p>Hello {$user['first_name']},</p>
                        <p>You requested a password reset for your FRSM account. Click the link below to reset your password:</p>
                        <p><a href='$resetLink'>Reset Password</a></p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                    ";
                    
                    $mail->send();
                    $forgotPasswordMessage = "Password reset link sent to your email";
                } catch (Exception $e) {
                    $forgotPasswordMessage = "Failed to send reset email. Please try again.";
                }
            } else {
                $forgotPasswordMessage = "If an account with that email exists, a reset link has been sent";
            }
        }
    }
}

// Process login form when submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid form submission";
    } else {
        // Sanitize and validate inputs
        $loginInput = trim($_POST['loginInput']);
        $password = $_POST['password'];
        $rememberMe = isset($_POST['remember']) ? true : false;
        
        // Validate inputs
        if (empty($loginInput)) {
            $errors[] = "Username or email is required";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        }
        
        // Rate limiting
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
        }
        
        if ($_SESSION['login_attempts'] > 10) {
            $errors[] = "Too many login attempts. Please try again later.";
        }
        
        // If no errors, proceed with login
        if (empty($errors)) {
            // First check in users table
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, username, password_hash, is_admin, is_verified 
                                   FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$loginInput, $loginInput]);
            $user = $stmt->fetch();
            
            // Then check in employees table
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, username, password_hash, is_verified 
                                   FROM employees WHERE email = ? OR username = ?");
            $stmt->execute([$loginInput, $loginInput]);
            $employee = $stmt->fetch();
            
            // Check if we found a user or employee
            $foundUser = false;
            
            // Check users table first
            if ($user) {
                $foundUser = true;
                
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Check if email is verified
                    if (!$user['is_verified']) {
                        $errors[] = "Please verify your email first. Check your inbox.";
                        $_SESSION['verification_email'] = $user['email'];
                    } else {
                        // Regenerate session ID to prevent fixation
                        session_regenerate_id(true);
                        
                        // Clear any existing session data
                        $_SESSION = [];
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_username'] = $user['username'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['last_activity'] = time();
                        
                        // Set secure remember me cookie if checked
                        if ($rememberMe) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = time() + (86400 * 30); // 30 days
                            
                            // Store hashed token in database
                            $hashedToken = password_hash($token, PASSWORD_DEFAULT);
                            $insertStmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token_hash, expires_at) 
                                         VALUES (?, ?, FROM_UNIXTIME(?))");
                            $insertStmt->execute([$user['id'], $hashedToken, $expiry]);
                            
                            setcookie('remember_me', $user['id'] . ':' . $token, $expiry, "/", "", true, true);
                        }
                        
                        // Reset login attempts
                        $_SESSION['login_attempts'] = 0;
                        
                        // Redirect based on user type
                        if ($user['is_admin']) {
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['user_type'] = 'admin';
                            header("Location: IRD/ird.php");
                            exit();
                        } else {
                            $_SESSION['community_logged_in'] = true;
                            $_SESSION['user_type'] = 'community';
                            header("Location: dashboard.php");
                            exit();
                        }
                    }
                } else {
                    // Password didn't match - continue to check employees table
                    $foundUser = false;
                }
            }
            
            // If no user found or password didn't match, check employees table
            if (!$foundUser && $employee) {
                // Verify password
                if (password_verify($password, $employee['password_hash'])) {
                    // Check if email is verified
                    if (!$employee['is_verified']) {
                        $errors[] = "Please verify your email first. Check your inbox.";
                        $_SESSION['verification_email'] = $employee['email'];
                    } else {
                        // Regenerate session ID to prevent fixation
                        session_regenerate_id(true);
                        
                        // Clear any existing session data
                        $_SESSION = [];
                        
                        // Set session variables
                        $_SESSION['employee_id'] = $employee['id'];
                        $_SESSION['employee_email'] = $employee['email'];
                        $_SESSION['employee_username'] = $employee['username'];
                        $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
                        $_SESSION['last_activity'] = time();
                        
                        // Set secure remember me cookie if checked
                        if ($rememberMe) {
                            $token = bin2hex(random_bytes(32));
                            $expiry = time() + (86400 * 30); // 30 days
                            
                            // Store hashed token in database
                            $hashedToken = password_hash($token, PASSWORD_DEFAULT);
                            $insertStmt = $pdo->prepare("INSERT INTO employee_auth_tokens (employee_id, token_hash, expires_at) 
                                         VALUES (?, ?, FROM_UNIXTIME(?))");
                            $insertStmt->execute([$employee['id'], $hashedToken, $expiry]);
                            
                            setcookie('remember_me_employee', $employee['id'] . ':' . $token, $expiry, "/", "", true, true);
                        }
                        
                        // Reset login attempts
                        $_SESSION['login_attempts'] = 0;
                        
                        // Redirect to employee portal
                        $_SESSION['employee_logged_in'] = true;
                        $_SESSION['user_type'] = 'employee';
                        header("Location: employee/ird.php");
                        exit();
                    }
                } else {
                    // Increment failed login attempts
                    $_SESSION['login_attempts']++;
                    $errors[] = "Invalid credentials";
                }
            } else {
                $_SESSION['login_attempts']++;
                $errors[] = "Invalid credentials";
            }
        }
    }
}

// Check for remember me cookie (secure version) for users
if (empty($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($userId, $token) = explode(':', $_COOKIE['remember_me']);
    
    // Validate the token
    $stmt = $pdo->prepare("SELECT users.id, users.first_name, users.last_name, users.email, users.username, users.is_admin 
                           FROM users 
                           JOIN auth_tokens ON users.id = auth_tokens.user_id 
                           WHERE users.id = ? AND auth_tokens.expires_at > NOW()");
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch();
        
        // Verify token against hashed version in DB
        $tokenCheck = $pdo->prepare("SELECT id FROM auth_tokens 
                                     WHERE user_id = ? AND token_hash = ?");
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $tokenCheck->execute([$userId, $hashedToken]);
        
        if ($tokenCheck->rowCount() === 1) {
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['last_activity'] = time();
            
            if ($user['is_admin']) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['user_type'] = 'admin';
                header("Location: IRD/ird.php");
                exit();
            } else {
                $_SESSION['community_logged_in'] = true;
                $_SESSION['user_type'] = 'community';
                header("Location: dashboard.php");
                exit();
            }
        }
    }
    
    // Invalid cookie - delete it
    setcookie('remember_me', '', time() - 3600, "/", "", true, true);
}

// Check for remember me cookie for employees
if (empty($_SESSION['employee_id']) && isset($_COOKIE['remember_me_employee'])) {
    list($employeeId, $token) = explode(':', $_COOKIE['remember_me_employee']);
    
    // Validate the token
    $stmt = $pdo->prepare("SELECT employees.id, employees.first_name, employees.last_name, employees.email, employees.username 
                           FROM employees 
                           JOIN employee_auth_tokens ON employees.id = employee_auth_tokens.employee_id 
                           WHERE employees.id = ? AND employee_auth_tokens.expires_at > NOW()");
    $stmt->execute([$employeeId]);
    
    if ($stmt->rowCount() === 1) {
        $employee = $stmt->fetch();
        
        // Verify token against hashed version in DB
        $tokenCheck = $pdo->prepare("SELECT id FROM employee_auth_tokens 
                                     WHERE employee_id = ? AND token_hash = ?");
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $tokenCheck->execute([$employeeId, $hashedToken]);
        
        if ($tokenCheck->rowCount() === 1) {
            // Regenerate session ID
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['employee_id'] = $employee['id'];
            $_SESSION['employee_email'] = $employee['email'];
            $_SESSION['employee_username'] = $employee['username'];
            $_SESSION['employee_name'] = $employee['first_name'] . ' ' . $employee['last_name'];
            $_SESSION['last_activity'] = time();
            
            $_SESSION['employee_logged_in'] = true;
            $_SESSION['user_type'] = 'employee';
            header("Location: employee/index.php");
            exit();
        }
    }
    
    // Invalid cookie - delete it
    setcookie('remember_me_employee', '', time() - 3600, "/", "", true, true);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Fire and Rescue Service Management</title>
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
            max-width: 1200px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .auth-wrapper {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.25),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            overflow: hidden;
            display: grid;
            grid-template-columns: 1fr 1.1fr;
            width: 100%;
            max-width: 1000px;
            min-height: 600px;
            backdrop-filter: blur(20px);
            position: relative;
        }
        
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
        
        .brand-features {
            margin-top: 32px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
        }
        
        .feature-item i {
            width: 18px;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .auth-form-section {
            padding: 40px 32px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
            position: relative;
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
        
        .form-group {
            margin-bottom: 20px;
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
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .checkbox-wrapper input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--primary-red);
        }
        
        .checkbox-wrapper label {
            font-size: 13px;
            color: var(--gray-600);
            cursor: pointer;
        }
        
        .forgot-link {
            color: var(--primary-red);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .forgot-link:hover {
            color: var(--secondary-red);
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
        }
        
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
            margin: 5% auto;
            border-radius: 16px;
            width: 90%;
            max-width: 450px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--white);
            font-size: 20px;
            cursor: pointer;
            padding: 4px;
            border-radius: 6px;
            transition: background 0.2s ease;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 20px 24px;
            background: var(--gray-50);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        @media (max-width: 1024px) {
            .auth-wrapper {
                grid-template-columns: 1fr;
                max-width: 450px;
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
            
            .brand-features {
                display: none;
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
            
            .brand-title {
                font-size: 24px;
            }
            
            .brand-subtitle {
                font-size: 14px;
            }
            
            .modal-content {
                margin: 10% auto;
                width: 95%;
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
            
            .form-actions {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
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
                <p class="brand-subtitle">Fire and Rescue Service Management System</p>
                
                <div class="brand-features">
                    <div class="feature-item">
                        <i class="fas fa-shield-alt"></i>
                        <span>Secure Authentication</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-users"></i>
                        <span>Multi-User Support</span>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span>Mobile Responsive</span>
                    </div>
                </div>
            </div>
            
            <!-- Login Form Section -->
            <div class="auth-form-section">
                <div class="form-header">
                    <h2 class="form-title">Welcome Back</h2>
                    <p class="form-subtitle">Sign in to your FRSM account</p>
                </div>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($forgotPasswordMessage)): ?>
                    <div class="alert <?php echo strpos($forgotPasswordMessage, 'sent') !== false ? 'alert-success' : 'alert-error'; ?>">
                        <p><?php echo htmlspecialchars($forgotPasswordMessage); ?></p>
                    </div>
                <?php endif; ?>
                
                <form class="auth-form" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="loginInput" class="form-label">Username or Email</label>
                        <input type="text" id="loginInput" name="loginInput" class="form-input" 
                               placeholder="Enter your username or email" 
                               value="<?php echo htmlspecialchars($loginInput); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="loginPassword" class="form-label">Password</label>
                        <input type="password" id="loginPassword" name="password" class="form-input" 
                               placeholder="Enter your password" required>
                    </div>
                    
                    <div class="form-actions">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" id="rememberMe" name="remember">
                            <label for="rememberMe">Remember me</label>
                        </div>
                        <a href="#" id="forgotPasswordLink" class="forgot-link">Forgot password?</a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-primary">
                        Sign In
                    </button>
                </form>
                
                <div class="auth-switch">
                    <span class="auth-switch-text">Don't have an account?</span>
                    <a href="register.php" class="auth-switch-link">Sign up</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-key" style="margin-right: 8px;"></i>Forgot Password</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: var(--gray-600); line-height: 1.5;">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
                <form id="forgotPasswordForm" method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               placeholder="Enter your email" required>
                        <small style="display: block; margin-top: 4px; color: var(--gray-500); font-size: 11px;">
                            We'll never share your email with anyone else.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="cancelForgotPassword">
                    Cancel
                </button>
                <button type="submit" form="forgotPasswordForm" name="forgot_password" class="btn-primary" 
                        style="width: auto; padding: 10px 16px; font-size: 13px;">
                    <i class="fas fa-paper-plane" style="margin-right: 6px;"></i>Send Reset Link
                </button>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const loginInput = document.getElementById('loginInput').value;
            const password = document.getElementById('loginPassword').value;
            
            if (!loginInput || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            return true;
        });

        // Modal functionality
        const modal = document.getElementById("forgotPasswordModal");
        const btn = document.getElementById("forgotPasswordLink");
        const closeBtn = document.querySelector(".modal-close");
        const cancelBtn = document.getElementById("cancelForgotPassword");

        btn.onclick = function(e) {
            e.preventDefault();
            modal.style.display = "block";
            document.body.style.overflow = "hidden";
        }

        function closeModal() {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }

        closeBtn.onclick = closeModal;
        cancelBtn.onclick = closeModal;

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
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

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
