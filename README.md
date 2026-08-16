# Dan Chautari — Sahayogko Chautari, Aashako Yatra

Dan Chautari ("The Platform of Help, The Journey of Hope") is a premium, fully modular PHP-based web application. It connects compassionate donors with ground-level community service volunteers and verified fundraising campaigns across Nepal.

---

## 📁 Folder Structure

The application is structured into clear, modular divisions:

```text
dan-chautari/
│
├── 📁 assets/
│   ├── 📁 css/
│   │   ├── style.css         # Global styles & layout variables (Header, Footer, Reset)
│   │   ├── home.css          # Landing, services, and content page layout styles
│   │   └── auth.css          # Forms, profile dashboards, and administrative tables
│   │
│   ├── 📁 js/
│   │   ├── main.js           # Flash message timers and general client animations
│   │   ├── auth.js           # Client-side form & password matches validations
│   │   └── navbar.js         # Mobile hamburger menu triggers & scroll dynamics
│   │
│   └── 📁 images/
│       └── logo.png          # App brand identity logo
│
├── 📁 includes/
│   ├── db.php                # Secure PDO database connection with setup guidance
│   ├── header.php            # Dynamic global navbar shell with session states
│   ├── footer.php            # Shared layout footer and client script loaders
│   └── config.php            # Global constants, sessions, and notification builders
│
├── 📁 auth/
│   ├── login.php             # User login portal card
│   ├── signup.php            # User registration (includes volunteer signup options)
│   ├── logout.php            # Clears credentials and active sessions
│   └── auth_handler.php      # Password hashing, SQL validations, and session bindings
│
├── 📁 pages/
│   ├── index.php             # Dynamic home page pulling dynamic database aggregates
│   ├── about.php             # Vision, background timeline, and core principles
│   ├── services.php          # Programs list (Education support, emergency relief)
│   ├── donate.php            # Simulated donation processor and official receipt invoicing
│   ├── contact.php           # Ground-level inquiry form saving messages to DB
│   └── profile.php           # User center with toggle volunteer status & donation logs
│
├── 📁 admin/
│   ├── login.php             # Dedicated administrative entry gateway check
│   ├── dashboard.php         # Admin dashboard showing stats and recent inquiry logs
│   ├── manage_users.php      # User table control (volunteer toggling, deletes)
│   └── manage_donations.php  # Transaction ledgers (approvals, safe metrics updates)
│
├── 📁 database/
│   └── dan_chautari.sql      # MySQL schema setup and default sample seeds
│
├── index.php                 # Root entry redirection script
└── README.md                 # Deployment & developer credentials guide
```

---

## 🛠️ Database & System Setup

To run the application locally:

### 1. Database Configuration
1. Start your local MySQL server (using XAMPP, WAMP, Docker, or native services).
2. Open your database administration portal (such as **phpMyAdmin**) or MySQL CLI, and create a database named `dan_chautari`:
   ```sql
   CREATE DATABASE `dan_chautari` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import the SQL seeding schema file located in `database/dan_chautari.sql` to instantly build the tables and insert test records.
4. Verify your local MySQL connection settings inside [includes/db.php](file:///home/shrestha/Desktop/Dan%20Chautari/includes/db.php):
   ```php
   $db_host = 'localhost';
   $db_name = 'dan_chautari';
   $db_user = 'root'; // Change if yours differs
   $db_pass = '';     // Change if yours has a password
   ```

### 2. Local Server Deployment
Launch PHP's built-in lightweight development server from the root of the workspace directory:
```bash
php -S localhost:8000
```
Open [http://localhost:8000](http://localhost:8000) in your web browser. You will be automatically redirected to the landing home page!

---

## 🔑 Pre-Seeded Test Credentials

We have seeded rich mock records into the SQL script so you can test all roles immediately:

### 1. System Administrator
- **Email:** `admin@danchautari.org`
- **Password:** `admin123`
- *Accesses administrative dashboards, user permissions, volunteer status, and transaction ledger controls.*

### 2. Regular Donor User
- **Email:** `user@danchautari.org`
- **Password:** `user123`
- *Accesses personal profiles showing contribution transaction logs, receipt metrics, and interactive volunteer self-enrollment toggles.*

### 3. Registered Volunteer User
- **Email:** `volunteer@danchautari.org`
- **Password:** `volunteer123`
- *Accesses profile dashboards showing pre-configured volunteer badge states.*
