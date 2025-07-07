<?php
/**
 * Webhook para Evolution API v2 - AutoBot WhatsApp
 * Migrado para MySQL
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: application/json");

// Incluir configuração do banco
require_once __DIR__ . '/includes/config.php';

// --- Funções Auxiliares ---
function logMessage($message, $type = 'info') {
    $timestamp = date('[Y-m-d H:i:s]');
    $logFile = ($type === 'error') ? 'data/webhook_error.log' : 'data/webhook.log';
    file_put_contents($logFile, "{$timestamp} {$message}\n", FILE_APPEND | LOCK_EX);
}

function cleanPhoneNumber($number) {
    if (!is_string($number)) {
        logMessage("Número inválido para limpeza: " . var_export($number, true), 'error');
        return '';
    }
    
    $cleaned = preg_replace('/[^\d@.]/', '', $number);
    
    if (strpos($cleaned, '@') !== false) {
        $cleaned = explode('@', $cleaned)[0];
    }
    
    if (strlen($cleaned) > 13 && substr($cleaned, 0, 2) === '55') {
        $cleaned = '55' . substr($cleaned, 4);
    }
    
    return $cleaned;
}

function isWithinOperatingHours($config) {
    if (!$config['hours_active']) {
        return true;
    }
    
    $inicio = DateTime::createFromFormat('H:i:s', $config['start_time']);
    $fim = DateTime::createFromFormat('H:i:s', $config['end_time']);
    $agora = new DateTime();
    
    if (!$inicio || !$fim) {
        logMessage("Horários inválidos: início={$config['start_time']}, fim={$config['end_time']}", 'error');
        return true;
    }
    
    return $agora >= $inicio && $agora <= $fim;
}

// --- Início do Processamento ---
try {
    // Receber payload JSON
    $input = file_get_contents('php://input');
    
    if (empty($input)) {
        logMessage("Webhook recebido sem payload", 'error');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Payload vazio']);
        exit();
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Erro ao decodificar JSON: " . json_last_error_msg(), 'error');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'JSON inválido']);
        exit();
    }
    
    logMessage("Webhook recebido: " . substr(json_encode($data), 0, 500));
    
    // Verificar estrutura básica
    if (!is_array($data)) {
        logMessage("Dados do webhook não são um array: " . var_export($data, true), 'error');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Dados inválidos']);
        exit();
    }
    
    // Processar diferentes estruturas da Evolution API v2
    $messageText = '';
    $senderNumber = '';
    $senderName = 'Cliente';
    $fromMe = false;
    $isGroup = false;
    
    // Estrutura: event + data.messages
    if (isset($data['event']) && $data['event'] === 'messages.upsert' && isset($data['data']['messages']) && is_array($data['data']['messages'])) {
        foreach ($data['data']['messages'] as $message) {
            if (!is_array($message)) {
                logMessage("Mensagem não é um array: " . var_export($message, true), 'error');
                continue;
            }
            $fromMe = $message['key']['fromMe'] ?? false;
            if ($fromMe) continue;
            
            $senderNumber = $message['key']['remoteJid'] ?? '';
            $isGroup = strpos($senderNumber, '@g.us') !== false;
            if ($isGroup) {
                logMessage("Mensagem de grupo ignorada: {$senderNumber}", 'info');
                continue;
            }
            
            $senderName = $message['pushName'] ?? $senderName;
            
            if (isset($message['message']['conversation'])) {
                $messageText = $message['message']['conversation'];
            } elseif (isset($message['message']['extendedTextMessage']['text'])) {
                $messageText = $message['message']['extendedTextMessage']['text'];
            } elseif (isset($message['message']['imageMessage']['caption'])) {
                $messageText = $message['message']['imageMessage']['caption'];
            }
            
            break;
        }
    }
    // Estrutura: data.message + data.key
    elseif (isset($data['data']['message']) && is_array($data['data']['message'])) {
        $message = $data['data']['message'];
        $key = $data['data']['key'] ?? [];
        
        if (!is_array($key)) {
            logMessage("Chave não é um array: " . var_export($key, true), 'error');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Chave inválida']);
            exit();
        }
        
        $fromMe = $key['fromMe'] ?? false;
        if (!$fromMe) {
            $senderNumber = $key['remoteJid'] ?? '';
            $isGroup = strpos($senderNumber, '@g.us') !== false;
            if ($isGroup) {
                logMessage("Mensagem de grupo ignorada: {$senderNumber}", 'info');
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Mensagem de grupo ignorada']);
                exit();
            }
            
            $senderName = $data['data']['pushName'] ?? $senderName;
            
            if (isset($message['conversation'])) {
                $messageText = $message['conversation'];
            } elseif (isset($message['extendedTextMessage']['text'])) {
                $messageText = $message['extendedTextMessage']['text'];
            } elseif (isset($message['text'])) {
                $messageText = $message['text'];
            }
        }
    }
    // Estrutura direta: message + key
    elseif (isset($data['message']) && is_array($data['message'])) {
        $message = $data['message'];
        $key = $data['key'] ?? [];
        
        if (!is_array($key)) {
            logMessage("Chave não é um array: " . var_export($key, true), 'error');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Chave inválida']);
            exit();
        }
        
        $fromMe = $key['fromMe'] ?? false;
        if (!$fromMe) {
            $senderNumber = $key['remoteJid'] ?? '';
            $isGroup = strpos($senderNumber, '@g.us') !== false;
            if ($isGroup) {
                logMessage("Mensagem de grupo ignorada: {$senderNumber}", 'info');
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Mensagem de grupo ignorada']);
                exit();
            }
            
            $senderName = $data['pushName'] ?? $senderName;
            
            if (isset($message['conversation'])) {
                $messageText = $message['conversation'];
            } elseif (isset($message['text'])) {
                $messageText = $message['text'];
            }
        }
    }
    
    // Ignorar mensagens próprias
    if ($fromMe) {
        logMessage("Mensagem própria ignorada");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Mensagem própria ignorada']);
        exit();
    }
    
    // Validar se temos mensagem de texto
    if (empty($messageText) || empty($senderNumber)) {
        logMessage("Mensagem sem texto ou remetente inválido: Text={$messageText}, Sender={$senderNumber}");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Não é mensagem de texto']);
        exit();
    }
    
    // Limpar número do remetente
    $cleanSenderNumber = cleanPhoneNumber($senderNumber);
    logMessage("Número original: {$senderNumber} | Número limpo: {$cleanSenderNumber} | Nome: {$senderName}");
    
    // Buscar configurações do AutoBot (assumindo user_id = 1 para simplificar)
    $autobot_config = fetchOne("SELECT * FROM autobot_config WHERE user_id = 1");
    if (!$autobot_config) {
        logMessage("Configuração AutoBot não encontrada", 'error');
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'AutoBot não configurado']);
        exit();
    }
    
    // Verificar se o bot está ativo
    if (!$autobot_config['active']) {
        logMessage("Bot inativo, ignorando mensagem");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Bot inativo']);
        exit();
    }
    
    // Buscar configurações do WhatsApp
    $whatsapp_config = fetchAll("SELECT config_key, config_value FROM system_config WHERE config_key IN ('whatsapp_server', 'whatsapp_instance', 'whatsapp_apikey')");
    $config = [];
    foreach ($whatsapp_config as $setting) {
        $config[$setting['config_key']] = $setting['config_value'];
    }
    
    if (empty($config['whatsapp_server']) || empty($config['whatsapp_instance']) || empty($config['whatsapp_apikey'])) {
        logMessage("Configuração WhatsApp incompleta", 'error');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Configuração WhatsApp incompleta']);
        exit();
    }
    
    // Verificar horário de funcionamento
    $withinOperatingHours = isWithinOperatingHours($autobot_config);
    
    // Buscar ou criar conversa
    $conversa = fetchOne("SELECT * FROM autobot_conversations WHERE user_id = 1 AND phone_number = ?", [$cleanSenderNumber]);
    
    $enviar_saudacao = false;
    $tempo_atual = time();
    
    if (!$conversa) {
        // Nova conversa
        executeQuery("INSERT INTO autobot_conversations (user_id, phone_number, contact_name, first_interaction, last_interaction, total_messages, greeting_sent) VALUES (1, ?, ?, NOW(), NOW(), 1, 1)", 
            [$cleanSenderNumber, $senderName]);
        $enviar_saudacao = true;
        
        // Atualizar estatísticas
        executeQuery("INSERT INTO autobot_statistics (user_id, conversations_started) VALUES (1, 1) ON DUPLICATE KEY UPDATE conversations_started = conversations_started + 1");
        
        logMessage("Nova conversa iniciada para {$cleanSenderNumber} (Nome: {$senderName})", 'info');
    } else {
        // Conversa existente
        $ultima_interacao = strtotime($conversa['last_interaction']);
        
        if (($tempo_atual - $ultima_interacao) > $autobot_config['inactivity_timeout']) {
            $enviar_saudacao = true;
            logMessage("Conversa retomada após inatividade para {$cleanSenderNumber} (Nome: {$senderName})", 'info');
        }
        
        // Atualizar conversa
        executeQuery("UPDATE autobot_conversations SET contact_name = ?, last_interaction = NOW(), total_messages = total_messages + 1 WHERE id = ?", 
            [$senderName, $conversa['id']]);
    }
    
    // Buscar variáveis dinâmicas
    $variables_rows = fetchAll("SELECT variable_name, variable_value FROM dynamic_variables WHERE user_id = 1");
    $variaveis = [];
    foreach ($variables_rows as $row) {
        $variaveis[$row['variable_name']] = $row['variable_value'];
    }
    
    $resposta = '';
    $palavra_encontrada = false;
    $tempo_resposta = 0;
    
    // Substituir variáveis dinâmicas
    $replaceVariables = function($text) use ($senderName, $cleanSenderNumber, $variaveis, $autobot_config) {
        $text = str_replace('{{nome}}', $senderName, $text);
        $text = str_replace('{{numero}}', $cleanSenderNumber, $text);
        $text = str_replace('{{data}}', date('d/m/Y'), $text);
        $text = str_replace('{{hora}}', date('H:i'), $text);
        $text = str_replace('{{horario_inicio}}', substr($autobot_config['start_time'] ?? '08:00:00', 0, 5), $text);
        $text = str_replace('{{horario_fim}}', substr($autobot_config['end_time'] ?? '18:00:00', 0, 5), $text);
        foreach ($variaveis as $key => $value) {
            $text = str_replace("{{{$key}}}", $value, $text);
        }
        return $text;
    };
    
    // Fora do horário de funcionamento
    if (!$withinOperatingHours && $autobot_config['hours_active']) {
        $resposta = $autobot_config['out_of_hours_message'] ?? 'Estamos fora do horário de atendimento. Retornaremos em breve!';
        $resposta = $replaceVariables($resposta);
        logMessage("Enviando mensagem fora de horário para {$cleanSenderNumber} (Nome: {$senderName})");
    } 
    // Dentro do horário, processar saudação ou palavras-chave
    else {
        // Buscar palavra-chave na mensagem (se não for saudação)
        if (!$enviar_saudacao) {
            $palavras_chave = fetchAll("SELECT * FROM autobot_keywords WHERE user_id = 1 AND active = 1");
            
            foreach ($palavras_chave as $palavra) {
                $keyword_text = $palavra['keyword'] ?? '';
                $keywords = array_map('trim', explode(',', $keyword_text));
                
                foreach ($keywords as $keyword) {
                    if (stripos($messageText, $keyword) !== false) {
                        $resposta = $palavra['response'] ?? '';
                        $resposta = str_replace('{{palavra}}', $keyword, $resposta);
                        $resposta = $replaceVariables($resposta);
                        
                        // Atualizar contador
                        executeQuery("UPDATE autobot_keywords SET usage_count = usage_count + 1 WHERE id = ?", [$palavra['id']]);
                        
                        $tempo_resposta = $palavra['response_delay'] ?? 0;
                        $palavra_encontrada = true;
                        
                        logMessage("Palavra-chave encontrada: '{$keyword}' para {$cleanSenderNumber} (Nome: {$senderName})");
                        break 2;
                    }
                }
            }
        }
        
        // Definir resposta
        if ($enviar_saudacao) {
            $resposta = $autobot_config['greeting_message'] ?? 'Olá, bem-vindo ao AutoBot!';
            $resposta = $replaceVariables($resposta);
            logMessage("Enviando saudação para {$cleanSenderNumber} (Nome: {$senderName})");
        } elseif (!$palavra_encontrada) {
            $resposta = $autobot_config['default_message'] ?? 'Não entendi sua mensagem. Como posso ajudar?';
            $resposta = $replaceVariables($resposta);
            logMessage("Enviando mensagem padrão para {$cleanSenderNumber} (Nome: {$senderName})");
        }
    }
    
    // Enviar resposta
    if (!empty($resposta)) {
        if ($tempo_resposta > 0) {
            sleep($tempo_resposta);
        }
        
        $base_url = rtrim($config['whatsapp_server'], '/');
        $url = $base_url . '/message/sendText/' . $config['whatsapp_instance'];
        
        $payload = [
            'number' => $senderNumber,
            'text' => $resposta
        ];
        
        $headers = [
            'apikey: ' . $config['whatsapp_apikey'],
            'Content-Type: application/json'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            logMessage("Erro cURL: {$curlError}", 'error');
        } elseif ($httpCode === 200 || $httpCode === 201) {
            logMessage("Resposta enviada com sucesso para {$cleanSenderNumber} (Nome: {$senderName}): {$resposta}");
            
            // Atualizar estatísticas
            executeQuery("INSERT INTO autobot_statistics (user_id, messages_sent) VALUES (1, 1) ON DUPLICATE KEY UPDATE messages_sent = messages_sent + 1");
        } else {
            logMessage("Erro ao enviar para {$cleanSenderNumber} (Nome: {$senderName}). HTTP: {$httpCode}, Response: {$response}", 'error');
        }
    }
    
    logMessage("Processamento concluído para {$cleanSenderNumber} (Nome: {$senderName}) com resposta: {$resposta}");
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Processado com sucesso']);
    
} catch (Exception $e) {
    logMessage("Erro no webhook: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine(), 'error');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro interno']);
} catch (Error $e) {
    logMessage("Erro fatal no webhook: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine(), 'error');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erro fatal']);
}
?>