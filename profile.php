<?php
session_start();
require 'vendor/autoload.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

// 2. DATABASE CONNECTION
$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$db = $client->misacinema_db;
$usersCollection = $db->users;

// 3. SMART USER ID DETECTION
$userId = null;
if (is_array($_SESSION['user']) && isset($_SESSION['user']['_id'])) {
    $userId = new MongoDB\BSON\ObjectId($_SESSION['user']['_id']);
} 
else if (is_string($_SESSION['user'])) {
    $nameStr = $_SESSION['user'];
    $tempUser = $usersCollection->findOne(['fullname' => $nameStr]);
    if ($tempUser) {
        $userId = $tempUser['_id'];
        $_SESSION['user'] = (array)$tempUser;
    } else {
        header("Location: logout.php");
        exit();
    }
} 
else {
    $arr = (array)$_SESSION['user'];
    if (isset($arr['_id'])) {
        $userId = new MongoDB\BSON\ObjectId($arr['_id']);
    }
}

// 4. LOGIC UPDATE PROFILE
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [];
    if (isset($_POST['fullname'])) $updateData['fullname'] = htmlspecialchars($_POST['fullname']);
    if (isset($_POST['phone'])) $updateData['phone'] = htmlspecialchars($_POST['phone']);
    if (isset($_POST['email'])) $updateData['email'] = htmlspecialchars($_POST['email']);
    if (isset($_POST['birthday'])) $updateData['birthday'] = $_POST['birthday'];

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_pic']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        if (in_array(strtolower($filetype), $allowed)) {
            $newFilename = "profile_" . (string)$userId . "." . $filetype;
            $destination = "uploads/" . $newFilename;
            if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                $updateData['profile_pic'] = $newFilename;
            }
        }
    }

    if (!empty($updateData)) {
        $usersCollection->updateOne(['_id' => $userId], ['$set' => $updateData]);
        $currentUser = $usersCollection->findOne(['_id' => $userId]);
        $_SESSION['user'] = (array)$currentUser;
        $message = "Profile updated successfully!";
    }
}

// 5. FETCH DATA
$user = $usersCollection->findOne(['_id' => $userId]);
$fullname = isset($user['fullname']) ? $user['fullname'] : 'User';
$email = isset($user['email']) ? $user['email'] : '';
$phone = isset($user['phone']) ? $user['phone'] : '';
$birthday = isset($user['birthday']) ? $user['birthday'] : '';
$pic = isset($user['profile_pic']) ? "uploads/".$user['profile_pic'] : "https://via.placeholder.com/150";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile - Misa Cinema</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.8)), url('assets/images/bg_cinema.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: white; 
            font-family: 'Roboto', sans-serif; 
            display: flex; 
            flex-direction: column; /* FIXED: Forces content and footer to stack vertically */
            min-height: 100vh; 
            margin: 0; 
            box-sizing: border-box;
        }

        /* Container for the profile card to handle centering */
        .profile-wrapper {
            flex: 1; /* Pushes footer to the bottom */
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 15px;
        }

        .profile-card { 
            background: #1a1a1a;
            width: 100%; 
            max-width: 500px; 
            padding: 40px; 
            border-radius: 12px; 
            border: 1px solid #333; 
            text-align: center; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .profile-img-container { position: relative; width: 130px; height: 130px; margin: 0 auto 25px; }
        .profile-img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 4px solid #e50914; }
        .upload-btn-wrapper { position: absolute; bottom: 5px; right: 5px; background: #e50914; color: white; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid #1a1a1a; }
        .upload-btn-wrapper input[type=file] { position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; height: 100%; width: 100%; }
        
        h2 { margin: 10px 0 20px; text-transform: uppercase; letter-spacing: 1px; font-size: 1.5rem; }
        .form-group { margin-bottom: 18px; text-align: left; }
        label { display: block; color: #bbb; font-size: 0.9em; margin-bottom: 8px; }
        input { width: 100%; padding: 14px; background: #222; border: 1px solid #444; color: white; border-radius: 6px; box-sizing: border-box; font-size: 16px; }
        input:focus { outline: none; border-color: #e50914; }
        
        .btn-save { width: 100%; background: #e50914; color: white; padding: 15px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 15px; text-transform: uppercase; }
        .links { margin-top: 25px; display: flex; justify-content: space-between; font-size: 0.95em; border-top: 1px solid #333; padding-top: 20px; }
        .links a { color: #888; text-decoration: none; }
        .msg { background: rgba(46, 204, 113, 0.2); border: 1px solid #2ecc71; color: #2ecc71; padding: 12px; border-radius: 6px; margin-bottom: 20px; }

        @media (max-width: 480px) { .profile-card { padding: 30px 20px; } }
    </style>
</head>
<body>

    <div class="profile-wrapper">
        <div class="profile-card">
            <?php if($message): ?>
                <div class="msg"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>

            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <div class="profile-img-container">
                    <img src="<?php echo $pic; ?>" alt="Profile" class="profile-img">
                    <div class="upload-btn-wrapper">
                        <i class="fas fa-camera"></i>
                        <input type="file" name="profile_pic" onchange="this.form.submit()">
                    </div>
                </div>

                <h2><?php echo $fullname; ?></h2>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" value="<?php echo $fullname; ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo $email; ?>">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo $phone; ?>">
                </div>
                <div class="form-group">
                    <label>Birthday</label>
                    <input type="date" name="birthday" value="<?php echo $birthday; ?>">
                </div>
                <button type="submit" class="btn-save">Save Changes</button>
            </form>

            <div class="links">
                <a href="home.php"><i class="fas fa-arrow-left"></i> Home</a>
                <a href="logout.php" style="color: #e50914;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>
</body>
</html>