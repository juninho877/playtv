<?php
$page_title = 'AutoBot WhatsApp';
include 'includes/auth.php';
verificarLogin();

$sucesso = '';
$erro = '';

// Processar ações
if ($_POST && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'toggle_bot':
                // Buscar configuração atual
                $config = fetchOne("SELECT * FROM autobot_config WHERE user_id = ?", [$_SESSION['user_id']]);
                
                if ($config) {
                    $new_status = $config['active'] ? 0 : 1;
                    executeQuery("UPDATE autobot_config SET active = ?, updated_at = NOW() WHERE user_id = ?", 
                        [$new_status, $_SESSION['user_id']]);
                } else {
                    // Criar configuração inicial
                    executeQuery("INSERT INTO autobot_config (user_id, active) VALUES (?, 1)", [$_SESSION['user_id']]);
                    $new_status = 1;
                }
                
                $sucesso = $new_status ? 'Bot ativado com sucesso!' : 'Bot desativado com sucesso!';
                break;
                
            case 'salvar_configuracoes':
                $emoji = trim($_POST['emoji'] ?? '');
                $saudacao = trim($_POST['saudacao'] ?? '');
                $mensagem_padrao = trim($_POST['mensagem_padrao'] ?? '');
                $mensagem_fora_horario = trim($_POST['mensagem_fora_horario'] ?? '');
                $horario_ativo = isset($_POST['horario_ativo']) ? 1 : 0;
                $horario_inicio = $_POST['horario_inicio'] ?? '08:00';
                $horario_fim = $_POST['horario_fim'] ?? '18:00';
                $tempo_inatividade_valor = (int)($_POST['tempo_inatividade_valor'] ?? 5);
                $tempo_inatividade_unidade = $_POST['tempo_inatividade_unidade'] ?? 'minutos';
                
                if (empty($saudacao) || empty($mensagem_padrao) || $tempo_inatividade_valor < 1) {
                    $erro = 'Campos obrigatórios (saudação, mensagem padrão, tempo de inatividade) não preenchidos!';
                    break;
                }
                
                if ($horario_ativo && (empty($horario_inicio) || empty($horario_fim) || empty($mensagem_fora_horario))) {
                    $erro = 'Preencha todos os campos de horário quando ativar o horário de funcionamento!';
                    break;
                }
                
                // Converter tempo de inatividade para segundos
                $tempo_inatividade = $tempo_inatividade_valor;
                if ($tempo_inatividade_unidade === 'minutos') {
                    $tempo_inatividade *= 60;
                } elseif ($tempo_inatividade_unidade === 'horas') {
                    $tempo_inatividade *= 3600;
                }
                
                if ($tempo_inatividade < 60) {
                    $erro = 'Tempo de inatividade deve ser no mínimo 60 segundos!';
                    break;
                }
                
                // Preparar mensagens com emoji
                $greeting_message = $emoji ? $emoji . ' ' . $saudacao : $saudacao;
                $default_message = $emoji ? $emoji . ' ' . $mensagem_padrao : $mensagem_padrao;
                $out_of_hours_message = $mensagem_fora_horario ? ($emoji ? $emoji . ' ' . $mensagem_fora_horario : $mensagem_fora_horario) : '';
                
                // Inserir ou atualizar configuração
                executeQuery("INSERT INTO autobot_config (user_id, emoji, greeting_message, default_message, out_of_hours_message, hours_active, start_time, end_time, inactivity_timeout, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE emoji = VALUES(emoji), greeting_message = VALUES(greeting_message), default_message = VALUES(default_message), out_of_hours_message = VALUES(out_of_hours_message), hours_active = VALUES(hours_active), start_time = VALUES(start_time), end_time = VALUES(end_time), inactivity_timeout = VALUES(inactivity_timeout), updated_at = NOW()", 
                    [$_SESSION['user_id'], $emoji, $greeting_message, $default_message, $out_of_hours_message, $horario_ativo, $horario_inicio, $horario_fim, $tempo_inatividade]);
                
                $sucesso = 'Configurações salvas com sucesso!';
                break;
                
            case 'salvar_variaveis':
                $variables = [
                    'valor_promocao' => trim($_POST['valor_promocao'] ?? 'R$ 99,90'),
                    'pix' => trim($_POST['pix'] ?? 'chave-pix@exemplo.com'),
                    'nome_banco' => trim($_POST['nome_banco'] ?? 'Banco Exemplo'),
                    'nome_titular' => trim($_POST['nome_titular'] ?? 'João Silva')
                ];
                
                foreach ($variables as $name => $value) {
                    executeQuery("INSERT INTO dynamic_variables (user_id, variable_name, variable_value, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE variable_value = VALUES(variable_value), updated_at = NOW()", 
                        [$_SESSION['user_id'], $name, $value]);
                }
                
                $sucesso = 'Variáveis dinâmicas salvas com sucesso!';
                break;
                
            case 'adicionar_palavra':
                $palavra = trim($_POST['palavra'] ?? '');
                $resposta = trim($_POST['resposta'] ?? '');
                $tempo_resposta = (int)($_POST['tempo_resposta'] ?? 0);
                $ativo = isset($_POST['ativo']) ? 1 : 0;
                
                if (empty($palavra) || empty($resposta)) {
                    $erro = 'Palavra ou resposta inválida!';
                    break;
                }
                
                // Buscar emoji da configuração
                $config = fetchOne("SELECT emoji FROM autobot_config WHERE user_id = ?", [$_SESSION['user_id']]);
                $emoji = $config['emoji'] ?? '';
                $response_with_emoji = $emoji ? $emoji . ' ' . $resposta : $resposta;
                
                executeQuery("INSERT INTO autobot_keywords (user_id, keyword, response, active, response_delay, created_at) VALUES (?, ?, ?, ?, ?, NOW())", 
                    [$_SESSION['user_id'], $palavra, $response_with_emoji, $ativo, $tempo_resposta]);
                
                $sucesso = 'Palavra-chave adicionada com sucesso!';
                break;
                
            case 'editar_palavra':
                $palavra_id = (int)$_POST['palavra_id'];
                $palavra = trim($_POST['palavra'] ?? '');
                $resposta = trim($_POST['resposta'] ?? '');
                $tempo_resposta = (int)($_POST['tempo_resposta'] ?? 0);
                
                if (empty($palavra) || empty($resposta)) {
                    $erro = 'Campos inválidos!';
                    break;
                }
                
                // Buscar emoji da configuração
                $config = fetchOne("SELECT emoji FROM autobot_config WHERE user_id = ?", [$_SESSION['user_id']]);
                $emoji = $config['emoji'] ?? '';
                $response_with_emoji = $emoji ? $emoji . ' ' . $resposta : $resposta;
                
                $affected = executeQuery("UPDATE autobot_keywords SET keyword = ?, response = ?, response_delay = ?, updated_at = NOW() WHERE id = ? AND user_id = ?", 
                    [$palavra, $response_with_emoji, $tempo_resposta, $palavra_id, $_SESSION['user_id']])->rowCount();
                
                if ($affected > 0) {
                    $sucesso = 'Palavra-chave editada com sucesso!';
                } else {
                    $erro = 'Palavra-chave não encontrada!';
                }
                break;
                
            case 'toggle_palavra':
                $palavra_id = (int)$_POST['palavra_id'];
                
                $keyword = fetchOne("SELECT active FROM autobot_keywords WHERE id = ? AND user_id = ?", [$palavra_id, $_SESSION['user_id']]);
                if (!$keyword) {
                    $erro = 'Palavra-chave não encontrada!';
                    break;
                }
                
                $new_status = $keyword['active'] ? 0 : 1;
                executeQuery("UPDATE autobot_keywords SET active = ?, updated_at = NOW() WHERE id = ? AND user_id = ?", 
                    [$new_status, $palavra_id, $_SESSION['user_id']]);
                
                $sucesso = 'Status da palavra-chave alterado!';
                break;
                
            case 'deletar_palavra':
                $palavra_id = (int)$_POST['palavra_id'];
                
                $affected = executeQuery("DELETE FROM autobot_keywords WHERE id = ? AND user_id = ?", [$palavra_id, $_SESSION['user_id']])->rowCount();
                
                if ($affected > 0) {
                    $sucesso = 'Palavra-chave deletada com sucesso!';
                } else {
                    $erro = 'Palavra-chave não encontrada!';
                }
                break;
        }
    } catch (Exception $e) {
        error_log("AutoBot error: " . $e->getMessage());
        $erro = "Erro interno. Tente novamente.";
    }
}

