<?php
// Webhook para Evolution API v2 - AutoBot WhatsApp
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir erros para não quebrar o webhook

// Desativar cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Content-Type: application/json");

// --- Funções Auxiliares ---
function logMessage($message, $type = 'info') {
    $timestamp = date('[Y-m-d H:i:s]');
    $logFile = ($type === 'error') ? 'data/webhook_error.log' : 'data/webhook.log';
    file_put_contents($logFile, "{$timestamp} {$message}\n", FILE_APPEND | LOCK_EX);
}

function getConfig($path) {
    if (!file_exists($path)) {
        logMessage("Arquivo de configuração não encontrado: {$path}", 'error');
        return [];
    }
    $content = file_get_contents($path);
    $decoded = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Erro ao decodificar JSON em {$path}: " . json_last_error_msg(), 'error');
        return [];
    }
    return is_array($decoded) ? $decoded : [];
}

function cleanPhoneNumber($number) {
    if (!is_string($number)) {
        logMessage("Número inválido para limpeza: " . var_export($number, true), 'error');
        return '';
    }
    // Remove tudo que não é dígito ou @
    $cleaned = preg_replace('/[^\d@.]/', '', $number);
    
    // Se tem @, pega só a parte antes do @
    if (strpos($cleaned, '@') !== false) {
        $cleaned = explode('@', $cleaned)[0];
    }
    
    // Remove códigos de país duplicados (ex: 5555119999 -> 5511999999)
    if (strlen($cleaned) > 13 && substr($cleaned, 0, 2) === '55') {
        $cleaned = '55' . substr($cleaned, 4);
    }
    
    return $cleaned;
}

