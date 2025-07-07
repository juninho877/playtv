
<?php
$page_title = 'Relatórios';
include 'includes/auth.php';
verificarLogin();

$logs = json_decode(file_get_contents('data/logs.json'), true) ?: [];

// Filtros
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_data = $_GET['data'] ?? '';

// Aplicar filtros
$logs_filtrados = $logs;

if ($filtro_tipo) {
    $logs_filtrados = array_filter($logs_filtrados, function($log) use ($filtro_tipo) {
        return stripos($log['tipo'], $filtro_tipo) !== false;
    });
}

if ($filtro_status) {
    $logs_filtrados = array_filter($logs_filtrados, function($log) use ($filtro_status) {
        return $log['status'] == $filtro_status;
    });
}

if ($filtro_data) {
    $logs_filtrados = array_filter($logs_filtrados, function($log) use ($filtro_data) {
        return date('Y-m-d', strtotime($log['data_hora'])) == $filtro_data;
    });
}

// Estatísticas
$total_envios = count($logs);
$envios_sucesso = count(array_filter($logs, function($log) { return $log['status'] == 'sucesso'; }));
$envios_erro = count(array_filter($logs, function($log) { return $log['status'] == 'erro'; }));
$taxa_sucesso = $total_envios > 0 ? round(($envios_sucesso / $total_envios) * 100, 1) : 0;

// Tipos mais enviados
$tipos = [];
foreach ($logs as $log) {
    $tipo = $log['tipo'];
    $tipos[$tipo] = ($tipos[$tipo] ?? 0) + 1;
}
arsort($tipos);

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
        width: 100%; /* Garantir largura uniforme */
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem; /* Espaço entre ícone e texto */
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
    /* Estilizar container de botões no formulário e exportação */
    .button-group {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    /* Mobile-specific styles */
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
            min-height: 48px; /* Altura uniforme */
            padding: 0.5rem;
        }
        .button-group {
            flex-direction: row; /* Lado a lado */
            justify-content: space-between; /* Alinhar com espaçamento uniforme */
        }
        .button-group .btn {
            flex: 1; /* Botões dividem espaço igualmente */
            max-width: calc(50% - 0.25rem); /* Metade da largura menos gap */
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
            flex-direction: column; /* Empilhar em telas muito pequenas */
        }
        .button-group .btn {
            max-width: 100%; /* Voltar à largura total */
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
                    return date('Y-m-d', strtotime($log['data_hora'])) == $hoje;
                }));
            ?>
            <div style="font-size: 2rem; font-weight: bold; color: #ffc107;">
                <?= $envios_hoje ?>
            </div>
            <p>envios hoje</p>
        </div>
    </div>
</div>

<?php if (!empty($tipos)): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-pie-chart"></i>
            Tipos de Conteúdo Mais Enviados
        </h3>
        </div>
    <div class="card-body">
        <div class="row">
            <?php foreach (array_slice($tipos, 0, 6) as $tipo => $quantidade): ?>
            <div class="col-md-4 mb-3">
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <strong><?= $tipo ?></strong>
                    <div style="font-size: 1.5rem; color: #007BFF;"><?= $quantidade ?></div>
                    <div style="background: #007BFF; height: 4px; border-radius: 2px; width: <?= ($quantidade / max($tipos)) * 100 ?>%;"></div>
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
                    <?php foreach (array_keys($tipos) as $tipo): ?>
                    <option value="<?= $tipo ?>" <?= $filtro_tipo == $tipo ? 'selected' : '' ?>>
                        <?= $tipo ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Todos os status</option>
                    <option value="sucesso" <?= $filtro_status == 'sucesso' ? 'selected' : '' ?>>Sucesso</option>
                    <option value="erro" <?= $filtro_status == 'erro' ? 'selected' : '' ?>>Erro</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Data</label>
                <input type="date" name="data" class="form-control" value="<?= $filtro_data ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label"> </label>
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
                    <?php foreach (array_reverse($logs_filtrados) as $log): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($log['data_hora'])) ?></td>
                        <td><?= substr($log['destino'], 0, 20) ?>...</td>
                        <td><?= $log['bot'] ?></td>
                        <td><?= $log['tipo'] ?></td>
                        <td>
                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($log['mensagem']) ?>">
                                <?= substr($log['mensagem'], 0, 50) ?>...
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $log['status'] == 'sucesso' ? 'badge-success' : 'badge-danger' ?>">
                                <?= $log['status'] == 'sucesso' ? '✅ Sucesso' : '❌ Erro' ?>
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
            <a href="?export=csv<?= $filtro_tipo ? '&tipo=' . $filtro_tipo : '' ?><?= $filtro_status ? '&status=' . $filtro_status : '' ?><?= $filtro_data ? '&data=' . $filtro_data : '' ?>" class="btn btn-success">
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
            $log['data_hora'],
            $log['destino'],
            $log['bot'],
            $log['tipo'],
            $log['status'],
            $log['mensagem']
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<?php include 'includes/footer.php'; ?>
