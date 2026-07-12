<?php
session_start();

// 1. SECURITY
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') { 
    header("Location: login.php"); exit(); 
}

require 'vendor/autoload.php';
$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$db = $client->misacinema_db;

// ==========================================
// A. DATA LOGIC (Backend Power)
// ==========================================

// 1. Statistik Umum
$totalMovies = $db->shows->countDocuments();
$totalUsers = $db->users->countDocuments(['role' => 'customer']);

// Tarik SEMUA booking dari database
$allBookings = $db->bookings->find()->toArray(); 

$totalRevenue = 0;
$totalTickets = 0;

// 2. Logic Movie History (Analytics)
$movieStats = [];
$allMoviesCursor = $db->shows->find();
$allMovies = iterator_to_array($allMoviesCursor); 

foreach ($allMovies as $m) {
    // Check status Expired (Valid 30 hari)
    $dateAdded = isset($m['date_added']) ? $m['date_added'] : date('Y-m-d'); 
    $validUntil = date('Y-m-d', strtotime($dateAdded . ' + 30 days'));
    $isExpired = (date('Y-m-d') > $validUntil);

    $movieStats[$m['name']] = [
        'poster' => $m['image'] ?? 'default.jpg',
        'revenue' => 0,
        'tickets' => 0,
        'genre' => $m['genre'] ?? '-',
        'status' => $isExpired ? 'Expired' : 'On-Air', 
        'status_color' => $isExpired ? '#e74c3c' : '#2ecc71'
    ];
}

// Kira Duit & Tiket ikut Movie
foreach ($allBookings as $b) {
    $totalRevenue += isset($b['total_price']) ? (float)$b['total_price'] : 0;
    $seatCount = (isset($b['seats']) && is_array($b['seats'])) ? count($b['seats']) : 1;
    $totalTickets += $seatCount;

    $mName = $b['movie_name'] ?? 'Unknown';
    if (isset($movieStats[$mName])) {
        $movieStats[$mName]['revenue'] += (float)$b['total_price'];
        $movieStats[$mName]['tickets'] += $seatCount;
    }
}

// ==========================================
// B. LOGIC LIVE HALL (Connect Database Booking)
// ==========================================
$halls = [];
for ($i = 1; $i <= 6; $i++) {
    $type = ($i % 3 == 1) ? "VIP" : (($i % 3 == 2) ? "IMAX" : "Standard");
    $badgeColor = ($type == "VIP") ? "#FFD700" : (($type == "IMAX") ? "#007bff" : "#888");
    
    $halls["Hall $i"] = [
        'type' => $type, 'badge_color' => $badgeColor,
        'status' => 'Available', 'movie' => '-', 'end_time' => '-', 
        'progress' => 0,
        'occupied_seats' => [] 
    ];
}

$currentTime = time(); 

