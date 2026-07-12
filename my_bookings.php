<?php
session_start();

// 1. CHECK LOGIN DULU (Paling Atas)
if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

require 'vendor/autoload.php';

// 2. SETUP DATABASE
$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$db = $client->misacinema_db;
$collection = $db->bookings;
$movieCollection = $db->shows;

// 3. HELPER FUNCTION
function safeStr($input) {
    if (is_null($input)) return '-';
    if (is_string($input) || is_numeric($input)) return htmlspecialchars((string)$input);
    $arr = (array)$input;
    if (isset($arr['fullname'])) return htmlspecialchars($arr['fullname']);
    if (isset($arr['name'])) return htmlspecialchars($arr['name']);
    return '-';
}

// 4. GET USER DATA (Support Array & Object)
$userFullname = '';
$userEmail = '';

// Tukar BSONDocument jadi Array supaya senang baca
$userData = (array)$_SESSION['user'];

$userFullname = $userData['fullname'] ?? $userData['username'] ?? 'User';
$userEmail = $userData['email'] ?? '';

// 5. QUERY BOOKINGS
$filter = [
    '$or' => [
        ['customer_name' => $userFullname],
        ['customer_name.fullname' => $userFullname],
        ['email' => $userEmail],
        ['customer_name.email' => $userEmail]
    ]
];

