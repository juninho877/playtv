
<?php
$page_title = 'Gerenciar Bots';
include 'includes/auth.php';
verificarLogin();

// Processar formulário
if ($_POST) {
    $bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $novo_bot = [
                    'id' => time(),
                    'nome' => $_POST['nome'],
                    'tipo' => $_POST['tipo'],
                    'token' => $_POST['token'],
                    'chat_id' => $_POST['chat_id'] ?? '',
                    'ativo' => true,
                    'criado_em' => date('Y-m-d H:i:s')
                ];
                $bots[] = $novo_bot;
                $sucesso = "Bot adicionado com sucesso!";
                break;
                
            case 'edit':
                foreach ($bots as &$bot) {
                    if ($bot['id'] == $_POST['id']) {
                        $bot['nome'] = $_POST['nome'];
                        $bot['tipo'] = $_POST['tipo'];
                        $bot['token'] = $_POST['token'];
                        $bot['chat_id'] = $_POST['chat_id'] ?? '';
                        break;
                    }
                }
                $sucesso = "Bot atualizado com sucesso!";
                break;
                
            case 'toggle':
                foreach ($bots as &$bot) {
                    if ($bot['id'] == $_POST['id']) {
                        $bot['ativo'] = !$bot['ativo'];
                        break;
                    }
                }
                $sucesso = "Status do bot alterado!";
                break;
                
            case 'delete':
                $bots = array_filter($bots, function($bot) {
                    return $bot['id'] != $_POST['id'];
                });
                $bots = array_values($bots);
                $sucesso = "Bot removido com sucesso!";
                break;
        }
        
        file_put_contents('data/bots.json', json_encode($bots, JSON_PRETTY_PRINT));
    }
}

$bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];

include 'includes/header.php';
?>

<?php if (isset($sucesso)): ?>
<div class="alert alert-success"><?= $sucesso ?></div>
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
                        <td><?= $bot['nome'] ?></td>
                        <td>
                            <i class="bi bi-<?= $bot['tipo'] == 'telegram' ? 'telegram' : 'whatsapp' ?>"></i>
                            <?= ucfirst($bot['tipo']) ?>
                        </td>
                        <td>
                            <span class="badge <?= $bot['ativo'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $bot['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($bot['criado_em'])) ?></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $bot['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $bot['ativo'] ? 'btn-danger' : 'btn-success' ?>">
                                    <i class="bi bi-<?= $bot['ativo'] ? 'pause' : 'play' ?>"></i>
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
    document.getElementById('edit_nome').value = bot.nome;
    document.getElementById('edit_tipo').value = bot.tipo;
    document.getElementById('edit_token').value = bot.token;
    document.getElementById('edit_chat_id').value = bot.chat_id || '';
    document.getElementById('editModal').style.display = 'block';
}

function fecharModal() {
    document.getElementById('editModal').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
