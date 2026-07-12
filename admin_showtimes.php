<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') { header("Location: login.php"); exit(); }

require 'vendor/autoload.php';
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$db = $client->misacinema_db;

$movies = $db->movies->find(); // Ambil list movie
$halls = $db->halls->find();   // Ambil list hall
$showtimes = $db->showtimes->find([], ['sort' => ['start_time' => 1]]); // Ambil jadual

$msg = "";

// --- 1. ADD SHOWTIME ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_showtime'])) {
    $movieId = new ObjectId($_POST['movie_id']);
    $hallId = new ObjectId($_POST['hall_id']);
    $datetime = $_POST['datetime']; // Format dari form: 2026-01-25T14:30
    
    // Cari detail movie & hall untuk simpan cache nama (senang nak display nanti)
    $movieDoc = $db->movies->findOne(['_id' => $movieId]);
    $hallDoc = $db->halls->findOne(['_id' => $hallId]);
    
    $utcTime = new UTCDateTime(strtotime($datetime) * 1000);

    $db->showtimes->insertOne([
        'movie_id'   => $movieId,
        'movie_title'=> $movieDoc['title'] ?? $movieDoc['name'],
        'hall_id'    => $hallId,
        'hall_name'  => $hallDoc['hall_name'],
        'hall_type'  => $hallDoc['type'],
        'price'      => (int)$_POST['price'], // Admin boleh set harga override
        'start_time' => $utcTime,
        'created_at' => new UTCDateTime()
    ]);
    
    $msg = "Showtime added successfully!";
    header("Refresh:0"); // Refresh page
}

// --- 2. DELETE SHOWTIME ---
if (isset($_POST['delete_id'])) {
    $db->showtimes->deleteOne(['_id' => new ObjectId($_POST['delete_id'])]);
    $msg = "Showtime deleted.";
    header("Refresh:0");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Showtimes</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0b0b0b; color: white; font-family: 'Roboto', sans-serif; display: flex; }
        .main-content { margin-left: 250px; flex: 1; padding: 40px; }
        .panel { background: #1a1a1a; padding: 25px; border-radius: 8px; border: 1px solid #333; margin-bottom: 30px; }
        input, select { width: 100%; padding: 12px; background: #111; border: 1px solid #333; color: white; margin-bottom: 15px; border-radius: 4px; }
        .btn { width: 100%; padding: 12px; background: #e50914; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #2a2a2a; }
        th { color: #e50914; text-transform: uppercase; font-size: 0.85em; }
        .tag { padding: 4px 8px; border-radius: 4px; font-size: 0.8em; font-weight: bold; background:#333; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <h2 style="color:#e50914">Manage Showtimes</h2>
        <?php if($msg) echo "<p style='color:lime'>$msg</p>"; ?>

        <div class="panel">
            <h3>Add New Schedule</h3>
            <form method="POST">
                <input type="hidden" name="add_showtime" value="1">
                
                <label>Select Movie</label>
                <select name="movie_id" required>
                    <?php foreach($movies as $m): ?>
                        <option value="<?php echo $m['_id']; ?>"><?php echo $m['title'] ?? $m['name']; ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Select Hall</label>
                <select name="hall_id" required>
                    <?php foreach($halls as $h): ?>
                        <option value="<?php echo $h['_id']; ?>">
                            <?php echo $h['hall_name']; ?> (<?php echo $h['type']; ?>) - <?php echo $h['capacity']; ?> Seats
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Date & Time</label>
                <input type="datetime-local" name="datetime" required>

                <label>Ticket Price (RM)</label>
                <input type="number" name="price" value="15" required>

                <button type="submit" class="btn">Add Showtime</button>
            </form>
        </div>

        <div class="panel">
            <h3>Upcoming Showtimes</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Movie</th>
                        <th>Hall</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($showtimes as $s): 
                        $time = $s['start_time']->toDateTime()->format('d M Y, h:i A');
                    ?>
                    <tr>
                        <td style="font-weight:bold; color:#fff;"><?php echo $time; ?></td>
                        <td><?php echo $s['movie_title']; ?></td>
                        <td>
                            <?php echo $s['hall_name']; ?> 
                            <span class="tag"><?php echo $s['hall_type']; ?></span>
                        </td>
                        <td>RM <?php echo $s['price']; ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this showtime?');">
                                <input type="hidden" name="delete_id" value="<?php echo $s['_id']; ?>">
                                <button style="background:none; border:none; cursor:pointer; color:#e50914;"><i class="fas fa-trash"></i></button>
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

