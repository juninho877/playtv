<?php
$page_title = 'Agendar Envios';
include 'includes/auth.php';
verificarLogin();

$sucesso = '';
$erro = '';

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'agendar':
                    $plataforma = $_POST['plataforma'];
                    $bot_id = $_POST['bot_id'] ?? null;
                    $data_hora = $_POST['data_hora'];
                    $destino = $_POST['destino'];
                    $tipo = $_POST['tipo'];
                    $mensagem = $_POST['mensagem'] ?? '';
                    $tmdb_id = $_POST['tmdb_id'] ?? null;
                    $tipo_tmdb = $_POST['tipo_tmdb'] ?? null;
                    
                    // Se for WhatsApp, bot_id pode ser null
                    if ($plataforma == 'whatsapp') {
                        $bot_id = null;
                    }
                    
                    $image_path = null;
                    
                    // Processar upload de imagem
                    if ($tipo === 'image' && isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = 'uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
                        $nome_arquivo = uniqid() . '.' . $ext;
                        $caminho_arquivo = $upload_dir . $nome_arquivo;
                        
                        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_arquivo)) {
                            $image_path = $caminho_arquivo;
                        } else {
                            $erro = "Erro ao fazer upload da imagem. Verifique permissões da pasta 'uploads/'.";
                            break;
                        }
                    } elseif ($tipo === 'image' && (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK)) {
                        $erro = "Você selecionou 'Imagem com Legenda', mas nenhuma imagem válida foi enviada.";
                        break;
                    }
                    
                    // Inserir agendamento no banco
                    executeQuery("INSERT INTO scheduled_messages (scheduled_time, bot_id, platform, destination, type, message, image_path, tmdb_id, tmdb_type, sent, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())", 
                        [$data_hora, $bot_id, $plataforma, $destino, $tipo, $mensagem, $image_path, $tmdb_id, $tipo_tmdb, $_SESSION['user_id']]);
                    
                    $sucesso = "Agendamento criado com sucesso!";
                    break;
                    
                case 'excluir':
                    $id = (int)$_POST['id'];
                    
                    $affected = executeQuery("DELETE FROM scheduled_messages WHERE id = ? AND user_id = ?", [$id, $_SESSION['user_id']])->rowCount();
                    
                    if ($affected > 0) {
                        $sucesso = "Agendamento excluído!";
                    } else {
                        $erro = "Agendamento não encontrado ou você não tem permissão para excluí-lo!";
                    }
                    break;

                case 'atualizar_status':
                    $id = (int)$_POST['id'];
                    
                    $affected = executeQuery("UPDATE scheduled_messages SET sent = 1, processed_at = NOW() WHERE id = ? AND user_id = ? AND sent = 0 AND scheduled_time <= NOW()", 
                        [$id, $_SESSION['user_id']])->rowCount();
                    
                    if ($affected > 0) {
                        $sucesso = "Status atualizado!";
                    } else {
                        $erro = "Não foi possível atualizar o status do agendamento!";
                    }
                    break;

                case 'limpar_concluidos':
                    $affected = executeQuery("DELETE FROM scheduled_messages WHERE user_id = ? AND (sent = 1 OR scheduled_time < NOW())", 
                        [$_SESSION['user_id']])->rowCount();
                    
                    $sucesso = "Agendamentos concluídos ou expirados foram removidos! ($affected registros)";
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Agendar error: " . $e->getMessage());
        $erro = "Erro interno. Tente novamente.";
    }
}

// Carregar dados do banco
try {
    $bots = fetchAll("SELECT * FROM bots WHERE active = 1 AND (user_id = ? OR user_id IS NULL)", [$_SESSION['user_id']]);
    $agendamentos = fetchAll("SELECT * FROM scheduled_messages WHERE user_id = ? ORDER BY created_at DESC", [$_SESSION['user_id']]);
    $grupos = fetchAll("SELECT * FROM groups WHERE user_id = ?", [$_SESSION['user_id']]);
    
    // Buscar chave TMDB
    $tmdb_config = fetchOne("SELECT config_value FROM system_config WHERE config_key = 'tmdb_api_key'");
    $tmdb_key = $tmdb_config['config_value'] ?? '';
} catch (Exception $e) {
    error_log("Agendar load error: " . $e->getMessage());
    $bots = [];
    $agendamentos = [];
    $grupos = [];
    $tmdb_key = '';
}

