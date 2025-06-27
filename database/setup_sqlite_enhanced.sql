-- Enhanced SQLite Database Setup with New Features

-- Users table with role-based access
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT DEFAULT 'admin' CHECK(role IN ('admin', 'viewer')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Vendors table for vendor management
CREATE TABLE IF NOT EXISTS vendors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    vendor_name TEXT NOT NULL,
    contact_info TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Enhanced products table with all new fields
CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    vendor_id INTEGER NULL,
    product_name TEXT NOT NULL,
    actual_price REAL NOT NULL,
    markup_percentage REAL NOT NULL,
    selling_price REAL NOT NULL,
    profit REAL NOT NULL,
    quantity INTEGER DEFAULT 1,
    total_profit REAL NOT NULL,
    product_url TEXT,
    product_image TEXT,
    notes TEXT,
    date_added DATE NOT NULL,
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'sold', 'discontinued')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE SET NULL
);

-- Sales tracking table
CREATE TABLE IF NOT EXISTS sales (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    quantity_sold INTEGER NOT NULL,
    sale_price REAL NOT NULL,
    actual_profit REAL NOT NULL,
    sale_date DATE NOT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Markup presets table
CREATE TABLE IF NOT EXISTS markup_presets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    preset_name TEXT NOT NULL,
    markup_percentage REAL NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_user_products ON products(user_id);
CREATE INDEX IF NOT EXISTS idx_user_vendors ON vendors(user_id);
CREATE INDEX IF NOT EXISTS idx_product_sales ON sales(product_id);
CREATE INDEX IF NOT EXISTS idx_user_sales ON sales(user_id);
CREATE INDEX IF NOT EXISTS idx_product_status ON products(status);
CREATE INDEX IF NOT EXISTS idx_date_added ON products(date_added);

-- Insert default admin user (password: admin123)
INSERT OR IGNORE INTO users (username, password_hash, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default markup presets
INSERT OR IGNORE INTO markup_presets (user_id, preset_name, markup_percentage, is_default) VALUES 
(1, 'Low Margin', 10.00, 0),
(1, 'Standard', 25.00, 1),
(1, 'High Margin', 50.00, 0),
(1, 'Premium', 100.00, 0);
