<?php
/**
 * Database Migration Script
 * Migrates data from JSON files to MySQL database
 */

require_once '../includes/config.php';

// Set execution time limit for large migrations
set_time_limit(300);

echo "Starting BotSystem Database Migration...\n";
echo "=====================================\n\n";

try {
    // Check if database connection is working
    $pdo = getDB();
    echo "✓ Database connection established\n";
    
    // Create tables from schema
    echo "Creating database tables...\n";
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);
    echo "✓ Database tables created\n\n";
    
    // Migrate users
    echo "Migrating users...\n";
    if (file_exists('../data/usuarios.json')) {
        $users = json_decode(file_get_contents('../data/usuarios.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO users (id, username, password, name, type, last_login) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($users as $user) {
            $stmt->execute([
                $user['id'],
                $user['usuario'],
                $user['senha'],
                $user['nome'],
                $user['tipo'] ?? 'user',
                $user['ultimo_login'] ?? null
            ]);
        }
        echo "✓ Users migrated: " . count($users) . " records\n";
    }
    
    // Migrate bots
    echo "Migrating bots...\n";
    if (file_exists('../data/bots.json')) {
        $bots = json_decode(file_get_contents('../data/bots.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO bots (id, name, type, token, chat_id, active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($bots as $bot) {
            $stmt->execute([
                $bot['id'],
                $bot['nome'],
                $bot['tipo'],
                $bot['token'],
                $bot['chat_id'] ?? null,
                $bot['ativo'] ? 1 : 0,
                $bot['criado_em'] ?? date('Y-m-d H:i:s')
            ]);
        }
        echo "✓ Bots migrated: " . count($bots) . " records\n";
    }
    
    // Migrate groups
    echo "Migrating groups...\n";
    if (file_exists('../data/grupos.json')) {
        $groups = json_decode(file_get_contents('../data/grupos.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO groups (name, external_id, type, user_id) VALUES (?, ?, ?, ?)");
        
        foreach ($groups as $group) {
            $stmt->execute([
                $group['nome'],
                $group['id_externo'],
                $group['tipo'],
                $group['user_id'] ?? 1
            ]);
        }
        echo "✓ Groups migrated: " . count($groups) . " records\n";
    }
    
    // Migrate logs
    echo "Migrating logs...\n";
    if (file_exists('../data/logs.json')) {
        $logs = json_decode(file_get_contents('../data/logs.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO logs (destination, bot_name, type, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($logs as $log) {
            $stmt->execute([
                $log['destino'],
                $log['bot'],
                $log['tipo'],
                $log['mensagem'],
                $log['status'] === 'sucesso' ? 'success' : 'error',
                $log['data_hora']
            ]);
        }
        echo "✓ Logs migrated: " . count($logs) . " records\n";
    }
    
    // Migrate scheduled messages
    echo "Migrating scheduled messages...\n";
    if (file_exists('../data/agendamentos.json')) {
        $scheduled = json_decode(file_get_contents('../data/agendamentos.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO scheduled_messages (scheduled_time, bot_id, platform, destination, type, message, tmdb_id, tmdb_type, sent, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($scheduled as $item) {
            $stmt->execute([
                $item['data_hora'],
                $item['bot_id'] === 'whatsapp' ? null : $item['bot_id'],
                $item['plataforma'],
                $item['destino'],
                $item['tipo'],
                $item['mensagem'],
                $item['tmdb_id'] ?? null,
                $item['tipo_tmdb'] ?? null,
                $item['enviado'] ? 1 : 0,
                1, // Default user ID
                $item['criado_em'] ?? date('Y-m-d H:i:s')
            ]);
        }
        echo "✓ Scheduled messages migrated: " . count($scheduled) . " records\n";
    }
    
    // Migrate AutoBot configuration
    echo "Migrating AutoBot configuration...\n";
    if (file_exists('../data/autobot_config.json')) {
        $config = json_decode(file_get_contents('../data/autobot_config.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO autobot_config (user_id, active, emoji, greeting_message, default_message, out_of_hours_message, hours_active, start_time, end_time, inactivity_timeout) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            1, // Default user ID
            $config['ativo'] ? 1 : 0,
            $config['emoji'] ?? '',
            $config['saudacao'] ?? '',
            $config['mensagem_padrao'] ?? '',
            $config['mensagem_fora_horario'] ?? '',
            $config['horario_ativo'] ? 1 : 0,
            $config['horario_inicio'] ?? '08:00:00',
            $config['horario_fim'] ?? '18:00:00',
            $config['tempo_inatividade'] ?? 300
        ]);
        echo "✓ AutoBot configuration migrated\n";
    }
    
    // Migrate AutoBot keywords
    echo "Migrating AutoBot keywords...\n";
    if (file_exists('../data/palavras_chave.json')) {
        $keywords = json_decode(file_get_contents('../data/palavras_chave.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO autobot_keywords (user_id, keyword, response, active, response_delay, usage_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($keywords as $keyword) {
            $stmt->execute([
                1, // Default user ID
                $keyword['palavra'],
                $keyword['resposta'],
                $keyword['ativo'] ? 1 : 0,
                $keyword['tempo_resposta'] ?? 0,
                $keyword['contador'] ?? 0,
                $keyword['criado_em'] ?? date('Y-m-d H:i:s')
            ]);
        }
        echo "✓ AutoBot keywords migrated: " . count($keywords) . " records\n";
    }
    
    // Migrate system configuration
    echo "Migrating system configuration...\n";
    if (file_exists('../data/config.json')) {
        $config = json_decode(file_get_contents('../data/config.json'), true);
        $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
        
        if (isset($config['tmdb_key'])) {
            $stmt->execute(['tmdb_api_key', $config['tmdb_key']]);
        }
        if (isset($config['whatsapp']['server'])) {
            $stmt->execute(['whatsapp_server', $config['whatsapp']['server']]);
        }
        if (isset($config['whatsapp']['instance'])) {
            $stmt->execute(['whatsapp_instance', $config['whatsapp']['instance']]);
        }
        if (isset($config['whatsapp']['apikey'])) {
            $stmt->execute(['whatsapp_apikey', $config['whatsapp']['apikey']]);
        }
        echo "✓ System configuration migrated\n";
    }
    
    // Migrate dynamic variables
    echo "Migrating dynamic variables...\n";
    if (file_exists('../data/variaveis.json')) {
        $variables = json_decode(file_get_contents('../data/variaveis.json'), true);
        $stmt = $pdo->prepare("INSERT IGNORE INTO dynamic_variables (user_id, variable_name, variable_value) VALUES (?, ?, ?)");
        
        foreach ($variables as $key => $value) {
            $stmt->execute([1, $key, $value]); // Default user ID
        }
        echo "✓ Dynamic variables migrated: " . count($variables) . " records\n";
    }
    
    echo "\n=====================================\n";
    echo "✓ Migration completed successfully!\n";
    echo "=====================================\n\n";
    
    echo "Next steps:\n";
    echo "1. Update your database credentials in includes/db.php\n";
    echo "2. Test the application to ensure everything works\n";
    echo "3. Backup your JSON files before removing them\n";
    echo "4. Update your application code to use MySQL instead of JSON\n\n";
    
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
}
?>