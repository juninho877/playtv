<?php
$page_title = 'Gerenciar Bots';
include 'includes/auth.php';
verificarLogin();

$sucesso = '';
$erro = '';

// Processar formulário
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $nome = trim($_POST['nome']);
                    $tipo = $_POST['tipo'];
                    $token = trim($_POST['token']);
                    $chat_id = trim($_POST['chat_id'] ?? '');
                    
                    if (empty($nome) || empty($tipo) || empty($token)) {
                        $erro = "Todos os campos obrigatórios devem ser preenchidos!";
                        break;
                    }
                    
                    executeQuery("INSERT INTO bots (name, type, token, chat_id, user_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
                        [$nome, $tipo, $token, $chat_id ?: null, $_SESSION['user_id']]);
                    
                    $sucesso = "Bot adicionado com sucesso!";
                    break;
                    
                case 'edit':
                    $id = (int)$_POST['id'];
                    $nome = trim($_POST['nome']);
                    $tipo = $_POST['tipo'];
                    $token = trim($_POST['token']);
                    $chat_id = trim($_POST['chat_id'] ?? '');
                    
                    if (empty($nome) || empty($tipo) || empty($token)) {
                        $erro = "Todos os campos obrigatórios devem ser preenchidos!";
                        break;
                    }
                    
                    $affected = executeQuery("UPDATE bots SET name = ?, type = ?, token = ?, chat_id = ?, updated_at = NOW() WHERE id = ? AND (user_id = ? OR user_id IS NULL)", 
                        [$nome, $tipo, $token, $chat_id ?: null, $id, $_SESSION['user_id']])->rowCount();
                    
                    if ($affected > 0) {
                        $sucesso = "Bot atualizado com sucesso!";
                    } else {
                        $erro = "Bot não encontrado ou você não tem permissão para editá-lo!";
                    }
                    break;
                    
                case 'toggle':
                    $id = (int)$_POST['id'];
                    
                    $bot = fetchOne("SELECT active FROM bots WHERE id = ? AND (user_id = ? OR user_id IS NULL)", [$id, $_SESSION['user_id']]);
                    if (!$bot) {
                        $erro = "Bot não encontrado!";
                        break;
                    }
                    
                    $new_status = $bot['active'] ? 0 : 1;
                    executeQuery("UPDATE bots SET active = ?, updated_at = NOW() WHERE id = ?", [$new_status, $id]);
                    
                    $sucesso = "Status do bot alterado!";
                    break;
                    
                case 'delete':
                    $id = (int)$_POST['id'];
                    
                    $affected = executeQuery("DELETE FROM bots WHERE id = ? AND (user_id = ? OR user_id IS NULL)", [$id, $_SESSION['user_id']])->rowCount();
                    
                    if ($affected > 0) {
                        $sucesso = "Bot removido com sucesso!";
                    } else {
                        $erro = "Bot não encontrado ou você não tem permissão para removê-lo!";
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Bots error: " . $e->getMessage());
        $erro = "Erro interno. Tente novamente.";
    }
}

// Carregar bots do usuário
try {
    $bots = fetchAll("SELECT * FROM bots WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC", [$_SESSION['user_id']]);
} catch (Exception $e) {
    error_log("Bots load error: " . $e->getMessage());
    $bots = [];
}

include 'includes/header.php';
?>

<?php if (!empty($sucesso)): ?>
<div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-plus-circle"></i>
            Adicionar Novo Bot
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Nome do Bot</label>
                <input type="text" name="nome" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select" required>
                    <option value="">Selecione...</option>
                    <option value="telegram">Telegram</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Token/Chave</label>
                <input type="text" name="token" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Chat ID (apenas Telegram)</label>
                <input type="text" name="chat_id" class="form-control">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-plus"></i> Adicionar Bot
            </button>
        </form>
    </div>
</div>

<?php if (!empty($bots)): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-robot"></i>
            Bots Cadastrados
        </h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bots as $bot): ?>
                    <tr>
                        <td><?= htmlspecialchars($bot['name']) ?></td>
                        <td>
                            <i class="bi bi-<?= $bot['type'] == 'telegram' ? 'telegram' : 'whatsapp' ?>"></i>
                            <?= ucfirst($bot['type']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $bot['active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $bot['active'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($bot['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $bot['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $bot['active'] ? 'btn-danger' : 'btn-success' ?>">
                                    <i class="bi bi-<?= $bot['active'] ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            
                            <button type="button" class="btn btn-sm btn-primary" onclick="editarBot(<?= htmlspecialchars(json_encode($bot)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este bot?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $bot['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="bi bi-trash"></i>
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

<!-- Modal de Edição -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3>Editar Bot</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group">
                <label class="form-label">Nome do Bot</label>
                <input type="text" name="nome" id="edit_nome" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select name="tipo" id="edit_tipo" class="form-select" required>
                    <option value="telegram">Telegram</option>
                    <option value="whatsapp">WhatsApp</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Token/Chave</label>
                <input type="text" name="token" id="edit_token" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Chat ID</label>
                <input type="text" name="chat_id" id="edit_chat_id" class="form-control">
            </div>
            
            <div style="text-align: right; margin-top: 1rem;">
                <button type="button" onclick="fecharModal()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarBot(bot) {
    document.getElementById('edit_id').value = bot.id;
    document.getElementById('edit_nome').value = bot.name;
    document.getElementById('edit_tipo').value = bot.type;
    document.getElementById('edit_token').value = bot.token;
    document.getElementById('edit_chat_id').value = bot.chat_id || '';
    document.getElementById('editModal').style.display = 'block';
}

function fecharModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>