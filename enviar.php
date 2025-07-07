<?php
$page_title = 'Enviar Mensagens';
include 'includes/auth.php';
verificarLogin();

$sucesso = '';
$erro = '';

// Processar envio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'enviar') {
    try {
        $plataforma_envio = $_POST['plataforma_envio'] ?? '';
        $destino = trim($_POST['destino'] ?? '');
        $mensagem = $_POST['mensagem'] ?? '';
        $bot_id = $_POST['bot_id'] ?? '';
        $tipo_envio = $_POST['tipo_envio'] ?? '';

        // Validar campos obrigatórios
        if (empty($plataforma_envio) || empty($destino) || empty($mensagem) || empty($tipo_envio)) {
            $erro = "Todos os campos obrigatórios devem ser preenchidos.";
        } elseif ($tipo_envio == 'imagem' && (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] != 0)) {
            $erro = "Uma imagem é obrigatória para envio de imagem.";
        } elseif ($plataforma_envio == 'whatsapp' && !preg_match('/@c\.us$|@g\.us$/', $destino)) {
            $erro = "O destino para WhatsApp deve terminar em @c.us (contato) ou @g.us (grupo).";
        } else {
            $bot_selecionado = null;
            $bot_name = '';

            // Configuração para WhatsApp
            if ($plataforma_envio == 'whatsapp') {
                $whatsapp_config = fetchAll("SELECT config_key, config_value FROM system_config WHERE config_key IN ('whatsapp_server', 'whatsapp_instance', 'whatsapp_apikey')");
                $config = [];
                foreach ($whatsapp_config as $setting) {
                    $config[$setting['config_key']] = $setting['config_value'];
                }
                
                if (empty($config['whatsapp_server']) || empty($config['whatsapp_instance']) || empty($config['whatsapp_apikey'])) {
                    $erro = "Configuração do WhatsApp incompleta. Verifique as configurações do sistema.";
                } else {
                    // Verificar estado da conexão do WhatsApp
                    $base_url = rtrim($config['whatsapp_server'], '/');
                    $status_url = $base_url . '/instance/connectionState/' . $config['whatsapp_instance'];
                    $ch_status = curl_init();
                    curl_setopt($ch_status, CURLOPT_URL, $status_url);
                    curl_setopt($ch_status, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch_status, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch_status, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch_status, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'apikey: ' . $config['whatsapp_apikey']
                    ]);
                    $status_response = curl_exec($ch_status);
                    $status_code = curl_getinfo($ch_status, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch_status);
                    curl_close($ch_status);

                    if ($curl_error) {
                        $erro = "Erro ao verificar conexão WhatsApp: $curl_error";
                    } elseif ($status_code != 200) {
                        $erro = "Erro ao verificar status do WhatsApp (HTTP $status_code)";
                    } else {
                        $status_data = json_decode($status_response, true);
                        $connection_state = $status_data['instance']['state'] ?? $status_data['state'] ?? 'unknown';
                        if ($connection_state !== 'open') {
                            $erro = "WhatsApp não está conectado. Status: $connection_state";
                        } else {
                            $bot_selecionado = [
                                'id' => 'whatsapp_config',
                                'name' => 'WhatsApp API',
                                'type' => 'whatsapp',
                                'active' => true,
                                'token' => null
                            ];
                            $bot_name = 'WhatsApp API';
                        }
                    }
                }
            } elseif ($plataforma_envio == 'telegram') {
                $bot_selecionado = fetchOne("SELECT * FROM bots WHERE id = ? AND active = 1 AND type = 'telegram' AND (user_id = ? OR user_id IS NULL)", 
                    [$bot_id, $_SESSION['user_id']]);
                
                if (!$bot_selecionado) {
                    $erro = "Bot Telegram não encontrado, inativo ou não corresponde à plataforma selecionada!";
                } else {
                    $bot_name = $bot_selecionado['name'];
                }
            } else {
                $erro = "Plataforma de envio inválida.";
            }

            if (!$bot_selecionado && empty($erro)) {
                $erro = "Não foi possível carregar a configuração da plataforma selecionada.";
            }

            if (empty($erro)) {
                $sucesso_envio = false;
                $erro_envio = '';
                
                if ($plataforma_envio == 'whatsapp') {
                    $base_url = rtrim($config['whatsapp_server'], '/');
                    
                    if ($tipo_envio == 'imagem' && isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
                        $upload_dir = 'uploads/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $filename = time() . '_' . basename($_FILES['imagem']['name']);
                        $upload_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['imagem']['tmp_name'], $upload_path)) {
                            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                            $request_uri = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
                            $full_url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $request_uri . '/' . $upload_path;
                            
                            $url = $base_url . '/message/sendMedia/' . $config['whatsapp_instance'];
                            $data = [
                                'number' => $destino,
                                'mediatype' => 'image',
                                'media' => $full_url,
                                'caption' => $mensagem
                            ];
                        } else {
                            $erro_envio = "Erro ao fazer upload da imagem.";
                        }
                    } else {
                        $url = $base_url . '/message/sendText/' . $config['whatsapp_instance'];
                        $data = [
                            'number' => $destino,
                            'text' => $mensagem
                        ];
                    }
                    
                    if (!isset($erro_envio) || empty($erro_envio)) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'apikey: ' . $config['whatsapp_apikey']
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        curl_close($ch);
                        
                        if ($curl_error) {
                            $erro_envio = "Erro de conexão WhatsApp: $curl_error";
                        } elseif ($httpCode == 200 || $httpCode == 201) {
                            $response_data = json_decode($response, true);
                            if (isset($response_data['key']['id']) || (isset($response_data['status']) && $response_data['status'] === 'success')) {
                                $sucesso_envio = true;
                            } else {
                                $erro_envio = "Resposta inválida da API WhatsApp: " . ($response_data['message'] ?? $response);
                            }
                        } else {
                            $response_data = json_decode($response, true);
                            $erro_envio = "Erro HTTP $httpCode da API WhatsApp: " . ($response_data['message'] ?? $response);
                        }
                    }
                    
                } elseif ($plataforma_envio == 'telegram') {
                    if (empty($bot_selecionado['token'])) {
                        $erro_envio = "Token do bot Telegram não configurado.";
                    } else {
                        if ($tipo_envio == 'imagem' && isset($_FILES['imagem']) && $_FILES['imagem']['error'] == 0) {
                            $url = "https://api.telegram.org/bot" . $bot_selecionado['token'] . "/sendPhoto";
                            $post_data = [
                                'chat_id' => $destino,
                                'caption' => $mensagem,
                                'parse_mode' => 'HTML',
                                'photo' => new CURLFile($_FILES['imagem']['tmp_name'], $_FILES['imagem']['type'], $_FILES['imagem']['name'])
                            ];
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        } else {
                            $url = "https://api.telegram.org/bot" . $bot_selecionado['token'] . "/sendMessage";
                            $post_data = [
                                'chat_id' => $destino,
                                'text' => $mensagem,
                                'parse_mode' => 'HTML'
                            ];
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $url);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        }
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curl_error = curl_error($ch);
                        curl_close($ch);
                        
                        if ($curl_error) {
                            $erro_envio = "Erro de conexão Telegram: $curl_error";
                        } elseif ($httpCode == 200) {
                            $response_data = json_decode($response, true);
                            if (isset($response_data['ok']) && $response_data['ok'] === true) {
                                $sucesso_envio = true;
                            } else {
                                $erro_envio = "Erro Telegram: " . ($response_data['description'] ?? 'Erro desconhecido - ' . $response);
                            }
                        } else {
                            $erro_envio = "Erro HTTP Telegram $httpCode: $response";
                        }
                    }
                }
                
                // Salvar log no banco
                executeQuery("INSERT INTO logs (destination, bot_name, type, message, status, platform, user_id, error_details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())", 
                    [$destino, $bot_name, $tipo_envio, substr($mensagem, 0, 500), $sucesso_envio ? 'success' : 'error', $plataforma_envio, $_SESSION['user_id'], $erro_envio ?: null]);
                
                if ($sucesso_envio) {
                    $sucesso = "Mensagem enviada com sucesso!";
                } else {
                    $erro = $erro_envio ?: "Erro desconhecido no envio.";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Enviar error: " . $e->getMessage());
        $erro = "Erro interno. Tente novamente.";
    }
}

