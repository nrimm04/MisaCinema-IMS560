<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') { header("Location: login.php"); exit(); }

require 'vendor/autoload.php';
use MongoDB\BSON\ObjectId;

$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$staffCollection = $client->misacinema_db->staff;

$editMode = false;
$staffToEdit = null;
$viewMode = false;
$staffView = null;

if (isset($_POST['delete_id'])) { $staffCollection->deleteOne(['_id' => new ObjectId($_POST['delete_id'])]); }

if (isset($_GET['view_id'])) {
    $staffView = $staffCollection->findOne(['_id' => new ObjectId($_GET['view_id'])]);
    if ($staffView) $viewMode = true;
}

if (isset($_GET['edit_id'])) {
    $staffToEdit = $staffCollection->findOne(['_id' => new ObjectId($_GET['edit_id'])]);
    if ($staffToEdit) $editMode = true;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_staff'])) {
    $staffCollection->updateOne(
        ['_id' => new ObjectId($_POST['id'])],
        ['$set' => ['name' => $_POST['staff_name'], 'role' => $_POST['role'], 'email' => $_POST['email']]]
    );
    header("Location: admin_staff.php"); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $staffCollection->insertOne(['name' => $_POST['staff_name'], 'role' => $_POST['role'], 'email' => $_POST['email']]);
}
$staff = $staffCollection->find();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { background-color: #0b0b0b; color: white; display: flex; }
        .main-content { margin-left: 250px; flex: 1; padding: 40px; display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .panel { background: #1a1a1a; padding: 25px; border-radius: 8px; border: 1px solid #333; }
        input, select { width: 100%; padding: 12px; background: #111; border: 1px solid #333; color: white; margin-bottom: 15px; }
        .btn { width: 100%; padding: 12px; background: #e50914; color: white; border: none; cursor: pointer; border-radius: 4px; }
        
        .staff-card { display: flex; align-items: center; justify-content: space-between; background: #222; padding: 15px; margin-bottom: 10px; border-radius: 4px; border-left: 3px solid #e50914; }
        .staff-info { display: flex; align-items: center; }
        .avatar { width: 40px; height: 40px; background: #444; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin-right: 15px; }
        .actions { display: flex; gap: 10px; }
        .btn-icon { background: none; border: none; color: #888; cursor: pointer; font-size: 1.1em; }
        .btn-icon:hover { color: white; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 200; }
        .modal-box { background: #1a1a1a; width: 400px; padding: 40px; border-radius: 8px; border: 1px solid #444; position: relative; text-align: center; }
        .close-btn { position: absolute; top: 10px; right: 15px; color: #aaa; font-size: 1.5em; text-decoration: none; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        
        <?php if($viewMode && $staffView): ?>
        <div class="modal-overlay">
            <div class="modal-box">
                <a href="admin_staff.php" class="close-btn">&times;</a>
                <div style="font-size: 3em; color: #e50914; margin-bottom: 20px;"><i class="fas fa-id-badge"></i></div>
                <h2><?php echo $staffView['name']; ?></h2>
                <p style="color:#aaa; text-transform:uppercase; letter-spacing:1px; margin-bottom:20px;"><?php echo $staffView['role']; ?></p>
                <div style="background:#222; padding:10px; border-radius:4px;">
                    Email: <?php echo $staffView['email']; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <h3><?php echo $editMode ? 'Edit Staff' : 'Add Staff'; ?></h3>
            <br>
            <form method="POST">
                <?php if($editMode): ?>
                    <input type="hidden" name="update_staff" value="1"><input type="hidden" name="id" value="<?php echo $staffToEdit['_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_staff" value="1">
                <?php endif; ?>
                <label>Name</label><input type="text" name="staff_name" required value="<?php echo $editMode ? $staffToEdit['name'] : ''; ?>">
                <label>Role</label>
                <select name="role">
                    <option value="Manager">Manager</option><option value="Counter Staff">Counter Staff</option><option value="Cleaner">Cleaner</option>
                </select>
                <label>Email</label><input type="text" name="email" required value="<?php echo $editMode ? $staffToEdit['email'] : ''; ?>">
                <button type="submit" class="btn"><?php echo $editMode ? 'Update' : 'Register'; ?></button>
                <?php if($editMode): ?><a href="admin_staff.php" style="display:block; text-align:center; margin-top:10px; color:#aaa;">Cancel</a><?php endif; ?>
            </form>
        </div>

        <div class="panel">
            <h3>Staff List</h3>
            <br>
            <?php foreach($staff as $s): ?>
            <div class="staff-card">
                <div class="staff-info">
                    <div class="avatar"><i class="fas fa-user"></i></div>
                    <div><strong><?php echo $s['name']; ?></strong><small style="display:block; color:#aaa;"><?php echo $s['role']; ?></small></div>
                </div>
                <div class="actions">
                    <a href="admin_staff.php?view_id=<?php echo $s['_id']; ?>" class="btn-icon"><i class="fas fa-eye"></i></a>
                    <a href="admin_staff.php?edit_id=<?php echo $s['_id']; ?>" class="btn-icon"><i class="fas fa-edit"></i></a>
                    <form method="POST" onsubmit="return confirm('Delete?');" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?php echo $s['_id']; ?>">
                        <button type="submit" class="btn-icon"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>

