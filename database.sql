CREATE DATABASE IF NOT EXISTS moneytracker;
USE moneytracker;

CREATE TABLE accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100),
  password VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  name VARCHAR(100)
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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);