<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'vendor/autoload.php';

// Setup MongoDB
$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$db = $client->misacinema_db;          // Tambahkan baris ni
$usersCollection = $db->users;        // Tambahkan baris ni
$localError = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $role = $_POST['role']; 

    if ($role == 'admin') {
        if ($email == 'admin@gmail.com' && $pass == '123') {
            $_SESSION['user'] = 'Administrator';
            $_SESSION['role'] = 'admin';
            header("Location: admin.php");
            exit();
        } else {
            $localError = "Invalid Admin Email or Password!";
        }
    } 
    else {
        $user = $usersCollection->findOne(['email' => $email]);
        if ($user && password_verify($pass, $user['password'])) {
            $_SESSION['user'] = (array)$user; 
            $_SESSION['role'] = 'customer';
            header("Location: home.php");
            exit();
        } else {
            $localError = "Invalid Customer Email or Password!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login - Misa Cinema</title>
    <link rel="icon" type="image/jpeg" href="assets/img/logo_misa.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('assets/images/bg_cinema.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            background-color: #111;
            color: white; 
            font-family: 'Roboto', sans-serif; 
            display: flex; 
            flex-direction: column; /* Fixed: Stack content vertically */
            align-items: center; 
            min-height: 100vh; 
            margin: 0; 
            box-sizing: border-box;
        }

        .login-container {
            flex: 1; /* Pushes footer to the bottom */
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 40px 20px;
        }

        .login-box { 
            background: rgba(20, 20, 20, 0.95);
            padding: 40px; 
            border-radius: 12px; 
            width: 100%; 
            max-width: 400px;
            text-align: center; 
            border: 1px solid #333; 
            box-shadow: 0px 10px 30px rgba(0,0,0,0.8);
            border-top: 3px solid #e50914;
        }

        h1 { color: #e50914; margin-bottom: 10px; letter-spacing: 2px; margin-top: 0; }
        .role-switch { display: flex; justify-content: center; margin-bottom: 25px; background: #333; border-radius: 30px; padding: 5px; }
        .role-btn { flex: 1; padding: 12px; border: none; background: transparent; color: #aaa; cursor: pointer; border-radius: 25px; font-size: 0.9em; font-weight: bold;}
        .role-btn.active { background: #e50914; color: white; }
        
        input { width: 100%; padding: 15px; background: #222; border: 1px solid #444; color: white; margin-bottom: 20px; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        .password-container { position: relative; width: 100%; margin-bottom: 20px; }
        .password-container input { margin-bottom: 0; padding-right: 45px; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #777; cursor: pointer; }
        
        .btn { width: 100%; padding: 15px; background: #e50914; color: white; border: none; font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; }
        .forgot-link { text-align: right; margin-bottom: 15px; font-size: 0.85em; }
        .forgot-link a { color: #aaa; text-decoration: none; }
        .link { margin-top: 25px; font-size: 0.9em; color: #aaa; }
        .link a { color: #e50914; text-decoration: none; font-weight: bold; }

        .alert-error { background: rgba(220, 53, 69, 0.2); color: #ff6b6b; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid rgba(220, 53, 69, 0.3); }

        @media (max-width: 480px) { .login-box { padding: 30px 20px; } }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-box">
            <h1>MISA CINEMA</h1>
            <p style="color:#aaa; font-size:0.9em; margin-bottom:25px;">Welcome back! Please login.</p>

            <?php if($localError): ?>
                <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $localError; ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="role-switch">
                    <button type="button" class="role-btn active" onclick="setRole('customer')">Customer</button>
                    <button type="button" class="role-btn" onclick="setRole('admin')">Admin</button>
                </div>
                <input type="hidden" name="role" id="roleInput" value="customer">
                <input type="email" name="email" placeholder="Email Address" required>
                <div class="password-container">
                    <input type="password" name="password" id="passwordInput" placeholder="Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                </div>
                <div class="forgot-link"><a href="forgot_password.php">Forgot Password?</a></div>
                <button type="submit" class="btn">LOGIN</button>
            </form>

            <div class="link">New here? <a href="register.php">Create Account</a></div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        function setRole(role) {
            document.getElementById('roleInput').value = role;
            const buttons = document.querySelectorAll('.role-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            role === 'customer' ? buttons[0].classList.add('active') : buttons[1].classList.add('active');
        }

        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const icon = document.querySelector('.toggle-password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>