// Carregar dados do banco
try {
    $bots = fetchAll("SELECT * FROM bots WHERE active = 1 AND (user_id = ? OR user_id IS NULL) ORDER BY name", [$_SESSION['user_id']]);
    $grupos = fetchAll("SELECT * FROM groups WHERE user_id = ? ORDER BY name", [$_SESSION['user_id']]);
} catch (Exception $e) {
    error_log("Enviar load error: " . $e->getMessage());
    $bots = [];
    $grupos = [];
}

include 'includes/header.php';
?>

<div class="container mt-4">
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

    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-send"></i>
                Enviar Mensagem
            </h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="enviar">
                
                <div class="mb-3">
                    <label for="plataforma_envio" class="form-label">Plataforma de Envio</label>
                    <select name="plataforma_envio" id="plataforma_envio" class="form-select" required onchange="updateBotOptions()">
                        <option value="">Selecione a Plataforma...</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="telegram">Telegram</option>
                    </select>
                </div>

                <div class="mb-3" id="bot_select_container" style="display: none;">
                    <label for="bot_id" class="form-label">Selecionar Bot</label>
                    <select name="bot_id" id="bot_id" class="form-select">
                        <option value="">Escolha um bot...</option>
                    </select>
                    <?php if (empty($bots)): ?>
                    <small class="form-text text-muted">
                        <strong>Nenhum bot cadastrado.</strong> <a href="bots.php">Clique aqui para cadastrar um bot</a>
                    </small>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label for="tipo_envio" class="form-label">Tipo de Mensagem</label>
                    <select name="tipo_envio" id="tipo_envio" class="form-select" required onchange="toggleTipoEnvio()">
                        <option value="">Selecione o Tipo de Mensagem...</option>
                        <option value="texto">Texto</option>
                        <option value="imagem">Imagem com Legenda</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="destino" class="form-label">Destino (Grupo/Chat ID)</label>
                    <select name="destino" id="destino" class="form-select" required>
                        <option value="">Selecione um grupo...</option>
                    </select>
                </div>
                
                <div id="campo_imagem" class="mb-3" style="display: none;">
                    <label for="imagem" class="form-label">Imagem</label>
                    <input type="file" name="imagem" id="imagem" class="form-control" accept="image/*" onchange="previewImage(this, 'preview_imagem')">
                    <img id="preview_imagem" style="max-width: 200px; margin-top: 10px; display: none;" alt="Preview da Imagem">
                </div>
                
                <div class="mb-3">
                    <label for="mensagem" class="form-label">Mensagem/Legenda</label>
                    <textarea name="mensagem" id="mensagem" class="form-control" rows="8" required placeholder="Digite sua mensagem aqui..."></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send"></i> Enviar Mensagem
                    </button>
                    
                    <button type="button" class="btn btn-secondary" onclick="limparFormulario()">
                        <i class="bi bi-arrow-clockwise"></i> Limpar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-info-circle"></i>
                Modelos de Mensagem Rápida
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card h-100 bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Modelo de Venda</h5>
                            <pre id="modeloVenda" class="bg-white p-3 border rounded" style="font-size: 0.9rem; white-space: pre-wrap; word-wrap: break-word;">🎉 OFERTA EXCLUSIVA! 🎉

