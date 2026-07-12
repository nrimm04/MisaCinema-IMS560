<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

// Security Check
if (!isset($_SESSION['user'])) { header("Location: login.php"); exit(); }

require 'vendor/autoload.php';
use MongoDB\BSON\ObjectId;

// =========================================================
// 1. TANGKAP DATA DARI BOOKING.PHP (POST)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_seats'])) {
    
    // Simpan data dalam variable biasa untuk paparan HTML
    $movieName = $_POST['movie_name'];
    $hallName = $_POST['hall_name'];
    $showtime = $_POST['showtime'];
    $showtimeId = $_POST['showtime_id']; 
    $seatsString = $_POST['selected_seats']; 
    $seatsArray = explode(',', $seatsString);
    $totalPrice = $_POST['total_price'];

} elseif (isset($_POST['submit_payment'])) {
    // =========================================================
    // 2. PROSES PEMBAYARAN & SAVE KE DB
    // =========================================================
    
    $client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
    $bookingCollection = $client->misacinema_db->bookings;

    $finalSeats = explode(",", $_POST['final_seats']); 
    
    $insertResult = $bookingCollection->insertOne([
        'customer_name' => $_SESSION['user']['fullname'], 
        'movie_name' => $_POST['final_movie'],
        'hall_name' => $_POST['final_hall'],
        'showtime' => $_POST['final_showtime'],
        'showtime_id' => $_POST['final_showtime_id'], 
        'seats' => $finalSeats,
        'total_price' => $_POST['final_price'],
        'payment_method' => $_POST['payment_method'],
        'bank_name' => ($_POST['payment_method'] == 'Online Banking') ? $_POST['bank_name'] : 'Visa/Mastercard',
        'booking_date' => date('Y-m-d H:i:s'),
        'status' => 'confirmed'
    ]);

    if ($insertResult->getInsertedCount() > 0) {
        $newId = $insertResult->getInsertedId();
        header("Location: receipt.php?id=" . $newId);
        exit();
    } else {
        echo "<script>alert('System Error. Please try again.'); window.location.href='home.php';</script>";
    }

} else {
    header("Location: home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <title>Secure Checkout</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #e50914;
            --dark-bg: #0a0a0a;
            --glass: rgba(25, 25, 25, 0.9);
            --border: rgba(255, 255, 255, 0.1);
            --text-main: #fff;
            --text-sub: #aaa;
            --success: #46d369;
        }

        body { 
            background-color: var(--dark-bg); 
            background-image: radial-gradient(circle at 50% 10%, #1f0505 0%, #000000 70%);
            font-family: 'Montserrat', sans-serif; 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
            margin: 0; 
            color: var(--text-main); 
        }

        .page-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            animation: fadeIn 0.8s ease-out;
        }

        .payment-wrapper { 
            background-color: var(--glass); 
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            width: 100%; 
            max-width: 550px; 
            border-radius: 16px; 
            overflow: hidden; 
            border: 1px solid var(--border); 
            box-shadow: 0 20px 60px rgba(0,0,0,0.6); 
            position: relative;
        }
        
        /* Top Accent Line */
        .payment-wrapper::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--primary), #ff4d4d);
        }

        .header { 
            padding: 25px; 
            text-align: center; 
            border-bottom: 1px solid var(--border);
        }
        .header h2 { 
            margin: 0; font-size: 1.1rem; 
            text-transform: uppercase; letter-spacing: 2px; font-weight: 700;
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .header i { color: var(--success); }

        .content { padding: 30px; }

        /* --- TICKET SUMMARY --- */
        .ticket-summary {
            background: linear-gradient(135deg, #1a1a1a, #111);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        /* Perforated edge effect */
        .ticket-summary::before, .ticket-summary::after {
            content: ''; position: absolute; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px; background: var(--dark-bg); border-radius: 50%;
        }
        .ticket-summary::before { left: -8px; }
        .ticket-summary::after { right: -8px; }

        .movie-info h3 { margin: 0 0 5px 0; font-size: 1.2rem; color: white; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }
        .movie-info p { margin: 0; color: var(--text-sub); font-size: 0.8rem; line-height: 1.4; }
        .movie-info .hl { color: var(--primary); font-weight: 600; }
        
        .price-tag { 
            text-align: right; 
        }
        .price-tag .amount { font-size: 1.6rem; color: var(--success); font-weight: 800; }
        .price-tag .label { font-size: 0.7rem; color: #666; text-transform: uppercase; }

        /* --- PAYMENT METHODS --- */
        .section-title { font-size: 0.8rem; color: var(--text-sub); margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px; }

        .method-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 25px; }

        .method-card { 
            background: rgba(255,255,255,0.03); 
            padding: 20px; 
            text-align: center; 
            cursor: pointer; 
            border: 1px solid var(--border); 
            border-radius: 10px; 
            transition: all 0.3s; 
            color: #888;
        }
        .method-card:hover { background: rgba(255,255,255,0.08); color: white; }
        
        .method-card.active { 
            background: rgba(229, 9, 20, 0.1); 
            border-color: var(--primary); 
            color: white; 
            box-shadow: 0 0 15px rgba(229, 9, 20, 0.2);
        }
        .method-card i { font-size: 1.8rem; margin-bottom: 8px; display: block; }
        .method-card span { font-size: 0.85rem; font-weight: 600; }

        /* --- FORMS --- */
        .form-container { position: relative; min-height: 200px; }
        
        .form-section { 
            display: none; 
            animation: slideUp 0.4s ease-out;
        }
        .form-section.show { display: block; }
        
        label { display: block; margin-bottom: 8px; font-size: 0.8rem; color: #ccc; font-weight: 600; }
        
        .input-group { margin-bottom: 15px; }
        input, select { 
            width: 100%; padding: 16px; 
            background: #0f0f0f; 
            border: 1px solid #333; 
            color: white; border-radius: 8px; 
            box-sizing: border-box; outline: none; font-size: 1rem; font-family: 'Montserrat', sans-serif;
            transition: 0.3s;
        }
        input:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 10px rgba(229, 9, 20, 0.2); }
        
        /* Specific Card Styling */
        .card-row { display: flex; gap: 15px; }
        .card-row > div { flex: 1; }

        /* --- BUTTON --- */
        .btn-pay { 
            width: 100%; padding: 18px; 
            background: var(--primary); 
            color: white; border: none; 
            font-weight: 800; font-size: 1rem; 
            cursor: pointer; margin-top: 20px; 
            border-radius: 50px; 
            text-transform: uppercase; letter-spacing: 1px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.4);
            display: flex; justify-content: center; align-items: center; gap: 10px;
        }
        .btn-pay:hover { 
            background: #ff0f1f; 
            transform: translateY(-2px); 
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.6);
        }

        .secure-badge {
            text-align: center; margin-top: 15px; font-size: 0.7rem; color: #555; display: flex; justify-content: center; align-items: center; gap: 5px;
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 500px) {
            .content { padding: 20px; }
            .ticket-summary { flex-direction: column; align-items: flex-start; gap: 15px; }
            .price-tag { text-align: left; width: 100%; border-top: 1px dashed #333; padding-top: 10px; }
        }
    </style>
</head>
<body>

<div class="page-container">
    <div class="payment-wrapper">
        <div class="header">
            <h2><i class="fas fa-lock"></i> Secure Checkout</h2>
        </div>

        <div class="content">
            
            <div class="ticket-summary">
                <div class="movie-info">
                    <h3><?php echo htmlspecialchars($movieName); ?></h3>
                    <p class="hl"><?php echo htmlspecialchars($hallName); ?></p>
                    <p><?php echo date('D, d M Y • h:i A', strtotime($showtime)); ?></p>
                    <p style="margin-top:5px; font-size:0.75rem; color:#888;">Seats: <span style="color:white;"><?php echo htmlspecialchars($seatsString); ?></span></p>
                </div>
                <div class="price-tag">
                    <div class="label">Total Amount</div>
                    <div class="amount">RM <?php echo number_format($totalPrice, 2); ?></div>
                </div>
            </div>

            <form action="" method="POST">
                <input type="hidden" name="final_movie" value="<?php echo htmlspecialchars($movieName); ?>">
                <input type="hidden" name="final_hall" value="<?php echo htmlspecialchars($hallName); ?>">
                <input type="hidden" name="final_showtime" value="<?php echo htmlspecialchars($showtime); ?>">
                <input type="hidden" name="final_showtime_id" value="<?php echo htmlspecialchars($showtimeId); ?>"> 
                <input type="hidden" name="final_seats" value="<?php echo htmlspecialchars($seatsString); ?>">
                <input type="hidden" name="final_price" value="<?php echo htmlspecialchars($totalPrice); ?>">
                <input type="hidden" name="payment_method" id="payment_method" value="Credit Card">

                <div class="section-title">Select Payment Method</div>
                
                <div class="method-grid">
                    <div class="method-card active" onclick="selectMethod('Credit Card', this)">
                        <i class="fab fa-cc-visa"></i> 
                        <span>Card</span>
                    </div>
                    <div class="method-card" onclick="selectMethod('Online Banking', this)">
                        <i class="fas fa-university"></i> 
                        <span>FPX / Bank</span>
                    </div>
                </div>

                <div class="form-container">
                    <div id="card-inputs" class="form-section show">
                        <div class="input-group">
                            <label>Card Number</label>
                            <input type="tel" placeholder="0000 0000 0000 0000" maxlength="19" style="letter-spacing: 2px;">
                        </div>
                        <div class="card-row">
                            <div class="input-group">
                                <label>Expiry Date</label>
                                <input type="tel" placeholder="MM / YY" maxlength="5">
                            </div>
                            <div class="input-group">
                                <label>CVC / CVV</label>
                                <input type="tel" placeholder="123" maxlength="4">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Cardholder Name</label>
                            <input type="text" placeholder="AS ON CARD">
                        </div>
                    </div>

                    <div id="bank-inputs" class="form-section">
                        <div class="input-group">
                            <label>Select Your Bank</label>
                            <select name="bank_name" style="height: 55px;">
                                <option value="Maybank2u">Maybank2u</option>
                                <option value="CIMB Clicks">CIMB Clicks</option>
                                <option value="Public Bank">Public Bank</option>
                                <option value="RHB Now">RHB Now</option>
                                <option value="Bank Islam">Bank Islam</option>
                                <option value="AmBank">AmBank</option>
                                <option value="Hong Leong Connect">Hong Leong Connect</option>
                            </select>
                        </div>
                        <div style="text-align:center; color:#888; font-size:0.8rem; margin-top:20px;">
                            <i class="fas fa-info-circle"></i> You will be redirected to your bank's secure login page.
                        </div>
                    </div>
                </div>

                <button type="submit" name="submit_payment" class="btn-pay">
                    Pay RM <?php echo number_format($totalPrice, 2); ?>
                </button>
                
                <div class="secure-badge">
                    <i class="fas fa-shield-alt"></i> 256-bit SSL Encrypted Payment
                </div>

            </form>
        </div>
    </div>
</div>

<script>
    function selectMethod(method, element) {
        // Handle visual state of cards
        document.querySelectorAll('.method-card').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
        
        // Handle hidden input value
        document.getElementById('payment_method').value = method;
        
        // Handle form switching with animation
        const cardSec = document.getElementById('card-inputs');
        const bankSec = document.getElementById('bank-inputs');
        
        if (method === 'Credit Card') {
            bankSec.classList.remove('show');
            setTimeout(() => cardSec.classList.add('show'), 50); // slight delay for smooth feel
        } else {
            cardSec.classList.remove('show');
            setTimeout(() => bankSec.classList.add('show'), 50);
        }
    }
</script>

<?php include 'footer.php'; ?>
</body>
</html>