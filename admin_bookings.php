<?php
session_start();

// 1. SECURITY CHECK
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') { 
    header("Location: login.php"); 
    exit(); 
}

// 2. LOAD LIBRARIES & SETUP DATABASE
require 'vendor/autoload.php';

// PENTING: Baris ini cuma boleh ada SEKALI sahaja
use MongoDB\BSON\ObjectId;

$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$bookingCollection = $client->misacinema_db->bookings;
$movieCollection = $client->misacinema_db->shows;

// 3. HELPER FUNCTION: safeStr (YANG DAH DIUPDATE UNTUK "fullname")
function safeStr($input) {
    if (is_null($input)) return '-';
    // Kalau text atau nombor, paparkan terus
    if (is_string($input) || is_numeric($input)) return htmlspecialchars((string)$input);
    
    // Kalau Object/Array, cari key yang sesuai
    $arr = (array)$input;
    
    // --- FIX UTAMA: Check "fullname" dulu (ikut database awak) ---
    if (isset($arr['fullname'])) return htmlspecialchars($arr['fullname']);
    
    // Backup checks
    if (isset($arr['name'])) return htmlspecialchars($arr['name']);
    if (isset($arr['username'])) return htmlspecialchars($arr['username']);
    if (isset($arr['email'])) return htmlspecialchars($arr['email']); // Kalau tak ada nama, guna email
    
    // Untuk Seat ID
    if (isset($arr['id'])) return htmlspecialchars($arr['id']);
    if (isset($arr['seat'])) return htmlspecialchars($arr['seat']);
    
    // Last choice: return JSON string (kalau tak jumpa juga)
    return htmlspecialchars(json_encode($arr));
}

// 4. DELETE BOOKING (CANCEL)
if (isset($_POST['delete_id'])) {
    try {
        $bookingCollection->deleteOne(['_id' => new ObjectId($_POST['delete_id'])]);
        header("Location: admin_bookings.php");
        exit();
    } catch (Exception $e) { }
}

