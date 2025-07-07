<?php
$page_title = 'Meus Dados e Grupos';
include 'includes/auth.php';
verificarLogin(); // Garante que o usuário esteja logado

$sucesso = '';
$erro = '';

// Caminho para o arquivo de grupos
$grupos_file = 'data/grupos.json';

// Carregar grupos existentes
$grupos = [];
if (file_exists($grupos_file)) {
    $grupos = json_decode(file_get_contents($grupos_file), true);
    if (!is_array($grupos)) {
        $grupos = []; // Garante que $grupos seja um array mesmo se o JSON estiver corrompido
    }
}

// Processar formulário de grupos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_grupo':
                $nome_grupo = trim($_POST['nome_grupo'] ?? '');
                $id_grupo = trim($_POST['id_grupo'] ?? '');
                $tipo_grupo = $_POST['tipo_grupo'] ?? '';

                if (empty($nome_grupo) || empty($id_grupo) || empty($tipo_grupo)) {
                    $erro = "Todos os campos do grupo são obrigatórios.";
                } elseif (!in_array($tipo_grupo, ['whatsapp', 'telegram'])) {
                    $erro = "Tipo de grupo inválido.";
                } else {
                    $novo_grupo = [
                        'id' => uniqid(), // Gera um ID único para o grupo
                        'nome' => $nome_grupo,
                        'id_externo' => $id_grupo,
                        'tipo' => $tipo_grupo,
                        'user_id' => $_SESSION['user_id'] // Associa o grupo ao usuário logado
                    ];
                    $grupos[] = $novo_grupo;
                    if (file_put_contents($grupos_file, json_encode($grupos, JSON_PRETTY_PRINT))) {
                        $sucesso = "Grupo adicionado com sucesso!";
                    } else {
                        $erro = "Erro ao salvar o grupo.";
                    }
                }
                break;

            case 'edit_grupo':
                $grupo_id = $_POST['grupo_id'] ?? '';
                $nome_grupo = trim($_POST['nome_grupo'] ?? '');
                $id_grupo = trim($_POST['id_grupo'] ?? '');
                $tipo_grupo = $_POST['tipo_grupo'] ?? '';

                $found = false;
                foreach ($grupos as &$grupo) {
                    if ($grupo['id'] === $grupo_id && $grupo['user_id'] === $_SESSION['user_id']) {
                        if (empty($nome_grupo) || empty($id_grupo) || empty($tipo_grupo)) {
                            $erro = "Todos os campos do grupo são obrigatórios.";
                        } elseif (!in_array($tipo_grupo, ['whatsapp', 'telegram'])) {
                            $erro = "Tipo de grupo inválido.";
                        } else {
                            $grupo['nome'] = $nome_grupo;
                            $grupo['id_externo'] = $id_grupo;
                            $grupo['tipo'] = $tipo_grupo;
                            $found = true;
                            break;
                        }
                    }
                }

                if ($found) {
                    if (file_put_contents($grupos_file, json_encode($grupos, JSON_PRETTY_PRINT))) {
                        $sucesso = "Grupo atualizado com sucesso!";
                    } else {
                        $erro = "Erro ao atualizar o grupo.";
                    }
                } elseif (empty($erro)) {
                    $erro = "Grupo não encontrado ou você não tem permissão para editá-lo.";
                }
                break;

            case 'delete_grupo':
                $grupo_id = $_POST['grupo_id'] ?? '';
                $grupos_filtrados = [];
                $found = false;
                foreach ($grupos as $grupo) {
                    if ($grupo['id'] === $grupo_id && $grupo['user_id'] === $_SESSION['user_id']) {
                        $found = true;
                    } else {
                        $grupos_filtrados[] = $grupo;
                    }
                }

                if ($found) {
                    if (file_put_contents($grupos_file, json_encode($grupos_filtrados, JSON_PRETTY_PRINT))) {
                        $grupos = $grupos_filtrados; // Atualiza a variável $grupos para a exibição
                        $sucesso = "Grupo excluído com sucesso!";
                    } else {
                        $erro = "Erro ao excluir o grupo.";
                    }
                } else {
                    $erro = "Grupo não encontrado ou você não tem permissão para excluí-lo.";
                }
                break;
        }
    }
}

// Carregar dados do usuário logado (assumindo que $_SESSION['user_id'] está definido por auth.php)
$usuarios = json_decode(file_get_contents('data/usuarios.json'), true);
$usuario_atual = null;
foreach ($usuarios as $user) {
    if ($user['id'] == $_SESSION['user_id']) {
        $usuario_atual = $user;
        break;
    }
}

// Filtrar grupos para mostrar apenas os do usuário logado
$meus_grupos = array_filter($grupos, function($grupo) {
    return isset($grupo['user_id']) && $grupo['user_id'] === $_SESSION['user_id'];
});