// Se veio do TMDB
$tmdb_id = $_GET['tmdb_id'] ?? '';
$tipo_tmdb = $_GET['tipo'] ?? '';
$titulo_tmdb = $_GET['titulo'] ?? '';

include 'includes/header.php';
?>

<?php if (!empty($sucesso)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($sucesso) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= htmlspecialchars($erro) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-calendar-plus"></i>
            Novo Agendamento
        </h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="agendar">
            <input type="hidden" name="plataforma" id="hiddenPlataforma">
            <?php if ($tmdb_id): ?>
            <input type="hidden" name="tmdb_id" value="<?= htmlspecialchars($tmdb_id) ?>">
            <input type="hidden" name="tipo_tmdb" value="<?= htmlspecialchars($tipo_tmdb) ?>">
            <div class="alert alert-info">
                <strong>Conteúdo TMDB selecionado:</strong> <?= htmlspecialchars($titulo_tmdb) ?>
                <br><small>A mensagem será gerada automaticamente com base nos dados do TMDB</small>
            </div>
            <?php endif; ?>
            
            <div class="form-group mb-3">
                <label class="form-label">Data e Hora</label>
                <input type="datetime-local" name="data_hora" class="form-control" required
                        value="<?= date('Y-m-d\TH:i', strtotime('now')) ?>" min="<?= date('Y-m-d\TH:i') ?>">
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Plataforma de Envio</label>
                <select name="plataforma" id="plataformaAgendar" class="form-select" required onchange="toggleBotAgendamento()">
                    <option value="">Selecione a plataforma...</option>
                    <option value="telegram">Telegram</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>

            <div class="form-group mb-3" id="grupoBotAgendamento" style="display: none;">
                <label class="form-label">Bot</label>
                <select name="bot_id" class="form-select">
                    <option value="">Escolha um bot...</option>
                    <?php foreach ($bots as $bot): ?>
                        <?php if ($bot['active'] && $bot['type'] == 'telegram'): ?>
                        <option value="<?= $bot['id'] ?>">
                            <?= htmlspecialchars($bot['name']) ?> (<?= ucfirst($bot['type']) ?>)
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Destino</label>
                <select name="destino" id="destinoAgendar" class="form-select" required>
                    <option value="">Selecione um grupo...</option>
                </select>
                <small class="form-text text-muted" id="destinoHelpAgendar">
                    Selecione uma plataforma primeiro
                </small>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" id="tipoEnvio" class="form-select" required onchange="toggleFieldsBasedOnType()">
                    <?php if ($tmdb_id): ?>
                    <option value="tmdb">Conteúdo TMDB</option>
                    <?php else: ?>
                    <option value="">Selecione...</option>
                    <option value="text">Texto</option>
                    <option value="image">Imagem com Legenda</option>
                    <?php endif; ?>
                </select>
            </div>

            <?php if (!$tmdb_id): ?>
            <div class="form-group mb-3" id="mensagemField">
                <label class="form-label">Mensagem</label>
                <textarea name="mensagem" id="mensagemInput" class="form-control" rows="6" placeholder="Digite a mensagem que será enviada..."></textarea>
            </div>
            <div class="form-group mb-3" id="imagemField" style="display: none;">
                <label class="form-label">Imagem</label>
                <input type="file" name="imagem" id="imagemInput" class="form-control" accept="image/*">
                <div id="imagemPreview" class="mt-2" style="display: none;">
                    <img id="previewImg" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                </div>
            </div>
            <?php else: ?>
            <input type="hidden" name="mensagem" value="[Conteúdo TMDB será gerado automaticamente]">
            <?php endif; ?>

            <button type="submit" class="btn btn-success">
                <i class="bi bi-calendar-check"></i> Agendar Envio
            </button>
            <a href="<?= $tmdb_id ? 'tmdb.php' : 'agendar.php' ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </form>
    </div>
</div>

<?php if (!empty($agendamentos)): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-calendar-event"></i>
            Agendamentos Criados
        </h3>
        <div class="card-tools" style="float: right;">
            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja limpar todos os agendamentos concluídos ou expirados?')">
                <input type="hidden" name="action" value="limpar_concluidos">
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="bi bi-eraser"></i> Limpar Concluídos
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Bot</th>
                        <th>Destino</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($agendamentos) as $ag): ?>
                    <?php
                        $bot_nome = $ag['platform'] == 'whatsapp' ? 'WhatsApp' : 'Bot não encontrado';
                        if ($ag['bot_id']) {
                            foreach ($bots as $bot) {
                                if ($bot['id'] == $ag['bot_id']) {
                                    $bot_nome = $bot['name'];
                                    break;
                                }
                            }
                        }
                        
                        $status_class = 'badge-warning';
                        $status_text = '⏳ Pendente';
                        if ($ag['sent']) {
                            $status_class = 'badge-success';
                            $status_text = '✅ Enviado';
                        } elseif (strtotime($ag['scheduled_time']) < time()) {
                            $status_class = 'badge-danger';
                            $status_text = '❌ Expirado';
                        }
                    ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($ag['scheduled_time'])) ?></td>
                        <td><?= htmlspecialchars($bot_nome) ?></td>
                        <td><?= htmlspecialchars(substr($ag['destination'], 0, 20)) ?>...</td>
                        <td><?= ucfirst($ag['type']) ?></td>
                        <td>
                            <span class="badge <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$ag['sent'] && strtotime($ag['scheduled_time']) > time()): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este agendamento?')">
                                <input type="hidden" name="action" value="excluir">
                                <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-info" onclick="verDetalhes(<?= htmlspecialchars(json_encode($ag)) ?>)">
                                <i class="bi bi-eye"></i>
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="atualizar_status">
                                <input type="hidden" name="id" value="<?= $ag['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-primary" title="Atualizar Status">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="detalhesModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 600px;">
        <h3>Detalhes do Agendamento</h3>
        <div id="detalhesConteudo"></div>
        <div style="text-align: right; margin-top: 1rem;">
            <button type="button" onclick="fecharDetalhes()" class="btn btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<script>
