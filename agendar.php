<?php
$page_title = 'Agendar Envios';
include 'includes/auth.php';
verificarLogin();

// Carregar dados
$bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];
$agendamentos = json_decode(file_get_contents('data/agendamentos.json'), true) ?: [];
$grupos = json_decode(file_get_contents('data/grupos.json'), true) ?: [];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debugging: Uncomment the lines below to inspect $_POST and $_FILES arrays
    /*
    echo '<pre>';
    print_r($_POST);
    print_r($_FILES);
    echo '</pre>';
    exit;
    */

    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'agendar':
                $plataforma = $_POST['plataforma'];
                $bot_id = $_POST['bot_id'] ?? null;
                
                // Se for WhatsApp, criar um "bot" virtual
                if ($plataforma == 'whatsapp') {
                    $bot_id = 'whatsapp'; // Usar um ID genérico para WhatsApp, se não houver um bot específico
                }
                
                $novo_agendamento = [
                    'id' => time(),
                    'data_hora' => $_POST['data_hora'],
                    'bot_id' => $bot_id,
                    'plataforma' => $plataforma,
                    'destino' => $_POST['destino'],
                    'tipo' => $_POST['tipo'],
                    'mensagem' => $_POST['mensagem'] ?? '', // Default to empty string if not set
                    'imagem' => null,
                    'tmdb_id' => $_POST['tmdb_id'] ?? null,
                    'tipo_tmdb' => $_POST['tipo_tmdb'] ?? null,
                    'enviado' => false,
                    'criado_em' => date('Y-m-d H:i:s')
                ];

                // Processar upload de imagem
                if ($_POST['tipo'] === 'imagem' && isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true); // Ensure directory exists with correct permissions
                    }
                    $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
                    $nome_arquivo = uniqid() . '.' . $ext;
                    $caminho_arquivo = $upload_dir . $nome_arquivo;
                    
                    if (move_uploaded_file($_FILES['imagem']['tmp_name'], $caminho_arquivo)) {
                        $novo_agendamento['imagem'] = $caminho_arquivo;
                    } else {
                        $erro = "Erro ao fazer upload da imagem. Verifique permissões da pasta 'uploads/'.";
                    }
                } elseif ($_POST['tipo'] === 'imagem' && (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK)) {
                    // Specific error if image type is selected but no valid file uploaded
                    $erro = "Você selecionou 'Imagem com Legenda', mas nenhuma imagem válida foi enviada.";
                }


                if (!isset($erro)) {
                    $agendamentos[] = $novo_agendamento;
                    $sucesso = "Agendamento criado com sucesso!";
                    file_put_contents('data/agendamentos.json', json_encode($agendamentos, JSON_PRETTY_PRINT));
                }
                break;
                
            case 'excluir':
                $agendamentos = array_filter($agendamentos, function($ag) {
                    return $ag['id'] != $_POST['id'];
                });
                $agendamentos = array_values($agendamentos);
                $sucesso = "Agendamento excluído!";
                file_put_contents('data/agendamentos.json', json_encode($agendamentos, JSON_PRETTY_PRINT));
                break;

            case 'atualizar_status':
                // Simulação de atualização de status
                foreach ($agendamentos as &$ag) {
                    if ($ag['id'] == $_POST['id'] && !$ag['enviado'] && strtotime($ag['data_hora']) <= time()) {
                        $ag['enviado'] = true;
                    }
                }
                $sucesso = "Status atualizado!";
                file_put_contents('data/agendamentos.json', json_encode($agendamentos, JSON_PRETTY_PRINT));
                break;

            case 'limpar_concluidos':
                $agendamentos = array_filter($agendamentos, function($ag) {
                    return !$ag['enviado'] && strtotime($ag['data_hora']) > time();
                });
                $agendamentos = array_values($agendamentos);
                $sucesso = "Agendamentos concluídos ou expirados foram removidos!";
                file_put_contents('data/agendamentos.json', json_encode($agendamentos, JSON_PRETTY_PRINT));
                break;
        }
    }
}

// Se veio do TMDB
$tmdb_id = $_GET['tmdb_id'] ?? '';
$tipo_tmdb = $_GET['tipo'] ?? '';
$titulo_tmdb = $_GET['titulo'] ?? '';

// Filtrar grupos do usuário logado
$meus_grupos = array_filter($grupos, function($grupo) {
    return isset($grupo['user_id']) && $grupo['user_id'] === $_SESSION['user_id'];
});

include 'includes/header.php';
?>

