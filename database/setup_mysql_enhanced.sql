-- Enhanced MySQL Database Setup with New Features
-- Run this script to create the enhanced database structure

CREATE DATABASE IF NOT EXISTS pricing_tracker;
USE pricing_tracker;

-- Users table with role-based access
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'viewer') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Vendors table for vendor management
CREATE TABLE IF NOT EXISTS vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_name VARCHAR(255) NOT NULL,
    contact_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Enhanced products table with all new fields
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    actual_price DECIMAL(10, 2) NOT NULL,
    markup_percentage DECIMAL(5, 2) NOT NULL,
    selling_price DECIMAL(10, 2) NOT NULL,
    profit DECIMAL(10, 2) NOT NULL,
    quantity INT DEFAULT 1,
    total_profit DECIMAL(10, 2) NOT NULL,
    product_url TEXT,
    product_image VARCHAR(500),
    notes TEXT,
    date_added DATE NOT NULL,
    status ENUM('active', 'sold', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
);

-- Sales tracking table
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    quantity_sold INT NOT NULL,
    sale_price DECIMAL(10, 2) NOT NULL,
    actual_profit DECIMAL(10, 2) NOT NULL,
    sale_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Markup presets table
CREATE TABLE IF NOT EXISTS markup_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preset_name VARCHAR(100) NOT NULL,
    markup_percentage DECIMAL(5, 2) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_user_products ON products(user_id);
CREATE INDEX idx_user_vendors ON vendors(user_id);
CREATE INDEX idx_product_sales ON sales(product_id);
CREATE INDEX idx_user_sales ON sales(user_id);
CREATE INDEX idx_product_status ON products(status);
CREATE INDEX idx_date_added ON products(date_added);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password_hash, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin')
ON DUPLICATE KEY UPDATE username = username;

-- Insert default markup presets
INSERT INTO markup_presets (user_id, preset_name, markup_percentage, is_default) 
SELECT 1, 'Low Margin', 10.00, FALSE
WHERE NOT EXISTS (SELECT 1 FROM markup_presets WHERE preset_name = 'Low Margin');

INSERT INTO markup_presets (user_id, preset_name, markup_percentage, is_default) 
SELECT 1, 'Standard', 25.00, TRUE
WHERE NOT EXISTS (SELECT 1 FROM markup_presets WHERE preset_name = 'Standard');

INSERT INTO markup_presets (user_id, preset_name, markup_percentage, is_default) 
SELECT 1, 'High Margin', 50.00, FALSE
WHERE NOT EXISTS (SELECT 1 FROM markup_presets WHERE preset_name = 'High Margin');

INSERT INTO markup_presets (user_id, preset_name, markup_percentage, is_default) 
SELECT 1, 'Premium', 100.00, FALSE
WHERE NOT EXISTS (SELECT 1 FROM markup_presets WHERE preset_name = 'Premium');
