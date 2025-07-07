<?php
/**
 * Processador de agendamentos - Executar via cronjob a cada minuto
 * Migrado para MySQL
 */
set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$base_path = dirname(__DIR__);

// Incluir configuração do banco
require_once $base_path . '/includes/config.php';

// Função para log
function logMessage($message) {
    $log_file = dirname(__DIR__) . '/data/cron.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logMessage("Iniciando processador de agendamentos");

try {
    // Buscar agendamentos pendentes
    $agendamentos = fetchAll("SELECT * FROM scheduled_messages WHERE sent = 0 AND scheduled_time <= NOW()");
    
    if (empty($agendamentos)) {
        logMessage("Nenhum agendamento pendente encontrado");
        exit(0);
    }
    
    logMessage("Agendamentos encontrados: " . count($agendamentos));
    
    // Buscar configurações do sistema
    $config_rows = fetchAll("SELECT config_key, config_value FROM system_config WHERE config_key IN ('tmdb_api_key', 'whatsapp_server', 'whatsapp_instance', 'whatsapp_apikey')");
    $config = [];
    foreach ($config_rows as $row) {
        $config[$row['config_key']] = $row['config_value'];
    }
    
    // Buscar bots ativos
    $bots = fetchAll("SELECT * FROM bots WHERE active = 1");
    $bots_by_id = [];
    foreach ($bots as $bot) {
        $bots_by_id[$bot['id']] = $bot;
    }
    
    logMessage("Configurações carregadas - Bots: " . count($bots));
    
    $processados = 0;
    
    foreach ($agendamentos as $agendamento) {
        logMessage("Processando agendamento ID: " . $agendamento['id']);
        
        $bot = null;
        $bot_name = '';
        
        // Identificar o bot
        if ($agendamento['platform'] == 'whatsapp') {
            $bot = ['type' => 'whatsapp', 'name' => 'WhatsApp API'];
            $bot_name = 'WhatsApp API';
        } elseif ($agendamento['bot_id'] && isset($bots_by_id[$agendamento['bot_id']])) {
            $bot = $bots_by_id[$agendamento['bot_id']];
            $bot_name = $bot['name'];
        }
        
        if (!$bot) {
            executeQuery("UPDATE scheduled_messages SET sent = 1, error_message = ?, processed_at = NOW() WHERE id = ?", 
                ['Bot não encontrado ou inativo', $agendamento['id']]);
            logMessage("ERRO: Bot não encontrado ou inativo - ID: " . $agendamento['bot_id']);
            continue;
        }
        
        $mensagem = $agendamento['message'];
        $item = null;
        $sucesso = false;
        $erro_msg = '';
        
        // Gerar conteúdo TMDB
        if ($agendamento['type'] == 'tmdb' && !empty($agendamento['tmdb_id'])) {
            $tmdb_key = $config['tmdb_api_key'] ?? '';
            $tipo_tmdb = $agendamento['tmdb_type'] ?? 'movie';
            
            if (!empty($tmdb_key)) {
                $url_detalhes = "https://api.themoviedb.org/3/{$tipo_tmdb}/{$agendamento['tmdb_id']}?api_key={$tmdb_key}&language=pt-BR&append_to_response=videos";
                $response = @file_get_contents($url_detalhes);
                
                if ($response) {
                    $item = json_decode($response, true);
                    $titulo = $item['title'] ?? $item['name'] ?? 'Título desconhecido';
                    $sinopse = $item['overview'] ?? 'Sinopse não disponível';
                    $avaliacao = number_format($item['vote_average'] ?? 0, 1);
                    $generos = implode(', ', array_column($item['genres'] ?? [], 'name'));
                    $data_lancamento = $item['release_date'] ?? $item['first_air_date'] ?? '';
                    $icone = $tipo_tmdb == 'movie' ? '🎬' : '📺';
                    $estrelas = str_repeat('⭐', round(($item['vote_average'] ?? 0) / 2));
                    
                    $mensagem = "{$icone} <b>{$titulo}</b>\n\n";
                    $mensagem .= "📝 <b>Sinopse:</b>\n{$sinopse}\n\n";
                    $mensagem .= "⭐ <b>Avaliação:</b> {$avaliacao}/10 {$estrelas}\n\n";
                    $mensagem .= "🎭 <b>Gêneros:</b> {$generos}\n\n";
                    if ($data_lancamento) {
                        $mensagem .= "📅 <b>Lançamento:</b> " . date('d/m/Y', strtotime($data_lancamento)) . "\n\n";
                    }
                    
                    if (!empty($item['videos']['results'])) {
                        foreach ($item['videos']['results'] as $video) {
                            if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                                $mensagem .= "🎥 <b>Trailer:</b> https://www.youtube.com/watch?v={$video['key']}\n\n";
                                break;
                            }
                        }
                    }
                    
                    $mensagem .= "🤖 <i>Sugestão automática via BotSystem</i>";
                }
            }
        }
        
        // Envio WhatsApp
        if ($bot['type'] === 'whatsapp') {
            if (empty($config['whatsapp_server']) || empty($config['whatsapp_instance']) || empty($config['whatsapp_apikey'])) {
                $erro_msg = "Configuração WhatsApp incompleta";
                logMessage("ERRO: $erro_msg");
            } else {
                $base_url = rtrim($config['whatsapp_server'], '/');
                
                if ($agendamento['type'] === 'tmdb' && $item && !empty($item['poster_path'])) {
                    $url = "$base_url/message/sendMedia/" . $config['whatsapp_instance'];
                    $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
                    $data = [
                        'number' => $agendamento['destination'],
                        'mediatype' => 'image',
                        'media' => $poster_url,
                        'caption' => strip_tags($mensagem)
                    ];
                } elseif ($agendamento['type'] === 'image' && !empty($agendamento['image_path'])) {
                    $url = "$base_url/message/sendMedia/" . $config['whatsapp_instance'];
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $image_url = $protocol . '://' . $host . '/' . $agendamento['image_path'];
                    $data = [
                        'number' => $agendamento['destination'],
                        'mediatype' => 'image',
                        'media' => $image_url,
                        'caption' => $mensagem
                    ];
                } else {
                    $url = "$base_url/message/sendText/" . $config['whatsapp_instance'];
                    $data = [
                        'number' => $agendamento['destination'],
                        'text' => strip_tags($mensagem)
                    ];
                }
                
                logMessage("Enviando para WhatsApp: " . $agendamento['destination']);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . $config['whatsapp_apikey']
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                logMessage("Resposta WhatsApp - HTTP: $httpCode - $response");
                
                if ($curl_error) {
                    $erro_msg = "Erro cURL: $curl_error";
                } elseif ($httpCode == 200 || $httpCode == 201) {
                    $res_data = json_decode($response, true);
                    $sucesso = isset($res_data['status']) && $res_data['status'] == 200;
                } else {
                    $erro_msg = "Erro HTTP WhatsApp $httpCode: $response";
                }
            }
        }
        
        // Envio Telegram
        elseif ($bot['type'] === 'telegram') {
            if (empty($bot['token'])) {
                $erro_msg = "Token Telegram não configurado";
                logMessage("ERRO: $erro_msg");
            } else {
                $chat_id = $agendamento['destination'];
                
                if ($agendamento['type'] === 'tmdb' && $item && !empty($item['poster_path'])) {
                    $url = "https://api.telegram.org/bot{$bot['token']}/sendPhoto";
                    $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
                    $data = [
                        'chat_id' => $chat_id,
                        'photo' => $poster_url,
                        'caption' => $mensagem,
                        'parse_mode' => 'HTML'
                    ];
                } elseif ($agendamento['type'] === 'image' && !empty($agendamento['image_path'])) {
                    $url = "https://api.telegram.org/bot{$bot['token']}/sendPhoto";
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $image_url = $protocol . '://' . $host . '/' . $agendamento['image_path'];
                    $data = [
                        'chat_id' => $chat_id,
                        'photo' => $image_url,
                        'caption' => $mensagem,
                        'parse_mode' => 'HTML'
                    ];
                } else {
                    $url = "https://api.telegram.org/bot{$bot['token']}/sendMessage";
                    $data = [
                        'chat_id' => $chat_id,
                        'text' => $mensagem,
                        'parse_mode' => 'HTML'
                    ];
                }
                
                logMessage("Enviando para Telegram: " . $chat_id);
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                logMessage("Resposta Telegram - HTTP: $httpCode - $response");
                
                if ($curl_error) {
                    $erro_msg = "Erro cURL: $curl_error";
                } elseif ($httpCode == 200) {
                    $res_data = json_decode($response, true);
                    $sucesso = isset($res_data['ok']) && $res_data['ok'];
                } else {
                    $erro_msg = "Erro HTTP Telegram $httpCode: $response";
                }
            }
        }
        
        // Atualizar agendamento
        executeQuery("UPDATE scheduled_messages SET sent = 1, processed_at = NOW(), error_message = ? WHERE id = ?", 
            [$sucesso ? null : $erro_msg, $agendamento['id']]);
        
        // Log individual
        $tipo_nome = $agendamento['type'] == 'tmdb' ? 'TMDB - ' . ($agendamento['tmdb_type'] == 'movie' ? 'Filme' : 'Série') : ucfirst($agendamento['type']);
        executeQuery("INSERT INTO logs (destination, bot_name, type, message, status, platform, user_id, error_details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())", 
            [$agendamento['destination'], $bot_name, 'Agendamento - ' . $tipo_nome, substr($mensagem, 0, 500), $sucesso ? 'success' : 'error', $agendamento['platform'], $agendamento['user_id'], $erro_msg ?: null]);
        
        $processados++;
        sleep(1); // evitar flood
    }
    
    logMessage("Processamento concluído. $processados agendamentos processados.");
    
} catch (Exception $e) {
    logMessage("Erro no processador: " . $e->getMessage());
    exit(1);
}
?>