<?php
session_start();
// Pastikan user adalah admin
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') { header("Location: login.php"); exit(); }

require 'vendor/autoload.php';
use MongoDB\BSON\ObjectId;

$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$db = $client->misacinema_db;
$hallsCollection = $db->halls;
$seatsCollection = $db->seats; 

$editMode = false;
$hallToEdit = null;
$viewMode = false;
$hallView = null;
$msg = "";

// --- DELETE HALL & SEATS ---
if (isset($_POST['delete_id'])) { 
    $id = new ObjectId($_POST['delete_id']);
    $hallsCollection->deleteOne(['_id' => $id]);
    // Delete semua kerusi yang link dengan hall ni
    $seatsCollection->deleteMany(['hall_id' => $id]);
    $msg = "Hall and its seats deleted.";
}

// --- VIEW DETAILS (MODAL) ---
if (isset($_GET['view_id'])) {
    $hallView = $hallsCollection->findOne(['_id' => new ObjectId($_GET['view_id'])]);
    if ($hallView) $viewMode = true;
}

// --- EDIT SETUP ---
if (isset($_GET['edit_id'])) {
    $hallToEdit = $hallsCollection->findOne(['_id' => new ObjectId($_GET['edit_id'])]);
    if ($hallToEdit) $editMode = true;
}

// --- UPDATE HALL NAME ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_hall'])) {
    $hallsCollection->updateOne(
        ['_id' => new ObjectId($_POST['id'])],
        ['$set' => ['hall_name' => $_POST['hall_name']]]
    );
    header("Location: admin_halls.php"); exit();
}

