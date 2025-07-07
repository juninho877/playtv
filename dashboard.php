<?php
$page_title = 'Dashboard';
include 'includes/auth.php';
verificarLogin();
include 'includes/header.php';

// Criar diretório data se não existir
if (!file_exists('data')) {
    mkdir('data', 0755, true);
}

// Inicializar arquivos JSON se não existirem
$files_to_check = [
    'data/bots.json' => '[]',
    'data/logs.json' => '[]',
    'data/agendamentos.json' => '[]',
    'data/config.json' => json_encode([
        "tmdb_key" => "SUA_CHAVE_TMDB",
        "whatsapp" => [
            "server" => "https://evov2.duckdns.org",
            "instance" => "SEU_INSTANCE",
            "apikey" => "79Bb4lpu2TzxrSMu3SDfSGvB3MIhkur7"
        ]
    ], JSON_PRETTY_PRINT),
    'data/autobot_config.json' => json_encode([
        'ativo' => false,
        'emoji' => '👋',
        'saudacao' => 'Olá! Seja bem-vindo(a)! Como posso ajudá-lo(a) hoje?',
        'mensagem_padrao' => 'Desculpe, não entendi sua mensagem. Digite *MENU* para ver as opções disponíveis ou aguarde que em breve um atendente irá lhe responder.',
        'mensagem_fora_horario' => 'Estamos fora do horário de atendimento. Nosso horário é de {{horario_inicio}} às {{horario_fim}}. Retornaremos em breve!',
        'horario_ativo' => false,
        'horario_inicio' => '08:00',
        'horario_fim' => '18:00',
        'tempo_inatividade' => 300,
        'estatisticas' => [
            'mensagens_respondidas' => 0,
            'conversas_iniciadas' => 0,
            'palavras_ativadas' => 0, // Esta é a contagem de palavras-chave ATIVAS (configuradas como ativas)
            'pessoas_respondidas' => 0
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    'data/conversas.json' => '[]',
    'data/palavras_chave.json' => '[]' // Garantir que palavras_chave.json existe
];

foreach ($files_to_check as $file => $default_content) {
    if (!file_exists($file)) {
        file_put_contents($file, $default_content);
    }
}

// Carregar dados
$bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];
$logs = json_decode(file_get_contents('data/logs.json'), true) ?: [];
$agendamentos = json_decode(file_get_contents('data/agendamentos.json'), true) ?: [];
$config = json_decode(file_get_contents('data/config.json'), true) ?: [];
$autobot_config = json_decode(file_get_contents('data/autobot_config.json'), true) ?: [];
$conversas = json_decode(file_get_contents('data/conversas.json'), true) ?: [];
$palavras_chave = json_decode(file_get_contents('data/palavras_chave.json'), true) ?: [];


// --- INÍCIO DA LÓGICA DE ATUALIZAÇÃO DAS ESTATÍSTICAS TOTAIS ---
// IMPORTANTE: Essas estatísticas devem ser atualizadas pelo seu webhook ou lógica do bot
// que processa as mensagens, garantindo que os contadores em autobot_config.json sejam
// incrementados para refletir os totais.

// Exemplo de como 'pessoas_respondidas' é atualizado no autobot.php:
// $autobot_config['estatisticas']['pessoas_respondidas'] = count(array_unique(array_column($conversas, 'numero')));
// Isso deve ser feito na lógica do bot ou em autobot.php antes de ser lido aqui para ser preciso.

// Contagem de palavras-chave ativas (já está no autobot.php)
$autobot_config['estatisticas']['palavras_ativadas'] = count(array_filter($palavras_chave, fn($p) => $p['ativo']));
// Salva a config atualizada se a contagem de palavras-chave mudou
file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
// --- FIM DA LÓGICA DE ATUALIZAÇÃO DAS ESTATÍSTICAS TOTAIS ---

// Estatísticas Gerais do Dashboard
$bots_ativos = count(array_filter($bots, function($bot) { return $bot['ativo'] ?? false; }));
$ultimos_logs = array_slice(array_reverse($logs), 0, 5);
$agendamentos_futuros = count(array_filter($agendamentos, function($ag) {
    return !($ag['enviado'] ?? false) && strtotime($ag['data_hora']) > time();
}));

// Verificar APIs
function verificarAPI($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode == 200;
}

$tmdb_status = !empty($config['tmdb_key']) && $config['tmdb_key'] != 'SUA_CHAVE_TMDB';
$whatsapp_status = verificarAPI($config['whatsapp']['server'] ?? '');
$autobot_status = $autobot_config['ativo'] ?? false; // Status do AutoBot

// As estatísticas do AutoBot serão lidas diretamente de $autobot_config
$mensagens_respondidas_total = $autobot_config['estatisticas']['mensagens_respondidas'] ?? 0;
$conversas_iniciadas_total = $autobot_config['estatisticas']['conversas_iniciadas'] ?? 0;
$pessoas_respondidas_total = $autobot_config['estatisticas']['pessoas_respondidas'] ?? 0;
$palavras_ativas_total = $autobot_config['estatisticas']['palavras_ativadas'] ?? 0;

?>

<div class="cards-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-robot"></i>
                Bots Ativos
            </h3>
        </div>
        <div class="card-body">
            <div style="font-size: 2rem; font-weight: bold; color: #007BFF;">
                <?= $bots_ativos ?>
            </div>
            <p>de <?= count($bots) ?> bots cadastrados</p>
        </div>
        <div class="card-footer">
            <a href="bots.php" class="btn btn-primary btn-sm">Gerenciar Bots</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-calendar-event"></i>
                Agendamentos
            </h3>
        </div>
        <div class="card-body">
            <div style="font-size: 2rem; font-weight: bold; color: #28a745;">
                <?= $agendamentos_futuros ?>
            </div>
            <p>mensagens agendadas</p>
        </div>
        <div class="card-footer">
            <a href="agendar.php" class="btn btn-success btn-sm">Ver Agendamentos</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-check-circle"></i>
                Status dos Serviços
            </h3>
        </div>
        <div class="card-body">
            <div style="margin-bottom: 1rem;">
                <strong>AutoBot:</strong>
                <span class="badge <?= $autobot_status ? 'badge-success' : 'badge-danger' ?>">
                    <?= $autobot_status ? '✅ Ativo' : '❌ Inativo' ?>
                </span>
            </div>
            <div style="margin-bottom: 1rem;">
                <strong>TMDB:</strong>
                <span class="badge <?= $tmdb_status ? 'badge-success' : 'badge-danger' ?>">
                    <?= $tmdb_status ? '✅ Ativo' : '❌ Inativo' ?>
                </span>
            </div>
            <div>
                <strong>WhatsApp API:</strong>
                <span class="badge <?= $whatsapp_status ? 'badge-success' : 'badge-danger' ?>">
                    <?= $whatsapp_status ? '✅ Ativo' : '❌ Inativo' ?>
                </span>
            </div>
        </div>
        <div class="card-footer d-flex justify-content-end align-items-center">
            <button class="btn btn-success btn-sm" onclick="location.reload();">
                <i class="bi bi-arrow-clockwise"></i> Atualizar Status
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-graph-up"></i>
                Total de Envios
            </h3>
        </div>
        <div class="card-body">
            <div style="font-size: 2rem; font-weight: bold; color: #ffc107;">
                <?= count($logs) ?>
            </div>
            <p>mensagens enviadas</p>
        </div>
        <div class="card-footer">
            <a href="relatorio.php" class="btn btn-secondary btn-sm">Ver Relatório</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-bar-chart-line"></i>
                AutoBot Estatísticas Gerais
            </h3>
        </div>
        <div class="card-body">
            <div style="margin-bottom: 0.8rem;">
                <strong>Conversas Iniciadas:</strong>
                <span style="font-size: 1.2rem; font-weight: bold; color: #007BFF;">
                    <?= $conversas_iniciadas_total ?>
                </span>
            </div>
            <div style="margin-bottom: 0.8rem;">
                <strong>Mensagens Respondidas:</strong>
                <span style="font-size: 1.2rem; font-weight: bold; color: #28a745;">
                    <?= $mensagens_respondidas_total ?>
                </span>
            </div>
            <div style="margin-bottom: 0.8rem;">
                <strong>Pessoas Respondidas:</strong>
                <span style="font-size: 1.2rem; font-weight: bold; color: #6f42c1;">
                    <?= $pessoas_respondidas_total ?>
                </span>
            </div>
            <div>
                <strong>Palavras-Chave Ativas:</strong>
                <span style="font-size: 1.2rem; font-weight: bold; color: #ffc107;">
                    <?= $palavras_ativas_total ?>
                </span>
                <small class="text-muted d-block">(Número total de palavras-chave *configuradas como ativas*.)</small>
            </div>
        </div>
        <div class="card-footer">
            <a href="auto_bot.php" class="btn btn-primary btn-sm">Configurar AutoBot e Ver Detalhes</a>
        </div>
    </div>
    </div>

<?php if (!empty($ultimos_logs)): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-clock-history"></i>
            Últimos Envios
        </h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Destino</th>
                        <th>Tipo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimos_logs as $log): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($log['data_hora'])) ?></td>
                        <td><?= $log['destino'] ?></td>
                        <td><?= $log['tipo'] ?></td>
                        <td>
                            <span class="badge <?= $log['status'] == 'sucesso' ? 'badge-success' : 'badge-danger' ?>">
                                <?= $log['status'] == 'sucesso' ? '✅ Enviado' : '❌ Erro' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Atualizar status das APIs a cada 30 segundos
setInterval(function() {
    location.reload();
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>