// Carregar dados do banco
try {
    // Configuração do AutoBot
    $autobot_config = fetchOne("SELECT * FROM autobot_config WHERE user_id = ?", [$_SESSION['user_id']]);
    if (!$autobot_config) {
        $autobot_config = [
            'active' => false,
            'emoji' => '👋',
            'greeting_message' => 'Olá! Seja bem-vindo(a)! Como posso ajudá-lo(a) hoje?',
            'default_message' => 'Desculpe, não entendi sua mensagem. Digite *MENU* para ver as opções disponíveis ou aguarde que em breve um atendente irá lhe responder.',
            'out_of_hours_message' => 'Estamos fora do horário de atendimento. Nosso horário é de {{horario_inicio}} às {{horario_fim}}. Retornaremos em breve!',
            'hours_active' => false,
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'inactivity_timeout' => 300
        ];
    }
    
    // Palavras-chave
    $palavras_chave = fetchAll("SELECT * FROM autobot_keywords WHERE user_id = ? ORDER BY created_at DESC", [$_SESSION['user_id']]);
    
    // Variáveis dinâmicas
    $variables_rows = fetchAll("SELECT variable_name, variable_value FROM dynamic_variables WHERE user_id = ?", [$_SESSION['user_id']]);
    $variaveis = [];
    foreach ($variables_rows as $row) {
        $variaveis[$row['variable_name']] = $row['variable_value'];
    }
    
    // Estatísticas
    $stats = fetchOne("SELECT * FROM autobot_statistics WHERE user_id = ?", [$_SESSION['user_id']]);
    if (!$stats) {
        $stats = [
            'messages_sent' => 0,
            'conversations_started' => 0,
            'active_keywords' => 0,
            'unique_contacts' => 0
        ];
    }
    
    // Atualizar contadores em tempo real
    $active_keywords_count = fetchOne("SELECT COUNT(*) as count FROM autobot_keywords WHERE user_id = ? AND active = 1", [$_SESSION['user_id']])['count'] ?? 0;
    $unique_contacts_count = fetchOne("SELECT COUNT(DISTINCT phone_number) as count FROM autobot_conversations WHERE user_id = ?", [$_SESSION['user_id']])['count'] ?? 0;
    
    // Atualizar estatísticas
    executeQuery("INSERT INTO autobot_statistics (user_id, active_keywords, unique_contacts, last_updated) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE active_keywords = VALUES(active_keywords), unique_contacts = VALUES(unique_contacts), last_updated = NOW()", 
        [$_SESSION['user_id'], $active_keywords_count, $unique_contacts_count]);
    
    $stats['active_keywords'] = $active_keywords_count;
    $stats['unique_contacts'] = $unique_contacts_count;
    
    // Verificar se WhatsApp está configurado
    $whatsapp_config = fetchAll("SELECT config_key, config_value FROM system_config WHERE config_key IN ('whatsapp_server', 'whatsapp_instance', 'whatsapp_apikey')");
    $whatsapp_settings = [];
    foreach ($whatsapp_config as $setting) {
        $whatsapp_settings[$setting['config_key']] = $setting['config_value'];
    }
    $whatsapp_configurado = !empty($whatsapp_settings['whatsapp_server']) && !empty($whatsapp_settings['whatsapp_instance']) && !empty($whatsapp_settings['whatsapp_apikey']);
    
} catch (Exception $e) {
    error_log("AutoBot load error: " . $e->getMessage());
    $autobot_config = ['active' => false];
    $palavras_chave = [];
    $variaveis = [];
    $stats = ['messages_sent' => 0, 'conversations_started' => 0, 'active_keywords' => 0, 'unique_contacts' => 0];
    $whatsapp_configurado = false;
}

