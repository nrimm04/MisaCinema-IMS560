<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="sidebar">
    <div class="sidebar-top">
        <div class="logo-container">
            <img src="assets/images/logo_misa.png" alt="MISA Logo" 
                 style="width: 110px; height: 110px; object-fit: cover; border-radius: 50%; border: 3px solid #333; box-shadow: 0 0 20px rgba(229, 9, 20, 0.4);">
        </div>

        <ul class="nav-links">
            <li>
                <a href="admin.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>

            <li>
                <a href="admin_movies.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_movies.php' ? 'active' : ''; ?>">
                    <i class="fas fa-film"></i> Movies
                </a>
            </li>

            <li>
                <a href="admin_halls.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_halls.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chair"></i> Halls & Seats
                </a>
            </li>

            <li>
                <a href="admin_bookings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_bookings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-ticket-alt"></i> Bookings
                </a>
            </li>

            <li>
                <a href="admin_staff.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_staff.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Staffs
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-bottom">
        <a href="home.php" target="_blank" class="nav-item">
            <i class="fas fa-home"></i> View Site
        </a>
        
        <a href="logout.php" class="nav-item logout">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</div>

<style>
    /* Reset & Font */
    body { margin: 0; font-family: 'Roboto', sans-serif; }

    /* SIDEBAR CONTAINER */
    .sidebar {
        width: 250px;
        height: 100vh;
        background-color: #151515;
        position: fixed;
        top: 0;
        left: 0;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        border-right: 1px solid #333;
        z-index: 99999;
    }

    /* LOGO AREA */
    .logo-container {
        padding: 30px 20px;
        text-align: center;
        border-bottom: 1px solid #222;
        margin-bottom: 10px;
        background: radial-gradient(circle, rgba(40,40,40,1) 0%, rgba(21,21,21,1) 70%);
    }
    
    /* MENU LIST */
    .nav-links { list-style: none; padding: 0; margin: 0; }

    .nav-links li a {
        display: flex;
        align-items: center;
        padding: 15px 25px;
        color: #aaa;
        text-decoration: none;
        font-size: 16px;
        transition: 0.3s;
        border-left: 4px solid transparent;
    }

    .nav-links li a i { width: 30px; text-align: center; margin-right: 10px; }

    .nav-links li a:hover, 
    .nav-links li a.active {
        background-color: #222;
        color: white;
        border-left: 4px solid #e50914;
    }

    /* BOTTOM AREA */
    .sidebar-bottom { padding: 20px; border-top: 1px solid #333; background-color: #111; }

    .sidebar-bottom a {
        display: flex; align-items: center; color: #aaa;
        text-decoration: none; padding: 12px 10px;
        font-size: 15px; transition: 0.3s;
        border-radius: 4px; margin-bottom: 5px;
    }
    .sidebar-bottom a i { width: 30px; text-align: center; margin-right: 10px; }
    .sidebar-bottom a:hover { background-color: #222; color: white; }

    .sidebar-bottom a.logout { color: #e50914; font-weight: bold; }
    .sidebar-bottom a.logout:hover { background-color: rgba(229, 9, 20, 0.1); color: #ff4f5e; }

    .main-content { margin-left: 250px; padding: 20px; }
</style>
