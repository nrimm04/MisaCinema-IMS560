<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['role'] != 'admin') { header("Location: login.php"); exit(); }

require 'vendor/autoload.php';
use MongoDB\BSON\ObjectId;

$client = new MongoDB\Client("mongodb+srv://nrimam04_db_user:admin123@cluster0.sv61lap.mongodb.net/?appName=Cluster0");// SAYA TUKAR 'shows' JADI 'movies' SUPAYA SAMA DENGAN BOOKING.PHP
$movieCollection = $client->misacinema_db->shows; 

$msg = "";
$error = "";
$editMode = false;
$movieToEdit = null;
$viewMode = false;
$movieView = null;

// --- FUNCTION TO HANDLE IMAGE UPLOAD ---
function uploadImage($file) {
    $targetDir = "assets/img/";
    
    if (!is_dir($targetDir)) { mkdir($targetDir, 0777, true); }

    if (!empty($file["name"])) {
        $fileName = time() . "_" . basename($file["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));

        $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');
        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
                return $fileName;
            }
        }
    }
    return null;
}

// 1. DELETE MOVIE
if (isset($_POST['delete_id'])) {
    $movieCollection->deleteOne(['_id' => new ObjectId($_POST['delete_id'])]);
    $msg = "Movie deleted successfully.";
}

// 2. VIEW DETAILS
if (isset($_GET['view_id'])) {
    $movieView = $movieCollection->findOne(['_id' => new ObjectId($_GET['view_id'])]);
    if ($movieView) $viewMode = true;
}

// 3. EDIT SETUP
if (isset($_GET['edit_id'])) {
    $movieToEdit = $movieCollection->findOne(['_id' => new ObjectId($_GET['edit_id'])]);
    if ($movieToEdit) $editMode = true;
}

// 4. UPDATE MOVIE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_movie'])) {
    
    $updateData = [
        'name' => $_POST['movie_name'], // Pastikan field ini konsisten (name vs title)
        'title' => $_POST['movie_name'], // Kita simpan dua-dua supaya tak error
        'genre' => $_POST['genre'],
        'duration' => $_POST['duration'],
        'price' => $_POST['price'],
        'description' => $_POST['description']
    ];

    if (!empty($_FILES["movie_image"]["name"])) {
        $uploadedFile = uploadImage($_FILES["movie_image"]);
        if ($uploadedFile) {
            $updateData['image'] = $uploadedFile;
            $updateData['poster'] = "assets/img/" . $uploadedFile; // Backup field
        } else {
            $error = "Error uploading image. Only JPG, PNG, WEBP allowed.";
        }
    }

    if (!$error) {
        $movieCollection->updateOne(
            ['_id' => new ObjectId($_POST['id'])],
            ['$set' => $updateData]
        );
        header("Location: admin_movies.php");
        exit();
    }
}

// 5. ADD MOVIE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_movie'])) {
    
    $imageFilename = "default.jpg"; 
    
    if (!empty($_FILES["movie_image"]["name"])) {
        $uploaded = uploadImage($_FILES["movie_image"]);
        if ($uploaded) {
            $imageFilename = $uploaded;
        } else {
            $error = "Invalid image format. Only JPG, PNG, WEBP allowed.";
        }
    } else {
        $error = "Please select a movie poster.";
    }

if (!$error) {
        // 1. Simpan movie dan dapatkan RESULT dia
        $insertOneResult = $movieCollection->insertOne([
            'name' => $_POST['movie_name'],
            'title' => $_POST['movie_name'],
            'genre' => $_POST['genre'],
            'duration' => $_POST['duration'],
            'price' => $_POST['price'],
            'description' => $_POST['description'],
            'image' => $imageFilename,
            'poster' => "assets/img/" . $imageFilename
        ]);

        // 2. Ambil ID movie baru tu
        $newId = $insertOneResult->getInsertedId();

        // 3. Terus lompat ke page Schedule untuk set masa
        header("Location: admin_movie_schedule.php?id=" . $newId);
        exit();
    }
}

