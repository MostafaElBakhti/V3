<footer class="footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-section">
                <h3>TaskGo</h3>
                <p>Get your tasks done efficiently with our intuitive task management system.</p>
            </div>
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#how-it-works">How it Works</a></li>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#about">About Us</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4>Contact Us</h4>
                <ul>
                    <li>Email: support@taskgo.com</li>
                    <li>Phone: (555) 123-4567</li>
                    <li>Address: 123 Task Street, City, Country</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> TaskGo. All rights reserved.</p>
        </div>
    </div>
</footer>

<style>
    .footer {
        background: #f8f9fa;
        padding: 4rem 0 2rem;
        margin-top: 4rem;
    }

    .footer-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 3rem;
        margin-bottom: 3rem;
    }

    .footer-section h3 {
        color: #1a73e8;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .footer-section h4 {
        color: #333;
        font-size: 1.2rem;
        margin-bottom: 1rem;
    }

    .footer-section p {
        color: #666;
        line-height: 1.6;
    }

    .footer-section ul {
        list-style: none;
        padding: 0;
    }

    .footer-section ul li {
        margin-bottom: 0.8rem;
    }

    .footer-section ul li a {
        color: #666;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .footer-section ul li a:hover {
        color: #1a73e8;
    }

    .footer-bottom {
        text-align: center;
        padding-top: 2rem;
        border-top: 1px solid #e9ecef;
    }

    .footer-bottom p {
        color: #666;
    }
</style> 