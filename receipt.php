<?php
// --- 1. SETTINGS & ERROR REPORTING ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// --- 2. CHECK VENDOR & LIBRARY ---
if (!file_exists('vendor/autoload.php')) {
    die("Error: Folder 'vendor' tak jumpa. Sila run 'composer install'.");
}
require 'vendor/autoload.php'; 

use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONArray; 

// --- 3. DATABASE CONNECTION ---
try {
    $client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
    $db = $client->misacinema_db;
    $collection = $db->bookings;
} catch (Exception $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// --- 4. GET & VALIDATE ID ---
if (!isset($_GET['id'])) { 
    echo "<h3 style='color:white;text-align:center;'>Error: No Ticket ID provided.</h3>"; exit(); 
}

try {
    $bookingId = new ObjectId($_GET['id']);
    $booking = $collection->findOne(['_id' => $bookingId]);

    if (!$booking) { 
        echo "<h3 style='color:white;text-align:center;'>Error: Booking not found.</h3>"; exit(); 
    }

    // --- LOGIC: NAMA CUSTOMER ---
    $customerName = 'Guest';
    if (isset($booking['customer_name'])) {
        $dbName = $booking['customer_name'];
        if (is_object($dbName) || is_array($dbName)) {
            $dbName = (array)$dbName;
            $customerName = $dbName['fullname'] ?? $dbName['username'] ?? 'Valued Customer';
        } else {
            $customerName = (string)$dbName;
        }
    }

    // --- LOGIC: SEATS FIX ---
    $seatsDisplay = "-";
    if (isset($booking['seats'])) {
        $rawSeats = $booking['seats'];
        if ($rawSeats instanceof BSONArray) {
            $rawSeats = $rawSeats->getArrayCopy();
        } elseif (is_object($rawSeats)) {
            $rawSeats = (array)$rawSeats;
        }

        if (is_array($rawSeats)) {
            $seatsDisplay = implode(", ", $rawSeats);
        } else {
            $seatsDisplay = (string)$rawSeats;
        }
    }

} catch (Exception $e) {
    die("Error Processing ID: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Misa Cinema</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        :root { --primary: #e50914; --dark: #050505; --card: #151515; }
        
        body {
            background-color: var(--dark);
            background-image: radial-gradient(circle at 50% 0%, #300 0%, #000 70%);
            font-family: 'Montserrat', sans-serif;
            color: white; margin: 0; padding: 20px;
            display: flex; flex-direction: column; align-items: center; min-height: 100vh;
        }

        .ticket-box {
            background: var(--card); width: 340px; border-radius: 15px;
            overflow: hidden; position: relative;
            box-shadow: 0 0 40px rgba(229, 9, 20, 0.2);
            border: 1px solid #333; margin-bottom: 20px;
        }

        .ticket-header { background: #e50914; padding: 20px; text-align: center; }
        .logo { font-weight: 900; font-size: 1.2rem; margin: 0; letter-spacing: 1px; }
        .badge { font-size: 0.7rem; background: rgba(0,0,0,0.3); padding: 4px 10px; border-radius: 20px; font-weight: bold; margin-top: 5px; display: inline-block; }

        .ticket-content { padding: 25px; }
        .movie-name { font-size: 1.3rem; font-weight: 800; text-transform: uppercase; line-height: 1.2; margin-bottom: 5px; }
        .hall { color: #e50914; font-weight: 700; font-size: 0.9rem; margin-bottom: 20px; display: block; }

        .row { display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 0.9rem; }
        .lbl { font-size: 0.65rem; color: #888; text-transform: uppercase; display: block; }
        .val { font-weight: 600; }

        .seats-area { background: #222; padding: 10px; border-radius: 8px; text-align: center; border: 1px solid #333; margin-top: 10px; }
        .seats-val { color: #e50914; font-weight: 800; font-size: 1.1rem; }

        .dashed { border-bottom: 2px dashed #444; margin: 0 15px; }

        .ticket-footer { padding: 20px; text-align: center; background: #fff; color: #000; }
        #qrcode { display: inline-block; }
        .id-text { font-family: monospace; font-size: 0.8rem; margin-top: 10px; color: #555; }

        .btn-container { width: 340px; display: flex; flex-direction: column; gap: 10px; text-align: center; }
        .btn { padding: 15px; border-radius: 50px; font-weight: bold; text-align: center; text-decoration: none; cursor: pointer; border: none; font-size: 1rem; width: 100%; display:block; box-sizing:border-box; }
        .btn-dl { background: white; color: black; }
        .btn-dl:hover { background: #eee; transform: translateY(-2px); }
        .btn-back { background: transparent; border: 1px solid #555; color: #aaa; }
        .btn-back:hover { border-color: white; color: white; }
        
        .status-msg { font-size: 0.8rem; color: #888; margin-bottom: 5px; font-style: italic; }
    </style>
</head>
<body>

    <div class="ticket-box" id="ticketVisual">
        <div class="ticket-header">
            <div class="logo"><i class="fas fa-film"></i> MISA CINEMA</div>
            <div class="badge">CONFIRMED</div>
        </div>

        <div class="ticket-content">
            <div class="movie-name"><?php echo htmlspecialchars((string)($booking['movie_name'] ?? 'Movie')); ?></div>
            <span class="hall"><?php echo htmlspecialchars((string)($booking['hall_name'] ?? 'Hall')); ?></span>

            <div class="row">
                <div><span class="lbl">Date</span><span class="val"><?php echo date('d M Y', strtotime($booking['showtime'])); ?></span></div>
                <div style="text-align:right;"><span class="lbl">Time</span><span class="val"><?php echo date('h:i A', strtotime($booking['showtime'])); ?></span></div>
            </div>
            
            <div class="row">
                <div><span class="lbl">Guest</span><span class="val"><?php echo htmlspecialchars(substr($customerName, 0, 15)); ?></span></div>
                <div style="text-align:right;"><span class="lbl">Price</span><span class="val">RM <?php echo number_format($booking['total_price'], 2); ?></span></div>
            </div>

            <div class="seats-area">
                <span class="lbl">SEATS</span>
                <div class="seats-val"><?php echo htmlspecialchars($seatsDisplay); ?></div>
            </div>
        </div>

        <div class="dashed"></div>

        <div class="ticket-footer">
            <div id="qrcode"></div>
            <div class="id-text">ID: <?php echo substr((string)$bookingId, -8); ?></div>
        </div>
    </div>

    <div class="btn-container">
        <div>
            <div class="status-msg" id="statusMsg"><i class="fas fa-spinner fa-spin"></i> Auto-downloading in 2s...</div>
            <button onclick="downloadPDF()" class="btn btn-dl">Download Ticket</button>
        </div>
        <a href="home.php" class="btn btn-back">Back to Home</a>
    </div>

    <script>
        window.onload = function() {
            // 1. Generate QR
            new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo (string)$bookingId; ?>",
                width: 90, height: 90
            });

            // 2. AUTO DOWNLOAD LOGIC
            // Tunggu 2000ms (2 saat) baru download
            setTimeout(function() {
                downloadPDF();
                // Tukar text status
                document.getElementById('statusMsg').innerText = "Download Started!";
            }, 2000);
        };

        function downloadPDF() {
            const element = document.getElementById('ticketVisual');
            const { jsPDF } = window.jspdf;

            html2canvas(element, { scale: 2, backgroundColor: "#151515" }).then(canvas => {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                const pdfW = pdf.internal.pageSize.getWidth();
                const imgW = 80; 
                const imgH = (canvas.height * imgW) / canvas.width;
                
                pdf.setFillColor(20, 20, 20);
                pdf.rect(0, 0, pdfW, 297, 'F');
                pdf.addImage(imgData, 'PNG', (pdfW - imgW)/2, 30, imgW, imgH);
                pdf.save("MisaTicket.pdf");
            });
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>