$myBookings = $collection->find($filter, ['sort' => ['_id' => -1]]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Bookings - Misa Cinema</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- GOD MODE CSS --- */
        :root {
            --primary: #e50914;
            --primary-glow: rgba(229, 9, 20, 0.5);
            --dark-bg: #0a0a0a;
            --card-bg: rgba(22, 22, 22, 0.85);
            --text-main: #ffffff;
            --text-sub: #b3b3b3;
            --glass-border: 1px solid rgba(255, 255, 255, 0.1);
        }

        * { box-sizing: border-box; }

        body {
            background-color: var(--dark-bg);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(229, 9, 20, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(20, 20, 100, 0.15) 0%, transparent 40%);
            color: var(--text-main);
            font-family: 'Montserrat', sans-serif;
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
        }

        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            animation: fadeIn 0.8s ease-out;
        }

        /* --- HEADER --- */
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 40px; 
            padding-bottom: 20px; 
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .header h1 { 
            color: #fff; 
            margin: 0; 
            font-size: 2.2rem; 
            font-weight: 800;
            text-transform: uppercase; 
            letter-spacing: 2px; 
            text-shadow: 0 0 20px rgba(229, 9, 20, 0.6); /* Neon Glow */
        }
        .header h1 i { color: var(--primary); margin-right: 10px; }
        
        .btn-back { 
            color: var(--text-sub); 
            text-decoration: none; 
            font-weight: 600; 
            padding: 10px 20px; 
            border-radius: 50px; 
            background: rgba(255,255,255,0.05);
            border: var(--glass-border);
            transition: 0.3s; 
            font-size: 0.85rem; 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-back:hover { 
            background: rgba(255,255,255,0.15); 
            color: white; 
            box-shadow: 0 0 15px rgba(255,255,255,0.1);
        }

        /* --- CARD DESIGN --- */
        .booking-card { 
            background: var(--card-bg); 
            backdrop-filter: blur(10px);
            border: var(--glass-border); 
            border-radius: 16px; 
            margin-bottom: 25px; 
            display: flex; 
            overflow: hidden; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            position: relative; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); 
        }

        .booking-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 4px; height: 100%;
            background: var(--primary);
            box-shadow: 0 0 15px var(--primary);
            opacity: 0.7;
        }

        .booking-card:hover { 
            transform: translateY(-5px) scale(1.01); 
            border-color: rgba(255,255,255,0.3);
            box-shadow: 0 15px 40px rgba(229, 9, 20, 0.15);
        }

        .poster-img { 
            width: 160px; 
            background-color: #111; 
            background-size: cover; 
            background-position: center; 
            flex-shrink: 0; 
            position: relative;
        }
        
        /* Gradient overlay on poster */
        .poster-img::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to right, rgba(0,0,0,0) 0%, rgba(22,22,22,1) 100%);
        }

        .card-details { 
            padding: 25px; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            z-index: 2;
        }

        .movie-title { 
            font-size: 1.6rem; 
            font-weight: 800; 
            color: white; 
            margin-bottom: 12px; 
            line-height: 1.1; 
            text-transform: uppercase;
        }

        .meta-info { 
            color: #ccc; 
            font-size: 0.95rem; 
            margin-bottom: 8px; 
            display: flex; 
            align-items: center; 
            font-weight: 500;
        }
        .meta-info i { 
            color: var(--primary); 
            margin-right: 12px; 
            width: 20px; 
            text-align: center; 
            filter: drop-shadow(0 0 5px var(--primary));
        }

        /* --- SEATS BADGE --- */
        .seats-container { margin-top: 20px; }
        .seats-badge { 
            background: rgba(255,255,255,0.1); 
            color: #fff; 
            padding: 6px 12px; 
            border-radius: 6px; 
            font-size: 0.8rem; 
            margin-right: 6px; 
            margin-bottom: 6px; 
            font-weight: 700; 
            display: inline-block; 
            border: 1px solid rgba(255,255,255,0.1);
            transition: 0.3s;
        }
        .booking-card:hover .seats-badge {
            background: rgba(229, 9, 20, 0.2);
            border-color: var(--primary);
        }

        /* --- ACTION SECTION --- */
        .action-section { 
            background: rgba(0,0,0,0.3); 
            padding: 25px; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center; 
            min-width: 200px; 
            border-left: 1px solid rgba(255,255,255,0.05); 
            backdrop-filter: blur(5px);
        }

        .total-price { 
            color: #fff; 
            font-size: 1.8rem; 
            font-weight: 800; 
            margin-bottom: 5px; 
            text-shadow: 0 0 10px rgba(255,255,255,0.3);
        }
        
        .status { 
            color: #46d369; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 2px; 
            margin-bottom: 20px; 
            font-weight: 700;
            display: flex; align-items: center; gap: 5px;
        }

        .btn-ticket { 
            background: linear-gradient(135deg, var(--primary) 0%, #b20710 100%); 
            color: white; 
            padding: 12px 25px; 
            text-decoration: none; 
            border-radius: 50px; 
            font-size: 0.85rem; 
            font-weight: 700; 
            transition: all 0.3s ease; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.4);
            border: 1px solid transparent;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-ticket:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(229, 9, 20, 0.6);
            background: white;
            color: var(--primary);
        }

        /* --- ANIMATION --- */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- RESPONSIVE --- */
        @media (max-width: 768px) {
            body { padding: 20px 15px; }
            .header { flex-direction: row; margin-bottom: 30px; }
            .header h1 { font-size: 1.5rem; }
            
            .booking-card { flex-direction: column; }
            .booking-card::before { width: 100%; height: 4px; top: 0; left: 0; bottom: auto; }
            
            .poster-img { width: 100%; height: 200px; background-position: center 20%; }
            .poster-img::after { background: linear-gradient(to bottom, rgba(0,0,0,0) 0%, var(--card-bg) 100%); }
            
            .card-details { padding: 20px; }
            
            .action-section { 
                border-left: none; 
                border-top: 1px solid rgba(255,255,255,0.1); 
                flex-direction: row; 
                justify-content: space-between; 
                padding: 20px; 
                background: rgba(0,0,0,0.5);
            }
            .status { display: none; }
            .total-price { margin-bottom: 0; font-size: 1.4rem; }
            .btn-ticket { padding: 10px 20px; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1><i class="fas fa-ticket-alt"></i> My History</h1>
        <a href="home.php" class="btn-back"><i class="fas fa-arrow-left"></i> Home</a>
    </div>

    <?php 
    $count = 0; 
    foreach ($myBookings as $booking): 
        $count++;
        $rawMovieName = $booking['movie_name'] ?? 'Unknown Movie';
        $movieName = safeStr($rawMovieName);
        $hallName = safeStr($booking['hall_name'] ?? 'Hall -');
        
        // Cari Poster Movie
        $posterUrl = 'assets/img/poster_placeholder.jpg'; // Default kalau tak jumpa
        $movieData = $movieCollection->findOne(['name' => $rawMovieName]);
        if ($movieData && isset($movieData['image']) && !empty($movieData['image'])) {
            $posterUrl = 'assets/img/' . $movieData['image']; 
        }

        $dateStr = '-';
        if (isset($booking['booking_date'])) $dateStr = date('d M Y, h:i A', strtotime($booking['booking_date']));
        elseif (isset($booking['showtime'])) $dateStr = date('d M Y, h:i A', strtotime($booking['showtime']));

        $price = isset($booking['total_price']) ? number_format((float)$booking['total_price'], 2) : '0.00';
        $seats = $booking['seats'] ?? [];
        $bookingId = (string)$booking['_id']; 
    ?>
    
    <div class="booking-card">
        <div class="poster-img" style="background-image: url('<?php echo $posterUrl; ?>');"></div>
        <div class="card-details">
            <div class="movie-title"><?php echo $movieName; ?></div>
            <div class="meta-info"><i class="far fa-calendar-alt"></i> <?php echo $dateStr; ?></div>
            <div class="meta-info"><i class="fas fa-couch"></i> <?php echo $hallName; ?></div>
            <div class="seats-container">
                <?php 
                $seatArr = (array)$seats;
                if (!empty($seatArr)) {
                    foreach($seatArr as $s) echo '<span class="seats-badge">' . safeStr($s) . '</span>';
                } else {
                    echo '<span style="color:#666; font-size:0.8rem;">No Seats</span>';
                }
                ?>
            </div>
        </div>
        <div class="action-section">
            <div style="text-align:center; width:100%;">
                <div class="total-price">RM <?php echo $price; ?></div>
                <div class="status" style="justify-content:center;"><i class="fas fa-check-circle"></i> Paid</div>
            </div>
            <a href="receipt.php?id=<?php echo $bookingId; ?>" target="_blank" class="btn-ticket">
                View Ticket <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($count == 0): ?>
        <div style="text-align:center; padding:100px 20px; color:#555; background: rgba(255,255,255,0.02); border-radius: 20px; border: 1px dashed rgba(255,255,255,0.1);">
            <div style="background: rgba(229,9,20,0.1); width: 100px; height: 100px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto;">
                <i class="fas fa-film" style="font-size:3rem; color: var(--primary);"></i>
            </div>
            <h3 style="color: white; font-size: 1.5rem; margin-bottom: 10px;">No Bookings Found</h3>
            <p style="color: #888; max-width: 400px; margin: 0 auto 30px auto;">It looks like you haven't watched any movies lately. Check out what's showing now!</p>
            <a href="home.php" class="btn-ticket" style="display:inline-flex; padding: 15px 40px;">Book Now</a>
        </div>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>
</body>
</html>