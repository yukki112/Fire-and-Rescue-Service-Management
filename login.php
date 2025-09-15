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
                    $resetLink = "https://frsm.qcprotektado.com/reset_password.php?token=" . $token;
                    $mail->Body = '
                        <div style="background:linear-gradient(135deg,#DC143C 0%,#B91C3C 50%,#991B1B 100%);padding:40px 0;">
                            <div style="max-width:480px;margin:0 auto;background:#fff;border-radius:16px;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -2px rgba(0,0,0,0.05);padding:32px;font-family:\'Inter\',Segoe UI,sans-serif;">
                                <div style="text-align:center;margin-bottom:24px;">
                                    <img src="https://frsm.qcprotektado.com/img/frsm1.png" alt="FRSM Logo" style="width:80px;height:80px;border-radius:50%;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1),0 2px 4px -1px rgba(0,0,0,0.06);margin-bottom:16px;">
                                    <h2 style="font-size:22px;font-weight:700;color:#DC143C;margin-bottom:8px;">Password Reset Request</h2>
                                </div>
                                <p style="font-size:16px;color:#374151;margin-bottom:18px;">Hello <b>' . htmlspecialchars($user['first_name']) . '</b>,</p>
                                <p style="font-size:15px;color:#374151;margin-bottom:18px;">
                                    You requested a password reset for your FRSM account.<br>
                                    Click the button below to reset your password:
                                </p>
                                <div style="text-align:center;margin:32px 0;">
                                    <a href="' . $resetLink . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(45deg,#EF4444 0%,#DC143C 100%);color:#fff;font-weight:600;font-size:16px;border-radius:10px;text-decoration:none;box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);transition:background 0.3s;">
                                        Reset Password
                                    </a>
                                </div>
                                <p style="font-size:14px;color:#6B7280;margin-bottom:12px;">This link will expire in <b>1 hour</b>.</p>
                                <p style="font-size:14px;color:#6B7280;">If you didn\'t request this, please ignore this email.</p>
                                <hr style="margin:32px 0;border:none;border-top:1px solid #E5E7EB;">
                                <div style="text-align:center;font-size:13px;color:#9CA3AF;">
                                    &copy; ' . date('Y') . ' Fire and Rescue Service Management
                                </div>
                            </div>
                        </div>
                    ';
                    
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
                            header("Location: Admin/dashboard.php");
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
                        header("Location: employee/index.php");
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
                header("Location: Admin/dashboard.php");
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
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 14px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
        }
        
        .remember-me input {
            margin-right: 8px;
            accent-color: var(--primary-red);
        }
        
        .forgot-password {
            color: var(--primary-red);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: var(--dark-red);
            text-decoration: underline;
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
            position: relative;
            overflow: hidden;
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
            font-weight: 500;
            margin-left: 5px;
            transition: color 0.3s;
        }
        
        .register-link a:hover {
            color: var(--dark-red);
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
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
            margin-right: 10px;
            font-size: 16px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-content {
            background-color: var(--white);
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            max-width: 450px;
            box-shadow: var(--shadow-xl);
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray-500);
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: var(--dark);
            background: none;
        }
        
        .modal-footer {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-secondary {
            padding: 10px 20px;
            background: var(--gray-200);
            color: var(--gray-700);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        .btn-primary {
            padding: 10px 20px;
            background: var(--gradient-secondary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--dark-red);
        }
        
        /* Loading Screen Styles */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .loading-screen.active {
            opacity: 1;
            visibility: visible;
        }
        
        .fire-loader {
            position: relative;
            width: 80px;
            height: 80px;
        }
        
        .fire-circle {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 4px solid transparent;
            border-top: 4px solid #FF5722;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        .fire-circle:nth-child(2) {
            border: 4px solid transparent;
            border-top: 4px solid #FF9800;
            animation: spin 1.2s linear infinite;
        }
        
        .fire-circle:nth-child(3) {
            border: 4px solid transparent;
            border-top: 4px solid #FFEB3B;
            animation: spin 0.8s linear infinite;
        }
        
        .fire-flames {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
        }
        
        .flame {
            position: absolute;
            background: linear-gradient(to top, #FF5722, #FFEB3B);
            border-radius: 50% 50% 20% 20%;
            opacity: 0.8;
            animation: flicker 1.5s infinite alternate;
        }
        
        .flame:nth-child(1) {
            width: 20px;
            height: 30px;
            top: 5px;
            left: 10px;
            animation-delay: 0s;
        }
        
        .flame:nth-child(2) {
            width: 15px;
            height: 25px;
            top: 8px;
            left: 25px;
            animation-delay: 0.3s;
        }
        
        .flame:nth-child(3) {
            width: 18px;
            height: 28px;
            top: 10px;
            left: 0px;
            animation-delay: 0.6s;
        }
        
        .loading-text {
            color: white;
            margin-top: 20px;
            font-size: 16px;
            font-weight: 500;
            text-align: center;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes flicker {
            0%, 100% { 
                opacity: 0.8;
                transform: scaleY(1);
            }
            50% { 
                opacity: 1;
                transform: scaleY(1.2);
            }
        }
        
        @media (max-width: 900px) {
            .login-container {
                flex-direction: column;
                max-width: 450px;
                min-height: auto;
            }
            
            .login-left {
                padding: 30px;
            }
            
            .welcome-text h1 {
                font-size: 24px;
            }
            
            .logo {
                width: 100px;
                height: 100px;
            }
            
            .logo img {
                width: 60px;
            }
        }
        
        @media (max-width: 480px) {
            .login-left, .login-right {
                padding: 20px;
            }
            
            .remember-forgot {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .features {
                margin-top: 30px;
            }
            
            .feature {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="fire-loader">
            <div class="fire-circle"></div>
            <div class="fire-circle"></div>
            <div class="fire-circle"></div>
            <div class="fire-flames">
                <div class="flame"></div>
                <div class="flame"></div>
                <div class="flame"></div>
            </div>
        </div>
        <div class="loading-text" id="loadingText">Authenticating, please wait...</div>
    </div>
    
    <div class="login-container">
        <div class="login-left">
            <div class="logo-container">
                <div class="logo">
                    <img src="img/frsm1.png" alt="FRSM Logo">
                </div>
                <div class="welcome-text">
                    <h1>Fire and Rescue Service Management</h1>
                    <p>Comprehensive fire safety and emergency response solution</p>
                </div>
            </div>
            
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="feature-text">
                        Secure authentication system with advanced encryption
                    </div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-fire-extinguisher"></i>
                    </div>
                    <div class="feature-text">
                        Real-time incident reporting and management
                    </div>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="feature-text">
                        Dedicated portals for community, employees, and administrators
                    </div>
                </div>
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h2>Sign In to Your Account</h2>
                <p>Enter your credentials to access the system</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle alert-icon"></i>
                    <?php echo $errors[0]; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    Registration successful! Please check your email to verify your account.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['verified']) && $_GET['verified'] == 'true'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    Email verified successfully! You can now log in.
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['reset']) && $_GET['reset'] == 'true'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle alert-icon"></i>
                    Password reset successfully! You can now log in with your new password.
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="loginInput">Username or Email</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" class="form-control" id="loginInput" name="loginInput" 
                            value="<?php echo htmlspecialchars($loginInput); ?>" 
                            placeholder="Enter your username or email" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="password" name="password" 
                            placeholder="Enter your password" required>
                        <i class="fas fa-eye-slash password-toggle" id="togglePassword"></i>
                    </div>
                </div>
                
                <div class="remember-forgot">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-password" id="forgotPasswordLink">Forgot password?</a>
                </div>
                
                <button type="submit" name="login" class="btn-login" id="loginButton">Sign In</button>
            </form>
            
            <div class="register-link">
                Don't have an account? <a href="register.php" id="registerLink">Register here</a>
            </div>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div class="modal" id="forgotPasswordModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reset Password</h3>
                <button class="close-modal">&times;</button>
            </div>
            
            <?php if (!empty($forgotPasswordMessage)): ?>
                <div class="alert <?php echo strpos($forgotPasswordMessage, 'sent') !== false ? 'alert-success' : 'alert-error'; ?>">
                    <i class="fas <?php echo strpos($forgotPasswordMessage, 'sent') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> alert-icon"></i>
                    <?php echo $forgotPasswordMessage; ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST" id="forgotPasswordForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="forgot_password" value="1">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" class="form-control" id="email" name="email" 
                            placeholder="Enter your email address" required>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" id="cancelForgotPassword">Cancel</button>
                    <button type="submit" class="btn-primary" id="submitForgotPassword">Send Reset Link</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
        
        // Forgot password modal
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const closeModal = document.querySelector('.close-modal');
        const cancelForgotPassword = document.getElementById('cancelForgotPassword');
        
        forgotPasswordLink.addEventListener('click', function(e) {
            e.preventDefault();
            forgotPasswordModal.style.display = 'flex';
        });
        
        closeModal.addEventListener('click', function() {
            forgotPasswordModal.style.display = 'none';
        });
        
        cancelForgotPassword.addEventListener('click', function() {
            forgotPasswordModal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            if (e.target === forgotPasswordModal) {
                forgotPasswordModal.style.display = 'none';
            }
        });
        
        // Loading screen functionality
        const loadingScreen = document.getElementById('loadingScreen');
        const loadingText = document.getElementById('loadingText');
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const registerLink = document.getElementById('registerLink');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        const submitForgotPassword = document.getElementById('submitForgotPassword');
        
        // Show loading screen function
        function showLoadingScreen(message = 'Authenticating, please wait...') {
            loadingText.textContent = message;
            loadingScreen.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        // Hide loading screen function
        function hideLoadingScreen() {
            loadingScreen.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Login form submission
        loginForm.addEventListener('submit', function(e) {
            const loginInput = document.getElementById('loginInput');
            const password = document.getElementById('password');
            
            if (!loginInput.value.trim()) {
                e.preventDefault();
                loginInput.focus();
                return false;
            }
            
            if (!password.value) {
                e.preventDefault();
                password.focus();
                return false;
            }
            
            // Show loading screen if form is valid
            showLoadingScreen();
        });
        
        // Register link click
        registerLink.addEventListener('click', function(e) {
            e.preventDefault();
            showLoadingScreen('Redirecting to registration...');
            window.location.href = this.href;
        });
        
        // Forgot password form submission
        if (forgotPasswordForm) {
            forgotPasswordForm.addEventListener('submit', function() {
                showLoadingScreen('Sending password reset link...');
            });
        }
        
        // If there are form errors, ensure loading screen is hidden
        <?php if (!empty($errors) || !empty($forgotPasswordMessage)): ?>
            hideLoadingScreen();
        <?php endif; ?>
    </script>
</body>
</html>