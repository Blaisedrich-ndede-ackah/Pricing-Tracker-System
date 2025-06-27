# Pricing Tracker System

A fully functional product pricing and profit tracker system built with vanilla HTML, CSS, JavaScript, and PHP with MySQL/SQLite database support.

## Features

- **User Authentication**: Secure login system with password hashing
- **Product Management**: Add, edit, delete, and view products
- **Automatic Calculations**: Selling price and profit calculated automatically
- **Search & Sort**: Find and organize products easily
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Clean Interface**: Intuitive design for daily business use
- **Customizable**: Well-commented code for easy modifications

## Installation

### Requirements
- Web server (Apache/Nginx) with PHP 7.4+
- MySQL 5.7+ OR SQLite 3+
- Modern web browser

### Setup Instructions

1. **Upload Files**
   - Upload all files to your web server directory
   - Ensure the `database/` folder has write permissions for SQLite

2. **Configure Database**
   - Edit `api/config.php` to set your database preferences
   - For MySQL: Update DB_HOST, DB_NAME, DB_USER, DB_PASS
   - For SQLite: Ensure SQLITE_PATH is writable

3. **Initialize Database**
   - Visit `setup.php` in your browser
   - This will create the necessary tables and default user

4. **Login**
   - Go to `index.html`
   - Default credentials: admin / admin123
   - Change the password after first login

## File Structure

\`\`\`
pricing-tracker/
├── api/
│   ├── config.php          # Database configuration
│   ├── auth.php           # Authentication endpoints
│   └── products.php       # Product CRUD endpoints
├── database/
│   ├── setup_mysql.sql    # MySQL database schema
│   └── setup_sqlite.sql   # SQLite database schema
├── index.html             # Login page
├── dashboard.html         # Main application
├── styles.css            # All styling
├── script.js             # Frontend JavaScript
├── setup.php             # Database setup script
└── README.md             # This file
\`\`\`

## Usage

### Adding Products
1. Click "Add New Product" button
2. Fill in product name, actual price, and markup percentage
3. Selling price and profit are calculated automatically
4. Optionally add a product URL
5. Click "Save Product"

### Managing Products
- **Search**: Use the search box to find products by name or URL
- **Sort**: Use the dropdown to sort by various criteria
- **Edit**: Click "Edit" button on any product row
- **Delete**: Click "Delete" button (with confirmation)

### Calculations
- **Selling Price** = Actual Price × (1 + Markup %)
- **Profit** = Selling Price - Actual Price

## Customization

### Adding New Fields
1. Update database schema in `database/setup_*.sql`
2. Modify the products table structure
3. Update `api/products.php` to handle new fields
4. Add form fields in `dashboard.html`
5. Update JavaScript in `script.js`

### Styling Changes
- All styles are in `styles.css`
- Uses CSS Grid and Flexbox for responsive layout
- CSS custom properties for easy color theming

### Database Switching
- Change `DB_TYPE` in `api/config.php`
- Run appropriate setup script
- No code changes needed

## Security Features

- Password hashing with PHP's `password_hash()`
- SQL injection prevention with prepared statements
- XSS protection with HTML escaping
- Session-based authentication
- CSRF protection through same-origin policy

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `api/config.php`
   - Ensure database server is running
   - Verify file permissions for SQLite

2. **Setup Script Errors**
   - Make sure PHP has write permissions
   - Check if database already exists
   - Verify SQL syntax for your database type

3. **Login Issues**
   - Clear browser cache and cookies
   - Check if sessions are enabled in PHP
   - Verify default user was created during setup

4. **Products Not Loading**
   - Check browser console for JavaScript errors
   - Verify API endpoints are accessible
   - Ensure user is properly authenticated

### Performance Tips

- For large product catalogs (1000+ items), consider adding pagination
- Use database indexes on frequently searched fields
- Enable gzip compression on your web server
- Optimize images and minimize HTTP requests

## Development Notes

### Code Architecture

The system follows a clean separation of concerns:

- **Frontend**: Pure HTML/CSS/JavaScript with no dependencies
- **Backend**: RESTful PHP API with proper error handling
- **Database**: Normalized schema with foreign key constraints
- **Security**: Industry-standard practices throughout

### API Endpoints

- `GET /api/auth.php` - Check authentication status
- `POST /api/auth.php` - Login/logout/register
- `GET /api/products.php` - Fetch all products
- `POST /api/products.php` - Create new product
- `PUT /api/products.php` - Update existing product
- `DELETE /api/products.php` - Delete product

### Database Schema

**Users Table:**
- `id` (Primary Key)
- `username` (Unique)
- `password_hash`
- `created_at`

**Products Table:**
- `id` (Primary Key)
- `user_id` (Foreign Key)
- `product_name`
- `actual_price`
- `markup_percentage`
- `selling_price` (Calculated)
- `profit` (Calculated)
- `product_url`
- `created_at`
- `updated_at`

## License

This project is provided as-is for educational and commercial use. Feel free to modify and distribute according to your needs.

## Support

For technical support or feature requests, please refer to the well-commented code or consult the PHP and JavaScript documentation for extending functionality.
