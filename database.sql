CREATE DATABASE IF NOT EXISTS moneytracker;
USE moneytracker;

CREATE TABLE accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100),
  password VARCHAR(255),
  email VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  name VARCHAR(100),
  INDEX idx_categories_user_id (user_id)
);

CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  category_id INT,
  amount DECIMAL(10,2),
  type ENUM('expense', 'income') NOT NULL DEFAULT 'expense',
  description VARCHAR(255),
  date DATE,
  is_recurring TINYINT(1) DEFAULT 0,
  recurring_interval ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT NULL,
  recurring_duration INT DEFAULT NULL,
  recurring_end_date DATE DEFAULT NULL,
  parent_id INT DEFAULT NULL,
  paid TINYINT(1) DEFAULT 0,
  paid_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expenses_user_id (user_id),
  INDEX idx_expenses_category_id (category_id),
  INDEX idx_expenses_date (date),
  INDEX idx_expenses_parent_id (parent_id),
  INDEX idx_expenses_user_date (user_id, date),
  INDEX idx_expenses_user_type (user_id, type)
);

CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100),
  ip_address VARCHAR(45),
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

