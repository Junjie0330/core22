<?php
include 'config.php';

// Redirect if already logged in as seller
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'seller') {
    header("Location: seller_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Portal - Start Selling Today</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(45deg, #e74c3c, #f39c12);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .nav-links .btn-primary {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            padding: 0.7rem 1.5rem;
            font-weight: bold;
        }
        
        .nav-links .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 60px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,133.3C672,139,768,181,864,186.7C960,192,1056,160,1152,138.7C1248,117,1344,107,1392,101.3L1440,96L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
        }
        
        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .hero-text h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 700;
            animation: fadeInUp 1s ease;
        }
        
        .hero-text .highlight {
            color: #f39c12;
        }
        
        .hero-text p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.2s both;
        }
        
        .hero-stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease 0.4s both;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #f39c12;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            animation: fadeInUp 1s ease 0.6s both;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-seller {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }
        
        .btn-seller:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.6);
        }
        
        .btn-outline {
            border: 2px solid white;
            color: white;
        }
        
        .btn-outline:hover {
            background: white;
            color: #667eea;
        }
        
        .hero-visual {
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeInRight 1s ease;
        }
        
        .seller-graphic {
            width: 400px;
            height: 300px;
            background: linear-gradient(45deg, #f39c12, #e67e22);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 6rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Benefits Section */
        .benefits {
            padding: 80px 0;
            background: #f8f9fa;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #2c3e50;
        }
        
        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .benefit-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .benefit-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(231, 76, 60, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        
        .benefit-card:hover {
            transform: translateY(-10px);
        }
        
        .benefit-card:hover::before {
            opacity: 1;
            animation: shine 0.5s ease;
        }
        
        @keyframes shine {
            0% { left: -50%; }
            100% { left: 100%; }
        }
        
        .benefit-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #e74c3c;
        }
        
        .benefit-card h3 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .benefit-card p {
            color: #666;
            line-height: 1.6;
        }
        
        /* How It Works */
        .how-it-works {
            padding: 80px 0;
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        
        .steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .step {
            text-align: center;
            position: relative;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.4);
        }
        
        .step h3 {
            margin-bottom: 1rem;
            color: #f39c12;
        }
        
        .step p {
            opacity: 0.9;
        }
        
        /* CTA Section */
        .final-cta {
            padding: 80px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }
        
        .final-cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .final-cta p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        /* Footer */
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 2rem 0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .hero-text h1 {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .seller-graphic {
                width: 300px;
                height: 200px;
                font-size: 4rem;
            }
            
            .nav-links {
                gap: 0.5rem;
            }
            
            .nav-links a {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
        
        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
            
            .nav-links {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: #2c3e50;
                flex-direction: column;
                padding: 1rem;
                transform: translateY(-100%);
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }
            
            .nav-links.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav container">
            <div class="logo">🛒 SellerHub</div>
            <button class="mobile-menu-toggle" onclick="toggleMenu()">☰</button>
            <div class="nav-links" id="navLinks">
                <a href="#benefits">Benefits</a>
                <a href="#how-it-works">How It Works</a>
                <a href="index.php">Customer Portal</a>
                <a href="login.php">Seller Login</a>
                <a href="register.php" class="btn-primary">Start Selling</a>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <div class="container">
                <div class="hero-content">
                    <div class="hero-text">
                        <h1>Start Your <span class="highlight">Selling Journey</span> Today</h1>
                        <p>Join thousands of successful sellers on our platform. Reach millions of customers, grow your business, and achieve your entrepreneurial dreams.</p>
                        
                        <div class="hero-stats">
                            <div class="stat">
                                <div class="stat-number">50K+</div>
                                <div class="stat-label">Active Sellers</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number">2M+</div>
                                <div class="stat-label">Monthly Buyers</div>
                            </div>
                            <div class="stat">
                                <div class="stat-number">₱5M+</div>
                                <div class="stat-label">Monthly Sales</div>
                            </div>
                        </div>
                        
                        <div class="cta-buttons">
                            <a href="seller_register.php" class="btn btn-seller">Start Selling Now</a>
                            <a href="#benefits" class="btn btn-outline">Learn More</a>
                        </div>
                    </div>
                    
                    <div class="hero-visual">
                        <div class="seller-graphic">
                            💼
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="benefits" id="benefits">
            <div class="container">
                <h2 class="section-title">Why Choose Our Seller Platform?</h2>
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <div class="benefit-icon">💰</div>
                        <h3>Higher Profit Margins</h3>
                        <p>Keep more of what you earn with our competitive commission rates and transparent fee structure.</p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon">🎯</div>
                        <h3>Targeted Marketing</h3>
                        <p>Reach the right customers with our advanced marketing tools and promotional features.</p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon">📊</div>
                        <h3>Analytics & Insights</h3>
                        <p>Make data-driven decisions with comprehensive sales reports and customer analytics.</p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon">🚀</div>
                        <h3>Easy Setup</h3>
                        <p>Get your store up and running in minutes with our user-friendly seller dashboard.</p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon">🛡️</div>
                        <h3>Secure Payments</h3>
                        <p>Fast and secure payment processing with multiple payment options for your customers.</p>
                    </div>
                    
                    <div class="benefit-card">
                        <div class="benefit-icon">📞</div>
                        <h3>24/7 Support</h3>
                        <p>Our dedicated seller support team is here to help you succeed every step of the way.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="how-it-works" id="how-it-works">
            <div class="container">
                <h2 class="section-title">How It Works</h2>
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Sign Up</h3>
                        <p>Create your seller account in just a few minutes with our simple registration process.</p>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>List Products</h3>
                        <p>Add your products with high-quality images and detailed descriptions to attract customers.</p>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>Manage Orders</h3>
                        <p>Receive orders, process payments, and manage inventory through your seller dashboard.</p>
                    </div>
                    
                    <div class="step">
                        <div class="step-number">4</div>
                        <h3>Grow & Scale</h3>
                        <p>Use our marketing tools and analytics to grow your business and increase sales.</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="final-cta">
            <div class="container">
                <h2>Ready to Start Your Success Story?</h2>
                <p>Join our community of successful sellers and turn your passion into profit!</p>
                <a href="seller_register.php" class="btn btn-seller">Get Started Today</a>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 SellerHub. All rights reserved. | <a href="../index.php" style="color: #f39c12;">Customer Portal</a></p>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        function toggleMenu() {
            const navLinks = document.getElementById('navLinks');
            navLinks.classList.toggle('active');
        }

        // Smooth scrolling for anchor links
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

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe benefit cards
        document.querySelectorAll('.benefit-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Add counter animation for stats
        function animateStats() {
            const stats = document.querySelectorAll('.stat-number');
            stats.forEach(stat => {
                const target = parseInt(stat.textContent.replace(/\D/g, ''));
                const suffix = stat.textContent.replace(/[\d.]/g, '');
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    stat.textContent = Math.floor(current) + suffix;
                }, 40);
            });
        }

        // Trigger stats animation when hero section is visible
        const heroObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateStats();
                    heroObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        heroObserver.observe(document.querySelector('.hero'));

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            const navLinks = document.getElementById('navLinks');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (!navLinks.contains(e.target) && !toggle.contains(e.target)) {
                navLinks.classList.remove('active');
            }
        });
    </script>
</body>
</html>