<?php if (isset($sucesso)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $sucesso ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>
<?php if (isset($erro)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $erro ?>
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
            <input type="hidden" name="tmdb_id" value="<?= $tmdb_id ?>">
            <input type="hidden" name="tipo_tmdb" value="<?= $tipo_tmdb ?>">
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
                        <?php if ($bot['ativo'] && $bot['tipo'] == 'telegram'): ?>
                        <option value="<?= htmlspecialchars($bot['id']) ?>">
                            <?= htmlspecialchars($bot['nome']) ?> (<?= ucfirst($bot['tipo']) ?>)
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
                    <option value="texto">Texto</option>
                    
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
                        $bot_nome = $ag['plataforma'] == 'whatsapp' ? 'WhatsApp' : 'Bot não encontrado';
                        foreach ($bots as $bot) {
                            if ($bot['id'] == $ag['bot_id']) {
                                $bot_nome = $bot['nome'];
                                break;
                            }
                        }
                        
                        $status_class = 'badge-warning';
                        $status_text = '⏳ Pendente';
                        if ($ag['enviado']) {
                            $status_class = 'badge-success';
                            $status_text = '✅ Enviado';
                        } elseif (strtotime($ag['data_hora']) < time()) {
                            $status_class = 'badge-danger';
                            $status_text = '❌ Expirado';
                        }
                    ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($ag['data_hora'])) ?></td>
                        <td><?= htmlspecialchars($bot_nome) ?></td>
                        <td><?= htmlspecialchars(substr($ag['destino'], 0, 20)) ?>...</td>
                        <td><?= ucfirst($ag['tipo']) ?></td>
                        <td>
                            <span class="badge <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!$ag['enviado'] && strtotime($ag['data_hora']) > time()): ?>
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
            <strong>Data/Hora:</strong> ${new Date(agendamento.data_hora).toLocaleString('pt-BR')}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Plataforma:</strong> ${agendamento.plataforma ? agendamento.plataforma.charAt(0).toUpperCase() + agendamento.plataforma.slice(1) : 'N/A'}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Bot ID:</strong> ${agendamento.bot_id || 'N/A'}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Destino:</strong> ${agendamento.destino}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Tipo:</strong> ${agendamento.tipo.charAt(0).toUpperCase() + agendamento.tipo.slice(1)}
        </div>
        <div style="margin-bottom: 1rem;">
            <strong>Mensagem:</strong>
            <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-top: 0.5rem; white-space: pre-wrap;">${agendamento.mensagem || '[Nenhuma mensagem]'}</div>
        </div>
        ${agendamento.imagem ? `
        <div style="margin-bottom: 1rem;">
            <strong>Imagem:</strong>
            <div><img src="${agendamento.imagem}" style="max-width: 200px; max-height: 200px; object-fit: cover;"></div>
        </div>` : ''}
        ${agendamento.tmdb_id ? `
        <div style="margin-bottom: 1rem;">
            <strong>TMDB ID:</strong> ${agendamento.tmdb_id} (${agendamento.tipo_tmdb})
        </div>` : ''}
        <div style="margin-bottom: 1rem;">
            <strong>Criado em:</strong> ${new Date(agendamento.criado_em).toLocaleString('pt-BR')}
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
        <?php foreach ($meus_grupos as $grupo): ?>
            <?php if ($grupo['tipo'] === 'telegram'): ?>
                destinoSelect.innerHTML += `<option value="<?= htmlspecialchars($grupo['id_externo']) ?>"><?= htmlspecialchars($grupo['nome']) ?> (<?= htmlspecialchars($grupo['id_externo']) ?>)</option>`;
            <?php endif; ?>
        <?php endforeach; ?>
    } else if (plataforma === 'whatsapp') {
        grupoBotAgendamento.style.display = 'none';
        grupoBotAgendamento.querySelector('select').required = false;
        destinoHelp.innerHTML = '<strong>WhatsApp:</strong> Selecione um grupo cadastrado';
        <?php foreach ($meus_grupos as $grupo): ?>
            <?php if ($grupo['tipo'] === 'whatsapp'): ?>
                destinoSelect.innerHTML += `<option value="<?= htmlspecialchars($grupo['id_externo']) ?>"><?= htmlspecialchars($grupo['nome']) ?> (<?= htmlspecialchars($grupo['id_externo']) ?>)</option>`;
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
    mensagemInput.required = false;
    imagemInput.required = false;
    mensagemField.style.display = 'block'; // Show message field by default
    imagemField.style.display = 'none'; // Hide image field by default

    // Determine visibility and required status based on type
    if (tipo === 'texto') {
        mensagemInput.required = true;
    } else if (tipo === 'imagem') {
        imagemField.style.display = 'block';
        imagemInput.required = true;
        // Message is optional for image with caption
    } else if (tipo === 'tmdb') {
        mensagemField.style.display = 'none'; // Message is hidden/auto-generated for TMDB
    } else {
        // If no type or an invalid type is selected, make message required but image not
        mensagemInput.required = true;
    }
    
    // Ensure preview is hidden if type changes away from image
    if (tipo !== 'imagem') {
        document.getElementById('imagemPreview').style.display = 'none';
        document.getElementById('previewImg').src = '';
    }
}


document.addEventListener('DOMContentLoaded', function() {
    const imagemInput = document.getElementById('imagemInput');
    const imagemPreview = document.getElementById('imagemPreview');
    const previewImg = document.getElementById('previewImg');

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
            previewImg.src = ''; // Clear preview if no file
        }
    });

    // Initial call to set correct field states on page load (e.g., if coming from TMDB)
    toggleFieldsBasedOnType();
    toggleBotAgendamento(); // Also call this to populate destination dropdown on load
});
</script>

<?php include 'includes/footer.php'; ?>