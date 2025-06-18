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
  <style>
    /* Color Variables */
    :root {
      --primary: #0F172A;      /* Deep Navy */
      --primary-light: #1E293B; /* Lighter Navy */
      --secondary: #0EA5E9;    /* Sky Blue */
      --accent: #06B6D4;       /* Cyan */
      --background: #F8FAFC;   /* Off White */
      --text-primary: #334155; /* Slate Gray */
      --text-secondary: #64748B; /* Cool Gray */
      --success: #10B981;      /* Emerald */
      --warning: #F59E0B;      /* Amber */
      --error: #EF4444;        /* Red */
    }

    * {
      margin: 0; padding: 0; box-sizing: border-box;
    }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--background);
      color: var(--text-primary);
      overflow-x: hidden;
    }
    
    /* Header Styles */
    header {
      display: flex; 
      justify-content: space-between; 
      align-items: center;
      padding: 20px 60px; 
      background-color: rgba(248, 250, 252, 0.95);
      backdrop-filter: blur(10px);
      box-shadow: 0 2px 20px rgba(15, 23, 42, 0.08);
      position: fixed; 
      width: 100%; 
      top: 0; 
      z-index: 1000;
      transition: all 0.3s ease;
    }
    
    .logo {
      font-size: 32px; 
      font-weight: 800; 
      color: var(--secondary);
      letter-spacing: -1px;
    }
    
    .nav-links {
      display: flex; 
      gap: 40px;
      align-items: center;
    }
    
    .nav-links a {
      text-decoration: none; 
      color: var(--text-secondary); 
      font-weight: 500;
      font-size: 15px;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .nav-links a:hover {
      color: var(--secondary);
    }
    
    .nav-links a::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -5px;
      left: 50%;
      background-color: var(--secondary);
      transition: all 0.3s ease;
      transform: translateX(-50%);
    }
    
    .nav-links a:hover::after {
      width: 100%;
    }
    
    .auth-buttons {
      display: flex; 
      gap: 15px;
      align-items: center;
    }
    
    .auth-buttons a {
      text-decoration: none; 
      padding: 12px 24px;
      border-radius: 15px; 
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
      white-space: nowrap;
    }
    
    .sign-in {
      color: var(--secondary);
      background: transparent;
      border: 2px solid var(--secondary);
    }
    
    .sign-in:hover {
      background-color: var(--secondary); 
      color: var(--background);
      transform: translateY(-1px);
    }
    
    .sign-up {
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      color: var(--background); 
      border: none;
      box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
    }
    
    .sign-up:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
    }

    /* Mobile Menu Styles */
    .menu-toggle {
      display: none;
      flex-direction: column;
      cursor: pointer;
      padding: 5px;
      z-index: 1001;
    }

    .menu-toggle span {
      width: 25px;
      height: 3px;
      background-color: var(--primary);
      margin: 3px 0;
      transition: 0.3s;
    }

    .menu-toggle.active span:nth-child(1) {
      transform: rotate(-45deg) translate(-5px, 6px);
    }

    .menu-toggle.active span:nth-child(2) {
      opacity: 0;
    }

    .menu-toggle.active span:nth-child(3) {
      transform: rotate(45deg) translate(-5px, -6px);
    }

    .mobile-menu {
      position: fixed;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100vh;
      background-color: var(--background);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 30px;
      transition: left 0.3s ease;
      z-index: 999;
    }

    .mobile-menu.active {
      left: 0;
    }

    .mobile-menu a {
      text-decoration: none;
      color: var(--text-primary);
      font-size: 24px;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .mobile-menu a:hover {
      color: var(--secondary);
    }

    .mobile-auth-buttons {
      display: flex;
      flex-direction: column;
      gap: 15px;
      margin-top: 30px;
    }

    .mobile-auth-buttons a {
      padding: 15px 30px !important;
      font-size: 16px !important;
      text-align: center;
      border-radius: 50px;
      transition: all 0.3s ease;
    }

    /* User Menu Styles */
    .user-menu {
      position: relative;
      display: inline-block;
    }
    
    .user-dropdown {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background: var(--background);
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
      border-radius: 12px;
      min-width: 200px;
      z-index: 1000;
      overflow: hidden;
      margin-top: 10px;
    }
    
    .user-dropdown a {
      display: block;
      padding: 12px 20px;
      text-decoration: none;
      color: var(--text-secondary);
      transition: all 0.3s ease;
      font-weight: 500;
      border-bottom: 1px solid #E2E8F0;
    }
    
    .user-dropdown a:hover {
      background: linear-gradient(135deg, var(--background), #E2E8F0);
      color: var(--secondary);
    }
    
    .user-dropdown a:last-child {
      border-bottom: none;
    }
    
    .user-menu:hover .user-dropdown {
      display: block;
    }
    
    /* Hero Section */
    .hero {
      min-height: 100vh;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      display: flex; 
      flex-direction: column; 
      align-items: center; 
      justify-content: center;
      text-align: center; 
      padding: 120px 20px 80px; 
      color: #fff;
      position: relative;
      overflow: hidden;
    }
    
    .hero-content {
      position: relative;
      z-index: 2;
      max-width: 1200px;
      width: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    
    .hero h1 {
      font-size: clamp(32px, 6vw, 72px);
      font-weight: 900;
      line-height: 1.1;
      margin-bottom: 24px;
      letter-spacing: -2px;
      text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
    }
    
    .hero h1 .highlight {
      color: var(--secondary);
      position: relative;
      display: inline-block;
    }
    
    .hero h1 .highlight::after {
      content: '';
      position: absolute;
      bottom: 5px;
      left: 0;
      width: 100%;
      height: 8px;
      background: linear-gradient(90deg, var(--secondary), var(--accent));
      opacity: 0.3;
      border-radius: 4px;
    }
    
    .hero p {
      font-size: clamp(16px, 3vw, 22px); 
      max-width: 700px; 
      margin: 0 auto 50px; 
      line-height: 1.6;
      font-weight: 400;
      opacity: 0.95;
    }
    
    /* Search Section */
    .hero-search {
      background: rgba(248, 250, 252, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 8px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
      margin: 40px auto;
      max-width: 600px;
      width: 100%;
      display: flex;
      align-items: center;
      transition: all 0.3s ease;
    }
    
    /* .hero-search:hover {
      transform: translateY(-2px);
      box-shadow: 0 25px 80px rgba(0, 0, 0, 0.15);
    } */
    
    .search-input {
      flex: 1;
      border: none;
      outline: none;
      padding: 18px 25px;
      font-size: 16px;
      font-weight: 500;
      color: var(--text-secondary);
      background: transparent;
      border-radius: 50px;
    }
    
    .search-input::placeholder {
      color: var(--text-secondary);
      font-weight: 400;
    }
    
    .location-input {
      border: none;
      outline: none;
      padding: 18px 20px;
      font-size: 16px;
      font-weight: 500;
      color: var(--text-secondary);
      background: transparent;
      border-left: 2px solid #E2E8F0;
      width: 150px;
    }
    
    .location-input::placeholder {
      color: var(--text-secondary);
      font-weight: 400;
    }
    
    .search-btn {
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      color: var(--background);
      border: none;
      padding: 18px 30px;
      border-radius: 15px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      white-space: nowrap;
      box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
    }
    
    .search-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
    }
    
    /* Action Buttons */
    .hero-buttons {
      display: flex; 
      gap: 20px; 
      flex-wrap: wrap;
      justify-content: center;
      margin-bottom: 60px;
    }
    
    .hero-buttons a {
      text-decoration: none; 
      padding: 16px 32px; 
      font-size: 16px; 
      font-weight: 600;
      border-radius: 15px; 
      transition: all 0.3s ease;
      white-space: nowrap;
      position: relative;
      overflow: hidden;
    }
    
    .post-task {
      background: rgba(248, 250, 252, 0.2);
      color: var(--background);
      border: 2px solid rgba(248, 250, 252, 0.3);
      backdrop-filter: blur(10px);
    }
    
    .post-task:hover {
      background: rgba(248, 250, 252, 0.9);
      color: var(--secondary);
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(248, 250, 252, 0.3);
    }
    
    .find-work {
      background: linear-gradient(135deg, var(--secondary), var(--accent));
      color: var(--background);
      border: none;
      box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
    }
    
    .find-work:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 35px rgba(14, 165, 233, 0.4);
    }
    
    /* Stats Section */
    .hero-stats {
      display: flex;
      gap: 40px;
      flex-wrap: wrap;
      justify-content: center;
      opacity: 0.9;
    }
    
    .stat-item {
      text-align: center;
      min-width: 120px;
    }
    
    .stat-number {
      font-size: clamp(24px, 5vw, 32px);
      font-weight: 800;
      margin-bottom: 5px;
      color: var(--secondary);
    }
    
    .stat-label {
      font-size: clamp(12px, 2vw, 14px);
      font-weight: 500;
      opacity: 0.8;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    /* Floating Elements */
    .floating-element {
      position: absolute;
      opacity: 0.1;
      pointer-events: none;
    }
    
    .floating-element.circle {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: linear-gradient(45deg, var(--secondary), var(--accent));
      animation: float 6s ease-in-out infinite;
    }
    
    .floating-element.square {
      width: 60px;
      height: 60px;
      background: linear-gradient(45deg, var(--accent), var(--secondary));
      transform: rotate(45deg);
      animation: float 8s ease-in-out infinite reverse;
    }
    
    .floating-element.triangle {
      width: 0;
      height: 0;
      border-left: 30px solid transparent;
      border-right: 30px solid transparent;
      border-bottom: 50px solid rgba(14, 165, 233, 0.7);
      animation: float 10s ease-in-out infinite;
    }
    
    .element-1 { top: 20%; left: 10%; animation-delay: 0s; }
    .element-2 { top: 60%; right: 15%; animation-delay: 2s; }
    .element-3 { bottom: 30%; left: 20%; animation-delay: 4s; }
    .element-4 { top: 40%; right: 30%; animation-delay: 1s; }
    .element-5 { bottom: 20%; right: 10%; animation-delay: 3s; }
    
    /* Animations */
    @keyframes float {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-20px) rotate(180deg); }
    }
    
    /* How it works section styles */
    .how-it-works {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, var(--background), #E2E8F0);
      padding: 60px 20px;
      color: #1a1a1a;
      margin: 0 auto;
    }
    .container {
      max-width: 900px;
      margin: 0 auto;
    }

    /* Header */
    .how-it-works .header h1 {
      font-size: clamp(2rem, 5vw, 3rem);
      font-weight: 900;
      margin-bottom: 10px;
      letter-spacing: 0.07em;
      color: var(--primary);
      text-transform: uppercase;
    }
    .how-it-works .header p {
      font-size: clamp(1rem, 3vw, 1.25rem);
      color: var(--text-secondary);
      max-width: 620px;
      margin: 0 auto 50px;
      font-weight: 600;
      line-height: 1.6;
      font-style: italic;
    }

    /* Steps container */
    .steps {
      display: flex;
      justify-content: space-between;
      gap: 40px;
      flex-wrap: wrap;
    }

    /* Each Step */
    .step {
      background: var(--background);
      border-radius: 20px;
      box-shadow: 0 12px 30px rgba(15, 23, 42, 0.1);
      padding: 30px 35px;
      flex: 1 1 300px;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: box-shadow 0.35s ease, transform 0.35s ease;
      cursor: default;
    }
    .step:hover {
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.15);
      transform: translateY(-6px);
    }

    /* Step Number */
    .step-number {
      font-size: clamp(2.5rem, 5vw, 3.5rem);
      font-weight: 900;
      color: var(--secondary);
      opacity: 0.12;
      align-self: flex-start;
      margin-bottom: 20px;
      font-family: 'Courier New', Courier, monospace;
      user-select: none;
    }

    /* Step Visual */
    .step-visual {
      margin-bottom: 25px;
      width: 100%;
      display: flex;
      justify-content: center;
    }

    /* Account Form mockup */
    .account-form {
      width: 100%;
      max-width: 220px;
      background: var(--background);
      border-radius: 12px;
      padding: 20px 25px;
      box-shadow: inset 0 0 10px rgba(14, 165, 233, 0.2);
      user-select: none;
    }
    .account-form h4 {
      margin-bottom: 15px;
      font-weight: 800;
      color: var(--primary);
      font-size: 1.15rem;
    }
    .form-field {
      height: 12px;
      background: #E2E8F0;
      border-radius: 6px;
      margin: 10px 0;
      box-shadow: inset 0 1px 3px rgba(14, 165, 233, 0.2);
    }
    .signup-btn {
      margin-top: 18px;
      background: var(--secondary);
      border: none;
      color: var(--background);
      font-weight: 700;
      padding: 10px 0;
      border-radius: 8px;
      width: 100%;
      cursor: pointer;
      transition: background-color 0.3s ease;
      font-size: 1rem;
    }
    .signup-btn:hover {
      background: var(--accent);
    }

    /* Search Box mockup */
    .search-container {
      width: 100%;
      max-width: 220px;
    }
    .search-box {
      display: flex;
      align-items: center;
      background: var(--background);
      padding: 10px 15px;
      border-radius: 12px;
      box-shadow: inset 0 0 10px rgba(14, 165, 233, 0.2);
      user-select: none;
    }
    .search-input-mock {
      flex-grow: 1;
      height: 16px;
      background: #E2E8F0;
      border-radius: 8px;
      margin-right: 12px;
      box-shadow: inset 0 1px 4px rgba(14, 165, 233, 0.2);
    }
    .search-icon {
      font-size: 1.5rem;
      color: var(--secondary);
      user-select: none;
    }

    /* Emoji container */
    .emoji-container {
      display: flex;
      gap: 18px;
      font-size: 2.5rem;
      user-select: none;
      justify-content: center;
      width: 100%;
      max-width: 220px;
    }

    /* Step Content */
    .step-content h3 {
      font-weight: 800;
      font-size: clamp(1.2rem, 3vw, 1.4rem);
      margin-bottom: 15px;
      color: var(--primary);
      text-align: center;
    }
    .step-content p {
      color: var(--text-secondary);
      font-size: clamp(0.95rem, 2vw, 1.05rem);
      line-height: 1.6;
      text-align: center;
      font-weight: 600;
    }

    /* Features Jobs Section */
    .features-jobs {
      background: var(--background);
      font-family: 'Poppins', sans-serif;
      padding: 80px 20px;
      margin: 0 auto;
      color: #1a1a1a;
    }
    
    .features-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 50px;
      max-width: 1200px;
      margin-left: auto;
      margin-right: auto;
    }
    
    .features-jobs .features-title {
      font-size: clamp(2rem, 5vw, 3rem);
      font-weight: 700;
      color: var(--primary);
      margin: 0;
      line-height: 1.2;
    }
    
    .view-all-btn {
      background: var(--primary);
      color: var(--background);
      padding: 12px 24px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
    }
    
    .view-all-btn:hover {
      background: var(--primary-light);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
    }
    
    .job-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 24px;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .job-card {
      background: var(--primary);
      border-radius: 16px;
      padding: 24px;
      color: var(--background);
      transition: all 0.3s ease;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }
    
    .job-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
      opacity: 0;
      transition: opacity 0.3s ease;
      pointer-events: none;
    }
    
    .job-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(15, 23, 42, 0.2);
    }
    
    .job-card:hover::before {
      opacity: 1;
    }
    
    .job-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 20px;
    }
    
    .company-info {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .company-logo {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 16px;
      color: var(--background);
      flex-shrink: 0;
    }
    
    .job-meta {
      display: flex;
      flex-direction: column;
    }
    
    .company-name {
      font-weight: 600;
      font-size: 14px;
      color: var(--background);
      margin-bottom: 2px;
    }
    
    .job-posted {
      font-size: 12px;
      color: var(--text-secondary);
    }
    
    .job-title {
      font-size: clamp(18px, 3vw, 20px);
      font-weight: 700;
      color: var(--background);
      margin-bottom: 12px;
      line-height: 1.3;
    }
    
    .job-location {
      font-size: 14px;
      color: var(--text-secondary);
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .job-details {
      display: flex;
      gap: 16px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    
    .job-type, .job-mode {
      background: rgba(248, 250, 252, 0.1);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
      color: var(--background);
      border: 1px solid rgba(248, 250, 252, 0.2);
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .job-category {
      background: rgba(14, 165, 233, 0.2);
      color: var(--background);
      padding: 8px 16px;
      border-radius: 6px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: 1px;
      text-transform: uppercase;
      align-self: flex-start;
      border: 1px solid rgba(14, 165, 233, 0.3);
    }
    
    /* Hover effects for individual cards */
    .job-card:nth-child(1):hover {
      box-shadow: 0 20px 40px rgba(255, 107, 107, 0.3);
    }
    
    .job-card:nth-child(2):hover {
      box-shadow: 0 20px 40px rgba(16, 172, 132, 0.3);
    }
    
    .job-card:nth-child(3):hover {
      box-shadow: 0 20px 40px rgba(95, 39, 205, 0.3);
    }
    
    .job-card:nth-child(4):hover {
      box-shadow: 0 20px 40px rgba(253, 121, 168, 0.3);
    }
    
    .job-card:nth-child(5):hover {
      box-shadow: 0 20px 40px rgba(243, 156, 18, 0.3);
    }
    
    .job-card:nth-child(6):hover {
      box-shadow: 0 20px 40px rgba(0, 184, 148, 0.3);
    }

    /* Footer */
    .site-footer {
      background: var(--background);
      padding: 40px 20px 20px;
      font-family: 'Poppins', sans-serif;
      color: var(--primary);
      border-top: 1px solid #E2E8F0;
      text-align: center;
    }

    .footer-container {
      max-width: 1100px;
      margin: 0 auto;
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 20px;
    }

    .footer-brand {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--secondary);
    }

    .footer-links {
      display: flex;
      gap: 25px;
    }

    .footer-links a {
      text-decoration: none;
      color: var(--text-secondary);
      font-weight: 500;
      transition: color 0.3s ease;
    }

    .footer-links a:hover {
      color: var(--secondary);
    }

    .footer-social a {
      color: var(--secondary);
      margin: 0 10px;
      font-size: 1.4rem;
      transition: color 0.3s ease;
    }

    .footer-social a:hover {
      color: var(--accent);
    }

    .footer-bottom {
      margin-top: 30px;
      font-size: 0.95rem;
      color: var(--text-secondary);
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
      header {
        padding: 15px 40px;
      }
      
      .nav-links {
        gap: 30px;
      }
      
      .hero-search {
        max-width: 500px;
      }
      
      .job-cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      }
    }
    
    @media (max-width: 768px) {
      header {
        padding: 15px 20px;
        justify-content: space-between;
      }
      
      .logo {
        font-size: 28px;
      }
      
      .nav-links, .auth-buttons {
        display: none;
      }
      
      .menu-toggle {
        display: flex;
      }
      
      .hero {
        padding: 100px 20px 60px;
      }
      
      .hero-search {
        flex-direction: column;
        border-radius: 20px;
        padding: 20px;
        gap: 15px;
        margin: 30px auto;
      }
      
      .search-input, .location-input {
        width: 100%;
        border: none;
        background: white;
        border-radius: 12px;
        padding: 15px 20px;
      }
      
      .search-btn {
        width: 100%;
        border-radius: 12px;
        padding: 15px 20px;
      }
      
      .hero-buttons {
        flex-direction: column;
        align-items: center;
        gap: 15px;
        margin-bottom: 40px;
      }
      
      .hero-buttons a {
        width: 100%;
        max-width: 300px;
        text-align: center;
      }
      
      .hero-stats {
        gap: 20px;
      }
      
      .stat-item {
        min-width: 80px;
      }
      
      .floating-element {
        display: none;
      }

      .steps {
        flex-direction: column;
        gap: 40px;
      }
      
      .step {
        max-width: 100%;
      }

      .features-header {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        margin-bottom: 30px;
      }
      
      .job-cards-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }
      
      .job-details {
        flex-direction: column;
        gap: 8px;
      }
      
      .job-type, .job-mode {
        align-self: flex-start;
      }

      .footer-container {
        flex-direction: column;
        align-items: center;
        text-align: center;
      }
      
      .footer-links {
        flex-wrap: wrap;
        justify-content: center;
      }
    }
    
    @media (max-width: 480px) {
      .logo {
        font-size: 24px;
      }
      
      .hero {
        padding: 90px 15px 50px;
      }
      
      .hero h1 {
        font-size: clamp(28px, 8vw, 42px);
      }
      
      .hero p {
        font-size: 16px;
        margin-bottom: 30px;
      }
      
      .hero-search {
        padding: 15px;
        margin: 20px auto;
      }
      
      .search-input, .location-input {
        padding: 12px 15px;
        font-size: 14px;
      }
      
      .search-btn {
        padding: 12px 15px;
        font-size: 14px;
      }
      
      .hero-buttons a {
        padding: 14px 28px;
        font-size: 14px;
      }
      
      .hero-stats {
        flex-direction: column;
        gap: 15px;
      }
      
      .stat-item {
        min-width: 100px;
      }
      
      .stat-number {
        font-size: 24px;
      }
      
      .stat-label {
        font-size: 12px;
      }
      
      .how-it-works {
        padding: 40px 15px;
      }
      
      .features-jobs {
        padding: 60px 15px;
      }
      
      .job-card {
        padding: 20px;
      }
      
      .footer-links {
        gap: 15px;
      }
    }
    
    @media (max-width: 360px) {
      .hero {
        padding: 80px 10px 40px;
      }
      
      .hero-search {
        padding: 12px;
      }
      
      .hero-buttons a {
        width: 100%;
        max-width: 280px;
      }
      
      .how-it-works .header h1 {
        font-size: 1.8rem;
      }
      
      .features-jobs .features-title {
        font-size: 1.8rem;
      }
      
      .step {
        padding: 25px 20px;
      }
      
      .job-card {
        padding: 18px;
      }
    }
  </style>
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