// Lista de emojis compatíveis com WhatsApp
$emojis = [
    '😊' => 'Sorriso',
    '👋' => 'Aceno',
    '✅' => 'Confirmado',
    '❌' => 'Cancelado',
    '📞' => 'Telefone',
    '💰' => 'Dinheiro',
    '🛒' => 'Carrinho',
    '🎉' => 'Festa',
    '🔥' => 'Fogo',
    '⭐' => 'Estrela',
    '👍' => 'Positivo',
    '👉' => 'Apontar Direita',
    '🔔' => 'Sino',
    '💡' => 'Lâmpada',
    '📩' => 'Envelope',
    '🚀' => 'Foguete',
    '🎁' => 'Presente',
    '🔒' => 'Cadeado',
    '🕒' => 'Relógio',
    '🌟' => 'Estrela Brilhante',
    '🤖' => 'Robô',
    '🙏' => 'Mãos Juntas',
    '🙌' => 'Mãos para Cima',
    '📆' => 'Calendário',
    '📝' => 'Bloco de Notas',
    '📋' => 'Prancheta',
    '🖥️' => 'Computador',
    '💻' => 'Notebook',
    '📲' => 'Celular',
    '🎯' => 'Alvo',
    '⚙️' => 'Engrenagem',
    '🔗' => 'Link',
    '🎶' => 'Música',
    '🍀' => 'Trevo',
    '❤️' => 'Coração',
    '💬' => 'Balão de Fala',
    '📚' => 'Livros',
    '🔍' => 'Lupa',
    '🆘' => 'Socorro',
    '😎' => 'Óculos Escuros',
    '🌞' => 'Sol',
    '🌙' => 'Lua',
    '🌈' => 'Arco-íris'
];

