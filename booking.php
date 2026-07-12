<?php
session_start();
// --- FIX TIMEZONE ---
date_default_timezone_set('Asia/Kuala_Lumpur'); 

// 1. SECURITY CHECK
if (!isset($_SESSION['user'])) { 
    header("Location: login.php"); 
    exit(); 
}

require 'vendor/autoload.php';
use MongoDB\BSON\ObjectId;

// 2. DATABASE CONNECTION
$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$db = $client->misacinema_db;
$collection = $db->shows; 

$movie = null;
$selectedShowtime = null;

// --- LOGIC TO GET MOVIE & SHOWTIME ---
try {
    if (isset($_GET['showtime_id'])) {
        // If user already chose a time (Screen 2 - Select Seat)
        $sId = new ObjectId($_GET['showtime_id']);
        $movie = $collection->findOne(['showtimes._id' => $sId]);
        
        if ($movie) {
            foreach ($movie['showtimes'] as $s) {
                if ($s['_id'] == $sId) {
                    $selectedShowtime = $s;
                    break;
                }
            }
        }
    } elseif (isset($_GET['id'])) {
        // If user just clicked from Home (Screen 1 - Select Time)
        $movie = $collection->findOne(['_id' => new ObjectId($_GET['id'])]);
    } else {
        header("Location: home.php"); exit();
    }
} catch (Exception $e) {
    echo "Error: Invalid ID format."; exit();
}

if (!$movie) { echo "Movie not found."; exit(); }

$movieTitle = isset($movie['name']) ? $movie['name'] : (isset($movie['title']) ? $movie['title'] : 'Unknown Movie');

