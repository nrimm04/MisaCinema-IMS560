<?php
session_start();
// --- FIX TIMEZONE JUGAK KAT SINI ---
date_default_timezone_set('Asia/Kuala_Lumpur');

if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') { 
    header("Location: login.php"); 
    exit(); 
}

require 'vendor/autoload.php';
use MongoDB\BSON\ObjectId;

$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$movieCollection = $client->misacinema_db->shows; 

// --- HALL SETUP (Ikut Screenshot) ---
// Hall 1 & 4 = VIP
// Hall 2 & 5 = IMAX
// Hall 3 & 6 = Standard
$cinemaHalls = [];
for ($i = 1; $i <= 6; $i++) {
    $remainder = $i % 3;
    if ($remainder == 1) $type = "(VIP)";
    elseif ($remainder == 2) $type = "(IMAX)";
    else $type = "(Standard)";
    $cinemaHalls[] = "Hall $i $type";
}

// --- FIXED TIMES (Tak payah isi manual dah) ---
$presetTimes = [
    1 => "10:00", 
    2 => "12:30", 
    3 => "15:00", 
    4 => "17:30", 
    5 => "20:00", 
    6 => "22:30" 
];

// GET MOVIE
if (!isset($_GET['id']) || empty($_GET['id'])) { header("Location: admin_movies.php"); exit(); }

try {
    $movieId = new ObjectId($_GET['id']);
    $movie = $movieCollection->findOne(['_id' => $movieId]);
} catch (Exception $e) { $movie = null; }

if (!$movie) { 
    echo "<h2 style='color:white; background:#0b0b0b; padding:20px;'>Error: Movie not found. <a href='admin_movies.php' style='color:red;'>Back</a></h2>"; 
    exit(); 
}

$msg = "";
$error = "";

// --- PROCESS GENERATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_bulk'])) {
    
    $startDate = new DateTime($_POST['start_date']);
    $endDate = new DateTime($_POST['end_date']);
    
    if ($startDate > $endDate) {
        $error = "Start Date cannot be later than End Date.";
    } else {
        $newShowtimes = [];
        
        while ($startDate <= $endDate) {
            $dateStr = $startDate->format('Y-m-d');
            
            // Loop 6 Slot (Fixed Time)
            for ($i = 1; $i <= 6; $i++) {
                $hallKey = 'hall_' . $i;
                $priceKey = 'price_' . $i;
                // Kita ambil time dari array PHP terus, tak payah ambil dari POST input
                $fixedTime = $presetTimes[$i]; 

                // Hanya simpan kalau Hall dipilih
                if (!empty($_POST[$hallKey])) {
                    $newShowtimes[] = [
                        '_id' => new ObjectId(),
                        'hall' => $_POST[$hallKey],
                        'datetime' => $dateStr . 'T' . $fixedTime,
                        'price' => $_POST[$priceKey],
                        'seats_booked' => []
                    ];
                }
            }
            $startDate->modify('+1 day');
        }

        if (count($newShowtimes) > 0) {
            $movieCollection->updateOne(
                ['_id' => $movieId],
                ['$push' => ['showtimes' => ['$each' => $newShowtimes]]]
            );
            $msg = "Success! Generated " . count($newShowtimes) . " sessions.";
            $movie = $movieCollection->findOne(['_id' => $movieId]);
        }
    }
}

// DELETE ONE
if (isset($_POST['delete_show_id'])) {
    $movieCollection->updateOne(['_id' => $movieId], ['$pull' => ['showtimes' => ['_id' => new ObjectId($_POST['delete_show_id'])]]]);
    $movie = $movieCollection->findOne(['_id' => $movieId]);
}

// DELETE ALL
if (isset($_POST['delete_all'])) {
    $movieCollection->updateOne(['_id' => $movieId], ['$set' => ['showtimes' => []]]);
    $movie = $movieCollection->findOne(['_id' => $movieId]);
    $msg = "All schedules cleared.";
}