// --- ADD HALL (AUTO GENERATE SEATS - SWEET SPOT LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_hall'])) {
    $hallName = $_POST['hall_name'];
    $type = $_POST['type'];
    
    // 1. Tentukan Capacity & Layout (TALLY DENGAN BOOKING.PHP)
    $rows = 0;
    $cols = 0;
    $price = 0;

    if ($type == 'Standard') {
        // Logic: 10 Baris x 12 Kerusi = 120 (Sesuai untuk visual)
        $rows = 10; $cols = 12; $price = 15;
    } elseif ($type == 'IMAX') {
        // Logic: 12 Baris x 14 Kerusi = 168 (Nampak besar)
        $rows = 12; $cols = 14; $price = 25;
    } elseif ($type == 'VIP') {
        // Logic: 5 Baris x 8 Kerusi = 40 (Eksklusif)
        $rows = 5;  $cols = 8;  $price = 35;
    }

    $capacity = $rows * $cols; // Auto kira total capacity

    // 2. Insert Hall ke Database
    $result = $hallsCollection->insertOne([
        'hall_name' => $hallName,
        'capacity' => $capacity,
        'type' => $type,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
    
    $newHallId = $result->getInsertedId();

    // 3. Auto Generate Seats Loop
    // Kita guna logic A1..A12, B1..B12 ikut row/col tadi
    $alphabet = range('A', 'Z'); // A, B, C...
    
    for ($r = 0; $r < $rows; $r++) {
        for ($c = 1; $c <= $cols; $c++) {
            // Nama kerusi: A1, A2... B1, B2...
            $seatNumber = $alphabet[$r] . $c;
            
            $seatsCollection->insertOne([
                'hall_id' => $newHallId,     
                'seat_number' => $seatNumber,
                'type' => $type,
                'price' => $price,
                'status' => 1 // 1 = Available
            ]);
        }
    }
    
    $msg = "Hall '$hallName' created with $capacity seats ($type Layout)!";
}

$halls = $hallsCollection->find();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Halls</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { background-color: #0b0b0b; color: white; display: flex; }
        .main-content { margin-left: 250px; flex: 1; padding: 40px; display: grid; grid-template-columns: 1fr 2fr; gap: 30px;}
        
        .panel { background: #1a1a1a; padding: 25px; border-radius: 8px; border: 1px solid #333; height: fit-content; }
        input, select { width: 100%; padding: 12px; background: #111; border: 1px solid #333; color: white; margin-bottom: 15px; border-radius: 4px; }
        label { display: block; margin-bottom: 5px; color: #aaa; font-size: 0.8em; font-weight: bold; }
        .btn { width: 100%; padding: 12px; background: #e50914; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        thead { background-color: #333; }
        th { color: #e50914; font-weight: bold; text-transform: uppercase; font-size: 0.85em; letter-spacing: 1px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #2a2a2a; }
        tr:hover { background-color: #222; }
        .action-link { margin-right: 10px; color: #888; font-size: 1.1em; transition: 0.3s; }
        .action-link:hover { color: white; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 200; }
        .modal-box { background: #1a1a1a; width: 400px; padding: 40px; border-radius: 8px; border: 1px solid #444; position: relative; text-align: center; }
        .close-btn { position: absolute; top: 10px; right: 15px; color: #aaa; font-size: 1.5em; text-decoration: none; }
        
        .tag { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
        .tag-imax { background: #0044cc; color: white; }
        .tag-vip { background: #ffd700; color: black; }
        .tag-std { background: #444; color: white; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-content">
        
        <?php if($viewMode && $hallView): ?>
        <div class="modal-overlay">
            <div class="modal-box">
                <a href="admin_halls.php" class="close-btn">&times;</a>
                <h2 style="color:#e50914; margin-bottom: 5px;"><?php echo $hallView['hall_name']; ?></h2>
                <span class="tag <?php echo ($hallView['type']=='IMAX'?'tag-imax':($hallView['type']=='VIP'?'tag-vip':'tag-std')); ?>">
                    <?php echo $hallView['type']; ?>
                </span>
                <hr style="border:0; border-top:1px solid #333; margin:20px 0;">
                <div style="font-size:4em; font-weight:bold; color: white;"><?php echo $hallView['capacity']; ?></div>
                <p style="color:#aaa; margin-top:-5px;">Total Seats (Visual Optimized)</p>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <h3><?php echo $editMode ? 'Edit Hall Name' : 'Add New Hall'; ?></h3>
            <p style="color:#777; font-size:0.9em; margin-bottom:15px;">Seats layout will be generated automatically.</p>
            
            <?php if($msg) echo "<p style='color:lime; margin-bottom:10px; font-size:0.9em;'>$msg</p>"; ?>
            
            <form method="POST">
                <?php if($editMode): ?>
                    <input type="hidden" name="update_hall" value="1"><input type="hidden" name="id" value="<?php echo $hallToEdit['_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_hall" value="1">
                <?php endif; ?>
                
                <label>Hall Name (e.g., Hall 1)</label>
                <input type="text" name="hall_name" required value="<?php echo $editMode ? $hallToEdit['hall_name'] : ''; ?>" placeholder="Enter Hall Name">
                
                <?php if(!$editMode): ?>
                <label>Hall Type</label>
                <select name="type" required style="width: 100%; padding: 10px; background: #333; color: white; border: 1px solid #555; border-radius: 4px;">
                    <option value="Standard">Standard (120 Seats)</option>
                    <option value="IMAX">IMAX (168 Seats)</option>
                    <option value="VIP">VIP / Premium (40 Seats)</option>
                </select>
                <?php endif; ?>
                
                <button type="submit" class="btn"><?php echo $editMode ? 'Update Name' : 'Generate Hall'; ?></button>
                <?php if($editMode): ?><a href="admin_halls.php" style="display:block; text-align:center; margin-top:10px; color:#aaa; text-decoration:none;">Cancel</a><?php endif; ?>
            </form>
        </div>

        <div class="panel">
            <h3>Halls List</h3>
            <table>
                <thead>
                    <tr>
                        <th>Hall Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($halls as $h): ?>
                    <tr>
                        <td style="font-weight:bold;"><?php echo $h['hall_name']; ?></td>
                        <td>
                            <span class="tag <?php echo ($h['type']=='IMAX'?'tag-imax':($h['type']=='VIP'?'tag-vip':'tag-std')); ?>">
                                <?php echo $h['type']; ?>
                            </span>
                        </td>
                        <td><?php echo $h['capacity']; ?> Seats</td>
                        <td>
                            <a href="admin_halls.php?view_id=<?php echo $h['_id']; ?>" class="action-link" title="View"><i class="fas fa-eye"></i></a>
                            <a href="admin_halls.php?edit_id=<?php echo $h['_id']; ?>" class="action-link" title="Edit Name"><i class="fas fa-edit"></i></a>
                            <form method="POST" onsubmit="return confirm('Deleting this hall will also delete all its seats. Continue?');" style="display:inline;">
                                <input type="hidden" name="delete_id" value="<?php echo $h['_id']; ?>">
                                <button type="submit" class="action-link" style="background:none; border:none; cursor:pointer;" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
   
</body>

</html>