function verDetalhes(agendamento) {
    let conteudo = `
        <div style="margin-bottom: 1rem;">
            <strong>Data/Hora:</strong> ${new Date(agendamento.scheduled_time).toLocaleString('pt-BR')}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Plataforma:</strong> ${agendamento.platform ? agendamento.platform.charAt(0).toUpperCase() + agendamento.platform.slice(1) : 'N/A'}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Bot ID:</strong> ${agendamento.bot_id || 'N/A'}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Destino:</strong> ${agendamento.destination}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Tipo:</strong> ${agendamento.type.charAt(0).toUpperCase() + agendamento.type.slice(1)}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Mensagem:</strong>
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 0.5rem; white-space: pre-wrap;">${agendamento.message || '[Nenhuma mensagem]'}</div>
        </div>
        ${agendamento.image_path ? `
        <div style="margin-bottom: 1rem;">
            <strong>Imagem:</strong>
            <div><img src="${agendamento.image_path}" style="max-width: 200px; max-height: 200px; object-fit: cover;"></div>
        </div>` : ''}
        ${agendamento.tmdb_id ? `
        <div style="margin-bottom: 1rem;">
            <strong>TMDB ID:</strong> ${agendamento.tmdb_id} (${agendamento.tmdb_type})
        </div>` : ''}
        <div style="margin-bottom: 1rem;">
            <strong>Criado em:</strong> ${new Date(agendamento.created_at).toLocaleString('pt-BR')}
        </div>
    `;
    
    document.getElementById('detalhesConteudo').innerHTML = conteudo;
    document.getElementById('detalhesModal').style.display = 'block';
}

