<?php
$page_title = 'Relatórios';
include 'includes/auth.php';
verificarLogin();

// Filtros
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_data = $_GET['data'] ?? '';

// Carregar dados do banco
try {
    // Query base
    $where_conditions = ["user_id = ?"];
    $params = [$_SESSION['user_id']];
    
    // Aplicar filtros
    if ($filtro_tipo) {
        $where_conditions[] = "type LIKE ?";
        $params[] = "%$filtro_tipo%";
    }
    
    if ($filtro_status) {
        $where_conditions[] = "status = ?";
        $params[] = $filtro_status;
    }
    
    if ($filtro_data) {
        $where_conditions[] = "DATE(created_at) = ?";
        $params[] = $filtro_data;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Buscar logs filtrados
    $logs_filtrados = fetchAll("SELECT * FROM logs WHERE $where_clause ORDER BY created_at DESC", $params);
    
    // Buscar todos os logs para estatísticas gerais
    $logs = fetchAll("SELECT * FROM logs WHERE user_id = ? ORDER BY created_at DESC", [$_SESSION['user_id']]);
    
    // Buscar tipos únicos para o filtro
    $tipos_result = fetchAll("SELECT DISTINCT type FROM logs WHERE user_id = ? AND type IS NOT NULL ORDER BY type", [$_SESSION['user_id']]);
    $tipos = array_column($tipos_result, 'type');
    
} catch (Exception $e) {
    error_log("Relatório error: " . $e->getMessage());
    $logs_filtrados = [];
    $logs = [];
    $tipos = [];
}

// Estatísticas
$total_envios = count($logs);
$envios_sucesso = count(array_filter($logs, function($log) { return $log['status'] == 'success'; }));
$envios_erro = count(array_filter($logs, function($log) { return $log['status'] == 'error'; }));
$taxa_sucesso = $total_envios > 0 ? round(($envios_sucesso / $total_envios) * 100, 1) : 0;

// Tipos mais enviados
$tipos_count = [];
foreach ($logs as $log) {
    $tipo = $log['type'] ?? 'Não definido';
    $tipos_count[$tipo] = ($tipos_count[$tipo] ?? 0) + 1;
}
arsort($tipos_count);

include 'includes/header.php';
?>

<style>
    .cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
    }
    .card {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    .card-header {
        padding: 1rem;
        border-bottom: 1px solid #dee2e6;
    }
    .card-title {
        margin: 0;
        font-size: 1.25rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .card-body {
        padding: 1rem;
    }
    .form-select, .form-control {
        min-height: 44px;
        touch-action: manipulation;
        font-size: 1rem;
    }
    .btn {
        min-height: 44px;
        touch-action: manipulation;
        font-size: 1rem;
        padding: 0.5rem 1rem;
        width: 100%;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    .table-responsive {
        overflow-x: auto;
    }
    .table {
        width: 100%;
        font-size: 0.9rem;
    }
    .table th, .table td {
        padding: 0.75rem;
        vertical-align: middle;
    }
    .badge {
        padding: 0.5rem;
        font-size: 0.85rem;
    }
    .alert {
        padding: 1rem;
        border-radius: 5px;
    }
    .button-group {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    @media (max-width: 768px) {
        .cards-grid {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
        .card {
            margin-bottom: 1rem;
        }
        .card-title {
            font-size: 1.1rem;
        }
        .card-body {
            padding: 0.75rem;
        }
        .card-body div[style*="font-size: 2rem"] {
            font-size: 1.5rem;
        }
        .row.g-3 {
            margin-bottom: 1rem;
        }
        .col-md-3 {
            margin-bottom: 0.75rem;
        }
        .form-select, .form-control {
            font-size: 0.9rem;
            padding: 0.5rem;
        }
        .btn {
            font-size: 0.9rem;
            min-height: 48px;
            padding: 0.5rem;
        }
        .button-group {
            flex-direction: row;
            justify-content: space-between;
        }
        .button-group .btn {
            flex: 1;
            max-width: calc(50% - 0.25rem);
        }
        .table {
            font-size: 0.8rem;
        }
        .table th, .table td {
            padding: 0.5rem;
        }
        .table td div[style*="max-width: 200px"] {
            max-width: 120px;
            font-size: 0.75rem;
        }
        .badge {
            font-size: 0.75rem;
        }
        .alert {
            font-size: 0.9rem;
            padding: 0.75rem;
        }
        .row > div[class*="col-"] {
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        div[style*="background: #007BFF; height: 4px"] {
            height: 3px;
        }
    }
    @media (max-width: 576px) {
        .card-title {
            font-size: 1rem;
        }
        .card-body div[style*="font-size: 2rem"] {
            font-size: 1.25rem;
        }
        .table th, .table td {
            padding: 0.4rem;
        }
        .table td div[style*="max-width: 200px"] {
            max-width: 100px;
        }
        .button-group {
            flex-direction: column;
        }
        .button-group .btn {
            max-width: 100%;
        }
    }
</style>

<div class="cards-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-graph-up"></i>
                Total de Envios
            </h3>
        </div>
        <div class="card-body">
            <div style="font-size: 2rem; font-weight: bold; color: #007BFF;">
                <?= $total_envios ?>
            </div>
            <p>mensagens enviadas</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-check-circle"></i>
                Envios com Sucesso
            </h3>
        </div>
        <div class="card-body">
            <div style="font-size: 2rem; font-weight: bold; color: #28a745;">
                <?= $envios_sucesso ?>
            </div>
            <p><?= $taxa_sucesso ?>% de taxa de sucesso</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-x-circle"></i>
                Envios com Erro
            </h3>
        </div>
        <div class="card-body">
            <div style="font-size: 2rem; font-weight: bold; color: #dc3545;">
                <?= $envios_erro ?>
            </div>
            <p><?= round(100 - $taxa_sucesso, 1) ?>% de taxa de erro</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-calendar-day"></i>
                Hoje
            </h3>
        </div>
        <div class="card-body">
            <?php
                $hoje = date('Y-m-d');
                $envios_hoje = count(array_filter($logs, function($log) use ($hoje) {
                    return date('Y-m-d', strtotime($log['created_at'])) == $hoje;
                }));
            ?>
            <div style="font-size: 2rem; font-weight: bold; color: #ffc107;">
                <?= $envios_hoje ?>
            </div>
            <p>envios hoje</p>
        </div>
    </div>
</div>

<?php if (!empty($tipos_count)): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-pie-chart"></i>
            Tipos de Conteúdo Mais Enviados
        </h3>
        </div>
    <div class="card-body">
        <div class="row">
            <?php foreach (array_slice($tipos_count, 0, 6) as $tipo => $quantidade): ?>
            <div class="col-md-4 mb-3">
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <strong><?= htmlspecialchars($tipo) ?></strong>
                    <div style="font-size: 1.5rem; color: #007BFF;"><?= $quantidade ?></div>
                    <div style="background: #007BFF; height: 4px; border-radius: 2px; width: <?= ($quantidade / max($tipos_count)) * 100 ?>%;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-funnel"></i>
            Filtros e Histórico
        </h3>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($tipos as $tipo): ?>
                    <option value="<?= htmlspecialchars($tipo) ?>" <?= $filtro_tipo == $tipo ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tipo) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="success" <?= $filtro_status == 'success' ? 'selected' : '' ?>>Sucesso</option>
                    <option value="error" <?= $filtro_status == 'error' ? 'selected' : '' ?>>Erro</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Data</label>
                <input type="date" name="data" class="form-control" value="<?= htmlspecialchars($filtro_data) ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label"> </label>
                <div class="button-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="relatorio.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-clockwise"></i> Limpar
                    </a>
                </div>
            </div>
        </form>

        <?php if (!empty($logs_filtrados)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Destino</th>
                        <th>Bot</th>
                        <th>Tipo</th>
                        <th>Mensagem</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs_filtrados as $log): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                        <td><?= htmlspecialchars(substr($log['destination'], 0, 20)) ?>...</td>
                        <td><?= htmlspecialchars($log['bot_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($log['type'] ?? 'N/A') ?></td>
                        <td>
                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($log['message'] ?? '') ?>">
                                <?= htmlspecialchars(substr($log['message'] ?? '', 0, 50)) ?>...
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $log['status'] == 'success' ? 'badge-success' : 'badge-danger' ?>">
                                <?= $log['status'] == 'success' ? '✅ Sucesso' : '❌ Erro' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="alert alert-info">
            <strong>Resultados:</strong> Mostrando <?= count($logs_filtrados) ?> de <?= $total_envios ?> registros
        </div>
        
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            Nenhum registro encontrado com os filtros aplicados.
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-download"></i>
            Exportar Dados
        </h3>
    </div>
    <div class="card-body">
        <p>Exporte os dados do relatório para análise externa:</p>
        <div class="button-group">
            <a href="?export=csv<?= $filtro_tipo ? '&tipo=' . urlencode($filtro_tipo) : '' ?><?= $filtro_status ? '&status=' . urlencode($filtro_status) : '' ?><?= $filtro_data ? '&data=' . urlencode($filtro_data) : '' ?>" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="bi bi-printer"></i> Imprimir Relatório
            </button>
        </div>
    </div>
</div>

<?php
// Processar export CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_botsystem_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Data/Hora', 'Destino', 'Bot', 'Tipo', 'Status', 'Mensagem']);
    
    foreach ($logs_filtrados as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['destination'],
            $log['bot_name'] ?? 'N/A',
            $log['type'] ?? 'N/A',
            $log['status'],
            $log['message'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<?php include 'includes/footer.php'; ?>