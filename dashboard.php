<?php
$page_title = 'Dashboard';
include 'includes/auth.php';
verificarLogin();
include 'includes/header.php';

try {
    // Carregar dados do banco de dados
    $bots = fetchAll("SELECT * FROM bots WHERE active = 1");
    $logs = fetchAll("SELECT * FROM logs ORDER BY created_at DESC LIMIT 100");
    $agendamentos = fetchAll("SELECT * FROM scheduled_messages WHERE sent = 0 AND scheduled_time > NOW()");
    
    // Buscar configurações do sistema
    $config_rows = fetchAll("SELECT config_key, config_value FROM system_config WHERE config_key IN ('tmdb_api_key', 'whatsapp_server', 'whatsapp_instance', 'whatsapp_apikey')");
    $config = [];
    foreach ($config_rows as $row) {
        $config[$row['config_key']] = $row['config_value'];
    }
    
    // Buscar configurações do AutoBot
    $autobot_config = fetchOne("SELECT * FROM autobot_config WHERE user_id = ?", [$_SESSION['user_id']]);
    if (!$autobot_config) {
        $autobot_config = [
            'active' => false,
            'greeting_message' => '',
            'default_message' => '',
            'hours_active' => false,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'inactivity_timeout' => 300
        ];
    }
    
    // Buscar estatísticas do AutoBot
    $autobot_stats = fetchOne("SELECT * FROM autobot_statistics WHERE user_id = ?", [$_SESSION['user_id']]);
    if (!$autobot_stats) {
        $autobot_stats = [
            'messages_sent' => 0,
            'conversations_started' => 0,
            'active_keywords' => 0,
            'unique_contacts' => 0
        ];
    }
    
    // Atualizar estatísticas em tempo real
    $active_keywords_count = fetchOne("SELECT COUNT(*) as count FROM autobot_keywords WHERE user_id = ? AND active = 1", [$_SESSION['user_id']])['count'] ?? 0;
    $unique_contacts_count = fetchOne("SELECT COUNT(DISTINCT phone_number) as count FROM autobot_conversations WHERE user_id = ?", [$_SESSION['user_id']])['count'] ?? 0;
    
    // Atualizar na tabela de estatísticas
    executeQuery("INSERT INTO autobot_statistics (user_id, active_keywords, unique_contacts) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE active_keywords = VALUES(active_keywords), unique_contacts = VALUES(unique_contacts)", 
        [$_SESSION['user_id'], $active_keywords_count, $unique_contacts_count]);
    
    $autobot_stats['active_keywords'] = $active_keywords_count;
    $autobot_stats['unique_contacts'] = $unique_contacts_count;
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $bots = [];
    $logs = [];
    $agendamentos = [];
    $config = [];
    $autobot_config = ['active' => false];
    $autobot_stats = ['messages_sent' => 0, 'conversations_started' => 0, 'active_keywords' => 0, 'unique_contacts' => 0];
}

// Estatísticas Gerais do Dashboard
$bots_ativos = count($bots);
$ultimos_logs = array_slice($logs, 0, 5);
$agendamentos_futuros = count($agendamentos);

// Verificar APIs
function verificarAPI($url) {
    if (empty($url)) return false;
    
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

$tmdb_status = !empty($config['tmdb_api_key']) && $config['tmdb_api_key'] != 'SUA_CHAVE_TMDB';
$whatsapp_status = verificarAPI($config['whatsapp_server'] ?? '');
$autobot_status = $autobot_config['active'] ?? false;

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
            <p>bots cadastrados e ativos</p>
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
                    <?= $autobot_stats['conversations_started'] ?>
                </span>
            </div>
            <div style="margin-bottom: 0.8rem;">
                <strong>Mensagens Respondidas:</strong>
                <span style="font-size: 1.2rem; font-weight: bold; color: #28a745;">
                    <?= $autobot_stats['messages_sent'] ?>
                </span>
            </div>
            <div style="margin-bottom: 0.8rem;">
                <strong>Pessoas Respondidas:</strong>
                <span style="font-size: 1.2rem; font-weight: bold; color: #6f42c1;">
                    <?= $autobot_stats['unique_contacts'] ?>
                </span>
            </div>
            <div>
                <strong>Palavras-Chave Ativas:</strong>
                <span style="font-size: 1.2rem; font-weight: bold; color: #ffc107;">
                    <?= $autobot_stats['active_keywords'] ?>
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
                        <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                        <td><?= htmlspecialchars($log['destination']) ?></td>
                        <td><?= htmlspecialchars($log['type']) ?></td>
                        <td>
                            <span class="badge <?= $log['status'] == 'success' ? 'badge-success' : 'badge-danger' ?>">
                                <?= $log['status'] == 'success' ? '✅ Enviado' : '❌ Erro' ?>
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