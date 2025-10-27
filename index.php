<?php
session_start();

// Check for "Remember Me" cookie and set session if it exists
if (isset($_COOKIE['remember_user']) && !isset($_SESSION['username'])) {
    $_SESSION['username'] = $_COOKIE['remember_user'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anglican Church of Kenya - Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" 
          crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow-x: hidden;
            background: #0f172a;
        }

        /* Hero Section */
        .hero-section {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('img/anglicankenya.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.15;
            z-index: 0;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.1) 0%, transparent 50%);
            z-index: 1;
        }

        /* Navigation */
        .navbar-custom {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 0;
            transition: all 0.3s ease;
        }

        .navbar-custom.scrolled {
            padding: 15px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .navbar-brand {
            font-size: 22px;
            font-weight: 700;
            color: #fff !important;
            letter-spacing: -0.5px;
        }

        .navbar-brand span {
            color: #6366f1;
        }

        .btn-login {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
            color: #fff;
        }

        .btn-dashboard {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.2);
            padding: 10px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s ease;
            margin-right: 12px;
        }

        .btn-dashboard:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: #fff;
        }

        /* Hero Content */
        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 0 20px;
            max-width: 900px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-block;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #818cf8;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 24px;
            animation: fadeInDown 0.6s ease;
        }

        .hero-title {
            font-size: clamp(36px, 6vw, 64px);
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -2px;
            animation: fadeInUp 0.6s ease 0.2s both;
        }

        .hero-title .gradient-text {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: clamp(16px, 2vw, 20px);
            color: #94a3b8;
            line-height: 1.6;
            margin-bottom: 40px;
            font-weight: 400;
            animation: fadeInUp 0.6s ease 0.4s both;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.6s ease 0.6s both;
        }

        .btn-primary-hero {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: #fff;
            border: none;
            padding: 16px 48px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 8px 30px rgba(99, 102, 241, 0.4);
        }

        .btn-primary-hero:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.5);
            color: #fff;
        }

        .btn-secondary-hero {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.2);
            padding: 16px 48px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .btn-secondary-hero:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
            color: #fff;
        }

        /* Features Section */
        .features-section {
            padding: 100px 0;
            background: #0f172a;
            position: relative;
        }

        .section-badge {
            display: inline-block;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            color: #818cf8;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: clamp(32px, 5vw, 48px);
            font-weight: 800;
            color: #fff;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .section-subtitle {
            font-size: 18px;
            color: #94a3b8;
            margin-bottom: 60px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .feature-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 40px 32px;
            transition: all 0.3s ease;
            height: 100%;
            backdrop-filter: blur(10px);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(30, 41, 59, 0.8);
            box-shadow: 0 20px 60px rgba(99, 102, 241, 0.2);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 24px;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.3);
        }

        .feature-title {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 12px;
        }

        .feature-description {
            font-size: 15px;
            color: #94a3b8;
            line-height: 1.7;
        }

        /* Footer */
        .footer {
            background: #020617;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px 0;
            text-align: center;
        }

        .footer-text {
            color: #64748b;
            font-size: 14px;
            margin: 0;
        }

        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-primary-hero,
            .btn-secondary-hero {
                width: 100%;
            }

            .feature-card {
                margin-bottom: 24px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar-custom">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <a class="navbar-brand" href="index.php">
                    Anglican Church of Kenya <span>Management System</span>
                </a>
                <div>
                    <?php if (isset($_SESSION['username'])): ?>
                        <a href="dashboard.php" class="btn btn-dashboard">Dashboard</a>
                    <?php endif; ?>
                    <a href="login.php" class="btn btn-login">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="hero-badge">Welcome to ACK</div>
            <h1 class="hero-title">
                Anglican Church of Kenya<br>
                <span class="gradient-text">Management System</span>
            </h1>
            <p class="hero-subtitle">
                A comprehensive digital platform for managing church operations, parishes, clergy, and congregations across all dioceses in Kenya.
            </p>
            <div class="hero-buttons">
                <?php if (isset($_SESSION['username'])): ?>
                    <a href="dashboard.php" class="btn btn-primary-hero">Go to Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-primary-hero">Get Started</a>
                <?php endif; ?>
                <a href="#features" class="btn btn-secondary-hero">Learn More</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-badge">Features</div>
                <h2 class="section-title">Everything You Need</h2>
                <p class="section-subtitle">
                    Powerful tools to manage your church community efficiently
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">⛪</div>
                        <h3 class="feature-title">Parish Management</h3>
                        <p class="feature-description">
                            Organize and manage parishes, deaneries, archdeaconries, and dioceses in a hierarchical structure.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3 class="feature-title">Clergy Database</h3>
                        <p class="feature-description">
                            Maintain comprehensive records of all clergy members, their assignments, and credentials.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">📊</div>
                        <h3 class="feature-title">Reports & Analytics</h3>
                        <p class="feature-description">
                            Generate detailed reports and insights on church activities, attendance, and finances.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3 class="feature-title">Secure Access</h3>
                        <p class="feature-description">
                            Role-based access control ensures data security and appropriate permissions for all users.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3 class="feature-title">Mobile Friendly</h3>
                        <p class="feature-description">
                            Access the system from any device - desktop, tablet, or smartphone.
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">🌐</div>
                        <h3 class="feature-title">Multi-Tenant</h3>
                        <p class="feature-description">
                            Support for multiple organizations with complete data isolation and security.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="footer-text">
                &copy; <?php echo date('Y'); ?> Anglican Church of Kenya Management System. All rights reserved.
            </p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" 
            crossorigin="anonymous"></script>
    
    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-custom');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