Olá, [Nome do Cliente]!

Temos uma *oferta imperdível* para você: [Nome do Produto/Serviço] com [desconto/benefício]!

✅ Benefício 1
✅ Benefício 2
✅ Benefício 3

Por apenas: *R$[Preço]* (De R$[Preço Antigo])

📢 Válido por tempo limitado!

Clique aqui e garanta já: [Link para Venda]

Atenciosamente,
Sua Equipe [Nome da Empresa]
</pre>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="copiarModelo('modeloVenda')">
                                <i class="bi bi-clipboard"></i> Copiar para Mensagem
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Modelo de Mensagem de Aviso/Alerta</h5>
                            <pre id="modeloAviso" class="bg-white p-3 border rounded" style="font-size: 0.9rem; white-space: pre-wrap; word-wrap: break-word;">🚨 *AVISO IMPORTANTE!* 🚨

Prezados(as) membros do grupo,

Informamos que [motivo do aviso/alerta].

Por favor, fiquem atentos a:
- [Item 1]
- [Item 2]

Agradecemos a compreensão e colaboração.

Em caso de dúvidas, contate-nos.

Atenciosamente,
A Administração
</pre>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="copiarModelo('modeloAviso')">
                                <i class="bi bi-clipboard"></i> Copiar para Mensagem
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Modelo de Boas-Vindas</h5>
                            <pre id="modeloBoasVindas" class="bg-white p-3 border rounded" style="font-size: 0.9rem; white-space: pre-wrap; word-wrap: break-word;">👋 Olá, [Nome do Cliente]!