include 'includes/header.php';
?>

<!-- Inline CSS for custom components -->
<style>
.autobot-toggle {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 30px;
}

.autobot-toggle input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, #6c757d, #adb5bd);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.slider:before {
    position: absolute;
    content: "";
    height: 24px;
    width: 24px;
    left: 3px;
    bottom: 3px;
    background: linear-gradient(45deg, #ffffff, #f8f9fa);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

input:checked + .slider {
    background: linear-gradient(45deg, #28a745, #1e7e34);
    box-shadow: 0 4px 20px rgba(40,167,69,0.3);
}

input:checked + .slider:before {
    transform: translateX(30px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.bot-status {
    padding: 6px 12px;
    border-radius: 50px;
    color: white;
    font-weight: 500;
    display: inline-block;
    animation: pulse 2s infinite;
}

.bot-ativo {
    background-color: #28a745;
}

.bot-inativo {
    background-color: #dc3545;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.stats-card {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    border-radius: 10px;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,123,255,0.2);
    transition: transform 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,123,255,0.3);
}

.stats-number {
    font-size: 2rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.palavra-item {
    border: 1px solid #eee;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.palavra-ativa {
    border-left: 4px solid #28a745;
}

.palavra-inativa {
    border-left: 4px solid #dc3545;
    opacity: 0.7;
}

@media (max-width: 768px) {
    .autobot-toggle {
        width: 50px;
        height: 25px;
    }
    
    .slider:before {
        height: 20px;
        width: 20px;
        left: 2px;
        bottom: 2.5px;
    }
    
    input:checked + .slider:before {
        transform: translateX(25px);
    }
    
    .stats-card {
        padding: 0.75rem;
    }
    
    .stats-number {
        font-size: 1.5rem;
    }
}
</style>

<?php if (!$whatsapp_configurado): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Atenção:</strong> Configure primeiro o WhatsApp em <a href="configuracoes.php">Configurações</a> para usar o AutoBot. Você precisa definir a URL do servidor, a instância e a chave API da Evolution API.
</div>
<?php endif; ?>

<?php if (!empty($sucesso)): ?>
<div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<?php if (!empty($erro)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<!-- Controle Principal -->
<div class="main-content">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-robot"></i> AutoBot WhatsApp - Controle Principal
            </h3>
        </div>
        <div class="card-body">
            <h5>Status do Bot</h5>
            <p><strong>Descrição:</strong> Ative ou desative o bot. Quando ativo, ele responde automaticamente às mensagens recebidas com base nas configurações e palavras-chave definidas.</p>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="toggle_bot">
                <label class="autobot-toggle">
                    <input type="checkbox" <?= $autobot_config['active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="slider"></span>
                </label>
                <span style="margin-left: 1rem;">
                    <span class="bot-status <?= $autobot_config['active'] ? 'bot-ativo' : 'bot-inativo' ?>">
                        🤖 Bot <?= $autobot_config['active'] ? 'ATIVO' : 'INATIVO' ?>
                    </span>
                </span>
            </form>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="cards-grid">
        <div class="stats-card">
            <div class="stats-number"><?= $stats['messages_sent'] ?></div>
            <div>Mensagens Respondidas</div>
            <small class="text-light">Total de mensagens automáticas enviadas pelo bot.</small>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?= $stats['conversations_started'] ?></div>
            <div>Conversas Iniciadas</div>
            <small class="text-light">Número de novos contatos que iniciaram uma conversa.</small>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?= $stats['unique_contacts'] ?></div>
            <div>Pessoas Respondidas</div>
            <small class="text-light">Número de contatos únicos que receberam respostas do bot.</small>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?= $stats['active_keywords'] ?></div>
            <div>Palavras-Chave Ativas</div>
            <small class="text-light">Total de palavras-chave atualmente ativas.</small>
        </div>
    </div>

    <!-- Configurações -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-gear"></i> Configurações do Bot
            </h3>
        </div>
        <div class="card-body">
            <p><strong>Descrição:</strong> Configure as mensagens automáticas, horário de funcionamento (opcional) e o comportamento do bot. Use variáveis como {{data}}, {{hora}}, {{valor_promocao}}, {{pix}}, {{nome_banco}}, {{nome_titular}}, {{horario_inicio}}, {{horario_fim}} e o emoji selecionado para personalizar as respostas.</p>
            <form method="POST">
                <input type="hidden" name="action" value="salvar_configuracoes">
                
                <div class="form-group">
                    <label class="form-label">Emoji Padrão</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <select name="emoji" id="emojiSelector" class="form-select" onchange="updateEmojiPreview()">
                            <option value="">Nenhum</option>
                            <?php foreach ($emojis as $emoji => $desc): ?>
                            <option value="<?= htmlspecialchars($emoji) ?>" <?= ($autobot_config['emoji'] ?? '') === $emoji ? 'selected' : '' ?>><?= htmlspecialchars($emoji . ' ' . $desc) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="copyEmoji()">
                            <i class="bi bi-clipboard"></i> Copiar Emoji
                        </button>
                    </div>
                    <small class="form-text text-muted">Escolha um emoji para usar em todas as mensagens automáticas. Clique em "Copiar Emoji" para usá-lo manualmente.</small>
                    <div id="emojiPreview" style="margin-top: 0.5rem; font-size: 1.5rem;"><?= htmlspecialchars($autobot_config['emoji'] ?? 'Nenhum emoji selecionado') ?></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mensagem de Saudação</label>
                    <textarea name="saudacao" class="form-control" rows="5" required><?= htmlspecialchars(preg_replace('/^' . preg_quote($autobot_config['emoji'] ?? '', '/') . '\s*/u', '', $autobot_config['greeting_message'] ?? '')) ?></textarea>
                    <small class="form-text text-muted">Enviada quando um usuário inicia uma conversa ou após o tempo de inatividade, dentro do horário de funcionamento (se ativo). O emoji selecionado será adicionado automaticamente.</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mensagem Padrão</label>
                    <textarea name="mensagem_padrao" class="form-control" rows="5" required><?= htmlspecialchars(preg_replace('/^' . preg_quote($autobot_config['emoji'] ?? '', '/') . '\s*/u', '', $autobot_config['default_message'] ?? '')) ?></textarea>
                    <small class="form-text text-muted">Enviada quando nenhuma palavra-chave é encontrada, dentro do horário de funcionamento (se ativo). O emoji selecionado será adicionado automaticamente.</small>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" name="horario_ativo" id="horarioAtivo" class="form-check-input" <?= $autobot_config['hours_active'] ? 'checked' : '' ?> onchange="toggleHorarioFields()">
                        <label class="form-check-label" for="horarioAtivo">Ativar Horário de Funcionamento</label>
                    </div>
                    <small class="form-text text-muted">Habilite para definir um horário de atendimento. Fora desse horário, a mensagem fora de horário será enviada.</small>
                </div>
                
                <div id="horarioFields" style="display: <?= $autobot_config['hours_active'] ? 'block' : 'none' ?>;">
                    <div class="form-group">
                        <label class="form-label">Mensagem Fora de Horário</label>
                        <textarea name="mensagem_fora_horario" class="form-control" rows="5"><?= htmlspecialchars(preg_replace('/^' . preg_quote($autobot_config['emoji'] ?? '', '/') . '\s*/u', '', $autobot_config['out_of_hours_message'] ?? '')) ?></textarea>
                        <small class="form-text text-muted">Enviada quando uma mensagem é recebida fora do horário de funcionamento. Use {{horario_inicio}} e {{horario_fim}}. O emoji selecionado será adicionado automaticamente.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Horário de Funcionamento</label>
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 1;">
                                <input type="time" name="horario_inicio" class="form-control" value="<?= htmlspecialchars(substr($autobot_config['start_time'] ?? '08:00:00', 0, 5)) ?>">
                                <small class="form-text text-muted">Início (ex.: 08:00)</small>
                            </div>
                            <div style="flex: 1;">
                                <input type="time" name="horario_fim" class="form-control" value="<?= htmlspecialchars(substr($autobot_config['end_time'] ?? '18:00:00', 0, 5)) ?>">
                                <small class="form-text text-muted">Fim (ex.: 18:00)</small>
                            </div>
                        </div>
                        <small class="form-text text-muted">Define o horário em que o bot responde com saudações ou palavras-chave. Fora desse horário, a mensagem fora de horário é enviada.</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tempo de Inatividade</label>
                    <div style="display: flex; gap: 1rem;">
                        <div style="flex: 1;">
                            <?php
                            $timeout = $autobot_config['inactivity_timeout'] ?? 300;
                            $unit = 'segundos';
                            $value = $timeout;
                            if ($timeout >= 3600) {
                                $unit = 'horas';
                                $value = floor($timeout / 3600);
                            } elseif ($timeout >= 60) {
                                $unit = 'minutos';
                                $value = floor($timeout / 60);
                            }
                            ?>
                            <input type="number" name="tempo_inatividade_valor" class="form-control" value="<?= max(1, $value) ?>" min="1" required>
                            <small class="form-text text-muted">Valor numérico (mínimo: 1).</small>
                        </div>
                        <div style="flex: 1;">
                            <select name="tempo_inatividade_unidade" class="form-select">
                                <option value="segundos" <?= $unit === 'segundos' ? 'selected' : '' ?>>Segundos</option>
                                <option value="minutos" <?= $unit === 'minutos' ? 'selected' : '' ?>>Minutos</option>
                                <option value="horas" <?= $unit === 'horas' ? 'selected' : '' ?>>Horas</option>
                            </select>
                            <small class="form-text text-muted">Unidade de tempo.</small>
                        </div>
                    </div>
                    <small class="form-text text-muted">Tempo após o qual uma conversa é considerada inativa, enviando a saudação novamente. Mínimo: 60 segundos.</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check"></i> Salvar Configurações
                </button>
            </form>
        </div>
    </div>

    <!-- Templates de Mensagens -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-list"></i> Templates de Mensagens
            </h3>
        </div>
        <div class="card-body">
            <p><strong>Descrição:</strong> Selecione uma variável para visualizar seu valor e copie-a para usar em saudações, mensagens padrão, mensagens fora de horário ou respostas de palavras-chave.</p>
            <div class="form-group">
                <label class="form-label">Selecionar Variável</label>
                <div style="display: flex; gap: 0.5rem;">
                    <select id="templateSelector" class="form-select" onchange="updateTemplatePreview()">
                        <option value="">Selecione uma variável</option>
                        <option value="{{data}}">{{data}} - Data atual (ex.: <?= date('d/m/Y') ?>)</option>
                        <option value="{{hora}}">{{hora}} - Hora atual (ex.: <?= date('H:i') ?>)</option>
                        <option value="{{palavra}}">{{palavra}} - Palavra-chave digitada pelo usuário</option>
                        <option value="{{valor_promocao}}">{{valor_promocao}} - Valor da promoção (ex.: <?= htmlspecialchars($variaveis['valor_promocao'] ?? 'R$ 99,90') ?>)</option>
                        <option value="{{pix}}">{{pix}} - Chave Pix (ex.: <?= htmlspecialchars($variaveis['pix'] ?? 'chave-pix@exemplo.com') ?>)</option>
                        <option value="{{nome_banco}}">{{nome_banco}} - Nome do banco (ex.: <?= htmlspecialchars($variaveis['nome_banco'] ?? 'Banco Exemplo') ?>)</option>
                        <option value="{{nome_titular}}">{{nome_titular}} - Nome do titular (ex.: <?= htmlspecialchars($variaveis['nome_titular'] ?? 'João Silva') ?>)</option>
                        <option value="{{horario_inicio}}">{{horario_inicio}} - Horário de início (ex.: <?= htmlspecialchars(substr($autobot_config['start_time'] ?? '08:00:00', 0, 5)) ?>)</option>
                        <option value="{{horario_fim}}">{{horario_fim}} - Horário de fim (ex.: <?= htmlspecialchars(substr($autobot_config['end_time'] ?? '18:00:00', 0, 5)) ?>)</option>
                        <option value="{{nome}}">{{nome}} - Nome do usuário (ex.: João)</option>
                        <option value="{{numero}}">{{numero}} - Número do usuário (ex.: 5599999999999)</option>
                        <option value="{{saudacao}}">{{saudacao}} - Saudação do momento (ex.: Bom dia / Boa tarde / Boa noite)</option>
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyTemplate()">
                        <i class="bi bi-clipboard"></i> Copiar Variável
                    </button>
                </div>
                <small class="form-text text-muted">Escolha uma variável para copiar. O valor atual é mostrado ao lado de cada variável.</small>
                <div id="templatePreview" style="margin-top: 0.5rem; font-size: 1rem;">Selecione uma variável para ver o preview</div>
            </div>
        </div>
    </div>

    <!-- Variáveis Dinâmicas -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="bi bi-variable"></i> Variáveis Dinâmicas
            </h3>
        </div>
        <div class="card-body">
            <p><strong>Descrição:</strong> Configure valores para variáveis dinâmicas usadas nas mensagens (ex.: {{valor_promocao}}, {{pix}}, {{nome_banco}}, {{nome_titular}}). Essas variáveis podem ser usadas na saudação, mensagem padrão, mensagem fora de horário e respostas de palavras-chave.</p>
            <form method="POST">
                <input type="hidden" name="action" value="salvar_variaveis">
                
                <div class="form-group">
                    <label class="form-label">Valor da Promoção</label>
                    <input type="text" name="valor_promocao" class="form-control" value="<?= htmlspecialchars($variaveis['valor_promocao'] ?? 'R$ 99,90') ?>" required>
                    <small class="form-text text-muted">Usado na variável {{valor_promocao}}. Exemplo: "R$ 99,90".</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Chave Pix</label>
                    <input type="text" name="pix" class="form-control" value="<?= htmlspecialchars($variaveis['pix'] ?? 'chave-pix@exemplo.com') ?>" required>
                    <small class="form-text text-muted">Usado na variável {{pix}}. Exemplo: "chave-pix@exemplo.com".</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nome do Banco</label>
                    <input type="text" name="nome_banco" class="form-control" value="<?= htmlspecialchars($variaveis['nome_banco'] ?? 'Banco Exemplo') ?>" required>
                    <small class="form-text text-muted">Usado na variável {{nome_banco}}. Exemplo: "Banco do Brasil".</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Nome do Titular</label>
                    <input type="text" name="nome_titular" class="form-control" value="<?= htmlspecialchars($variaveis['nome_titular'] ?? 'João Silva') ?>" required>
                    <small class="form-text text-muted">Usado na variável {{nome_titular}}. Exemplo: "João Silva".</small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check"></i> Salvar Variáveis
                </button>
            </form>
        </div>
    </div>

    <!-- Palavras-Chave -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h3 class="card-title">
                    <i class="bi bi-key"></i> Palavras-Chave (<?= count($palavras_chave) ?>)
                </h3>
                <button class="btn btn-success btn-sm" onclick="mostrarModalPalavra()">
                    <i class="bi bi-plus"></i> Nova Palavra-Chave
                </button>
            </div>
        </div>
        <div class="card-body">
            <p><strong>Descrição:</strong> Configure palavras ou frases que o bot reconhecerá nas mensagens recebidas, enviando respostas automáticas personalizadas com o emoji padrão e variáveis, dentro do horário de funcionamento (se ativo).</p>
            <?php if (empty($palavras_chave)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> Nenhuma palavra-chave cadastrada ainda. Clique em "Nova Palavra-Chave" para começar.
            </div>
            <?php else: ?>
            <div id="palavras-lista">
                <?php foreach ($palavras_chave as $palavra): ?>
                <div class="palavra-item <?= $palavra['active'] ? 'palavra-ativa' : 'palavra-inativa' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <h5 style="margin: 0; color: <?= $palavra['active'] ? '#28a745' : '#dc3545' ?>;">
                                <?= htmlspecialchars($palavra['keyword']) ?>
                                <?= $palavra['active'] ? '✅' : '❌' ?>
                            </h5>
                            <p style="margin: 0.5rem 0; color: #666;">
                                <?= nl2br(htmlspecialchars(substr($palavra['response'], 0, 100))) ?><?= strlen($palavra['response']) > 100 ? '...' : '' ?>
                            </p>
                            <small class="text-muted">
                                Usada <?= $palavra['usage_count'] ?> vezes • Tempo de resposta: <?= $palavra['response_delay'] ?>s • Criada em <?= date('d/m/Y H:i', strtotime($palavra['created_at'])) ?>
                            </small>
                        </div>
                        <div style="margin-left: 1rem; display: flex; gap: 0.5rem;">
                            <button class="btn btn-sm btn-outline-primary" onclick="editarPalavra(<?= htmlspecialchars(json_encode($palavra)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_palavra">
                                <input type="hidden" name="palavra_id" value="<?= $palavra['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $palavra['active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                    <i class="bi bi-<?= $palavra['active'] ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletarPalavra(<?= $palavra['id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Palavra-Chave -->
    <div id="modalPalavra" class="overlay">
        <div class="card" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 500px; max-height: 80vh; overflow-y: auto;">
            <div class="card-header">
                <h3 class="card-title" id="modalPalavraTitulo">Nova Palavra-Chave</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="formPalavra">
                    <input type="hidden" name="action" value="adicionar_palavra" id="palavraAction">
                    <input type="hidden" name="palavra_id" id="palavraId">
                    
                    <div class="form-group">
                        <label class="form-label">Palavra ou Frase-Chave</label>
                        <input type="text" name="palavra" id="palavraCampo" class="form-control" required placeholder="Ex: menu, horário, preço">
                        <small class="form-text text-muted">Palavra ou frase que o bot reconhecerá. Separe múltiplas palavras com vírgula.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Resposta Automática</label>
                        <textarea name="resposta" id="respostaCampo" class="form-control" rows="6" required placeholder="Digite a resposta que será enviada..."></textarea>
                        <small class="form-text text-muted">Use variáveis: {{palavra}}, {{data}}, {{hora}}, {{valor_promocao}}, {{pix}}, {{nome_banco}}, {{nome_titular}}, {{horario_inicio}}, {{horario_fim}}. O emoji padrão será adicionado automaticamente.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tempo de Resposta (segundos)</label>
                        <input type="number" name="tempo_resposta" id="tempoRespostaCampo" class="form-control" value="0" min="0">
                        <small class="form-text text-muted">Atraso antes de enviar a resposta (0 para imediato).</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" name="ativo" id="ativoCampo" class="form-check-input" checked>
                            <label class="form-check-label" for="ativoCampo">Ativar palavra-chave</label>
                        </div>
                    </div>
                    
                    <div style="text-align: right; margin-top: 1rem;">
                        <button type="button" onclick="fecharModalPalavra()" class="btn btn-secondary btn-sm">Cancelar</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="btnSalvarPalavra">
                            <i class="bi bi-check"></i> Salvar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarModalPalavra() {
    document.getElementById('modalPalavraTitulo').textContent = 'Nova Palavra-Chave';
    document.getElementById('palavraAction').value = 'adicionar_palavra';
    document.getElementById('palavraId').value = '';
    document.getElementById('palavraCampo').value = '';
    document.getElementById('respostaCampo').value = '';
    document.getElementById('tempoRespostaCampo').value = '0';
    document.getElementById('ativoCampo').checked = true;
    document.getElementById('btnSalvarPalavra').innerHTML = '<i class="bi bi-check"></i> Salvar';
    document.getElementById('modalPalavra').classList.add('active');
}

function editarPalavra(palavra) {
    document.getElementById('modalPalavraTitulo').textContent = 'Editar Palavra-Chave';
    document.getElementById('palavraAction').value = 'editar_palavra';
    document.getElementById('palavraId').value = palavra.id;
    document.getElementById('palavraCampo').value = palavra.keyword;
    
    // Remove emoji from response for editing
    const emoji = '<?= htmlspecialchars($autobot_config['emoji'] ?? '') ?>';
    let response = palavra.response;
    if (emoji && response.startsWith(emoji + ' ')) {
        response = response.substring(emoji.length + 1);
    }
    document.getElementById('respostaCampo').value = response;
    
    document.getElementById('tempoRespostaCampo').value = palavra.response_delay;
    document.getElementById('ativoCampo').checked = palavra.active;
    document.getElementById('btnSalvarPalavra').innerHTML = '<i class="bi bi-check"></i> Atualizar';
    document.getElementById('modalPalavra').classList.add('active');
}

function fecharModalPalavra() {
    document.getElementById('modalPalavra').classList.remove('active');
}

function deletarPalavra(id) {
    if (confirm('Tem certeza que deseja deletar esta palavra-chave?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="deletar_palavra">
            <input type="hidden" name="palavra_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function updateEmojiPreview() {
    const selector = document.getElementById('emojiSelector');
    const preview = document.getElementById('emojiPreview');
    preview.textContent = selector.value || 'Nenhum emoji selecionado';
}

function copyEmoji() {
    const emoji = document.getElementById('emojiSelector').value;
    if (emoji) {
        navigator.clipboard.writeText(emoji).then(() => {
            alert('Emoji copiado para a área de transferência!');
        }).catch(() => {
            alert('Erro ao copiar o emoji.');
        });
    } else {
        alert('Selecione um emoji primeiro!');
    }
}

function updateTemplatePreview() {
    const selector = document.getElementById('templateSelector');
    const preview = document.getElementById('templatePreview');
    const value = selector.value;
    if (!value) {
        preview.textContent = 'Selecione uma variável para ver o preview';
        return;
    }
    const optionText = selector.options[selector.selectedIndex].text;
    const previewText = optionText.split(' - ')[1] || value;
    preview.textContent = previewText;
}

function copyTemplate() {
    const template = document.getElementById('templateSelector').value;
    if (template) {
        navigator.clipboard.writeText(template).then(() => {
            alert(`Variável ${template} copiada para a área de transferência!`);
        }).catch(() => {
            alert('Erro ao copiar a variável.');
        });
    } else {
        alert('Selecione uma variável primeiro!');
    }
}

function toggleHorarioFields() {
    const horarioAtivo = document.getElementById('horarioAtivo').checked;
    document.getElementById('horarioFields').style.display = horarioAtivo ? 'block' : 'none';
}

// Inicializar previews
updateEmojiPreview();
updateTemplatePreview();

// Fechar modal ao clicar fora
document.getElementById('modalPalavra').addEventListener('click', function(e) {
    if (e.target === this) {
        fecharModalPalavra();
    }
});
</script>

<?php include 'includes/footer.php'; ?>