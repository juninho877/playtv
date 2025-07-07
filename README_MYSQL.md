# MySQL Migration Guide for BotSystem

This guide will help you migrate your BotSystem from JSON files to MySQL database.

## Prerequisites

1. **MySQL/MariaDB Server**: Ensure you have MySQL 5.7+ or MariaDB 10.2+ installed
2. **PHP Extensions**: Make sure you have `pdo` and `pdo_mysql` extensions enabled
3. **Database Access**: You need a MySQL user with CREATE, INSERT, UPDATE, DELETE privileges

## Step 1: Database Setup

### Create Database
```sql
CREATE DATABASE botsystem_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Create Database User (Optional but recommended)
```sql
CREATE USER 'botsystem_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON botsystem_db.* TO 'botsystem_user'@'localhost';
FLUSH PRIVILEGES;
```

## Step 2: Configure Database Connection

1. Open `includes/db.php`
2. Update the database credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'botsystem_db');
   define('DB_USER', 'botsystem_user');
   define('DB_PASS', 'your_secure_password');
   ```

## Step 3: Run Migration

1. Navigate to the `database/` directory
2. Run the migration script:
   ```bash
   php migrate.php
   ```

This will:
- Create all necessary tables
- Migrate data from JSON files to MySQL
- Set up indexes for optimal performance

## Step 4: Update Application Files

After successful migration, you'll need to update your PHP files to use MySQL instead of JSON. Here are the key changes:

### Example: Converting JSON operations to MySQL

**Before (JSON):**
```php
$users = json_decode(file_get_contents('data/usuarios.json'), true);
```

**After (MySQL):**
```php
require_once 'includes/db.php';
$users = fetchAll("SELECT * FROM users WHERE active = 1");
```

## Database Schema Overview

### Core Tables
- `users` - User accounts and authentication
- `bots` - Telegram/WhatsApp bot configurations
- `groups` - Chat groups and channels
- `logs` - Message sending history
- `scheduled_messages` - Scheduled message queue

### AutoBot Tables
- `autobot_config` - AutoBot settings per user
- `autobot_keywords` - Keyword-response pairs
- `autobot_conversations` - Conversation tracking
- `autobot_statistics` - Usage statistics

### Configuration Tables
- `system_config` - Global system settings
- `dynamic_variables` - User-defined variables

The complete schema is defined in `supabase/migrations/20250707211614_delicate_paper.sql`.

## Helper Functions Available

The `includes/db.php` file provides several helper functions:

```php
// Execute a query with parameters
$result = executeQuery("SELECT * FROM users WHERE id = ?", [$userId]);

// Fetch a single row
$user = fetchOne("SELECT * FROM users WHERE username = ?", [$username]);

// Fetch all rows
$bots = fetchAll("SELECT * FROM bots WHERE active = 1");

// Get last inserted ID
$newId = getLastInsertId();

// Transaction support
beginTransaction();
try {
    // Your database operations
    commitTransaction();
} catch (Exception $e) {
    rollbackTransaction();
    throw $e;
}
```

## Security Features

- **Prepared Statements**: All queries use prepared statements to prevent SQL injection
- **Error Handling**: Comprehensive error logging and handling
- **Connection Security**: Secure connection options and charset handling
- **Environment-based Configuration**: Different settings for development/production

## Performance Optimizations

- **Indexes**: Strategic indexes on frequently queried columns
- **UTF8MB4**: Full Unicode support including emojis
- **Connection Pooling**: Efficient connection management
- **Query Optimization**: Optimized table structure for common operations

## Backup and Recovery

### Before Migration
```bash
# Backup your JSON files
tar -czf botsystem_json_backup_$(date +%Y%m%d).tar.gz data/
```

### After Migration
```bash
# Regular MySQL backup
mysqldump -u botsystem_user -p botsystem_db > botsystem_backup_$(date +%Y%m%d).sql
```

## Troubleshooting

### Common Issues

1. **Connection Failed**: Check database credentials and server status
2. **Permission Denied**: Ensure database user has proper privileges
3. **Character Encoding**: Make sure your database uses utf8mb4 charset
4. **Migration Errors**: Check PHP error logs for detailed information

### Verification Queries

```sql
-- Check if migration was successful
SELECT COUNT(*) as user_count FROM users;
SELECT COUNT(*) as bot_count FROM bots;
SELECT COUNT(*) as log_count FROM logs;

-- Verify data integrity
SELECT * FROM users LIMIT 5;
SELECT * FROM autobot_config;
```

## Performance Monitoring

Monitor your database performance with these queries:

```sql
-- Check table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables 
WHERE table_schema = 'botsystem_db';

-- Check slow queries (if enabled)
SHOW VARIABLES LIKE 'slow_query_log';
```

## Next Steps

1. Test all functionality thoroughly
2. Update any custom scripts or integrations
3. Set up regular database backups
4. Monitor performance and optimize as needed
5. Consider implementing database connection pooling for high-traffic scenarios

For support or questions, refer to the main documentation or create an issue in the project repository.