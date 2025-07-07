<?php
$page_title = 'Configurações';
include 'includes/auth.php';
verificarLogin();

// Processar formulário
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'config_api':
                $config = [
                    'tmdb_key' => $_POST['tmdb_key'],
                    'whatsapp' => [
                        'server' => $_POST['whatsapp_server'],
                        'instance' => $_POST['whatsapp_instance'],
                        'apikey' => $_POST['whatsapp_apikey']
                    ]
                ];
                file_put_contents('data/config.json', json_encode($config, JSON_PRETTY_PRINT));
                $sucesso = "Configurações das APIs atualizadas!";
                break;
                
            case 'alterar_senha':
                $usuarios = json_decode(file_get_contents('data/usuarios.json'), true);
                $senha_atual = $_POST['senha_atual'];
                $nova_senha = $_POST['nova_senha'];
                
                foreach ($usuarios as &$user) {
                    if ($user['id'] == $_SESSION['user_id']) {
                        if (password_verify($senha_atual, $user['senha'])) {
                            $user['senha'] = password_hash($nova_senha, PASSWORD_DEFAULT);
                            $user['nome'] = $_POST['nome'];
                            $sucesso = "Dados atualizados com sucesso!";
                        } else {
                            $erro = "Senha atual incorreta!";
                        }
                        break;
                    }
                }
                
                if (isset($sucesso)) {
                    file_put_contents('data/usuarios.json', json_encode($usuarios, JSON_PRETTY_PRINT));
                    $_SESSION['user_name'] = $_POST['nome'];
                }
                break;
        }
    }
}

$config = json_decode(file_get_contents('data/config.json'), true) ?: [];
$usuarios = json_decode(file_get_contents('data/usuarios.json'), true);
$usuario_atual = null;

foreach ($usuarios as $user) {
    if ($user['id'] == $_SESSION['user_id']) {
        $usuario_atual = $user;
        break;
    }
}

include 'includes/header.php';
?>

<?php if (isset($sucesso)): ?>
<div class="alert alert-success"><?= $sucesso ?></div>
<?php endif; ?>

<?php if (isset($erro)): ?>
<div class="alert alert-error"><?= $erro ?></div>
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
                        <input type="text" name="tmdb_key" class="form-control" value="<?= htmlspecialchars($config['tmdb_key'] ?? '') ?>" placeholder="Sua chave da API do TMDB">
                        <small class="form-text text-muted">
                            <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener noreferrer">Obter chave TMDB</a>
                        </small>
                    </div>
                    
                    <h5 style="margin-top: 2rem; margin-bottom: 1rem;">WhatsApp (EVO API)</h5>
                    
                    <div class="form-group">
                        <label class="form-label">Servidor</label>
                        <input type="url" name="whatsapp_server" class="form-control" value="<?= htmlspecialchars($config['whatsapp']['server'] ?? '') ?>" placeholder="https://evov2.duckdns.org">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Instance</label>
                        <input type="text" name="whatsapp_instance" class="form-control" value="<?= htmlspecialchars($config['whatsapp']['instance'] ?? '') ?>" placeholder="SEU_INSTANCE">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">API Key</label>
                        <input type="text" name="whatsapp_apikey" class="form-control" value="<?= htmlspecialchars($config['whatsapp']['apikey'] ?? '') ?>" placeholder="Sua API Key">
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
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($usuario_atual['nome'] ?? '') ?>" required>
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
                        <td>1.0.0</td>
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
                        <td>Arquivos JSON</td>
                    </tr>
                    <tr>
                        <td><strong>Último Login:</strong></td>
                        <td><?= date('d/m/Y H:i:s', strtotime($usuario_atual['ultimo_login'] ?? 'now')) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-code-square"></i>
            Configuração JSON
        </h3>
    </div>
    <div class="card-body">
        <p>Configuração atual do sistema:</p>
        <pre style="background: #f8f9fa; padding: 1rem; border-radius: 5px; overflow-x: auto;"><?= json_encode($config, JSON_PRETTY_PRINT) ?></pre>
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

    // Endpoint correto para Evolution API v2
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
        
        // Verifica diferentes formatos de resposta
        let qrCodeData = null;
        
        if (data.qrcode) {
            qrCodeData = data.qrcode;
        } else if (data.base64) {
            qrCodeData = data.base64;
        } else if (data.code) {
            qrCodeData = data.code;
        }
        
        if (qrCodeData) {
            // Se já contém o prefixo data:image, usa direto
            if (qrCodeData.startsWith('data:image')) {
                document.getElementById('qrCodeImage').src = qrCodeData;
            } else {
                // Adiciona o prefixo se necessário
                document.getElementById('qrCodeImage').src = `data:image/png;base64,${qrCodeData}`;
            }
            
            document.getElementById('qrCodeArea').style.display = 'block';
            
            // Inicia verificação de status a cada 3 segundos por 2 minutos
            const statusCheck = setInterval(() => {
                verificarStatus(true);
            }, 3000);
            
            // Para a verificação após 2 minutos
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

    // Endpoint correto para verificar status na v2
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
        
        // Verifica diferentes formatos de resposta
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

    // Endpoint correto para desconectar na v2
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
        
        // Verifica se a desconexão foi bem-sucedida
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

// Função adicional para verificar se a instância existe
function verificarInstancia() {
    const server = document.querySelector('input[name="whatsapp_server"]').value.trim();
    const instance = document.querySelector('input[name="whatsapp_instance"]').value.trim();
    const apikey = document.querySelector('input[name="whatsapp_apikey"]').value.trim();

    if (!server || !instance || !apikey) {
        alert('Preencha todos os campos primeiro!');
        return;
    }

    const url = server.replace(/\/$/, '') + `/instance/fetchInstances`;

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
        console.log('Instâncias encontradas:', data);
        
        // Verifica se a instância existe
        const instanceExists = data.some(inst => inst.name === instance || inst.instanceName === instance);
        
        if (instanceExists) {
            alert('✅ Instância encontrada! Você pode gerar o QR Code.');
            verificarStatus(); // Verifica o status atual
        } else {
            alert('❌ Instância não encontrada. Verifique o nome da instância.');
        }
    })
    .catch(error => {
        console.error('Erro ao verificar instância:', error);
        alert(`Erro ao verificar instância: ${error.message}`);
    });
}
</script>