function fecharDetalhes() {
    document.getElementById('detalhesModal').style.display = 'none';
}

function toggleBotAgendamento() {
    const plataforma = document.getElementById('plataformaAgendar').value;
    const grupoBotAgendamento = document.getElementById('grupoBotAgendamento');
    const destinoSelect = document.getElementById('destinoAgendar');
    const destinoHelp = document.getElementById('destinoHelpAgendar');
    const hiddenPlataforma = document.getElementById('hiddenPlataforma');
    
    hiddenPlataforma.value = plataforma;
    destinoSelect.innerHTML = '<option value="">Selecione um grupo...</option>';
    
    if (plataforma === 'telegram') {
        grupoBotAgendamento.style.display = 'block';
        grupoBotAgendamento.querySelector('select').required = true;
        destinoHelp.innerHTML = '<strong>Telegram:</strong> Selecione um grupo cadastrado';
        <?php foreach ($grupos as $grupo): ?>
            <?php if ($grupo['type'] === 'telegram'): ?>
                destinoSelect.innerHTML += `<option value="<?= htmlspecialchars($grupo['external_id']) ?>"><?= htmlspecialchars($grupo['name']) ?> (<?= htmlspecialchars($grupo['external_id']) ?>)</option>`;
            <?php endif; ?>
        <?php endforeach; ?>
    } else if (plataforma === 'whatsapp') {
        grupoBotAgendamento.style.display = 'none';
        grupoBotAgendamento.querySelector('select').required = false;
        destinoHelp.innerHTML = '<strong>WhatsApp:</strong> Selecione um grupo cadastrado';
        <?php foreach ($grupos as $grupo): ?>
            <?php if ($grupo['type'] === 'whatsapp'): ?>
                destinoSelect.innerHTML += `<option value="<?= htmlspecialchars($grupo['external_id']) ?>"><?= htmlspecialchars($grupo['name']) ?> (<?= htmlspecialchars($grupo['external_id']) ?>)</option>`;
            <?php endif; ?>
        <?php endforeach; ?>
    } else {
        grupoBotAgendamento.style.display = 'none';
        grupoBotAgendamento.querySelector('select').required = false;
        destinoHelp.innerHTML = 'Selecione uma plataforma primeiro';
    }
}

function toggleFieldsBasedOnType() {
    const tipo = document.getElementById('tipoEnvio').value;
    const mensagemField = document.getElementById('mensagemField');
    const mensagemInput = document.getElementById('mensagemInput');
    const imagemField = document.getElementById('imagemField');
    const imagemInput = document.getElementById('imagemInput');

    // Reset required states
    if (mensagemInput) mensagemInput.required = false;
    if (imagemInput) imagemInput.required = false;
    if (mensagemField) mensagemField.style.display = 'block';
    if (imagemField) imagemField.style.display = 'none';

    // Determine visibility and required status based on type
    if (tipo === 'text') {
        if (mensagemInput) mensagemInput.required = true;
    } else if (tipo === 'image') {
        if (imagemField) imagemField.style.display = 'block';
        if (imagemInput) imagemInput.required = true;
    } else if (tipo === 'tmdb') {
        if (mensagemField) mensagemField.style.display = 'none';
    } else {
        if (mensagemInput) mensagemInput.required = true;
    }
    
    // Ensure preview is hidden if type changes away from image
    if (tipo !== 'image') {
        const imagemPreview = document.getElementById('imagemPreview');
        const previewImg = document.getElementById('previewImg');
        if (imagemPreview) imagemPreview.style.display = 'none';
        if (previewImg) previewImg.src = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const imagemInput = document.getElementById('imagemInput');
    const imagemPreview = document.getElementById('imagemPreview');
    const previewImg = document.getElementById('previewImg');

    if (imagemInput && imagemPreview && previewImg) {
        imagemInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagemPreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                imagemPreview.style.display = 'none';
                previewImg.src = '';
            }
        });
    }

    // Initial call to set correct field states on page load
    toggleFieldsBasedOnType();
    toggleBotAgendamento();
});
</script>

<?php include 'includes/footer.php'; ?>