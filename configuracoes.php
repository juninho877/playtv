<?php
$page_title = 'Configurações';
include 'includes/auth.php';
verificarLogin();

$sucesso = '';
$erro = '';

// Processar formulário
if ($_POST) {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'config_api':
                    $tmdb_key = trim($_POST['tmdb_key']);
                    $whatsapp_server = trim($_POST['whatsapp_server']);
                    $whatsapp_instance = trim($_POST['whatsapp_instance']);
                    $whatsapp_apikey = trim($_POST['whatsapp_apikey']);
                    
                    // Atualizar configurações no banco
                    executeQuery("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)", 
                        ['tmdb_api_key', $tmdb_key]);
                    executeQuery("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)", 
                        ['whatsapp_server', $whatsapp_server]);
                    executeQuery("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)", 
                        ['whatsapp_instance', $whatsapp_instance]);
                    executeQuery("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)", 
                        ['whatsapp_apikey', $whatsapp_apikey]);
                    
                    $sucesso = "Configurações das APIs atualizadas!";
                    break;
                    
                case 'alterar_senha':
                    $senha_atual = $_POST['senha_atual'];
                    $nova_senha = $_POST['nova_senha'];
                    $nome = trim($_POST['nome']);
                    
                    if (empty($senha_atual) || empty($nova_senha) || empty($nome)) {
                        $erro = "Todos os campos são obrigatórios!";
                        break;
                    }
                    
                    // Verificar senha atual
                    $user = fetchOne("SELECT password FROM users WHERE id = ?", [$_SESSION['user_id']]);
                    
                    if (!$user || !password_verify($senha_atual, $user['password'])) {
                        $erro = "Senha atual incorreta!";
                        break;
                    }
                    
                    // Atualizar dados do usuário
                    $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    executeQuery("UPDATE users SET name = ?, password = ?, updated_at = NOW() WHERE id = ?", 
                        [$nome, $nova_senha_hash, $_SESSION['user_id']]);
                    
                    $_SESSION['user_name'] = $nome;
                    $sucesso = "Dados atualizados com sucesso!";
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Configurações error: " . $e->getMessage());
        $erro = "Erro interno. Tente novamente.";
    }
}

// Carregar configurações atuais
try {
    $config_rows = fetchAll("SELECT config_key, config_value FROM system_config WHERE config_key IN ('tmdb_api_key', 'whatsapp_server', 'whatsapp_instance', 'whatsapp_apikey')");
    $config = [];
    foreach ($config_rows as $row) {
        $config[$row['config_key']] = $row['config_value'];
    }
    
    $usuario_atual = fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
} catch (Exception $e) {
    error_log("Configurações load error: " . $e->getMessage());
    $config = [];
    $usuario_atual = ['name' => '', 'email' => '', 'last_login' => null];
}

include 'includes/header.php';
?>

