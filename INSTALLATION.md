# 📚 Online Examination System - Complete Installation Guide

A complete web-based examination system with automated grading, certificate generation, AI-powered programming practice, and comprehensive reporting.

## 📋 Table of Contents
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Database Setup](#database-setup)
- [API Keys Setup](#api-keys-setup-optional)
- [Default Accounts](#default-accounts)
- [Folder Structure](#folder-structure)
- [Access URLs](#access-urls)
- [Troubleshooting](#troubleshooting)
- [Security Recommendations](#security-recommendations)
- [Testing Checklist](#testing-checklist)
- [License](#license)

## ✨ Features

### Student Features
- 📝 Take online exams with randomized questions
- 🔄 Unlimited exam retakes
- 📊 Instant results with detailed feedback
- 🎓 Automatic certificate generation for passed exams
- 💻 AI-powered programming practice module
- 📚 Study materials access
- 🏆 Leaderboard and achievements system
- 👤 Profile management with avatar upload

### Admin Features
- 👥 Student management (CRUD operations)
- 📋 Exam creation and management
- ❓ Question bank management
- 📎 Assign questions to exams
- 📈 Results viewing and analytics
- 📢 Announcement system
- 📁 Study materials management
- ⚙️ System configuration

## 💻 Requirements

### Server Requirements
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependency management)

### PHP Extensions Required
- `curl` - For API calls
- `mysqli` - Database connection
- `gd` - Image processing
- `mbstring` - String handling
- `zip` - File compression
- `json` - Data encoding

### Recommended Setup
- **Local Development:** XAMPP / WAMP / MAMP
- **Production:** Linux server with Apache/Nginx

## 🔧 Installation

### Step 1: Clone or Download

```bash
# Clone the repository
git clone https://github.com/yourusername/online-exam-system.git

# Or download ZIP and extract


Step 2: Move to Web Directory

XAMPP (Windows):
bash

move online-exam-system C:\xampp\htdocs\

WAMP (Windows):
bash

move online-exam-system C:\wamp64\www\

Linux (Apache):
bash

sudo mv online-exam-system /var/www/html/

MAMP (Mac):
bash

mv online-exam-system /Applications/MAMP/htdocs/

Step 3: Install Dependencies

Navigate to the project directory and install Composer dependencies:
bash

cd /path/to/online-exam-system
composer install

    Note: If you don't have Composer, download it from getcomposer.org

Step 4: Set File Permissions (Linux/Mac only)
bash

chmod 755 uploads/
chmod 755 uploads/avatars/
chmod 755 uploads/background-Image/
chmod 755 uploads/materials/

Step 5: Create Database

Open phpMyAdmin or MySQL command line and run:
sql

CREATE DATABASE online_exam_system;
CREATE USER 'exam_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON online_exam_system.* TO 'exam_user'@'localhost';
FLUSH PRIVILEGES;

Step 6: Import Database Schema

Import the SQL file using phpMyAdmin or command line:

Using phpMyAdmin:

    Open http://localhost/phpmyadmin

    Select your database

    Click "Import" tab

    Choose database/schema.sql file

    Click "Go"

Using command line:
bash

mysql -u username -p online_exam_system < database/schema.sql

⚙️ Configuration
Step 1: Environment Configuration

Copy the example environment file and edit it:
bash

cp config/.env.example config/.env

Edit config/.env with your settings:
ini

# Database Configuration
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=online_exam_system

# Site URL (change for production)
SITE_URL=http://localhost/online-exam-system

# Email toggle (1=enabled, 0=disabled)
# Set to 0 to disable all email notifications during development
ENABLE_EMAILS=0

# Site Name
SITE_NAME=Online Examination System

Step 2: Web Server Configuration

For Apache (XAMPP/WAMP):
The .htaccess file is already included. Make sure mod_rewrite is enabled:
bash

# Enable mod_rewrite (if not already enabled)
sudo a2enmod rewrite
sudo systemctl restart apache2

For Nginx:
Add this to your server block:
nginx

location /online-exam-system {
    try_files $uri $uri/ /index.php?$args;
}

Step 3: Update Base Path (if needed)

If your project is in a subdirectory, update .htaccess:
apache

RewriteBase /online-exam-system/

🔑 API Keys Setup (Optional)
Brevo Email API (for Email Notifications)

    Sign up at Brevo

    Go to SMTP & API settings

    Copy your API key

    Add to config/.env:

ini

BREVO_API_KEY=your_api_key_here
BREVO_SENDER_EMAIL=noreply@yourapp.com
BREVO_SENDER_NAME=Your App Name
ENABLE_EMAILS=1

Google Gemini AI (for AI Features)

    Go to Google AI Studio

    Create an API key

    Add to config/.env:

ini

GEMINI_API_KEY=your_gemini_api_key_here

    Note: Without these API keys, email and AI features will be disabled. The system will still work for basic exam functionality.

👤 Default Accounts

After installation, create your admin account:
Option 1: Via Database (phpMyAdmin)
sql

INSERT INTO users (username, email, password, full_name, role, status) 
VALUES ('admin', 'admin@example.com', '$2y$10$YourHashedPassword', 'Administrator', 'admin', 1);

    Use password_hash('your_password', PASSWORD_DEFAULT) to generate hash

Option 2: Via Registration

    Register as student first at http://localhost/online-exam-system/student/register.php

    Manually change role to 'admin' in database

Default Student Account

    Register at: http://localhost/online-exam-system/student/register.php

## 📂 Folder Structure


online-exam-system/
│
├── 📁 admin/ # Admin panel files
│ ├── admin-management.php # Manage admin users and roles
│ ├── ai-generator.php # AI question generator
│ ├── announcements.php # Manage announcements
│ ├── assign-questions.php # Assign questions to exams
│ ├── bulk-import.php # Bulk import questions via CSV
│ ├── chatbot-settings.php # AI chatbot configuration
│ ├── clear-email-logs.php # Clear email notification logs
│ ├── dashboard.php # Admin dashboard
│ ├── edit-exam.php # Edit exam details
│ ├── edit-question.php # Edit question
│ ├── email-settings.php # Email notification toggle
│ ├── exam-schedule.php # Schedule exams
│ ├── exams.php # Manage exams
│ ├── export-report.php # Export student reports
│ ├── export-results.php # Export exam results
│ ├── forgot-password.php # Admin password reset
│ ├── index.php # Admin landing page
│ ├── login.php # Admin login
│ ├── logout.php # Admin logout
│ ├── profile.php # Admin profile management
│ ├── question-analytics.php # Question performance analytics
│ ├── questions.php # Manage questions
│ ├── reset-password.php # Reset admin password
│ ├── results.php # View exam results
│ ├── settings.php # System settings
│ ├── sidebar.php # Admin sidebar navigation
│ ├── study-materials.php # Manage study materials
│ ├── subjects.php # Manage subjects & topics
│ ├── update-exam.php # Update exam handler
│ ├── update-question.php # Update question handler
│ ├── users.php # Manage students
│ └── view-student.php # View student details
│
├── 📁 api/ # API endpoints
│ ├── chatbot.php # AI chatbot API
│ └── get-topics.php # Get topics by subject
│
├── 📁 assets/ # Static assets
│ ├── 📁 css/
│ │ └── style.css # Main stylesheet
│ └── 📁 js/
│ └── main.js # Main JavaScript file
│
├── 📁 config/ # Configuration files
│ ├── .env # Environment variables (create from .env.example)
│ ├── .env.example # Environment template
│ ├── chatbot.php # Chatbot configuration
│ ├── database.php # Database connection
│ ├── email.php # Email configuration
│ ├── gemini.php # Google Gemini AI config
│ └── loadenv.php # Environment loader
│
├── 📁 database/ # Database files (create this folder)
│ └── schema.sql # Database schema (export from phpMyAdmin)
│
├── 📁 includes/ # Core includes
│ ├── auth.php # Authentication functions
│ ├── chat-widget.php # Floating chat widget
│ ├── EmailHelper.php # Email helper class
│ ├── footer.php # Page footer
│ ├── functions.php # Core helper functions
│ ├── header.php # Page header
│ └── permissions.php # Permission management
│
├── 📁 student/ # Student portal files
│ ├── achievements.php # Badges and achievements
│ ├── ai-tutor.php # AI learning assistant
│ ├── certificate.php # View/download certificates
│ ├── change-password.php # Change password
│ ├── dashboard.php # Student dashboard
│ ├── exams.php # Available exams
│ ├── forgot-password.php # Password reset request
│ ├── index.php # Student landing
│ ├── leaderboard.php # Rankings leaderboard
│ ├── login.php # Student login
│ ├── logout.php # Student logout
│ ├── materials.php # Study materials
│ ├── privacy.php # Privacy policy
│ ├── profile.php # Student profile
│ ├── programming.php # Programming practice module
│ ├── register.php # Student registration
│ ├── reset-password.php # Reset password
│ ├── results.php # View exam results
│ ├── retake-exam.php # Retake exam handler
│ ├── sidebar.php # Student sidebar
│ ├── submit-exam.php # Submit exam handler
│ └── take-exam.php # Take exam interface
│
├── 📁 uploads/ # User uploaded files
│ ├── 📁 avatars/ # Profile pictures
│ ├── 📁 background-Image/ # Login page backgrounds
│ └── 📁 materials/ # Study materials files
│
├── 📁 vendor/ # Composer dependencies (auto-generated)
│ ├── autoload.php # Composer autoloader
│ ├── 📁 brick/ # Math library
│ ├── 📁 composer/ # Composer autoloader files
│ ├── 📁 dompdf/ # PDF generation
│ ├── 📁 firebase/ # JWT authentication
│ ├── 📁 google/ # Google API libraries
│ ├── 📁 google-gemini-php/ # Gemini AI client
│ ├── 📁 grpc/ # gRPC library
│ ├── 📁 guzzlehttp/ # HTTP client
│ ├── 📁 maennchen/ # ZipStream for Excel
│ ├── 📁 markbaker/ # Math functions
│ ├── 📁 masterminds/ # HTML5 parser
│ ├── 📁 php-http/ # HTTP client abstraction
│ ├── 📁 phpoffice/ # Excel export (PhpSpreadsheet)
│ ├── 📁 psr/ # PHP Standards Recommendations
│ ├── 📁 ralouphie/ # Get all headers
│ ├── 📁 ramsey/ # UUID generation
│ ├── 📁 rumenx/ # PHP Chatbot library
│ ├── 📁 sabberworm/ # CSS parser
│ ├── 📁 symfony/ # Symfony components
│ └── 📁 thecodingmachine/ # Safe PHP functions
│
├── .gitignore # Git ignore file
├── .htaccess # Apache configuration
├── 404.php # 404 error page
├── composer.json # Composer dependencies
├── composer.lock # Composer lock file
├── debug-gemini.php # Gemini API debug (remove in production)
├── generate-hash.php # Password hash generator (remove in production)
├── index.php # Main landing page
├── INSTALLATION.md # This installation guide
└── project_structure.txt # Project structure reference

🚀 Access URLs
Portal	URL
Landing Page	http://localhost/online-exam-system/
Student Login	http://localhost/online-exam-system/student/login.php
Student Register	http://localhost/online-exam-system/student/register.php
Admin Login	http://localhost/online-exam-system/admin/login.php
Programming Practice	http://localhost/online-exam-system/student/programming.php
Certificates	http://localhost/online-exam-system/student/certificate.php
Leaderboard	http://localhost/online-exam-system/student/leaderboard.php
🔧 Troubleshooting
Database Connection Error

Error: Connection failed: Access denied for user
Solution: Check database credentials in config/.env
White Screen / PHP Errors

Solution: Enable error reporting temporarily:
php

error_reporting(E_ALL);
ini_set('display_errors', 1);

Add this to the top of index.php
404 Not Found

Solution:

    Check .htaccess file exists

    Enable mod_rewrite in Apache

    Update RewriteBase in .htaccess

Email Not Sending

Solution:

    Check ENABLE_EMAILS=1 in .env

    Verify Brevo API key is correct

    Check spam folder

    Enable error logging

AI Features Not Working

Solution:

    Verify Gemini API key in .env

    Check API quota limits

    Enable cURL extension in PHP

Session Issues

Solution:
apache

# Add to .htaccess
php_value session.save_path "/tmp"

Certificate Generation Issues

Solution:

    Check dompdf library is installed: composer require dompdf/dompdf

    Check uploads folder permissions

    Enable GD extension in PHP

File Upload Issues

Solution:

    Check uploads folder permissions (755)

    Check PHP upload limits in php.ini

    Check file size limits

🔒 Security Recommendations

    Change default database credentials before production

    Update PHP and MySQL to latest versions

    Change default admin password after first login

    Enable firewall on production server

    Use strong passwords for all accounts

    Regular security audits

📊 Testing Checklist

After installation, verify these features work:

    Landing page loads with features section

    Student registration creates account

    Student login redirects to dashboard

    Admin login redirects to admin panel

    Create a subject in admin panel

    Create questions with options

    Create an exam with title and duration

    Assign questions to exam

    Student can view available exams

    Student can start and take exam

    Timer counts down correctly

    Student can submit exam

    Results display correctly

    Certificate generates for passed exams

    Certificate can be downloaded as PDF

    Programming challenges load

    AI assistant responds (if API key configured)

    Study materials can be uploaded and viewed

    Announcements display for students

    Leaderboard shows rankings

    Email notifications work (if configured)

    Password reset works

    Profile picture upload works

