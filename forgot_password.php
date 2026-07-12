<?php
session_start();
require 'vendor/autoload.php';

// 1. CONNECT TO DATABASE
// Ensure your connection string is correct
$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$usersCollection = $client->misacinema_db->users;

$step = 1; // Default to Step 1
$error = '';
$success = '';

// 2. CHECK SESSION STATE (Fix: Keep user on Step 2 if they refresh the page)
if (isset($_SESSION['reset_email']) && empty($success)) {
    $step = 2;
}

// --- BACKEND LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // ACTION 1: VERIFY USER
    if (isset($_POST['verify_user'])) {
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);

        $user = $usersCollection->findOne(['email' => $email, 'phone' => $phone]);

        if ($user) {
            $_SESSION['reset_email'] = $email; 
            $step = 2;
            // Optional: Redirect to self to prevent form resubmission on refresh
            header("Location: forgot_password.php");
            exit();
        } else {
            $error = "No account found with these details.";
            $step = 1;
        }
    }

    // ACTION 2: UPDATE PASSWORD
    if (isset($_POST['reset_password'])) {
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];
        
        if (!isset($_SESSION['reset_email'])) {
            header("Location: forgot_password.php"); 
            exit();
        }
        $email = $_SESSION['reset_email'];

        if ($new_pass === $confirm_pass) {
            if (strlen($new_pass) >= 6) {
                
                // Hash and Update
                $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

                $usersCollection->updateOne(
                    ['email' => $email],
                    ['$set' => ['password' => $hashed_password]] 
                );
                
                $success = "Password updated successfully! <br><br> <a href='login.php' class='btn' style='display:inline-block; width:auto; padding:10px 20px; text-decoration:none;'>Login Now</a>";
                
                unset($_SESSION['reset_email']); // Clear session
                $step = 3; 
            } else {
                $error = "Password must be at least 6 characters long.";
                $step = 2;
            }
        } else {
            $error = "Passwords do not match!";
            $step = 2;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Misa Cinema</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    
    <style>
        body { 
            background-color: #0b0b0b; 
            color: white; 
            font-family: 'Roboto', sans-serif;
            display: flex; 
            flex-direction: column; /* FIX: Stack content vertically so footer stays at bottom */
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0;
            padding: 20px; 
            box-sizing: border-box;
        }

        .box { 
            background: #181818; 
            padding: 40px; 
            border-radius: 12px; 
            width: 100%; 
            max-width: 400px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            text-align: center;
            border-top: 3px solid #e50914;
            margin-bottom: 30px; /* Space between box and footer */
        }

        h2 { color: #e50914; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px; }
        p.subtitle { color: #aaa; font-size: 0.9em; margin-bottom: 25px; line-height: 1.5; }
        
        /* Input Styling */
        input { 
            width: 100%; padding: 14px; background: #222; border: 1px solid #333; 
            color: white; margin-bottom: 15px; border-radius: 6px; box-sizing: border-box; font-size: 1em;
        }
        input:focus { outline: none; border-color: #e50914; background: #2a2a2a; }

        /* Password Eye Container */
        .password-container { position: relative; width: 100%; margin-bottom: 15px; }
        .password-container input { margin-bottom: 0; padding-right: 45px; } 
        
        .toggle-password {
            position: absolute; right: 15px; top: 50%; transform: translateY(-50%);
            color: #777; cursor: pointer; font-size: 1.1em; padding: 5px;
        }
        .toggle-password:hover { color: #e50914; }

        /* Buttons */
        .btn { 
            width: 100%; padding: 15px; background: #e50914; color: white; border: none; 
            font-weight: bold; border-radius: 6px; cursor: pointer; margin-top: 10px;
            font-size: 1em; text-transform: uppercase; transition: 0.3s;
        }
        .btn:hover { background: #ff0f1f; }

        /* Messages */
        .error { 
            background: rgba(255, 68, 68, 0.1); color: #ff4444; padding: 10px; 
            border-radius: 4px; font-size: 0.9em; margin-bottom: 15px; 
            border: 1px solid rgba(255, 68, 68, 0.3);
        }
        .success { color: #00ff7f; font-size: 1.1em; margin-bottom: 20px; line-height: 1.6; }
        
        .back-link { display: block; margin-top: 20px; color: #777; text-decoration: none; font-size: 0.9em;}
        .back-link:hover { color: white; }
    </style>
</head>
<body>

    <div class="box">
        
        <?php if ($step == 1): ?>
            <h2>Recovery</h2>
            <p class="subtitle">Enter your registered Email and Phone Number to verify your identity.</p>
            
            <?php if($error) echo "<div class='error'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>

            <form method="POST">
                <input type="email" name="email" placeholder="Email Address" required>
                <input type="tel" name="phone" placeholder="Phone Number" required>
                <button type="submit" name="verify_user" class="btn">Verify Account</button>
            </form>

        <?php elseif ($step == 2): ?>
            <h2>Reset Password</h2>
            <p class="subtitle">Identity verified. Please create a new password.</p>

            <?php if($error) echo "<div class='error'><i class='fas fa-exclamation-circle'></i> $error</div>"; ?>

            <form method="POST">
                <div class="password-container">
                    <input type="password" name="new_password" id="newPass" placeholder="New Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass('newPass', this)"></i>
                </div>

                <div class="password-container">
                    <input type="password" name="confirm_password" id="confirmPass" placeholder="Confirm Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePass('confirmPass', this)"></i>
                </div>

                <button type="submit" name="reset_password" class="btn">Update Password</button>
            </form>

        <?php elseif ($step == 3): ?>
            <h2 style="color:#00ff7f;">Success!</h2>
            <i class="fas fa-check-circle" style="font-size: 4em; color: #00ff7f; margin: 20px 0;"></i>
            <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if($step != 3): ?>
            <a href="login.php" class="back-link">Back to Login</a>
        <?php endif; ?>
    </div>

    <script>
        function togglePass(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>