foreach ($allMovies as $m) {
    if (isset($m['showtimes'])) {
        foreach ($m['showtimes'] as $show) {
            $showStart = strtotime($show['datetime']);
            $duration = isset($m['duration']) ? intval($m['duration']) : 120;
            $showEnd = $showStart + ($duration * 60);
            $cleaningEnd = $showEnd + (30 * 60);

            $hallKey = $show['hall'];
            if (strpos($hallKey, '(') !== false) {
                $hallKey = trim(explode('(', $hallKey)[0]);
            }

            if (isset($halls[$hallKey])) {
                if ($currentTime >= $showStart && $currentTime < $showEnd) {
                    $halls[$hallKey]['status'] = 'ONGOING';
                    $halls[$hallKey]['movie'] = $m['name'];
                    $halls[$hallKey]['end_time'] = date('h:i A', $showEnd);
                    
                    $totalDuration = $showEnd - $showStart;
                    $elapsed = $currentTime - $showStart;
                    $halls[$hallKey]['progress'] = ($elapsed / $totalDuration) * 100;

                    $occupied = [];
                    foreach ($allBookings as $b) {
                        if (isset($b['movie_name']) && $b['movie_name'] == $m['name']) {
                            if (isset($b['seats']) && is_array($b['seats'])) {
                                foreach($b['seats'] as $s) {
                                    $occupied[] = trim($s); 
                                }
                            }
                        }
                    }
                    $halls[$hallKey]['occupied_seats'] = array_unique($occupied);

                } elseif ($currentTime >= $showEnd && $currentTime < $cleaningEnd) {
                    $halls[$hallKey]['status'] = 'CLEANING';
                    $halls[$hallKey]['movie'] = 'Housekeeping...';
                    $halls[$hallKey]['end_time'] = date('h:i A', $cleaningEnd);
                    $halls[$hallKey]['progress'] = 100;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard Pro</title>
    
    <link rel="icon" type="image/jpeg" href="assets/images/logo_misa.png">
    
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { background-color: #0b0b0b; color: white; display: flex; min-height: 100vh; }

        .main-content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); }
        .header-title h1 { color: #e50914; text-transform: uppercase; letter-spacing: 1px; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 30px 0; }
        .stat-card { background: #1a1a1a; padding: 25px; border-radius: 8px; border-left: 4px solid #e50914; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        .stat-card .value { font-size: 1.8rem; font-weight: bold; margin-top: 10px; }

        /* TABLES */
        .section-title { margin: 50px 0 20px; border-left: 4px solid #e50914; padding-left: 10px; font-size: 1.4rem; display: flex; justify-content: space-between; align-items: center; }
        .table-container { background: #1a1a1a; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #333; }
        th { background: #222; color: #e50914; text-transform: uppercase; font-size: 0.8rem; }
        tr:hover { background: #1f1f1f; }
        .thumb-img { width: 40px; height: 50px; object-fit: cover; border-radius: 4px; vertical-align: middle; margin-right: 10px; }

        /* --- VISUAL SEAT MAP BESAR (SCROLL DOWN) --- */
        .halls-container {
            display: flex;
            flex-direction: column; 
            gap: 30px; 
        }

        .hall-card { 
            background: #161616; 
            border: 1px solid #333; 
            border-radius: 10px; 
            padding: 25px; 
            position: relative; 
            width: 100%; 
        }
        
        .hall-header { display: flex; justify-content: space-between; margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 10px;}
        .hall-name { font-weight: bold; font-size: 1.3rem; }
        
        .status-badge { font-size: 0.8rem; padding: 5px 10px; border-radius: 12px; font-weight: bold; text-transform: uppercase; }
        .status-ongoing { background: rgba(229, 9, 20, 0.2); color: #e50914; border: 1px solid #e50914; animation: pulse 2s infinite; }
        .status-available { background: #222; color: #777; border: 1px solid #444; }
        .status-cleaning { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid #ffc107; }
        
        /* SEAT MAP DISPLAY */
        .seat-visualizer {
            background: #000; border: 1px solid #333; border-radius: 6px; padding: 20px;
            margin: 15px 0; display: flex; flex-direction: column; align-items: center;
        }
        .screen-line { width: 60%; height: 5px; background: #555; margin-bottom: 30px; border-radius: 50%; box-shadow: 0 0 10px #555; text-align:center; color:#555; font-size: 0.7rem; line-height: 20px;}
        
        .mini-grid { 
            display: grid; 
            grid-template-columns: repeat(8, 1fr); 
            gap: 10px; 
        }
        .seat-dot {
            width: 20px; height: 20px; 
            background: #333; 
            border-radius: 4px; 
            display: flex; align-items: center; justify-content: center;
            font-size: 0.6rem; color: #777;
        }
        
        .seat-booked { 
            background: #e50914 !important; 
            box-shadow: 0 0 8px #e50914; 
            color: white;
            border: 1px solid white;
        } 
        
        .movie-info { font-size: 1.1rem; color: #ccc; margin-bottom: 5px; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header-title">
            <h1>Business Dashboard</h1>
            <p style="color: #666;">Real-time analytics & monitoring</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-coins" style="float:right; color:#333;"></i>
                <h3>Total Revenue</h3>
                <div class="value">RM <?php echo number_format($totalRevenue, 2); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #3498db;">
                <i class="fas fa-ticket-alt" style="float:right; color:#333;"></i>
                <h3>Tickets Sold</h3>
                <div class="value"><?php echo $totalTickets; ?></div>
            </div>
        </div>

        <h3 class="section-title">
            <span><i class="fas fa-chart-line"></i> &nbsp; Movie Analytics History</span>
            <span style="font-size:0.8rem; color:#888; font-weight:normal;">Includes Expired Movies</span>
        </h3>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Movie</th>
                        <th>Status</th>
                        <th>Genre</th>
                        <th>Total Tickets</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($movieStats as $name => $stat): ?>
                    <tr>
                        <td>
                            <img src="assets/img/<?php echo $stat['poster']; ?>" class="thumb-img" alt="poster">
                            <span style="font-weight:bold; color:white;"><?php echo $name; ?></span>
                        </td>
                        <td>
                            <span style="font-size:0.7rem; border: 1px solid <?php echo $stat['status_color']; ?>; color:<?php echo $stat['status_color']; ?>; padding:3px 8px; border-radius:4px;">
                                <?php echo $stat['status']; ?>
                            </span>
                        </td>
                        <td style="color:#888;"><?php echo $stat['genre']; ?></td>
                        <td><?php echo $stat['tickets']; ?> sold</td>
                        <td style="color: #2ecc71; font-weight:bold;">RM <?php echo number_format($stat['revenue'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="section-title">
            <span><i class="fas fa-desktop"></i> &nbsp; Live Hall Status</span>
            <span style="font-size:0.8rem; background:#e50914; color:white; padding:4px 10px; border-radius:4px; font-weight:bold;">
                RED = BOOKED
            </span>
        </h3>
        
        <div class="halls-container">
            <?php foreach($halls as $name => $info): ?>
            <div class="hall-card">
                <div class="hall-header">
                    <div>
                        <span class="hall-name"><?php echo $name; ?></span>
                        <span style="font-size:0.8rem; border:1px solid <?php echo $info['badge_color']; ?>; color:<?php echo $info['badge_color']; ?>; padding:2px 8px; border-radius:3px; margin-left:10px;">
                            <?php echo $info['type']; ?>
                        </span>
                    </div>
                    <div class="status-badge status-<?php echo strtolower($info['status']); ?>">
                        <?php echo $info['status']; ?>
                    </div>
                </div>

                <?php if($info['status'] == 'ONGOING'): ?>
                    <div class="movie-info">Now Showing: <b style="color:white; font-size:1.3rem;"><?php echo $info['movie']; ?></b></div>
                    <div style="font-size:0.9rem; color:#666; margin-bottom:10px;">
                        Ends at: <?php echo $info['end_time']; ?> 
                        &bull; Seats Sold: <b style="color:#e50914"><?php echo count($info['occupied_seats']); ?></b> / 40
                    </div>
                <?php elseif($info['status'] == 'CLEANING'): ?>
                    <div class="movie-info" style="color:#ffc107;">Cleaning in progress...</div>
                <?php else: ?>
                    <div class="movie-info" style="color:#444;">Standby Mode (No Movie)</div>
                <?php endif; ?>

                <div class="seat-visualizer">
                    <div class="screen-line">SCREEN</div> 
                    <div class="mini-grid">
                        <?php 
                        // Generate 40 kerusi (5 baris x 8 kerusi)
                        $rows = ['A','B','C','D','E'];
                        for($r=0; $r<5; $r++) {
                            for($c=1; $c<=8; $c++) {
                                $seatID = $rows[$r] . $c; 
                                
                                $isBooked = in_array($seatID, $info['occupied_seats']);
                                $class = $isBooked ? 'seat-booked' : '';
                                
                                echo "<div class='seat-dot $class' title='$seatID'>$seatID</div>";
                            }
                        }
                        ?>
                    </div>
                </div>

                <div style="height:6px; background:#333; margin-top:10px; border-radius:3px;">
                    <div style="height:100%; width:<?php echo $info['progress']; ?>%; background:<?php echo ($info['status']=='CLEANING')?'#ffc107':'#e50914'; ?>; transition:width 1s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <br><br>
    </div>

</body>
</html>
