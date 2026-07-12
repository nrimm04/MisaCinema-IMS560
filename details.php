<?php
session_start();
require 'vendor/autoload.php';

// 1. DATABASE CONNECTION
$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");
$collection = $client->misacinema_db->shows;

// 2. GET MOVIE DATA
if (!isset($_GET['id'])) {
    header("Location: home.php"); // Redirect jika tiada ID
    exit();
}

try {
    $id = $_GET['id'];
    $movie = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($id)]);
    
    if (!$movie) {
        echo "Movie not found."; exit();
    }
} catch (Exception $e) {
    echo "Invalid ID."; exit();
}

// 3. PRE-FILL DATA IF LOGGED IN
$prefillName = '';
if (isset($_SESSION['user'])) {
    if (is_array($_SESSION['user'])) {
        $prefillName = $_SESSION['user']['fullname'] ?? $_SESSION['user']['username'] ?? '';
    } elseif (is_object($_SESSION['user'])) {
        $prefillName = $_SESSION['user']->fullname ?? $_SESSION['user']->username ?? '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    
    <title>Book: <?php echo htmlspecialchars($movie['name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #e50914;
            --dark: #0f0f0f;
            --glass: rgba(20, 20, 20, 0.85);
            --border: rgba(255, 255, 255, 0.1);
        }

        body { 
            background-color: #000; 
            color: white; 
            font-family: 'Montserrat', sans-serif; 
            margin: 0; 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column;
            overflow-x: hidden;
        }

        /* --- IMMERSIVE BACKGROUND --- */
        .backdrop {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: url('assets/img/<?php echo $movie['image'] ?? 'default_poster.jpg'; ?>') no-repeat center center/cover;
            filter: blur(20px) brightness(0.4);
            z-index: -1;
            transform: scale(1.1); /* Prevent blur edges */
        }

        header { 
            padding: 20px 40px; 
            z-index: 10;
        }
        .logo { color: var(--primary); font-size: 1.5rem; font-weight: 900; text-transform: uppercase; text-decoration: none; letter-spacing: 2px; text-shadow: 0 0 20px rgba(229, 9, 20, 0.6); }

        /* --- MAIN CONTENT --- */
        .main-content { 
            flex: 1; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            padding: 40px 20px;
        }

        .glass-card { 
            display: flex; 
            background: var(--glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 20px; 
            overflow: hidden; 
            max-width: 1000px; 
            width: 100%; 
            box-shadow: 0 30px 60px rgba(0,0,0,0.8); 
            animation: slideUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- LEFT: POSTER --- */
        .poster-side { 
            width: 40%; 
            position: relative; 
            overflow: hidden;
        }
        .poster-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            transition: transform 0.5s ease;
        }
        .glass-card:hover .poster-img { transform: scale(1.05); }

        /* --- RIGHT: INFO & FORM --- */
        .info-side { 
            width: 60%; 
            padding: 50px; 
            display: flex; 
            flex-direction: column; 
            justify-content: center;
        }

        h2 { 
            color: #fff; margin: 0 0 15px 0; 
            font-weight: 800; font-size: 2.5rem; line-height: 1.1; 
            text-transform: uppercase;
        }

        .tags { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .tag { 
            background: rgba(255,255,255,0.1); 
            padding: 6px 14px; 
            border-radius: 50px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            border: 1px solid rgba(255,255,255,0.1);
        }
        .tag i { margin-right: 5px; color: var(--primary); }

        .desc { 
            line-height: 1.7; color: #ccc; margin-bottom: 30px; 
            font-size: 0.95rem; border-left: 3px solid var(--primary); 
            padding-left: 15px;
        }

        .price-area { margin-bottom: 30px; display: flex; align-items: baseline; gap: 10px; }
        .price-label { font-size: 0.9rem; color: #888; text-transform: uppercase; }
        .price-val { font-size: 2.2rem; font-weight: 800; color: white; }
        .price-val small { font-size: 1rem; color: var(--primary); margin-right: 2px; }

        /* --- FORM --- */
        .form-grid { display: grid; gap: 20px; }
        
        .input-group { position: relative; }
        .input-group i { position: absolute; top: 50%; left: 15px; transform: translateY(-50%); color: #666; transition: 0.3s; }
        
        input { 
            width: 100%; padding: 18px 18px 18px 45px; 
            background: rgba(0,0,0,0.3); 
            border: 1px solid #333; 
            color: white; border-radius: 12px; 
            font-size: 1rem; outline: none; transition: 0.3s; 
            box-sizing: border-box; font-family: 'Montserrat', sans-serif;
        }
        input:focus { border-color: var(--primary); background: rgba(0,0,0,0.5); }
        input:focus + i { color: var(--primary); }

        .btn-confirm { 
            background: var(--primary); color: white; width: 100%; padding: 20px; 
            border: none; font-size: 1rem; font-weight: 800; text-transform: uppercase; 
            cursor: pointer; border-radius: 12px; transition: 0.3s; 
            letter-spacing: 1px; margin-top: 10px;
            box-shadow: 0 10px 20px rgba(229, 9, 20, 0.3);
        }
        .btn-confirm:hover { 
            background: #ff0f1f; 
            transform: translateY(-3px); 
            box-shadow: 0 15px 30px rgba(229, 9, 20, 0.5); 
        }

        .btn-back {
            display: inline-block; text-align: center; margin-top: 15px; 
            color: #888; text-decoration: none; font-size: 0.85rem; font-weight: 600;
            transition: 0.3s;
        }
        .btn-back:hover { color: white; }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 900px) {
            .glass-card { flex-direction: column; max-width: 500px; }
            .poster-side { width: 100%; height: 250px; }
            .poster-img { object-position: center top; }
            .info-side { width: 100%; padding: 30px; box-sizing: border-box; }
            h2 { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <div class="backdrop"></div>

    <header>
        <a href="home.php" class="logo"><i class="fas fa-film"></i> Misa Cinema</a>
    </header>

    <div class="main-content">
        <div class="glass-card">
            <div class="poster-side">
                <?php $imgFile = isset($movie['image']) && $movie['image'] ? $movie['image'] : 'default_poster.jpg'; ?>
                <img src="assets/img/<?php echo $imgFile; ?>" class="poster-img" alt="Poster">
            </div>
            
            <div class="info-side">
                <h2><?php echo htmlspecialchars($movie['name']); ?></h2>
                
                <div class="tags">
                    <div class="tag"><i class="far fa-clock"></i> <?php echo $movie['duration']; ?></div>
                    <div class="tag"><i class="fas fa-theater-masks"></i> <?php echo isset($movie['genre']) ? $movie['genre'] : 'Movie'; ?></div>
                    <div class="tag"><i class="fas fa-star"></i> <?php echo isset($movie['rating']) ? $movie['rating'] : '8.5'; ?></div>
                </div>
                
                <div class="desc">
                    <?php echo isset($movie['description']) ? substr($movie['description'], 0, 180) . '...' : 'Experience the magic of cinema with this masterpiece.'; ?>
                </div>
                
                <div class="price-area">
                    <div class="price-label">Ticket Price:</div>
                    <div class="price-val"><small>RM</small><?php echo number_format($movie['price'], 2); ?></div>
                </div>

                <form action="book.php" method="POST">
                    <input type="hidden" name="movie_name" value="<?php echo htmlspecialchars($movie['name']); ?>">
                    <input type="hidden" name="price" value="<?php echo $movie['price']; ?>">
                    <input type="hidden" name="movie_id" value="<?php echo $id; ?>">
                    
                    <div class="form-grid">
                        <div class="input-group">
                            <input type="text" name="customer_name" required placeholder="Full Name" value="<?php echo htmlspecialchars($prefillName); ?>">
                            <i class="fas fa-user"></i>
                        </div>
                        
                        <div class="input-group">
                            <input type="tel" name="customer_phone" required placeholder="Phone Number (e.g. 012-3456789)">
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-confirm">
                        Confirm Booking <i class="fas fa-arrow-right" style="margin-left:8px;"></i>
                    </button>
                    <a href="home.php" class="btn-back">Cancel & Return</a>
                </form>
            </div>
        </div>
    </div>

<?php include 'footer.php'; ?>
</body>
</html>