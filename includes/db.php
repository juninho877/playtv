<?php
/**
 * Database Connection Configuration
 * Using PDO for secure and efficient database operations
 */

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_NAME', 'playtv');
define('DB_USER', 'playtv');
define('DB_PASS', '%$oKt3ejcWk2jp9R');
define('DB_CHARSET', 'utf8mb4');

// Global PDO instance
$pdo = null;

try {
    // Data Source Name (DSN)
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    // PDO options for security and performance
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Fetch associative arrays by default
        PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
        PDO::ATTR_PERSISTENT         => false,                    // Don't use persistent connections
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET // Set charset
    ];
    
    // Create PDO instance
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Log successful connection (optional, remove in production)
    error_log("[" . date('Y-m-d H:i:s') . "] Database connection established successfully");
    
} catch (PDOException $e) {
    // Log the error (don't expose sensitive information)
    error_log("[" . date('Y-m-d H:i:s') . "] Database connection failed: " . $e->getMessage());
    
    // In production, you might want to show a generic error message
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please try again later.");
    }
}

/**
 * Helper function to get database connection
 * @return PDO
 */
function getDB() {
    global $pdo;
    if ($pdo === null) {
        throw new Exception("Database connection not established");
    }
    return $pdo;
}

/**
 * Helper function to execute a query with parameters
 * @param string $sql
 * @param array $params
 * @return PDOStatement
 */
function executeQuery($sql, $params = []) {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Query execution failed: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Helper function to fetch a single row
 * @param string $sql
 * @param array $params
 * @return array|false
 */
function fetchOne($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Helper function to fetch all rows
 * @param string $sql
 * @param array $params
 * @return array
 */
function fetchAll($sql, $params = []) {
    $stmt = executeQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Helper function to get the last inserted ID
 * @return string
 */
function getLastInsertId() {
    $pdo = getDB();
    return $pdo->lastInsertId();
}

/**
 * Helper function to begin a transaction
 */
function beginTransaction() {
    $pdo = getDB();
    return $pdo->beginTransaction();
}

/**
 * Helper function to commit a transaction
 */
function commitTransaction() {
    $pdo = getDB();
    return $pdo->commit();
}

/**
 * Helper function to rollback a transaction
 */
function rollbackTransaction() {
    $pdo = getDB();
    return $pdo->rollBack();
}

/**
 * Helper function to check if we're in a transaction
 * @return bool
 */
function inTransaction() {
    $pdo = getDB();
    return $pdo->inTransaction();
}
?>