<?php if (!empty($sucesso)): ?>
<div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-gear"></i>
                    Configurações das APIs
                </h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="config_api">
                    
                    <div class="form-group">
                        <label class="form-label">Chave do TMDB</label>
                        <input type="text" name="tmdb_key" class="form-control" value="<?= htmlspecialchars($config['tmdb_api_key'] ?? '') ?>" placeholder="Sua chave da API do TMDB">
                        <small class="form-text text-muted">
                            <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener noreferrer">Obter chave TMDB</a>
                        </small>
                    </div>
                    
                    <h5 style="margin-top: 2rem; margin-bottom: 1rem;">WhatsApp (EVO API)</h5>
                    
                    <div class="form-group">
                        <label class="form-label">Servidor</label>
                        <input type="url" name="whatsapp_server" class="form-control" value="<?= htmlspecialchars($config['whatsapp_server'] ?? '') ?>" placeholder="https://evov2.duckdns.org">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Instance</label>
                        <input type="text" name="whatsapp_instance" class="form-control" value="<?= htmlspecialchars($config['whatsapp_instance'] ?? '') ?>" placeholder="SEU_INSTANCE">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">API Key</label>
                        <input type="text" name="whatsapp_apikey" class="form-control" value="<?= htmlspecialchars($config['whatsapp_apikey'] ?? '') ?>" placeholder="Sua API Key">
                    </div>
                    
                    <div class="form-group" style="text-align: center;">
                        <button type="button" class="btn btn-success" onclick="gerarQRCode()">
                            <i class="bi bi-qr-code"></i> Gerar QR Code WhatsApp
                        </button>
                        <button type="button" class="btn btn-info" onclick="verificarStatus()">
                            <i class="bi bi-check-circle"></i> Verificar Status
                        </button>
                        <button type="button" class="btn btn-danger" onclick="desconectarSessao()">
                            <i class="bi bi-x-circle"></i> Desconectar Sessão Atual
                        </button>
                    </div>
                    
                    <div id="qrCodeArea" style="text-align: center; margin-top: 20px; display: none;">
                        <h6>Escaneie o QR Code com WhatsApp:</h6>
                        <img id="qrCodeImage" style="max-width: 300px; border: 1px solid #ddd;">
                    </div>
                    
                    <div id="statusArea" style="margin-top: 15px;"></div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check"></i> Salvar Configurações
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-person-gear"></i>
                    Dados do Usuário
                </h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="alterar_senha">
                    
                    <div class="form-group">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($usuario_atual['name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Senha Atual</label>
                        <input type="password" name="senha_atual" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Nova Senha</label>
                        <input type="password" name="nova_senha" class="form-control" required minlength="4">
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check"></i> Atualizar Dados
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="bi bi-info-circle"></i>
                    Informações do Sistema
                </h3>
            </div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td><strong>Versão do Sistema:</strong></td>
                        <td>2.0.0 (MySQL)</td>
                    </tr>
                    <tr>
                        <td><strong>PHP:</strong></td>
                        <td><?= phpversion() ?></td>
                    </tr>
                    <tr>
                        <td><strong>Data/Hora do Servidor:</strong></td>
                        <td><?= date('d/m/Y H:i:s') ?></td>
                    </tr>
                    <tr>
                        <td><strong>Armazenamento:</strong></td>
                        <td>MySQL Database</td>
                    </tr>
                    <tr>
                        <td><strong>Último Login:</strong></td>
                        <td><?= $usuario_atual['last_login'] ? date('d/m/Y H:i:s', strtotime($usuario_atual['last_login'])) : 'Nunca' ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-database"></i>
            Configuração do Banco de Dados
        </h3>
    </div>
    <div class="card-body">
        <p>Sistema agora utiliza MySQL para armazenamento de dados:</p>
        <ul>
            <li><strong>Host:</strong> <?= DB_HOST ?></li>
            <li><strong>Database:</strong> <?= DB_NAME ?></li>
            <li><strong>Charset:</strong> <?= DB_CHARSET ?></li>
            <li><strong>Status:</strong> <span class="badge badge-success">✅ Conectado</span></li>
        </ul>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-clock-history"></i>
            Cronjob para Agendamentos
        </h3>
    </div>
    <div class="card-body">
        <p>Para que os agendamentos funcionem automaticamente, configure o seguinte cronjob no seu servidor:</p>
        <pre style="background: #2d3748; color: white; padding: 1rem; border-radius: 5px;">* * * * * php <?= realpath('.') ?>/cron/processar_agendamentos.php</pre>
        <p><small>Este comando executará o processador de agendamentos a cada minuto.</small></p>
        
        <div class="alert alert-info">
            <strong>Como configurar:</strong>
            <ol>
                <li>Acesse o cPanel do seu servidor</li>
                <li>Procure por "Cron Jobs" ou "Tarefas Agendadas"</li>
                <li>Adicione o comando acima</li>
                <li>Defina para executar a cada minuto (* * * * *)</li>
            </ol>
        </div>
    </div>
</div>

<script>
function gerarQRCode() {
    const server = document.querySelector('input[name="whatsapp_server"]').value.trim();
    const instance = document.querySelector('input[name="whatsapp_instance"]').value.trim();
    const apikey = document.querySelector('input[name="whatsapp_apikey"]').value.trim();

    if (!server || !instance || !apikey) {
        alert('Preencha todos os campos do WhatsApp primeiro!');
        return;
    }

    const url = server.replace(/\/$/, '') + `/instance/connect/${instance}`;

    fetch(url, {
        method: 'GET',
        headers: {
            'apikey': apikey,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta da API:', data);
        
        let qrCodeData = null;
        
        if (data.qrcode) {
            qrCodeData = data.qrcode;
        } else if (data.base64) {
            qrCodeData = data.base64;
        } else if (data.code) {
            qrCodeData = data.code;
        }
        
        if (qrCodeData) {
            if (qrCodeData.startsWith('data:image')) {
                document.getElementById('qrCodeImage').src = qrCodeData;
            } else {
                document.getElementById('qrCodeImage').src = `data:image/png;base64,${qrCodeData}`;
            }
            
            document.getElementById('qrCodeArea').style.display = 'block';
            
            const statusCheck = setInterval(() => {
                verificarStatus(true);
            }, 3000);
            
            setTimeout(() => {
                clearInterval(statusCheck);
                console.log('Verificação de status interrompida após 2 minutos');
            }, 120000);
            
            alert('QR Code gerado! Escaneie com seu WhatsApp.');
        } else {
            console.error('Resposta completa:', data);
            alert('Erro: QR Code não encontrado na resposta da API');
        }
    })
    .catch(error => {
        console.error('Erro ao gerar QR Code:', error);
        alert(`Erro ao gerar QR Code: ${error.message}`);
    });
}

function verificarStatus(silent = false) {
    const server = document.querySelector('input[name="whatsapp_server"]').value.trim();
    const instance = document.querySelector('input[name="whatsapp_instance"]').value.trim();
    const apikey = document.querySelector('input[name="whatsapp_apikey"]').value.trim();

    if (!server || !instance || !apikey) {
        if (!silent) alert('Preencha todos os campos do WhatsApp primeiro!');
        return;
    }

    const url = server.replace(/\/$/, '') + `/instance/connectionState/${instance}`;

    fetch(url, {
        method: 'GET',
        headers: { 
            'apikey': apikey,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Status da conexão:', data);
        const statusArea = document.getElementById('statusArea');
        
        let connectionState = null;
        
        if (data.instance && data.instance.state) {
            connectionState = data.instance.state;
        } else if (data.state) {
            connectionState = data.state;
        } else if (data.status) {
            connectionState = data.status;
        }
        
        if (connectionState === 'open' || connectionState === 'connected') {
            statusArea.innerHTML = '<div class="alert alert-success">✅ WhatsApp conectado com sucesso!</div>';
            document.getElementById('qrCodeArea').style.display = 'none';
            if (!silent) {
                alert('WhatsApp conectado com sucesso!');
            }
        } else {
            if (!silent) {
                statusArea.innerHTML = `<div class="alert alert-warning">⏳ WhatsApp não conectado. Status: ${connectionState || 'desconhecido'}</div>`;
            }
        }
    })
    .catch(error => {
        console.error('Erro ao verificar status:', error);
        if (!silent) {
            document.getElementById('statusArea').innerHTML = '<div class="alert alert-danger">❌ Erro ao verificar status: ' + error.message + '</div>';
        }
    });
}

function desconectarSessao() {
    const server = document.querySelector('input[name="whatsapp_server"]').value.trim();
    const instance = document.querySelector('input[name="whatsapp_instance"]').value.trim();
    const apikey = document.querySelector('input[name="whatsapp_apikey"]').value.trim();

    if (!server || !instance || !apikey) {
        alert('Preencha todos os campos do WhatsApp primeiro!');
        return;
    }

    if (!confirm("Tem certeza que deseja desconectar esta sessão do WhatsApp?")) {
        return;
    }

    const url = server.replace(/\/$/, '') + `/instance/logout/${instance}`;

    fetch(url, {
        method: 'DELETE',
        headers: {
            'apikey': apikey,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Resposta do logout:', data);
        
        if (data.status === 200 || data.message || data.success !== false) {
            alert('Sessão desconectada com sucesso!');
            document.getElementById('qrCodeArea').style.display = 'none';
            document.getElementById('statusArea').innerHTML = '<div class="alert alert-info">📱 Sessão desconectada</div>';
        } else {
            throw new Error(data.message || 'Erro desconhecido');
        }
    })
    .catch(error => {
        console.error('Erro ao desconectar:', error);
        alert(`Erro ao desconectar sessão: ${error.message}`);
    });
}
</script>

<?php include 'includes/footer.php'; ?>