$movies = $movieCollection->find();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Movies</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        body { background-color: #0b0b0b; color: white; display: flex; }
        .main-content { margin-left: 250px; flex: 1; padding: 40px; display: grid; grid-template-columns: 1fr 2fr; gap: 30px; }
        .panel { background: #1a1a1a; padding: 25px; border-radius: 8px; border: 1px solid #333; height: fit-content; }
        
        input[type="text"], textarea { width: 100%; padding: 12px; background: #111; border: 1px solid #333; color: white; margin-bottom: 15px; border-radius: 4px; }
        input[type="file"] { padding: 10px; background: #222; border: 1px dashed #555; width: 100%; margin-bottom: 15px; cursor: pointer; }
        
        label { display: block; margin-bottom: 5px; color: #aaa; font-size: 0.8em; font-weight: bold; }
        .btn { width: 100%; padding: 12px; background: #e50914; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; text-transform: uppercase; }
        
        .movie-item { display: flex; background: #222; margin-bottom: 15px; border-radius: 6px; overflow: hidden; border: 1px solid #333; }
        .movie-poster { width: 90px; min-height: 120px; background-size: cover; background-position: center; background-color: #333; }
        .movie-info { padding: 15px; flex: 1; }
        .action-btn { font-size: 0.8em; padding: 6px 12px; border-radius: 4px; cursor: pointer; border: none; color: white; text-decoration: none; display:inline-block; margin-right: 5px; }
        
        .btn-view { background: #17a2b8; } .btn-edit { background: #007bff; } .btn-delete { background: #dc3545; }
        .btn-schedule { background: #6f42c1; } /* Warna Purple untuk Jadual */

        /* Modal */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: flex; justify-content: center; align-items: center; z-index: 200; }
        .modal-box { background: #1a1a1a; width: 500px; padding: 30px; border-radius: 8px; border: 1px solid #444; position: relative; display: flex; gap: 20px; }
        .close-btn { position: absolute; top: 10px; right: 15px; color: #aaa; font-size: 1.5em; text-decoration: none; }
        .modal-img { width: 150px; height: 220px; background-size: cover; background-position: center; border-radius: 4px; border: 1px solid #444; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">

        <?php if($viewMode && $movieView): ?>
        <div class="modal-overlay">
            <div class="modal-box">
                <a href="admin_movies.php" class="close-btn">&times;</a>
                <div class="modal-img" style="background-image: url('assets/img/<?php echo $movieView['image']; ?>');"></div>
                <div style="flex:1;">
                    <h2 style="color:#e50914; margin-bottom:10px;"><?php echo $movieView['name']; ?></h2>
                    <p><strong>Genre:</strong> <?php echo $movieView['genre']; ?></p>
                    <p><strong>Duration:</strong> <?php echo $movieView['duration']; ?></p>
                    <p><strong>Price:</strong> RM <?php echo $movieView['price']; ?></p>
                    <br>
                    <p style="color:#ccc; font-size:0.9em; line-height:1.5;">
                        <?php echo $movieView['description']; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <h3><?php echo $editMode ? '<i class="fas fa-edit"></i> Edit Movie' : '<i class="fas fa-plus-circle"></i> Add New Movie'; ?></h3>
            <br>
            
            <?php if($msg) echo "<p style='color:lime; margin-bottom:10px;'>$msg</p>"; ?>
            <?php if($error) echo "<p style='color:red; margin-bottom:10px;'>$error</p>"; ?>

            <form method="POST" enctype="multipart/form-data">
                <?php if($editMode): ?>
                    <input type="hidden" name="update_movie" value="1">
                    <input type="hidden" name="id" value="<?php echo $movieToEdit['_id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_movie" value="1">
                <?php endif; ?>
                
                <label>Movie Title</label>
                <input type="text" name="movie_name" required value="<?php echo $editMode ? $movieToEdit['name'] : ''; ?>" placeholder="e.g. Spiderman">
                
                <label>Genre</label>
                <input type="text" name="genre" required value="<?php echo $editMode ? $movieToEdit['genre'] : ''; ?>" placeholder="e.g. Action">
                
                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Duration (mins)</label>
                        <input type="text" name="duration" value="<?php echo $editMode ? $movieToEdit['duration'] : ''; ?>" placeholder="120">
                    </div>
                    <div style="flex:1;">
                        <label>Price (RM)</label>
                        <input type="text" name="price" value="<?php echo $editMode ? $movieToEdit['price'] : ''; ?>" placeholder="20">
                    </div>
                </div>

                <label>Movie Poster</label>
                <input type="file" name="movie_image" accept="image/*">
                
                <?php if($editMode): ?>
                    <p style="color:#777; font-size:0.8em; margin-top:-10px; margin-bottom:15px;">
                        Current: <strong><?php echo $movieToEdit['image']; ?></strong>. Leave empty to keep.
                    </p>
                <?php endif; ?>
                
                <label>Description</label>
                <textarea name="description" rows="3" placeholder="Synopsis..."><?php echo $editMode ? $movieToEdit['description'] : ''; ?></textarea>
                
                <button type="submit" class="btn"><?php echo $editMode ? 'Update Details' : 'Publish Movie'; ?></button>
                <?php if($editMode): ?><a href="admin_movies.php" style="display:block; text-align:center; margin-top:10px; color:#aaa;">Cancel</a><?php endif; ?>
            </form>
        </div>

        <div class="panel">
            <h3>Now Showing</h3>
            <br>
            <?php foreach($movies as $m): ?>
            <div class="movie-item">
                <div class="movie-poster" style="background-image: url('assets/img/<?php echo $m['image']; ?>');"></div>
                <div class="movie-info">
                    <strong style="font-size:1.1em;"><?php echo $m['name']; ?></strong>
                    <div style="color:#888; font-size:0.9em; margin-bottom:10px;">
                        <?php echo $m['genre']; ?> | <?php echo $m['duration']; ?> mins
                    </div>
                    
                    <div style="display:flex;">
                        <a href="admin_movie_schedule.php?id=<?php echo $m['_id']; ?>" class="action-btn btn-schedule" title="Manage Schedule">
                            <i class="fas fa-calendar-alt"></i> Schedule
                        </a>

                        <a href="admin_movies.php?view_id=<?php echo $m['_id']; ?>" class="action-btn btn-view" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="admin_movies.php?edit_id=<?php echo $m['_id']; ?>" class="action-btn btn-edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="POST" onsubmit="return confirm('Delete movie?');" style="display:inline;">
                            <input type="hidden" name="delete_id" value="<?php echo $m['_id']; ?>">
                            <button type="submit" class="action-btn btn-delete" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>