Seja muito bem-vindo(a) à [Nome da Empresa/Grupo]! Estamos felizes em tê-lo(a) conosco.

Aqui você encontrará [breve descrição do que o grupo/empresa oferece].

Para começar, que tal:
1. Visitar nosso site: [Link do Site]
2. Conhecer nossos produtos/serviços: [Link para Produtos/Serviços]
3. Fazer uma pergunta: [Contato de Suporte]

Sinta-se à vontade para explorar e interagir!

Qualquer dúvida, estamos à disposição.

Atenciosamente,
Equipe [Nome da Empresa]</pre>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="copiarModelo('modeloBoasVindas')">
                                <i class="bi bi-clipboard"></i> Copiar para Mensagem
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 bg-light">
                        <div class="card-body">
                            <h5 class="card-title">Modelo de Suporte/Atendimento</h5>
                            <pre id="modeloSuporte" class="bg-white p-3 border rounded" style="font-size: 0.9rem; white-space: pre-wrap; word-wrap: break-word;">🧑‍💻 SUPORTE [Nome da Empresa] 🧑‍💻

Olá! Agradecemos o seu contato.

Para que possamos te ajudar da melhor forma, por favor, descreva seu problema ou dúvida com o máximo de detalhes possível.

Você pode incluir:
- Nome completo
- CPF/CNPJ (se aplicável)
- Número do pedido/serviço (se aplicável)
- Capturas de tela ou vídeos (se puder anexar)

Nosso horário de atendimento é de [Horário de Início] às [Horário de Término], de [Dia da Semana Início] a [Dia da Semana Fim].

Aguarde, em breve um de nossos atendentes entrará em contato!

Obrigado(a) pela paciência.
Equipe de Suporte [Nome da Empresa]</pre>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="copiarModelo('modeloSuporte')">
                                <i class="bi bi-clipboard"></i> Copiar para Mensagem
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-info-circle"></i>
                Guia de Chat IDs
            </h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>📱 WhatsApp:</strong>
                    <ul>
                        <li><strong>Contato:</strong> 5521999999999@c.us</li>
                        <li><strong>Grupo:</strong> 120363123456789@g.us</li>
                    </ul>
                    <div class="alert alert-warning">
                        <strong>⚠️ Importante:</strong> Para WhatsApp, use sempre o formato completo com @c.us ou @g.us
                    </div>
                </div>
                <div class="col-md-6">
                    <strong>📱 Telegram:</strong>
                    <ul>
                        <li><strong>Usuário:</strong> 123456789 (ID numérico)</li>
                        <li><strong>Grupo:</strong> -1001234567890 (ID negativo)</li>
                        <li><strong>Canal:</strong> -1001234567890 (ID negativo)</li>
                    </ul>
                </div>
            </div>
            <div class="alert alert-info">
                <strong>💡 Dica:</strong> Para obter o Chat ID do Telegram, adicione o bot @userinfobot ou @getidsbot ao seu grupo/canal.
            </div>
        </div>
    </div>