// 5. FETCH ALL BOOKINGS
$bookings = $bookingCollection->find([], ['sort' => ['_id' => -1]]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking List - Misa Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { background-color: #0b0b0b; color: white; display: flex; }
        
        .main-content { margin-left: 250px; flex: 1; padding: 40px; }
        
        .panel { background: #1a1a1a; padding: 25px; border-radius: 8px; border: 1px solid #333; }
        h3 { margin-bottom: 20px; border-bottom: 1px solid #333; padding-bottom: 15px; color: #e50914; text-transform: uppercase; letter-spacing: 1px; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        thead { background-color: #252525; }
        th { color: #aaa; font-weight: bold; text-transform: uppercase; font-size: 0.8em; letter-spacing: 1px; padding: 15px; text-align: left; }
        td { padding: 15px; border-bottom: 1px solid #2a2a2a; vertical-align: middle; color: #ddd; font-size: 0.95rem; }
        tr:hover { background-color: #222; }

        .hall-tag { background-color: #222; border: 1px solid #444; color: #fff; padding: 5px 10px; border-radius: 4px; font-size: 0.85em; display: inline-block; }
        
        .seat-tag { 
            background-color: #e50914; color: white; 
            padding: 3px 8px; border-radius: 3px; 
            font-size: 0.8em; margin-right: 4px; font-weight: bold; 
            display: inline-block; margin-bottom: 3px;
        }

        .payment-info { font-size: 0.85em; color: #888; }
        .movie-cell { display: flex; align-items: center; gap: 15px; }
        .mini-poster { width: 45px; height: 65px; background-size: cover; background-position: center; border-radius: 4px; background-color: #333; border: 1px solid #444; }
        .action-btn { color: #666; cursor: pointer; border: none; background: none; font-size: 1.2em; transition: 0.2s; }
        .action-btn:hover { color: #ff4444; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div class="panel">
            <h3><i class="fas fa-list-alt"></i> Customer Bookings</h3>
            
            <table>
                <thead>
                    <tr>
                        <th width="15%">Customer</th>
                        <th width="25%">Movie Details</th>
                        <th width="15%">Hall & Seats</th>
                        <th width="20%">Payment</th>
                        <th width="10%">Amount</th>
                        <th width="5%">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $count = 0;
                    foreach($bookings as $b): 
                        $count++;
                        
                        // --- GET CUSTOMER DATA ---
                        // Kita cuba ambil field 'customer_name' atau 'user'
                        $rawCustomer = $b['customer_name'] ?? ($b['user'] ?? 'Guest');
                        
                        // Gunakan safeStr yang baru (dia akan detect 'fullname')
                        $customerName = safeStr($rawCustomer);

                        $bookingDate = isset($b['booking_date']) ? date('d M Y', strtotime($b['booking_date'])) : 'Unknown';
                        
                        // Movie Info
                        $rawMovieName = $b['movie_name'] ?? 'Unknown Movie';
                        $movieName = safeStr($rawMovieName);

                        $hallName = safeStr($b['hall_name'] ?? '-');
                        $totalPrice = isset($b['total_price']) ? number_format((float)$b['total_price'], 2) : '0.00';
                        $payMethod = safeStr($b['payment_method'] ?? 'Cash');
                        $bankName = safeStr($b['bank_name'] ?? '-');
                        
                        // Seats (Array)
                        $seats = $b['seats'] ?? [];
                        
                        // Poster Logic
                        $imgUrl = 'assets/img/default_poster.jpg'; 
                        $duration = 'N/A';
                        
                        if (is_string($rawMovieName)) {
                            $movieDetails = $movieCollection->findOne(['name' => $rawMovieName]);
                            if ($movieDetails) {
                                if (isset($movieDetails['image']) && !empty($movieDetails['image'])) {
                                    $imgUrl = 'assets/img/' . $movieDetails['image'];
                                }
                                if (isset($movieDetails['duration'])) $duration = $movieDetails['duration'];
                            }
                        }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:bold; color: white;"><?php echo $customerName; ?></div>
                            <small style="color:#666;"><i class="far fa-clock"></i> <?php echo $bookingDate; ?></small>
                        </td>

                        <td>
                            <div class="movie-cell">
                                <div class="mini-poster" style="background-image: url('<?php echo $imgUrl; ?>');"></div>
                                <div>
                                    <span style="display:block; font-weight:bold; color:#eee;"><?php echo $movieName; ?></span>
                                    <small style="color:#aaa;">Duration: <?php echo $duration; ?></small>
                                </div>
                            </div>
                        </td>

                        <td>
                            <div style="margin-bottom: 5px;">
                                <span class="hall-tag"><?php echo $hallName; ?></span>
                            </div>
                            <div>
                                <?php 
                                // Seat Loop
                                $seatArray = (array)$seats; 
                                if (!empty($seatArray)) {
                                    foreach($seatArray as $s) { 
                                        echo "<span class='seat-tag'>" . safeStr($s) . "</span>"; 
                                    }
                                } else {
                                    echo "<span style='color:#666;'>No Seats</span>";
                                }
                                ?>
                            </div>
                        </td>

                        <td>
                            <div class="payment-info">
                                <div><i class="fas fa-credit-card"></i> <?php echo $payMethod; ?></div>
                                <?php if($bankName !== '-' && $bankName !== ''): ?>
                                    <div style="margin-top:2px; color:#aaa;"><i class="fas fa-university"></i> <?php echo $bankName; ?></div>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td style="color:#2ecc71; font-weight:bold; font-size: 1rem;">
                            RM <?php echo $totalPrice; ?>
                        </td>

                        <td style="text-align: center;">
                            <form method="POST" onsubmit="return confirm('Confirm delete this booking?');">
                                <input type="hidden" name="delete_id" value="<?php echo $b['_id']; ?>">
                                <button type="submit" class="action-btn" title="Delete Booking">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if($count == 0): ?>
                    <tr><td colspan="6" style="text-align:center; padding:50px; color:#555;">No bookings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</body>

</html>