// =========================================================
// SCREEN 1: SELECT TIME (User hasn't chosen time yet)
// =========================================================
if (!isset($_GET['showtime_id']) || !$selectedShowtime) {
    $allShowtimes = isset($movie['showtimes']) ? (array)$movie['showtimes'] : [];
    
    // Filter past times
    $now = new DateTime(); 
    $futureShows = [];
    foreach ($allShowtimes as $s) {
        if(isset($s['datetime'])){
            $sTime = new DateTime($s['datetime']);
            if ($sTime > $now) {
                $futureShows[] = $s;
            }
        }
    }
    
    // Sort times
    usort($futureShows, function($a, $b) { return strcmp($a['datetime'], $b['datetime']); });

    // Group by Date
    $groupedShows = [];
    foreach ($futureShows as $s) {
        $dateKey = (new DateTime($s['datetime']))->format('Y-m-d');
        $groupedShows[$dateKey][] = $s;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Select Time - <?php echo $movieTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #e50914;
            --dark-bg: #0a0a0a;
            --card-bg: #161616;
            --text-main: #fff;
            --text-sub: #aaa;
        }

        body { 
            background: var(--dark-bg); 
            color: var(--text-main); 
            font-family: 'Montserrat', sans-serif; 
            padding: 0; margin: 0; 
            min-height: 100vh;
        }
        
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            padding: 40px 20px;
            animation: fadeIn 0.8s ease-out;
        }

        /* Header */
        h1 { 
            color: white; 
            text-align: center; 
            text-transform: uppercase; 
            font-size: 2rem; 
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: 2px;
            text-shadow: 0 0 20px rgba(229, 9, 20, 0.5);
        }
        
        .sub-header {
            text-align: center; 
            color: var(--text-sub);
            margin-bottom: 40px;
            font-weight: 400;
        }

        /* Date Section */
        .date-section { margin-bottom: 40px; }
        .date-header { 
            border-bottom: 1px solid rgba(255,255,255,0.1); 
            padding-bottom: 15px; 
            margin-bottom: 20px; 
            color: var(--primary); 
            font-size: 1.1rem; 
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex; align-items: center; gap: 10px;
        }
        .date-header i { color: white; }

        /* Time Grid */
        .time-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); 
            gap: 15px; 
        }

        /* Buttons */
        .time-btn { 
            background: var(--card-bg); 
            border: 1px solid rgba(255,255,255,0.1); 
            color: white; 
            padding: 20px 15px; 
            border-radius: 12px; 
            text-decoration: none; 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative; 
            display: flex; 
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .time-btn:hover { 
            background: rgba(229, 9, 20, 0.1); 
            transform: translateY(-5px); 
            border-color: var(--primary); 
            box-shadow: 0 10px 20px rgba(0,0,0,0.5), 0 0 15px rgba(229, 9, 20, 0.3);
        }

        .t-time { font-size: 1.4rem; font-weight: 700; display: block; margin-bottom: 5px; }
        .t-hall { font-size: 0.75rem; color: var(--text-sub); text-transform: uppercase; letter-spacing: 1px; }
        .t-price { font-size: 0.85rem; color: #46d369; margin-top: 8px; font-weight: 600; }

        /* Badges */
        .hall-badge { 
            position: absolute; top: 0; right: 0; left: 0;
            font-size: 0.6rem; padding: 4px; 
            text-align: center;
            font-weight: 800; letter-spacing: 1px;
        }
        .vip { background: linear-gradient(90deg, #FFD700, #FFA500); color: black; }
        .imax { background: linear-gradient(90deg, #007bff, #00c6ff); color: white; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Mobile Adjustments */
        @media (max-width: 600px) {
            .time-grid { grid-template-columns: repeat(2, 1fr); }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $movieTitle; ?></h1>
        <div class="sub-header">Select Show Time</div>
        
        <?php if(empty($groupedShows)): ?>
            <div style="text-align:center; margin-top:80px; color:#555; padding: 40px; border: 1px dashed #333; border-radius: 10px;">
                <i class="far fa-clock" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                <h3>No upcoming showtimes</h3>
                <a href="home.php" style="color:var(--primary); text-decoration: none; font-weight: bold;">Back to Home</a>
            </div>
        <?php endif; ?>

        <?php foreach ($groupedShows as $date => $shows): 
            $dateObj = new DateTime($date);
            $label = $dateObj->format('D, d M Y');
        ?>
        <div class="date-section">
            <div class="date-header"><i class="far fa-calendar-alt"></i> <?php echo $label; ?></div>
            <div class="time-grid">
                <?php foreach ($shows as $s): 
                    $timeObj = new DateTime($s['datetime']);
                    
                    // --- PRICE LOGIC ---
                    $hallType = 'std';
                    if (stripos($s['hall'], 'IMAX') !== false) $hallType = 'imax';
                    if (stripos($s['hall'], 'VIP') !== false) $hallType = 'vip';

                    $displayPrice = $s['price']; 
                    if ($hallType == 'vip') { $displayPrice += 30; } 
                    elseif ($hallType == 'imax') { $displayPrice += 15; }
                ?>
                <a href="booking.php?showtime_id=<?php echo $s['_id']; ?>" class="time-btn">
                    <?php if($hallType != 'std') echo "<div class='hall-badge $hallType'>".strtoupper($hallType)."</div>"; ?>
                    <span class="t-time"><?php echo $timeObj->format('h:i A'); ?></span>
                    <span class="t-hall"><?php echo $s['hall']; ?></span>
                    <span class="t-price">RM <?php echo number_format($displayPrice, 2); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
<?php exit(); } 

// =========================================================
// SCREEN 2: SELECT SEAT (User has chosen a time)
// =========================================================

$timeObj = new DateTime($selectedShowtime['datetime']);
$displayDateTime = $timeObj->format('d M Y, h:i A');
$hallName = $selectedShowtime['hall'];

// --- TICKET PRICE LOGIC (SCREEN 2) ---
$basePrice = $selectedShowtime['price']; 
$extraCharge = 0;
$chargeLabel = "";

if (stripos($hallName, 'VIP') !== false) { 
    $extraCharge = 30; 
    $chargeLabel = "(VIP)";
} elseif (stripos($hallName, 'IMAX') !== false) { 
    $extraCharge = 15; 
    $chargeLabel = "(IMAX)";
}

$finalPrice = $basePrice + $extraCharge;

// CONFIG SEAT LAYOUT
if (stripos($hallName, 'VIP') !== false) { 
    $rows = 5; $cols = 8; $gapIndex = 4; // VIP
    $seatSize = '45px';
} elseif (stripos($hallName, 'IMAX') !== false) { 
    $rows = 12; $cols = 14; $gapIndex = 7; // IMAX
    $seatSize = '30px'; 
} else { 
    $rows = 8; $cols = 10; $gapIndex = 5; // Standard
    $seatSize = '34px';
}

// FETCH BOOKED SEATS
$bookedSeats = [];
$currentShowtimeId = (string)$selectedShowtime['_id'];

$existingBookings = $db->bookings->find([
    'showtime_id' => $currentShowtimeId 
]);

foreach ($existingBookings as $b) {
    if (isset($b['seats'])) {
        $bookedSeats = array_merge($bookedSeats, (array)$b['seats']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Select Seats - <?php echo $movieTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #e50914;
            --dark-bg: #0a0a0a;
            --seat-avail: #3a3a3a;
            --seat-hover: #666;
            --seat-selected: #e50914;
            --glass-bar: rgba(20, 20, 20, 0.9);
        }

        body { 
            background-color: var(--dark-bg); 
            background-image: radial-gradient(circle at 50% 0%, #2a0a0a 0%, #0a0a0a 60%);
            color: white; 
            font-family: 'Montserrat', sans-serif; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            min-height: 100vh; 
            margin: 0;
            overflow-x: hidden; 
        }
        
        .header-info {
            text-align: center;
            margin-top: 30px;
            padding: 0 20px;
            z-index: 10;
        }
        
        .header-info h2 {
            margin: 0;
            text-transform: uppercase; 
            font-size: 1.4rem; 
            font-weight: 800;
            letter-spacing: 1px;
            text-shadow: 0 4px 10px rgba(0,0,0,0.5);
        }

        .meta-details {
            display: flex; gap: 15px; justify-content: center;
            font-size: 0.85rem; color: #ccc; margin-top: 10px;
        }
        .meta-details span { display: flex; align-items: center; gap: 5px; }
        .meta-details i { color: var(--primary); }

        /* --- THE SCREEN --- */
        .screen-container {
            perspective: 800px;
            margin: 30px auto 40px;
            width: 80%;
            max-width: 500px;
            display: flex; justify-content: center;
        }
        
        .screen { 
            background: linear-gradient(to bottom, rgba(255,255,255,0.8), rgba(255,255,255,0)); 
            height: 60px; 
            width: 100%; 
            transform: rotateX(-20deg) scale(0.9); 
            box-shadow: 0 30px 50px rgba(255,255,255,0.15); 
            border-radius: 12px; 
            opacity: 0.8;
            position: relative;
        }
        .screen::after {
            content: "SCREEN";
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            color: black; font-weight: 800; letter-spacing: 5px; font-size: 0.8rem;
            opacity: 0.5;
        }

        /* --- LEGEND --- */
        .legend {
            display: flex; gap: 20px; margin-bottom: 20px; 
            font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 1px;
            justify-content: center;
        }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        .dot { width: 12px; height: 12px; border-radius: 4px; }
        
        /* --- SEATS --- */
        .cinema-hall { 
            display: flex; flex-direction: column; gap: 8px; align-items: center; 
            padding-bottom: 140px; width: 100%; overflow-x: auto; 
            padding-left: 20px; padding-right: 20px; box-sizing: border-box;
            mask-image: linear-gradient(to bottom, black 90%, transparent 100%);
        }

        .row { display: flex; gap: 8px; width: max-content; margin: 0 auto; }
        .row-label { 
            width: 20px; display: flex; align-items: center; justify-content: center; 
            color: #555; font-size: 0.7rem; font-weight: bold;
        }

        .seat { 
            width: <?php echo $seatSize; ?>; height: <?php echo $seatSize; ?>; 
            background: var(--seat-avail); 
            border-radius: 8px 8px 4px 4px; /* Chair shape */
            cursor: pointer; display: flex; align-items: center; justify-content: center; 
            font-size: 0.65rem; color: rgba(255,255,255,0.3); font-weight: 600;
            transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 0 4px 0 #222; /* 3D effect */
        }
        
        .seat:hover { background: var(--seat-hover); transform: translateY(-2px); color: white; }
        
        .seat.selected { 
            background: var(--seat-selected); 
            color: white; 
            box-shadow: 0 0 15px rgba(229, 9, 20, 0.6), 0 4px 0 #900;
            transform: translateY(-4px);
            border: 1px solid rgba(255,255,255,0.4);
        }
        
        .seat.occupied { 
            background: #1a1a1a; 
            color: #333; cursor: not-allowed; pointer-events: none; 
            box-shadow: none; border: 1px solid #333;
        }
        .seat.occupied::after { content: 'X'; font-size: 10px; }

        .aisle-gap { width: 30px; flex-shrink: 0; }
        
        /* --- BOTTOM BAR --- */
        .booking-bar { 
            position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
            width: 90%; max-width: 600px;
            background: var(--glass-bar); 
            backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px);
            padding: 15px 25px; 
            border-radius: 50px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: space-between; align-items: center; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.6);
            z-index: 100;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp { from { transform: translate(-50%, 100%); } to { transform: translate(-50%, 0); } }

        .info-col { display: flex; flex-direction: column; justify-content: center; }
        .seats-text { color: #888; font-size: 0.75rem; margin-bottom: 2px; }
        .selected-seats-list { color: white; font-weight: 700; font-size: 0.9rem; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .price-tag { font-size: 1.2rem; font-weight: 800; color: #46d369; margin-left: 15px; margin-right: auto; }

        .btn-pay { 
            background: white; color: var(--primary); 
            border: none; padding: 12px 30px; 
            font-weight: 800; border-radius: 30px; 
            cursor: pointer; font-size: 0.9rem; text-transform: uppercase;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(255,255,255,0.2);
        }
        .btn-pay:disabled { background: #333; color: #555; cursor: not-allowed; box-shadow: none; }
        .btn-pay:hover:not(:disabled) { transform: scale(1.05); box-shadow: 0 8px 20px rgba(255,255,255,0.4); }

        /* Scrollbar */
        .cinema-hall::-webkit-scrollbar { height: 4px; }
        .cinema-hall::-webkit-scrollbar-thumb { background: #444; border-radius: 2px; }
    </style>
</head>
<body>

    <div class="header-info">
        <h2><?php echo $movieTitle; ?></h2>
        <div class="meta-details">
            <span><i class="far fa-calendar"></i> <?php echo $displayDateTime; ?></span>
            <span><i class="fas fa-couch"></i> <?php echo $hallName; ?> <?php echo $extraCharge > 0 ? $chargeLabel : ''; ?></span>
        </div>
    </div>
    
    <div class="screen-container">
        <div class="screen"></div>
    </div>
    
    <div class="legend">
        <div class="legend-item"><div class="dot" style="background:var(--seat-avail)"></div> Available</div>
        <div class="legend-item"><div class="dot" style="background:var(--seat-selected); box-shadow:0 0 5px red;"></div> Selected</div>
        <div class="legend-item"><div class="dot" style="background:#1a1a1a; border:1px solid #333"></div> Sold</div>
    </div>

    <div class="cinema-hall">
        <?php 
        for ($r = 0; $r < $rows; $r++) {
            $rowLetter = chr(65 + $r); // A, B, C...
            echo "<div class='row'>"; 
            echo "<div class='row-label'>$rowLetter</div>"; 
            for ($c = 1; $c <= $cols; $c++) {
                if ($c == $gapIndex + 1) echo "<div class='aisle-gap'></div>";
                
                $seatLabel = $rowLetter . $c;
                $isOccupied = in_array($seatLabel, $bookedSeats) ? 'occupied' : '';
                
                echo "<div class='seat $isOccupied' data-seat='$seatLabel'>$c</div>";
            }
            echo "</div>";
        }
        ?>
    </div>

    <form action="payment.php" method="POST">
        <input type="hidden" name="showtime_id" value="<?php echo $currentShowtimeId; ?>">
        <input type="hidden" name="movie_name" value="<?php echo $movieTitle; ?>">
        <input type="hidden" name="hall_name" value="<?php echo $hallName; ?>">
        <input type="hidden" name="showtime" value="<?php echo $selectedShowtime['datetime']; ?>">
        
        <input type="hidden" name="selected_seats" id="inputSeats">
        <input type="hidden" name="total_price" id="inputPrice">

        <div class="booking-bar">
            <div class="info-col">
                <div class="seats-text">Selected Seats</div>
                <div class="selected-seats-list" id="seatList">-</div>
            </div>
            
            <div class="price-tag">RM <span id="totalDisplay">0.00</span></div>
            
            <button type="submit" class="btn-pay" id="payBtn" disabled>Book <i class="fas fa-arrow-right"></i></button>
        </div>
    </form>

<script>
    const hall = document.querySelector('.cinema-hall');
    const seatListSpan = document.getElementById('seatList');
    const totalDisplay = document.getElementById('totalDisplay');
    const inputSeats = document.getElementById('inputSeats');
    const inputPrice = document.getElementById('inputPrice');
    const payBtn = document.getElementById('payBtn');
    
    // Use the final price calculated by PHP
    const pricePerTicket = <?php echo $finalPrice; ?>;

    hall.addEventListener('click', (e) => {
        if (e.target.classList.contains('seat') && !e.target.classList.contains('occupied')) {
            e.target.classList.toggle('selected');
            updateSelection();
        }
    });

    function updateSelection() {
        const selected = document.querySelectorAll('.seat.selected');
        const seatsArr = [...selected].map(s => s.getAttribute('data-seat'));
        
        // Update Seat List Text
        if(seatsArr.length > 0) {
            seatListSpan.innerText = seatsArr.join(", ");
        } else {
            seatListSpan.innerText = "-";
        }
        
        // Calc Price
        const total = seatsArr.length * pricePerTicket;
        totalDisplay.innerText = total.toFixed(2);
        
        // Update Hidden Inputs
        inputSeats.value = seatsArr.join(",");
        inputPrice.value = total;
        
        // Enable/Disable Button
        if (seatsArr.length > 0) {
            payBtn.disabled = false;
        } else {
            payBtn.disabled = true;
        }
    }
</script>
    <?php include 'footer.php'; ?>
</body>

</html>