function isWithinOperatingHours($config) {
    if (!$config['horario_ativo']) {
        return true; // Horário não ativado, sempre considerado "dentro"
    }
    $inicio = DateTime::createFromFormat('H:i', $config['horario_inicio']);
    $fim = DateTime::createFromFormat('H:i', $config['horario_fim']);
    $agora = new DateTime();
    
    if (!$inicio || !$fim) {
        logMessage("Horários inválidos: início={$config['horario_inicio']}, fim={$config['horario_fim']}", 'error');
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
    $senderName = 'Cliente'; // Valor padrão se pushName não estiver disponível
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
            
            // Verificar se é mensagem de grupo
            $senderNumber = $message['key']['remoteJid'] ?? '';
            $isGroup = strpos($senderNumber, '@g.us') !== false;
            if ($isGroup) {
                logMessage("Mensagem de grupo ignorada: {$senderNumber}", 'info');
                continue;
            }
            
            // Capturar o nome do remetente (pushName)
            $senderName = $message['pushName'] ?? $senderName;
            
            if (isset($message['message']['conversation'])) {
                $messageText = $message['message']['conversation'];
            } elseif (isset($message['message']['extendedTextMessage']['text'])) {
                $messageText = $message['message']['extendedTextMessage']['text'];
            } elseif (isset($message['message']['imageMessage']['caption'])) {
                $messageText = $message['message']['imageMessage']['caption'];
            }
            
            break; // Processar apenas a primeira mensagem válida
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
            
            // Capturar o nome do remetente (pushName)
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
            
            // Capturar o nome do remetente (pushName)
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
    
    // Carregar configurações
    $config = getConfig('data/config.json');
    if (empty($config)) {
        logMessage("Falha ao carregar config.json", 'error');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Configuração inválida']);
        exit();
    }
    
    $autobot_config = getConfig('data/autobot_config.json');
    if (empty($autobot_config)) {
        logMessage("Falha ao carregar autobot_config.json", 'error');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Configuração de autobot inválida']);
        exit();
    }
    
    $variaveis = getConfig('data/variaveis.json');
    if (empty($variaveis)) {
        logMessage("Falha ao carregar variaveis.json", 'error');
        $variaveis = [];
    }
    
    // Verificar se o bot está ativo
    if (!isset($autobot_config['ativo']) || !$autobot_config['ativo']) {
        logMessage("Bot inativo, ignorando mensagem");
        http_response_code(200);
        echo json_encode(['status' => 'success', 'message' => 'Bot inativo']);
        exit();
    }
    
    // Verificar configuração WhatsApp
    if (empty($config['whatsapp']['server']) || empty($config['whatsapp']['instance']) || empty($config['whatsapp']['apikey'])) {
        logMessage("Configuração WhatsApp incompleta", 'error');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Configuração WhatsApp incompleta']);
        exit();
    }
    
    // Verificar horário de funcionamento
    $withinOperatingHours = isWithinOperatingHours($autobot_config);
    
    // Carregar histórico de conversas
    $conversas = getConfig('data/conversas.json');
    if (!is_array($conversas)) {
        logMessage("Conversas não é um array: " . var_export($conversas, true), 'error');
        $conversas = [];
    }
    
    // Verificar se é primeira interação ou retorno após inatividade
    $conversa_existente = false;
    $ultima_interacao = 0;
    $enviar_saudacao = false;
    
    foreach ($conversas as &$conversa) {
        if (!is_array($conversa)) {
            logMessage("Conversa inválida no conversas.json: " . var_export($conversa, true), 'error');
            continue;
        }
        if ($conversa['numero'] === $cleanSenderNumber) {
            $conversa_existente = true;
            $ultima_interacao = strtotime($conversa['ultima_interacao']);
            $conversa['ultima_interacao'] = date('Y-m-d H:i:s');
            $conversa['total_mensagens'] = ($conversa['total_mensagens'] ?? 0) + 1;
            $conversa['nome'] = $senderName; // Atualizar nome
            break;
        }
    }
    
    $tempo_atual = time();
    
    if (!$conversa_existente) {
        // Nova conversa com número limpo
        $conversas[] = [
            'numero' => $cleanSenderNumber,
            'nome' => $senderName,
            'primeira_interacao' => date('Y-m-d H:i:s'),
            'ultima_interacao' => date('Y-m-d H:i:s'),
            'total_mensagens' => 1,
            'saudacao_enviada' => true
        ];
        $enviar_saudacao = true;
        $autobot_config['estatisticas']['conversas_iniciadas'] = ($autobot_config['estatisticas']['conversas_iniciadas'] ?? 0) + 1;
        logMessage("Nova conversa iniciada para {$cleanSenderNumber} (Nome: {$senderName})", 'info');
    } elseif (($tempo_atual - $ultima_interacao) > ($autobot_config['tempo_inatividade'] ?? 3600)) {
        // Conversa retomada após inatividade
        $enviar_saudacao = true;
        logMessage("Conversa retomada após inatividade para {$cleanSenderNumber} (Nome: {$senderName})", 'info');
    }
    
    // Atualizar pessoas respondidas
    $autobot_config['estatisticas']['pessoas_respondidas'] = count(array_unique(array_column($conversas, 'numero')));
    
    // Salvar conversas atualizadas
    if (is_writable('data/conversas.json')) {
        file_put_contents('data/conversas.json', json_encode($conversas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        logMessage("Sem permissão para gravar em data/conversas.json", 'error');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar conversas']);
        exit();
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
        $text = str_replace('{{horario_inicio}}', $autobot_config['horario_inicio'] ?? '08:00', $text);
        $text = str_replace('{{horario_fim}}', $autobot_config['horario_fim'] ?? '18:00', $text);
        foreach ($variaveis as $key => $value) {
            $text = str_replace("{{{$key}}}", $value, $text);
        }
        return $text;
    };
    
    // Fora do horário de funcionamento
    if (!$withinOperatingHours && $autobot_config['horario_ativo']) {
        $resposta = $autobot_config['mensagem_fora_horario'] ?? 'Estamos fora do horário de atendimento. Retornaremos em breve!';
        $resposta = $replaceVariables($resposta);
        logMessage("Enviando mensagem fora de horário para {$cleanSenderNumber} (Nome: {$senderName})");
    } 
    // Dentro do horário, processar saudação ou palavras-chave
    else {
        // Buscar palavra-chave na mensagem (se não for saudação)
        if (!$enviar_saudacao) {
            $palavras_chave = getConfig('data/palavras_chave.json');
            if (!is_array($palavras_chave)) {
                logMessage("Palavras-chave não é um array: " . var_export($palavras_chave, true), 'error');
                $palavras_chave = [];
            }
            
            foreach ($palavras_chave as &$palavra) {
                if (!is_array($palavra)) {
                    logMessage("Palavra-chave inválida: " . var_export($palavra, true), 'error');
                    continue;
                }
                if (!$palavra['ativo']) continue;
                
                // Busca case-insensitive com suporte a múltiplas palavras
                $keyword_text = $palavra['palavra'] ?? '';
                $keywords = array_map('trim', explode(',', $keyword_text));
                
                foreach ($keywords as $keyword) {
                    if (stripos($messageText, $keyword) !== false) {
                        // Substituir variáveis na resposta
                        $resposta = $palavra['resposta'] ?? '';
                        $resposta = str_replace('{{palavra}}', $keyword, $resposta);
                        $resposta = $replaceVariables($resposta);
                        
                        $palavra['contador'] = ($palavra['contador'] ?? 0) + 1;
                        $tempo_resposta = $palavra['tempo_resposta'] ?? 0;
                        $palavra_encontrada = true;
                        
                        logMessage("Palavra-chave encontrada: '{$keyword}' para {$cleanSenderNumber} (Nome: {$senderName})");
                        break 2;
                    }
                }
            }
            
            // Se encontrou palavra-chave, salvar estatística
            if ($palavra_encontrada && is_writable('data/palavras_chave.json')) {
                file_put_contents('data/palavras_chave.json', json_encode($palavras_chave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                $autobot_config['estatisticas']['palavras_ativadas'] = count(array_filter($palavras_chave, fn($p) => $p['ativo']));
            }
        }
        
        // Definir resposta
        if ($enviar_saudacao) {
            $resposta = $autobot_config['saudacao'] ?? 'Olá, bem-vindo ao AutoBot!';
            $resposta = $replaceVariables($resposta);
            logMessage("Enviando saudação para {$cleanSenderNumber} (Nome: {$senderName})");
        } elseif (!$palavra_encontrada) {
            $resposta = $autobot_config['mensagem_padrao'] ?? 'Não entendi sua mensagem. Como posso ajudar?';
            $resposta = $replaceVariables($resposta);
            logMessage("Enviando mensagem padrão para {$cleanSenderNumber} (Nome: {$senderName})");
        }
    }
    
    // Enviar resposta
    if (!empty($resposta)) {
        if ($tempo_resposta > 0) {
            sleep($tempo_resposta); // Atrasar a resposta conforme configurado
        }
        
        $base_url = rtrim($config['whatsapp']['server'], '/');
        $url = $base_url . '/message/sendText/' . $config['whatsapp']['instance'];
        
        // Payload atualizado para Evolution API v2
        $payload = [
            'number' => $senderNumber, // Mantido $senderNumber para compatibilidade com a API
            'text' => $resposta
        ];
        
        $headers = [
            'apikey: ' . $config['whatsapp']['apikey'],
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
            $autobot_config['estatisticas']['mensagens_respondidas'] = ($autobot_config['estatisticas']['mensagens_respondidas'] ?? 0) + 1;
        } else {
            logMessage("Erro ao enviar para {$cleanSenderNumber} (Nome: {$senderName}). HTTP: {$httpCode}, Response: {$response}", 'error');
        }
    }
    
    // Salvar estatísticas atualizadas
    if (is_writable('data/autobot_config.json')) {
        file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        logMessage("Sem permissão para gravar em data/autobot_config.json", 'error');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Erro ao salvar estatísticas']);
        exit();
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