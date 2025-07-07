<?php
// Desativar limite de tempo de execução para scripts de backend
set_time_limit(0);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclua seus arquivos de configuração e helpers se necessário
// include 'includes/config.php';
// include 'includes/helpers.php'; // Se tiver funções para enviar WhatsApp/Telegram

// Carregar dados
$agendamentos = json_decode(file_get_contents('data/agendamentos.json'), true) ?: [];
$bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];
$config = json_decode(file_get_contents('data/config.json'), true) ?: [];
$logs = json_decode(file_get_contents('data/logs.json'), true) ?: [];

$agendamentos_atualizados = [];
$now = time(); // Hora atual em timestamp

foreach ($agendamentos as &$agendamento) { // Use & para modificar o array original
    // Verifica se o agendamento já passou e ainda não foi enviado
    if (!$agendamento['enviado'] && strtotime($agendamento['data_hora']) <= $now) {
        $sucesso_envio = false;
        $erro_envio = '';
        $log_status = 'erro';
        $bot_usado_nome = '';

        // Lógica de envio baseada na plataforma
        if ($agendamento['plataforma'] == 'telegram') {
            $bot_selecionado = null;
            foreach ($bots as $bot) {
                if ($bot['id'] == $agendamento['bot_id'] && $bot['ativo'] && $bot['tipo'] == 'telegram') {
                    $bot_selecionado = $bot;
                    break;
                }
            }

            if ($bot_selecionado && !empty($bot_selecionado['token'])) {
                $bot_usado_nome = $bot_selecionado['nome'];
                $chat_id = !empty($agendamento['destino']) ? $agendamento['destino'] : ($bot_selecionado['chat_id'] ?? null);

                if ($chat_id) {
                    $url = "https://api.telegram.org/bot" . $bot_selecionado['token'] . "/sendMessage";
                    $post_data = [
                        'chat_id' => $chat_id,
                        'text' => $agendamento['mensagem'],
                        'parse_mode' => 'HTML'
                    ];

                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    curl_close($ch);

                    if ($curl_error) {
                        $erro_envio = "Erro de conexão Telegram: " . $curl_error;
                    } elseif ($httpCode == 200) {
                        $response_data = json_decode($response, true);
                        if (isset($response_data['ok']) && $response_data['ok'] === true) {
                            $sucesso_envio = true;
                            $log_status = 'sucesso';
                        } else {
                            $erro_envio = "Erro Telegram: " . ($response_data['description'] ?? 'Erro desconhecido - ' . $response);
                        }
                    } else {
                        $erro_envio = "Erro HTTP Telegram $httpCode: " . $response;
                    }
                } else {
                    $erro_envio = "Chat ID do Telegram não definido para o bot ou agendamento.";
                }
            } else {
                $erro_envio = "Bot Telegram não encontrado, inativo ou token não configurado.";
            }
        } elseif ($agendamento['plataforma'] == 'whatsapp') {
            $bot_usado_nome = 'WhatsApp API';
            if (empty($config['whatsapp']['server']) || empty($config['whatsapp']['instance']) || empty($config['whatsapp']['apikey'])) {
                $erro_envio = "Configuração do WhatsApp incompleta";
            } else {
                $base_url = rtrim($config['whatsapp']['server'], '/');
                $url = $base_url . '/message/sendText/' . $config['whatsapp']['instance']; // Assumindo apenas texto para agendamento, por simplicidade. Para imagem, precisaria de uma URL pública.
                $data = [
                    'number' => $agendamento['destino'],
                    'text' => $agendamento['mensagem']
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . $config['whatsapp']['apikey']
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($curl_error) {
                    $erro_envio = "Erro de conexão WhatsApp: " . $curl_error;
                } elseif ($httpCode == 201 || $httpCode == 200) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['key']['id']) || isset($response_data['message']['key']) || isset($response_data['data']['key']) || (isset($response_data['status']) && $response_data['status'] === 'success')) {
                        $sucesso_envio = true;
                        $log_status = 'sucesso';
                    } else {
                        $erro_envio = "Resposta inválida da API: " . $response;
                    }
                } else {
                    $response_data = json_decode($response, true);
                    $error_message = $response_data['message'] ?? $response_data['error'] ?? $response;
                    $erro_envio = "Erro HTTP $httpCode: " . $error_message;
                }
            }
        }

        // Atualiza o status do agendamento no array
        $agendamento['enviado'] = $sucesso_envio;
        $agendamento['status_envio'] = $sucesso_envio ? 'Sucesso' : 'Falha: ' . $erro_envio;
        $agendamento['data_envio'] = date('Y-m-d H:i:s');

        // Adiciona ao log
        $logs[] = [
            'id' => time() . rand(100, 999),
            'data_hora' => date('Y-m-d H:i:s'),
            'destino' => $agendamento['destino'],
            'bot' => $bot_usado_nome,
            'tipo' => $agendamento['tipo'],
            'mensagem' => substr($agendamento['mensagem'], 0, 100) . (strlen($agendamento['mensagem']) > 100 ? '...' : ''),
            'status' => $log_status,
            'detalhes_erro' => $erro_envio
        ];
    }
    $agendamentos_atualizados[] = $agendamento; // Adiciona o agendamento (modificado ou não) ao novo array
}

// Salvar agendamentos e logs atualizados
file_put_contents('data/agendamentos.json', json_encode($agendamentos_atualizados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents('data/logs.json', json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Processamento de agendamentos concluído em " . date('Y-m-d H:i:s') . "\n";
?>