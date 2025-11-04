<?php
// Start session to check login status for nav bar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Organic Food Traceability - Home</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <link rel="stylesheet" href="public_style.css">
</head>
<body>

    <header class="public-navbar">
        <a href="index.php" class="brand">Organic Food Traceability</a>
        <div class="links">
            <a href="track_food.php">Track Food</a>
            <?php
            // Show Dashboard/Logout if logged in, else Login/Register
            if (isset($_SESSION['user_id']) && isset($_SESSION['role_name'])) {
                echo '<a href="dashboard.php">Dashboard</a>';
                echo '<a href="logout.php">Logout</a>';
            } else {
                echo '<a href="login.php">Login</a>';
                echo '<a href="register.php">Register</a>';
            }
            ?>
        </div>
    </header>

    <div class="public-content-wrapper">

        <section class="hero-section">
            <h1>Know Your Food, Trust Your Farmer.</h1>
            <p class="subtitle">
                Welcome to the future of food transparency. Our system provides a <strong>verifiable, farm-to-table journey</strong>
                for every product, ensuring the organic food you buy is authentic.
            </p>
            <div class="cta-buttons">
                <a href="#tracker" class="cta-primary"><i class="fas fa-search"></i> Track Your Product Now</a>
                <a href="register.php" class="cta-secondary">Join as a Partner</a>
            </div>
        </section>

        <section class="how-it-works-section">
            <h2>How It Works</h2>
            <div class="steps-container">
                <div class="step-card">
                    <i class="fas fa-tractor"></i>
                    <h3>1. Farmer Creates Batch</h3>
                    <p>Farmers register their harvest, generating a unique, secure batch ID and QR code.</p>
                </div>
                <div class="step-card">
                    <i class="fas fa-industry"></i>
                    <h3>2. Supply Chain Logs</h3>
                    <p>Manufacturers and distributors update the product's status as it moves to the store.</p>
                </div>
                <div class="step-card">
                    <i class="fas fa-qrcode"></i>
                    <h3>3. Consumer Scans</h3>
                    <p>You scan the final QR code in-store to see the product's full, verified history.</p>
                </div>
            </div>
        </section>

        <section class="tracker-section" id="tracker">
            <h2>Track Your Food</h2>
            <div class="search-form">
                <form action="track_food.php" method="POST">
                    <label for="search_term">Enter your Order ID or Batch ID to begin:</label>
                    <input type="text" id="search_term" name="search_term" placeholder="Enter ID Here...">
                    <button type="submit" name="search_order" class="btn-track">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </section>

        <section class="partners-section">
            <h2>Join Our Transparent Network</h2>
            <p class="section-subtitle">
                Whether you're a farmer, manufacturer, or distributor, our platform helps you build consumer trust and prove the quality of your products.
            </p>
            <div class="partner-cards">
                <div class="partner-card">
                    <i class="fas fa-seedling"></i>
                    <h3>For Farmers</h3>
                    <p>Certify your harvests, manage batches, and connect directly with manufacturers.</p>
                    <a href="register.php">Register as a Farmer</a>
                </div>
                <div class="partner-card">
                    <i class="fas fa-cogs"></i>
                    <h3>For Manufacturers</h3>
                    <p>Verify the origin of your raw materials and provide a verifiable story for your final product.</p>
                    <a href="register.php">Register as a Manufacturer</a>
                </div>
                <div class="partner-card">
                    <i class="fas fa-store"></i>
                    <h3>For Retailers</h3>
                    <p>Gain a competitive edge by offering your customers proven, traceable organic goods.</p>
                    <a href="register.php">Register as a Retailer</a>
                </div>
            </div>
        </section>

        <section class="features-section">
            <h2>Guaranteed Authenticity</h2>
            <div class="features-grid">
                <div class="feature-item">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Blockchain Verified</h3>
                    <p>Every step is secured with a "hash," creating a digital fingerprint that ensures the data cannot be manipulated.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-leaf"></i>
                    <h3>Organic Certification</h3>
                    <p>We verify our partners' organic certificates, so you know you're getting what you paid for.</p>
                </div>
                <div class="feature-item">
                    <i class="fas fa-map-marked-alt"></i>
                    <h3>Full Transparency</h3>
                    <p>See every part of the supply chain, from the soil details at the farm to the delivery to your store.</p>
                </div>
            </div>
        </section>

    </div> <footer class="public-footer">
        <p>&copy; <?php echo date("Y"); ?> Organic Food Traceability System. All rights reserved.</p>
    </footer>

</body>
</html>