</div>

<script>
    // PHP variables for JavaScript access
    const allBots = <?= json_encode($bots); ?>;
    const myGroups = <?= json_encode($grupos); ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const plataformaSelect = document.getElementById('plataforma_envio');
        const botSelect = document.getElementById('bot_id');
        const botSelectContainer = document.getElementById('bot_select_container');
        const destinoSelect = document.getElementById('destino');
        const mensagemTextarea = document.getElementById('mensagem');
        const tipoEnvioSelect = document.getElementById('tipo_envio');
        const campoImagem = document.getElementById('campo_imagem');
        const previewImagem = document.getElementById('preview_imagem');

        // Function to update bot options based on selected platform
        function updateBotOptions() {
            const selectedPlatform = plataformaSelect.value;
            botSelect.innerHTML = '<option value="">Escolha um bot...</option>';

            if (selectedPlatform === 'whatsapp') {
                botSelectContainer.style.display = 'none';
                botSelect.removeAttribute('required');
            } else if (selectedPlatform === 'telegram') {
                botSelectContainer.style.display = 'block';
                botSelect.setAttribute('required', 'required');
                allBots.forEach(bot => {
                    if (bot.active && bot.type === selectedPlatform) {
                        const option = document.createElement('option');
                        option.value = bot.id;
                        option.textContent = `${bot.name} (${bot.type.charAt(0).toUpperCase() + bot.type.slice(1)})`;
                        botSelect.appendChild(option);
                    }
                });
            } else {
                botSelectContainer.style.display = 'none';
                botSelect.removeAttribute('required');
            }
            updateDestinoOptions();
        }

        // Function to update destination options based on selected platform
        function updateDestinoOptions() {
            const selectedPlatform = plataformaSelect.value;
            destinoSelect.innerHTML = '<option value="">Selecione um grupo...</option>';
            if (selectedPlatform) {
                myGroups.forEach(grupo => {
                    if (grupo.type === selectedPlatform) {
                        const option = document.createElement('option');
                        option.value = grupo.external_id;
                        option.textContent = `${grupo.name} (${grupo.external_id})`;
                        destinoSelect.appendChild(option);
                    }
                });
            }
        }

        // Event listeners
        plataformaSelect.addEventListener('change', function() {
            updateBotOptions();
            botSelect.value = '';
            destinoSelect.value = '';
        });

        window.updateBotOptions = updateBotOptions;

        window.toggleTipoEnvio = function() {
            const tipo = tipoEnvioSelect.value;
            const imagemInput = document.getElementById('imagem');
            if (tipo === 'imagem') {
                campoImagem.style.display = 'block';
                imagemInput.setAttribute('required', 'required');
            } else {
                campoImagem.style.display = 'none';
                imagemInput.removeAttribute('required');
                previewImagem.style.display = 'none';
                imagemInput.value = '';
            }
        }

        window.previewImage = function(input, previewId) {
            const preview = document.getElementById(previewId);
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
                preview.src = '';
            }
        }

        window.limparFormulario = function() {
            document.querySelector('form').reset();
            campoImagem.style.display = 'none';
            previewImagem.style.display = 'none';
            plataformaSelect.value = '';
            updateBotOptions();
            destinoSelect.innerHTML = '<option value="">Selecione um grupo...</option>';
            tipoEnvioSelect.value = '';
        }

        window.copiarModelo = function(idModelo) {
            const modeloText = document.getElementById(idModelo).innerText;
            mensagemTextarea.value = modeloText.trim();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>