include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if ($sucesso): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $sucesso ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($erro): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $erro ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-person-circle"></i>
                        Meus Dados
                    </h3>
                </div>
                <div class="card-body">
                    <p><strong>Nome:</strong> <?= htmlspecialchars($usuario_atual['nome'] ?? 'N/A') ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($usuario_atual['email'] ?? 'N/A') ?></p>
                    <p><strong>Último Login:</strong> <?= date('d/m/Y H:i:s', strtotime($usuario_atual['ultimo_login'] ?? 'now')) ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-people"></i>
                        Meus Grupos (WhatsApp e Telegram)
                    </h3>
                </div>
                <div class="card-body">
                    <form id="formGrupo" method="POST" class="mb-4">
                        <input type="hidden" name="action" id="grupoAction" value="add_grupo">
                        <input type="hidden" name="grupo_id" id="grupoId">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="nomeGrupo" class="form-label">Nome do Grupo</label>
                                <input type="text" class="form-control" id="nomeGrupo" name="nome_grupo" placeholder="Ex: Grupo Família" required>
                            </div>
                            <div class="col-md-4">
                                <label for="idGrupo" class="form-label">ID do Grupo</label>
                                <input type="text" class="form-control" id="idGrupo" name="id_grupo" placeholder="Ex: 1234567890@g.us" required>
                            </div>
                            <div class="col-md-2">
                                <label for="tipoGrupo" class="form-label">Tipo</label>
                                <select class="form-select" id="tipoGrupo" name="tipo_grupo" required>
                                    <option value="">Selecione</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="telegram">Telegram</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100" id="btnSalvarGrupo">
                                    <i class="bi bi-plus-circle"></i> Adicionar Grupo
                                </button>
                                <button type="button" class="btn btn-secondary w-100 ms-2" id="btnCancelarEdicao" style="display:none;">
                                    <i class="bi bi-x-circle"></i> Cancelar
                                </button>
                            </div>
                        </div>
                    </form>

                    <?php if (empty($meus_grupos)): ?>
                        <div class="alert alert-info" role="alert">
                            Nenhum grupo cadastrado ainda. Adicione um grupo acima!
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>ID</th>
                                        <th>Tipo</th>
                                        <th style="width: 180px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($meus_grupos as $grupo): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($grupo['nome']) ?></td>
                                            <td class="grupo-id-cell"><?= htmlspecialchars($grupo['id_externo']) ?></td>
                                            <td>
                                                <?php
                                                if ($grupo['tipo'] === 'whatsapp') {
                                                    echo '<i class="bi bi-whatsapp text-success"></i> WhatsApp';
                                                } elseif ($grupo['tipo'] === 'telegram') {
                                                    echo '<i class="bi bi-telegram text-info"></i> Telegram';
                                                } else {
                                                    echo htmlspecialchars($grupo['tipo']);
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary copy-btn" data-id="<?= htmlspecialchars($grupo['id_externo']) ?>" title="Copiar ID">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-info edit-btn"
                                                        data-id="<?= htmlspecialchars($grupo['id']) ?>"
                                                        data-nome="<?= htmlspecialchars($grupo['nome']) ?>"
                                                        data-id-externo="<?= htmlspecialchars($grupo['id_externo']) ?>"
                                                        data-tipo="<?= htmlspecialchars($grupo['tipo']) ?>"
                                                        title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Tem certeza que deseja excluir este grupo?');">
                                                    <input type="hidden" name="action" value="delete_grupo">
                                                    <input type="hidden" name="grupo_id" value="<?= htmlspecialchars($grupo['id']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Função para copiar ID
    document.querySelectorAll('.copy-btn').forEach(button => {
        button.addEventListener('click', function() {
            const idToCopy = this.dataset.id;
            navigator.clipboard.writeText(idToCopy).then(() => {
                alert('ID copiado: ' + idToCopy);
            }).catch(err => {
                console.error('Erro ao copiar: ', err);
                alert('Erro ao copiar o ID.');
            });
        });
    });

    // Função para editar grupo
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const grupoId = this.dataset.id;
            const nomeGrupo = this.dataset.nome;
            const idExterno = this.dataset.idExterno;
            const tipoGrupo = this.dataset.tipo;

            document.getElementById('grupoAction').value = 'edit_grupo';
            document.getElementById('grupoId').value = grupoId;
            document.getElementById('nomeGrupo').value = nomeGrupo;
            document.getElementById('idGrupo').value = idExterno;
            document.getElementById('tipoGrupo').value = tipoGrupo;

            document.getElementById('btnSalvarGrupo').innerHTML = '<i class="bi bi-check-circle"></i> Atualizar Grupo';
            document.getElementById('btnSalvarGrupo').classList.remove('btn-primary');
            document.getElementById('btnSalvarGrupo').classList.add('btn-success');
            document.getElementById('btnCancelarEdicao').style.display = 'inline-block';

            // Rola para o topo do formulário
            document.getElementById('formGrupo').scrollIntoView({ behavior: 'smooth' });
        });
    });

    // Função para cancelar edição
    document.getElementById('btnCancelarEdicao').addEventListener('click', function() {
        document.getElementById('formGrupo').reset(); // Limpa o formulário
        document.getElementById('grupoAction').value = 'add_grupo';
        document.getElementById('grupoId').value = '';
        document.getElementById('btnSalvarGrupo').innerHTML = '<i class="bi bi-plus-circle"></i> Adicionar Grupo';
        document.getElementById('btnSalvarGrupo').classList.remove('btn-success');
        document.getElementById('btnSalvarGrupo').classList.add('btn-primary');
        this.style.display = 'none';
    });
});
</script>

<?php include 'includes/footer.php'; // Se você tiver um footer, inclua-o aqui ?>