// SORTING
$showtimes = isset($movie['showtimes']) ? (array)$movie['showtimes'] : [];
usort($showtimes, function($a, $b) { return strcmp($a['datetime'], $b['datetime']); });
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedule: <?php echo $movie['name']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { background-color: #0b0b0b; color: white; display: flex; }
        .main-content { margin-left: 250px; flex: 1; padding: 40px; }
        .panel { background: #1a1a1a; padding: 25px; border-radius: 8px; border: 1px solid #333; margin-bottom: 30px; }
        input, select { width: 100%; padding: 10px; background: #111; border: 1px solid #444; color: white; border-radius: 4px; color-scheme: dark; }
        label { color: #aaa; font-size: 0.8em; display: block; margin-bottom: 4px; margin-top: 8px; }
        
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        
        .session-box { background: #222; padding: 15px; border-radius: 6px; border-left: 4px solid #444; }
        .session-box:hover { border-left-color: #e50914; background: #252525; }
        
        .btn { padding: 10px 20px; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; width: 100%; margin-top: 15px; }
        .btn-gen { background: #28a745; color: white; }
        .btn-clear { background: #dc3545; color: white; width: auto; font-size: 0.8em; padding: 5px 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.9em; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
        th { color: #e50914; background: #222; }
        tr:hover { background: #1f1f1f; }
        
        .tag-vip { color: gold; font-weight: bold; }
        .tag-imax { color: #007bff; font-weight: bold; }
        .fixed-time { font-size: 1.2em; font-weight: bold; color: #fff; display: block; margin: 10px 0; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <a href="admin_movies.php" style="color:#aaa; text-decoration:none;"><i class="fas fa-arrow-left"></i> Back to Movies</a>
        <h2 style="margin: 20px 0;">Manage Schedule: <span style="color:#e50914;"><?php echo $movie['name']; ?></span></h2>

        <div class="panel">
            <h3 style="margin-bottom: 15px;"><i class="fas fa-calendar-alt"></i> Bulk Generator (Auto Time)</h3>
            <p style="color:#888; font-size:0.9em; margin-bottom:20px;">
                Masa dah ditetapkan. Pilih <strong>Hall</strong> sahaja untuk slot yang awak nak buka.
            </p>
            
            <?php if($msg) echo "<p style='color:lime; margin-bottom:15px;'>$msg</p>"; ?>
            <?php if($error) echo "<p style='color:red; margin-bottom:15px;'>$error</p>"; ?>

            <form method="POST">
                <input type="hidden" name="generate_bulk" value="1">
                
                <div class="grid-2">
                    <div><label>Start Date</label><input type="date" name="start_date" required></div>
                    <div><label>End Date</label><input type="date" name="end_date" required></div>
                </div>
                <br>
                
                <div class="grid-3">
                    <?php for($i=1; $i<=6; $i++): ?>
                    <div class="session-box">
                        <strong style="color:white; font-size:1em; color:#888;">Slot <?php echo $i; ?></strong>
                        <span class="fixed-time"><?php echo date('h:i A', strtotime($presetTimes[$i])); ?></span>
                        
                        <label>Select Hall</label>
                        <select name="hall_<?php echo $i; ?>">
                            <option value="">-- Close (No Show) --</option>
                            <?php foreach($cinemaHalls as $h): ?>
                                <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <label>Price (RM)</label>
                        <input type="number" name="price_<?php echo $i; ?>" value="15">
                    </div>
                    <?php endfor; ?>
                </div>

                <button type="submit" class="btn btn-gen" onclick="return confirm('Generate jadual ini?');">
                    GENERATE SCHEDULE
                </button>
            </form>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Current Schedule (<?php echo count($showtimes); ?> slots)</h3>
            <?php if(count($showtimes) > 0): ?>
            <form method="POST" onsubmit="return confirm('Delete SEMUA schedule?');"><button type="submit" name="delete_all" class="btn-clear">Clear All</button></form>
            <?php endif; ?>
        </div>
        
        <div class="panel" style="padding:0; overflow:hidden;">
            <table>
                <thead><tr><th>Date & Time</th><th>Hall</th><th>Price</th><th>Action</th></tr></thead>
                <tbody>
                    <?php 
                    $now = new DateTime();
                    if(count($showtimes) == 0) echo "<tr><td colspan='4' style='text-align:center;'>No showtimes set.</td></tr>";
                    foreach($showtimes as $show): 
                        $showTime = new DateTime($show['datetime']);
                        $isPast = $showTime < $now;
                    ?>
                    <tr style="<?php echo $isPast ? 'opacity:0.4;' : ''; ?>">
                        <td><?php echo $showTime->format('d M Y, h:i A'); ?></td>
                        <td>
                            <?php 
                            if(strpos($show['hall'], 'VIP')) echo "<span class='tag-vip'>".$show['hall']."</span>";
                            elseif(strpos($show['hall'], 'IMAX')) echo "<span class='tag-imax'>".$show['hall']."</span>";
                            else echo $show['hall'];
                            ?>
                        </td>
                        <td>RM <?php echo $show['price']; ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="delete_show_id" value="<?php echo $show['_id']; ?>">
                                <button style="background:none; border:none; color:red; cursor:pointer;"><i class="fas fa-trash"></i></button>
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

