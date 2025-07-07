-- BotSystem Database Schema
-- MySQL/MariaDB compatible

-- Create database (run this separately if needed)
-- CREATE DATABASE botsystem_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE botsystem_db;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    type ENUM('admin', 'user') DEFAULT 'user',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bots table
CREATE TABLE IF NOT EXISTS bots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('telegram', 'whatsapp') NOT NULL,
    token VARCHAR(500),
    chat_id VARCHAR(100),
    active BOOLEAN DEFAULT TRUE,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_type (type),
    INDEX idx_active (active),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Groups table
CREATE TABLE IF NOT EXISTS groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    external_id VARCHAR(100) NOT NULL,
    type ENUM('telegram', 'whatsapp') NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_external_id (external_id),
    INDEX idx_type (type),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logs table
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination VARCHAR(100) NOT NULL,
    bot_name VARCHAR(100),
    type VARCHAR(50),
    message TEXT,
    status ENUM('success', 'error') NOT NULL,
    platform ENUM('telegram', 'whatsapp'),
    user_id INT,
    error_details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_platform (platform),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled messages table
CREATE TABLE IF NOT EXISTS scheduled_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scheduled_time DATETIME NOT NULL,
    bot_id INT,
    platform ENUM('telegram', 'whatsapp') NOT NULL,
    destination VARCHAR(100) NOT NULL,
    type ENUM('text', 'image', 'tmdb') NOT NULL,
    message TEXT,
    image_path VARCHAR(255),
    tmdb_id VARCHAR(20),
    tmdb_type ENUM('movie', 'tv'),
    sent BOOLEAN DEFAULT FALSE,
    error_message TEXT,
    user_id INT NOT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bot_id) REFERENCES bots(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_scheduled_time (scheduled_time),
    INDEX idx_sent (sent),
    INDEX idx_platform (platform),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AutoBot configuration table
CREATE TABLE IF NOT EXISTS autobot_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    active BOOLEAN DEFAULT FALSE,
    emoji VARCHAR(10) DEFAULT '',
    greeting_message TEXT,
    default_message TEXT,
    out_of_hours_message TEXT,
    hours_active BOOLEAN DEFAULT FALSE,
    start_time TIME DEFAULT '08:00:00',
    end_time TIME DEFAULT '18:00:00',
    inactivity_timeout INT DEFAULT 300,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_config (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AutoBot keywords table
CREATE TABLE IF NOT EXISTS autobot_keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    response TEXT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    response_delay INT DEFAULT 0,
    usage_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_keyword (keyword),
    INDEX idx_active (active),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AutoBot conversations table
CREATE TABLE IF NOT EXISTS autobot_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    contact_name VARCHAR(100),
    first_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    total_messages INT DEFAULT 1,
    greeting_sent BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_phone (user_id, phone_number),
    INDEX idx_phone_number (phone_number),
    INDEX idx_last_interaction (last_interaction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AutoBot statistics table
CREATE TABLE IF NOT EXISTS autobot_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    messages_sent INT DEFAULT 0,
    conversations_started INT DEFAULT 0,
    active_keywords INT DEFAULT 0,
    unique_contacts INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_stats (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dynamic variables table
CREATE TABLE IF NOT EXISTS dynamic_variables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    variable_name VARCHAR(50) NOT NULL,
    variable_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_variable (user_id, variable_name),
    INDEX idx_variable_name (variable_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System configuration table
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default admin user (password: admin)
INSERT IGNORE INTO users (id, username, password, name, type) VALUES 
(1, 'admin', '$2y$10$jffRFLNr827PcFmXeH8tcO6TGWVqDGQpg1GVIZYfgWyDZtN.962CG', 'Administrador', 'admin');

-- Insert default system configuration
INSERT IGNORE INTO system_config (config_key, config_value, description) VALUES
('tmdb_api_key', '', 'TMDB API Key for movie/TV data'),
('whatsapp_server', 'https://evov2.duckdns.org', 'WhatsApp Evolution API server URL'),
('whatsapp_instance', '', 'WhatsApp instance name'),
('whatsapp_apikey', '', 'WhatsApp API key'),
('app_version', '2.0.0', 'Application version'),
('maintenance_mode', '0', 'Maintenance mode (0=off, 1=on)');