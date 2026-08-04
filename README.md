# 💰 Money Tracker

A simple, self-hosted personal expense & income tracker built with **PHP** and **MySQL**, styled with **Tailwind CSS** (via CDN). Features include dashboard analytics, category management, recurring records, charts, and a secure login system.

## 📁 Project Structure

```
moneytracker/
├── index.php               # Entry point — redirects to public/login.php
├── database.sql            # Database schema (reference only)
├── config/
│   ├── db.example.php      # Database config template (copy to db.php)
│   └── db.php              # ⚠️ Real credentials — NOT committed (gitignored)
├── includes/
│   └── helpers.php         # Security, CSRF, session, nav helpers
└── public/                 # All application files (PHP + assets)
    ├── index.php           # Dashboard
    ├── login.php           # Login
    ├── register.php        # Register account
    ├── logout.php          # Logout
    ├── add.php             # Add expense/income record
    ├── edit.php            # Edit record
    ├── delete.php          # Delete record
    ├── categories.php      # Manage categories
    ├── recurring.php       # Manage recurring records
    ├── process_recurring.php # Recurring record generator
    ├── charts.php          # Analytics charts
    └── profile.php         # Change username/password
```

## 🚀 Local Setup (XAMPP)

1. **Place the project** in your web root, e.g. `C:\xampp\htdocs\moneytracker` (or via git clone).
2. **Create the database config:**
   ```bash
   cp config/db.example.php config/db.php
   ```
   Then edit `config/db.php` with your local credentials:
   ```php
   $host = "localhost";
   $dbname = "moneytracker";
   $dbuser = "root";
   $dbpass = "";
   ```
3. **Create the database:** Open `database.sql` and run it in phpMyAdmin (or import it).
4. **Run the app:** Visit `http://localhost/moneytracker/` — the root `index.php` redirects to `public/login.php`.

## ☁️ Deploying to InfinityFree

InfinityFree provides free PHP + MySQL hosting. Your existing database data **will be preserved** — this restructure only changes the file layout, not the database.

### 1. Upload the files
- Zip the project folder (excluding `.git`, `config/db.php`, and `TODO.md`).
- Upload & extract into your `htdocs` folder via the **File Manager** in the InfinityFree control panel.

### 2. Configure the database
1. In your InfinityFree control panel, go to **MySQL Databases** and create a database.
2. Copy the **hostname** (usually `sql###.infinityfree.com`), **database name**, **username**, and **password**.
3. Edit `config/db.php` on the server with these values:
   ```php
   $host = "sql###.infinityfree.com";  // your InfinityFree host
   $dbname = "if0_###_moneytracker";   // your DB name
   $dbuser = "if0_###";                // your DB user
   $dbpass = "your-password";
   ```

### 3. Import/verify your database
> ⚠️ **Do NOT run `database.sql` on your production database if you already have data.** It's a fresh-install reference only.

- If you're **migrating existing data**, your current tables (`accounts`, `categories`, `expenses`) are already correct — no import needed.
- If this is a **fresh install**, import `database.sql` once via phpMyAdmin.

### 4. Visit your site
Your app will be at `https://your-account.byethost.com/` (or your linked domain). The root `index.php` automatically redirects to the login page.

## 🔒 Security Notes

- `config/db.php` contains your credentials and is **ignored by git** (see `.gitignore`).
- Passwords are hashed with `password_hash()`.
- Forms and state-changing actions are protected against **CSRF**.
- All output is escaped against **XSS** via the `e()` helper.

## 🧱 Tech Stack

- **PHP 7+** (PDO prepared statements)
- **MySQL**
- **Tailwind CSS** (CDN)
- **Flatpickr** (date picker)
- **Chart.js** (charts)

