<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Helpify - Get Help Anytime</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <link href="./css/index.css" rel="stylesheet" />
</head>
<body>
  <header>
    <div class="logo">Helpify</div>
    
    <!-- Desktop Navigation -->
    <nav class="nav-links">
      <a href="index.php">Home</a>
      <a href="#how-it-works">How It Works</a>
      <?php if (isLoggedIn()): ?>
        <?php if ($_SESSION['user_type'] === 'client'): ?>
          <a href="client-dashboard.php">Dashboard</a>
        <?php else: ?>
          <a href="helper-dashboard.php">Dashboard</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
    
    <!-- Desktop Auth Buttons -->
    <div class="auth-buttons">
      <?php if (isLoggedIn()): ?>
        <div class="user-menu">
          <a href="#" class="sign-in">Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['fullname'])[0]); ?></a>
          <div class="user-dropdown">
            <?php if ($_SESSION['user_type'] === 'client'): ?>
              <a href="client-dashboard.php">Dashboard</a>
              <a href="post-task.php">Post Task</a>
              <a href="my-tasks.php">My Tasks</a>
              <a href="client-messages.php">Messages</a>
            <?php else: ?>
              <a href="helper-dashboard.php">Dashboard</a>
              <a href="find-tasks.php">Find Tasks</a>
              <a href="my-applications.php">Applications</a>
              <a href="my-jobs.php">My Jobs</a>
              <a href="helper-messages.php">Messages</a>
            <?php endif; ?>
            <a href="settings.php">Settings</a>
            <a href="logout.php">Logout</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="sign-in">Log In</a>
        <a href="register.php" class="sign-up">Sign Up</a>
      <?php endif; ?>
    </div>
    
    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" onclick="toggleMobileMenu()">
      <span></span>
      <span></span>
      <span></span>
    </div>
  </header>

  <!-- Mobile Menu -->
  <div class="mobile-menu" id="mobileMenu">
    <a href="index.php">Home</a>
    <a href="#how-it-works" onclick="closeMobileMenu()">How It Works</a>
    <?php if (isLoggedIn()): ?>
      <?php if ($_SESSION['user_type'] === 'client'): ?>
        <a href="client-dashboard.php">Dashboard</a>
        <a href="post-task.php">Post Task</a>
        <a href="my-tasks.php">My Tasks</a>
        <a href="client-messages.php">Messages</a>
      <?php else: ?>
        <a href="helper-dashboard.php">Dashboard</a>
        <a href="find-tasks.php">Find Tasks</a>
        <a href="my-applications.php">Applications</a>
        <a href="my-jobs.php">My Jobs</a>
        <a href="helper-messages.php">Messages</a>
      <?php endif; ?>
      <a href="settings.php">Settings</a>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <div class="mobile-auth-buttons">
        <a href="login.php" class="sign-in">Log In</a>
        <a href="register.php" class="sign-up">Sign Up</a>
      </div>
    <?php endif; ?>
  </div>

  <section class="hero">
    <!-- Floating Background Elements -->
    <div class="floating-element circle element-1"></div>
    <div class="floating-element square element-2"></div>
    <div class="floating-element triangle element-3"></div>
    <div class="floating-element circle element-4"></div>
    <div class="floating-element square element-5"></div>
    
    <div class="hero-content">
      <h1>Choose an <span class="highlight">expert</span>.<br>And get the job done.</h1>
      <p>Connect with skilled professionals in your area. From home repairs to personal assistance – find help for any task, anytime.</p>
      
      <!-- Search Section -->
      <form class="hero-search" action="<?php echo isLoggedIn() ? 'find-tasks.php' : 'register.php'; ?>" method="GET">
        <input type="text" class="search-input" name="search" placeholder="What do you need help with?">
        <input type="text" class="location-input" name="location" placeholder="Location">
        <button type="submit" class="search-btn">SEARCH</button>
      </form>
      
      <!-- Action Buttons -->
      <div class="hero-buttons">
        <?php if (isLoggedIn()): ?>
          <?php if ($_SESSION['user_type'] === 'client'): ?>
            <a href="post-task.php" class="post-task">Post a Task</a>
            <a href="find-tasks.php" class="find-work">Browse Helpers</a>
          <?php else: ?>
            <a href="find-tasks.php" class="post-task">Find Tasks</a>
            <a href="my-applications.php" class="find-work">My Applications</a>
          <?php endif; ?>
        <?php else: ?>
          <a href="register.php?type=client" class="post-task">Post a Task</a>
          <a href="register.php?type=helper" class="find-work">Find Work</a>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section id="how-it-works" class="how-it-works">
    <div class="container">
      <div class="header">
        <h1>How it works</h1>
        <p>
          Get help with your tasks in three simple steps. Whether you need help with home repairs, 
          moving, or any other task, we make it easy to connect with reliable helpers.
        </p>
      </div>

      <div class="steps">
        <!-- Step 1 -->
        <div class="step">
          <div class="step-number">01</div>
          <div class="step-visual">
            <div class="account-form">
              <h4>Create Account</h4>
              <div class="form-field"></div>
              <div class="form-field"></div>
              <div class="form-field"></div>
              <button class="signup-btn">Sign up</button>
            </div>
          </div>
          <div class="step-content">
            <h3>Create Your Account</h3>
            <p>
              Sign up in minutes with your email or social media account. Choose whether you want to 
              post tasks or offer your services as a helper. Your profile helps build trust in our community.
            </p>
          </div>
        </div>

        <!-- Step 2 -->
        <div class="step">
          <div class="step-number">02</div>
          <div class="step-visual">
            <div class="search-container">
              <div class="search-box">
                <div class="search-input-mock"></div>
                <div class="search-icon">&#128269;</div>
              </div>
            </div>
          </div>
          <div class="step-content">
            <h3>Post or Find Tasks</h3>
            <p>
              Need help? Post your task with details and your budget. Looking for work? Browse available 
              tasks in your area and apply to help. Our smart matching system connects you with the right people.
            </p>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="step">
          <div class="step-number">03</div>
          <div class="step-visual">
            <div class="emoji-container">
              <div class="emoji">😊</div>
              <div class="emoji">😟</div>
              <div class="emoji">❤️</div>
            </div>
          </div>
          <div class="step-content">
            <h3>Get Things Done</h3>
            <p>
              Connect with your chosen helper, agree on details, and get your task completed. 
              Our secure payment system and rating system ensure a smooth experience for everyone.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="features" class="features-jobs">
    <div class="container">
      <div class="features-header">
        <h2 class="features-title">Find a task that's just right</h2>
        <a href="<?php echo isLoggedIn() ? 'find-tasks.php' : 'register.php'; ?>" class="view-all-btn">VIEW ALL →</a>
      </div>
      
      <div class="job-cards-grid">
        <!-- Task Card 1 -->
        <div class="job-card">
          <div class="job-header">
            <div class="company-info">
              <div class="company-logo" style="background: linear-gradient(135deg, #ff6b6b, #ee5a24);">
                <span>HF</span>
              </div>
              <div class="job-meta">
                <div class="company-name">HomeFixers</div>
                <div class="job-posted">2 hours ago</div>
              </div>
            </div>
          </div>
          
          <div class="job-title">Plumbing Repair Specialist</div>
          <div class="job-location">📍 New York, NY</div>
          
          <div class="job-details">
            <span class="job-type">💰 $150-200</span>
            <span class="job-mode">🏠 On-site</span>
          </div>
          
          <div class="job-category">HOME & MAINTENANCE</div>
        </div>

        <!-- Task Card 2 -->
        <div class="job-card">
          <div class="job-header">
            <div class="company-info">
              <div class="company-logo" style="background: linear-gradient(135deg, #10ac84, #00d2d3);">
                <span>TM</span>
              </div>
              <div class="job-meta">
                <div class="company-name">TaskMaster</div>
                <div class="job-posted">5 hours ago</div>
              </div>
            </div>
          </div>
          
          <div class="job-title">Moving & Packing Helper</div>
          <div class="job-location">📍 Los Angeles, CA</div>
          
          <div class="job-details">
            <span class="job-type">💰 $25/hour</span>
            <span class="job-mode">🚚 Physical</span>
          </div>
          
          <div class="job-category">MOVING & DELIVERY</div>
        </div>

        <!-- Task Card 3 -->
        <div class="job-card">
          <div class="job-header">
            <div class="company-info">
              <div class="company-logo" style="background: linear-gradient(135deg, #5f27cd, #341f97);">
                <span>DH</span>
              </div>
              <div class="job-meta">
                <div class="company-name">DigitalHelp</div>
                <div class="job-posted">1 day ago</div>
              </div>
            </div>
          </div>
          
          <div class="job-title">Website Setup & Design</div>
          <div class="job-location">📍 Remote</div>
          
          <div class="job-details">
            <span class="job-type">💰 $300-500</span>
            <span class="job-mode">💻 Remote</span>
          </div>
          
          <div class="job-category">TECH & DIGITAL</div>
        </div>

        <!-- Task Card 4 -->
        <div class="job-card">
          <div class="job-header">
            <div class="company-info">
              <div class="company-logo" style="background: linear-gradient(135deg, #fd79a8, #e84393);">
                <span>CC</span>
              </div>
              <div class="job-meta">
                <div class="company-name">CleanCrew</div>
                <div class="job-posted">3 hours ago</div>
              </div>
            </div>
          </div>
          
          <div class="job-title">House Cleaning Service</div>
          <div class="job-location">📍 Chicago, IL</div>
          
          <div class="job-details">
            <span class="job-type">💰 $80-120</span>
            <span class="job-mode">🏠 On-site</span>
          </div>
          
          <div class="job-category">CLEANING & ORGANIZING</div>
        </div>

        <!-- Task Card 5 -->
        <div class="job-card">
          <div class="job-header">
            <div class="company-info">
              <div class="company-logo" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                <span>GH</span>
              </div>
              <div class="job-meta">
                <div class="company-name">GardenGurus</div>
                <div class="job-posted">6 hours ago</div>
              </div>
            </div>
          </div>
          
          <div class="job-title">Landscaping & Garden Care</div>
          <div class="job-location">📍 Miami, FL</div>
          
          <div class="job-details">
            <span class="job-type">💰 $40/hour</span>
            <span class="job-mode">🌱 Outdoor</span>
          </div>
          
          <div class="job-category">GARDEN & OUTDOOR</div>
        </div>

        <!-- Task Card 6 -->
        <div class="job-card">
          <div class="job-header">
            <div class="company-info">
              <div class="company-logo" style="background: linear-gradient(135deg, #00b894, #00cec9);">
                <span>PS</span>
              </div>
              <div class="job-meta">
                <div class="company-name">PetSitters</div>
                <div class="job-posted">4 hours ago</div>
              </div>
            </div>
          </div>
          
          <div class="job-title">Pet Care & Dog Walking</div>
          <div class="job-location">📍 San Francisco, CA</div>
          
          <div class="job-details">
            <span class="job-type">💰 $20/hour</span>
            <span class="job-mode">🐕 Pet Care</span>
          </div>
          
          <div class="job-category">PET & ANIMAL CARE</div>
        </div>
      </div>
    </div>
  </section>

  <footer class="site-footer">
    <div class="footer-container">
      <div class="footer-brand">Helpify</div>

      <div class="footer-links">
        <a href="#features">Features</a>
        <a href="#about">About</a>
        <a href="#contact">Contact</a>
      </div>

      <div class="footer-social">
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-facebook-f"></i></a>
      </div>
    </div>

    <div class="footer-bottom">© 2025 Helpify. All rights reserved.</div>
  </footer>
  
  <script>
    // Mobile menu functionality
    function toggleMobileMenu() {
      const mobileMenu = document.getElementById('mobileMenu');
      const menuToggle = document.querySelector('.menu-toggle');
      
      mobileMenu.classList.toggle('active');
      menuToggle.classList.toggle('active');
      
      // Prevent body scrolling when menu is open
      if (mobileMenu.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = '';
      }
    }
    
    function closeMobileMenu() {
      const mobileMenu = document.getElementById('mobileMenu');
      const menuToggle = document.querySelector('.menu-toggle');
      
      mobileMenu.classList.remove('active');
      menuToggle.classList.remove('active');
      document.body.style.overflow = '';
    }
    
    // Close mobile menu when clicking on a link
    document.querySelectorAll('.mobile-menu a').forEach(link => {
      link.addEventListener('click', function() {
        if (this.getAttribute('href').startsWith('#')) {
          closeMobileMenu();
        }
      });
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
      const mobileMenu = document.getElementById('mobileMenu');
      const menuToggle = document.querySelector('.menu-toggle');
      
      if (mobileMenu.classList.contains('active') && 
          !mobileMenu.contains(e.target) && 
          !menuToggle.contains(e.target)) {
        closeMobileMenu();
      }
    });
    
    // Add interactive functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Smooth scrolling for navigation links
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
      
      // Header background change on scroll
      window.addEventListener('scroll', function() {
        const header = document.querySelector('header');
        if (window.scrollY > 100) {
          header.style.background = 'rgba(255, 255, 255, 0.98)';
          header.style.boxShadow = '0 2px 30px rgba(0,0,0,0.1)';
        } else {
          header.style.background = 'rgba(255, 255, 255, 0.95)';
          header.style.boxShadow = '0 2px 20px rgba(0,0,0,0.08)';
        }
      });
      
      // Search form validation
      const heroSearch = document.querySelector('.hero-search');
      const searchInput = document.querySelector('.search-input');
      
      if (heroSearch && searchInput) {
        heroSearch.addEventListener('submit', function(e) {
          const task = searchInput.value.trim();
          
          if (!task) {
            e.preventDefault();
            searchInput.focus();
            searchInput.placeholder = 'Please enter what you need help with';
            searchInput.style.borderColor = '#ff6b6b';
            setTimeout(() => {
              searchInput.placeholder = 'What do you need help with?';
              searchInput.style.borderColor = '';
            }, 3000);
          }
        });
      }
      
      // Enhanced user menu functionality
      const userMenu = document.querySelector('.user-menu');
      if (userMenu) {
        userMenu.addEventListener('click', function(e) {
          e.preventDefault();
          const dropdown = this.querySelector('.user-dropdown');
          if (dropdown) {
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
          }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
          if (!userMenu.contains(e.target)) {
            const dropdown = userMenu.querySelector('.user-dropdown');
            if (dropdown) {
              dropdown.style.display = 'none';
            }
          }
        });
      }
      
      // Animate stats numbers on scroll
      const statNumbers = document.querySelectorAll('.stat-number');
      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const target = entry.target;
            const finalValue = target.textContent;
            animateNumber(target, finalValue);
          }
        });
      });
      
      statNumbers.forEach(stat => observer.observe(stat));
      
      function animateNumber(element, finalValue) {
        const numValue = parseInt(finalValue.replace(/[^\d]/g, ''));
        const suffix = finalValue.replace(/[\d,+]/g, '');
        let current = 0;
        const increment = numValue / 50;
        const timer = setInterval(() => {
          current += increment;
          if (current >= numValue) {
            element.textContent = finalValue;
            clearInterval(timer);
          } else {
            element.textContent = Math.floor(current) + suffix;
          }
        }, 30);
      }
      
      // Handle window resize to close mobile menu
      window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
          closeMobileMenu();
        }
      });
    });
  </script>

  <!-- Font Awesome for icons -->
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>