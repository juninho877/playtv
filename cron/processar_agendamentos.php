<?php
// Processador de agendamentos - Executar via cronjob a cada minuto
set_time_limit(0);
ini_set('display_errors', 1);
error_reporting(E_ALL);

$base_path = dirname(__DIR__);

// Função para log
function logMessage($message) {
    $log_file = dirname(__DIR__) . '/data/cron.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message\n";
}

logMessage("Iniciando processador de agendamentos");

// Carregar arquivos
$agendamentos_file = "$base_path/data/agendamentos.json";
$logs_file = "$base_path/data/logs.json";
$config_file = "$base_path/data/config.json";
$bots_file = "$base_path/data/bots.json";

$required_files = [
    'agendamentos' => $agendamentos_file,
    'logs' => $logs_file,
    'config' => $config_file,
    'bots' => $bots_file
];

foreach ($required_files as $name => $file) {
    if (!file_exists($file)) {
        logMessage("ERRO: Arquivo $name não encontrado: $file");
        exit(1);
    }
}

$agendamentos = json_decode(file_get_contents($agendamentos_file), true) ?: [];
$logs = json_decode(file_get_contents($logs_file), true) ?: [];
$config = json_decode(file_get_contents($config_file), true) ?: [];
$bots = json_decode(file_get_contents($bots_file), true) ?: [];

logMessage("Arquivos carregados - Agendamentos: " . count($agendamentos) . ", Bots: " . count($bots));

$agora = time();
$processados = 0;

foreach ($agendamentos as &$agendamento) {
    if ($agendamento['enviado']) continue;

    $hora_agendamento = strtotime($agendamento['data_hora']);
    if ($hora_agendamento > $agora) continue;

    logMessage("Processando agendamento ID: " . $agendamento['id']);

    // Identificar o bot
    $bot = null;
    if (($agendamento['bot_id'] ?? '') == 'whatsapp' || $agendamento['plataforma'] == 'whatsapp') {
        $bot = ['tipo' => 'whatsapp', 'nome' => 'WhatsApp API'];
    } else {
        foreach ($bots as $b) {
            if ($b['id'] == $agendamento['bot_id'] && $b['ativo']) {
                $bot = $b;
                break;
            }
        }
    }

    if (!$bot) {
        $agendamento['enviado'] = true;
        $agendamento['erro'] = 'Bot não encontrado ou inativo';
        logMessage("ERRO: Bot não encontrado ou inativo - ID: " . $agendamento['bot_id']);
        continue;
    }

    $mensagem = $agendamento['mensagem'];
    $item = null;
    $sucesso = false;
    $erro_msg = '';

    // Gerar conteúdo TMDB
    if ($agendamento['tipo'] == 'tmdb' && !empty($agendamento['tmdb_id'])) {
        $tmdb_key = $config['tmdb_key'] ?? '';
        $tipo_tmdb = $agendamento['tipo_tmdb'] ?? 'movie';

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
    if ($bot['tipo'] === 'whatsapp') {
        $whatsapp = $config['whatsapp'] ?? [];
        if (empty($whatsapp['server']) || empty($whatsapp['instance']) || empty($whatsapp['apikey'])) {
            $erro_msg = "Configuração WhatsApp incompleta";
            logMessage("ERRO: $erro_msg");
        } else {
            $base_url = rtrim($whatsapp['server'], '/');

            if ($agendamento['tipo'] === 'tmdb' && $item && !empty($item['poster_path'])) {
                $url = "$base_url/message/sendMedia/" . $whatsapp['instance'];
                $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
                $data = [
                    'number' => $agendamento['destino'],
                    'mediatype' => 'image',
                    'media' => $poster_url,
                    'caption' => strip_tags($mensagem)
                ];
            } else {
                $url = "$base_url/message/sendText/" . $whatsapp['instance'];
                $data = [
                    'number' => $agendamento['destino'],
                    'text' => strip_tags($mensagem)
                ];
            }

            logMessage("Enviando para WhatsApp: " . $agendamento['destino']);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'apikey: ' . $whatsapp['apikey']
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
    elseif ($bot['tipo'] === 'telegram') {
        if (empty($bot['token'])) {
            $erro_msg = "Token Telegram não configurado";
            logMessage("ERRO: $erro_msg");
        } else {
            $chat_id = $agendamento['destino'];
            if ($agendamento['tipo'] === 'tmdb' && $item && !empty($item['poster_path'])) {
                $url = "https://api.telegram.org/bot{$bot['token']}/sendPhoto";
                $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
                $data = [
                    'chat_id' => $chat_id,
                    'photo' => $poster_url,
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

    // Marcar como processado
    $agendamento['enviado'] = true;
    $agendamento['processado_em'] = date('Y-m-d H:i:s');
    if (!$sucesso) $agendamento['erro'] = $erro_msg;

    // Log individual
    $logs[] = [
        'id' => time() . rand(100, 999),
        'data_hora' => date('Y-m-d H:i:s'),
        'destino' => $agendamento['destino'],
        'bot' => $bot['nome'],
        'tipo' => 'Agendamento - ' . $agendamento['tipo'],
        'mensagem' => substr($mensagem, 0, 100) . '...',
        'status' => $sucesso ? 'sucesso' : 'erro'
    ];

    $processados++;
    sleep(1); // evitar flood
}

// Salvar arquivos
file_put_contents($agendamentos_file, json_encode($agendamentos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($logs_file, json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

logMessage("Processamento concluído. $processados agendamentos processados.");
