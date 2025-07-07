<?php
$page_title = 'AutoBot WhatsApp';
include 'includes/auth.php';
verificarLogin();

// Iniciar sessão para CSRF
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Carregar configurações
$config = json_decode(file_get_contents('data/config.json'), true) ?: [];

// Inicializar configurações se não existirem
if (!file_exists('data/autobot_config.json')) {
    $initial_config = [
        'ativo' => false,
        'emoji' => '👋',
        'saudacao' => 'Olá! Seja bem-vindo(a)! Como posso ajudá-lo(a) hoje?',
        'mensagem_padrao' => 'Desculpe, não entendi sua mensagem. Digite *MENU* para ver as opções disponíveis ou aguarde que em breve um atendente irá lhe responder.',
        'mensagem_fora_horario' => 'Estamos fora do horário de atendimento. Nosso horário é de {{horario_inicio}} às {{horario_fim}}. Retornaremos em breve!',
        'horario_ativo' => false,
        'horario_inicio' => '08:00',
        'horario_fim' => '18:00',
        'tempo_inatividade' => 300,
        'estatisticas' => [
            'mensagens_respondidas' => 0,
            'conversas_iniciadas' => 0,
            'palavras_ativadas' => 0,
            'pessoas_respondidas' => 0
        ]
    ];
    if (is_writable('data/')) {
        file_put_contents('data/autobot_config.json', json_encode($initial_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        $erro = 'Sem permissão para gravar em data/autobot_config.json!';
    }
}

if (!file_exists('data/palavras_chave.json')) {
    if (is_writable('data/')) {
        file_put_contents('data/palavras_chave.json', json_encode([], JSON_PRETTY_PRINT));
    } else {
        $erro = 'Sem permissão para gravar em data/palavras_chave.json!';
    }
}

if (!file_exists('data/conversas.json')) {
    if (is_writable('data/')) {
        file_put_contents('data/conversas.json', json_encode([], JSON_PRETTY_PRINT));
    } else {
        $erro = 'Sem permissão para gravar em data/conversas.json!';
    }
}

if (!file_exists('data/variaveis.json')) {
    $initial_variaveis = [
        'valor_promocao' => 'R$ 99,90',
        'pix' => 'chave-pix@exemplo.com',
        'nome_banco' => 'Banco Exemplo',
        'nome_titular' => 'João Silva'
    ];
    if (is_writable('data/')) {
        file_put_contents('data/variaveis.json', json_encode($initial_variaveis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    } else {
        $erro = 'Sem permissão para gravar em data/variaveis.json!';
    }
}

$autobot_config = json_decode(file_get_contents('data/autobot_config.json'), true) ?: [
    'ativo' => false,
    'emoji' => '👋',
    'saudacao' => 'Olá! Seja bem-vindo(a)! Como posso ajudá-lo(a) hoje?',
    'mensagem_padrao' => 'Desculpe, não entendi sua mensagem. Digite *MENU* para ver as opções disponíveis ou aguarde que em breve um atendente irá lhe responder.',
    'mensagem_fora_horario' => 'Estamos fora do horário de atendimento. Nosso horário é de {{horario_inicio}} às {{horario_fim}}. Retornaremos em breve!',
    'horario_ativo' => false,
    'horario_inicio' => '08:00',
    'horario_fim' => '18:00',
    'tempo_inatividade' => 300,
    'estatisticas' => [
        'mensagens_respondidas' => 0,
        'conversas_iniciadas' => 0,
        'palavras_ativadas' => 0,
        'pessoas_respondidas' => 0
    ]
];
$palavras_chave = json_decode(file_get_contents('data/palavras_chave.json'), true) ?: [];
$conversas = json_decode(file_get_contents('data/conversas.json'), true) ?: [];
$variaveis = json_decode(file_get_contents('data/variaveis.json'), true) ?: [];

// Calcular número de pessoas únicas respondidas
$autobot_config['estatisticas']['pessoas_respondidas'] = count(array_unique(array_column($conversas, 'numero')));
file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Migrar dados do sistema antigo se existir
if (file_exists('data/auto_bot_rules.json') && empty($palavras_chave)) {
    $old_rules = json_decode(file_get_contents('data/auto_bot_rules.json'), true) ?: [];
    $new_palavras = [];
    
    foreach ($old_rules as $id => $rule) {
        $new_palavras[] = [
            'id' => $id,
            'palavra' => $rule['keyword'],
            'resposta' => $rule['response'],
            'ativo' => $rule['enabled'] ?? true,
            'contador' => 0,
            'tempo_resposta' => 0,
            'criado_em' => date('Y-m-d H:i:s')
        ];
    }
    
    if (is_writable('data/')) {
        file_put_contents('data/palavras_chave.json', json_encode($new_palavras, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $palavras_chave = $new_palavras;
    } else {
        $erro = 'Sem permissão para gravar em data/palavras_chave.json!';
    }
}

// Processar ações
if ($_POST && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_bot':
                $autobot_config['ativo'] = !$autobot_config['ativo'];
                if (is_writable('data/autobot_config.json')) {
                    file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    file_put_contents('data/webhook.log', "[" . date('Y-m-d H:i:s') . "] Bot " . ($autobot_config['ativo'] ? 'ativado' : 'desativado') . " por usuário\n", FILE_APPEND);
                    $sucesso = $autobot_config['ativo'] ? 'Bot ativado com sucesso!' : 'Bot desativado com sucesso!';
                } else {
                    $erro = 'Sem permissão para gravar em data/autobot_config.json!';
                }
                break;
                
            case 'salvar_configuracoes':
                $emoji = filter_input(INPUT_POST, 'emoji', FILTER_SANITIZE_STRING);
                $saudacao = filter_input(INPUT_POST, 'saudacao', FILTER_SANITIZE_STRING);
                $mensagem_padrao = filter_input(INPUT_POST, 'mensagem_padrao', FILTER_SANITIZE_STRING);
                $mensagem_fora_horario = filter_input(INPUT_POST, 'mensagem_fora_horario', FILTER_SANITIZE_STRING);
                $horario_ativo = isset($_POST['horario_ativo']);
                $horario_inicio = filter_input(INPUT_POST, 'horario_inicio', FILTER_SANITIZE_STRING);
                $horario_fim = filter_input(INPUT_POST, 'horario_fim', FILTER_SANITIZE_STRING);
                $tempo_inatividade_valor = filter_input(INPUT_POST, 'tempo_inatividade_valor', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                $tempo_inatividade_unidade = filter_input(INPUT_POST, 'tempo_inatividade_unidade', FILTER_SANITIZE_STRING);
                
                if (!$saudacao || !$mensagem_padrao || !$tempo_inatividade_valor) {
                    $erro = 'Campos obrigatórios (saudação, mensagem padrão, tempo de inatividade) não preenchidos!';
                    break;
                }
                
                if ($horario_ativo && (!$horario_inicio || !$horario_fim || !$mensagem_fora_horario)) {
                    $erro = 'Preencha todos os campos de horário (início, fim e mensagem fora de horário) quando ativar o horário de funcionamento!';
                    break;
                }
                
                if ($horario_ativo && (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $horario_inicio) || !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $horario_fim))) {
                    $erro = 'Horários de início ou fim inválidos! Use o formato HH:MM.';
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
                
                $autobot_config['emoji'] = $emoji;
                $autobot_config['saudacao'] = $emoji . ' ' . $saudacao;
                $autobot_config['mensagem_padrao'] = $emoji . ' ' . $mensagem_padrao;
                $autobot_config['mensagem_fora_horario'] = $mensagem_fora_horario ? ($emoji . ' ' . $mensagem_fora_horario) : $autobot_config['mensagem_fora_horario'];
                $autobot_config['horario_ativo'] = $horario_ativo;
                $autobot_config['horario_inicio'] = $horario_inicio ?: $autobot_config['horario_inicio'];
                $autobot_config['horario_fim'] = $horario_fim ?: $autobot_config['horario_fim'];
                $autobot_config['tempo_inatividade'] = $tempo_inatividade;
                
                if (is_writable('data/autobot_config.json')) {
                    file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    file_put_contents('data/webhook.log', "[" . date('Y-m-d H:i:s') . "] Configurações do bot atualizadas\n", FILE_APPEND);
                    $sucesso = 'Configurações salvas com sucesso!';
                } else {
                    $erro = 'Sem permissão para gravar em data/autobot_config.json!';
                }
                break;
                
            case 'salvar_variaveis':
                $variaveis['valor_promocao'] = filter_input(INPUT_POST, 'valor_promocao', FILTER_SANITIZE_STRING) ?: 'R$ 99,90';
                $variaveis['pix'] = filter_input(INPUT_POST, 'pix', FILTER_SANITIZE_STRING) ?: 'chave-pix@exemplo.com';
                $variaveis['nome_banco'] = filter_input(INPUT_POST, 'nome_banco', FILTER_SANITIZE_STRING) ?: 'Banco Exemplo';
                $variaveis['nome_titular'] = filter_input(INPUT_POST, 'nome_titular', FILTER_SANITIZE_STRING) ?: 'João Silva';
                if (is_writable('data/variaveis.json')) {
                    file_put_contents('data/variaveis.json', json_encode($variaveis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    file_put_contents('data/webhook.log', "[" . date('Y-m-d H:i:s') . "] Variáveis dinâmicas atualizadas\n", FILE_APPEND);
                    $sucesso = 'Variáveis dinâmicas salvas com sucesso!';
                } else {
                    $erro = 'Sem permissão para gravar em data/variaveis.json!';
                }
                break;
                
            case 'adicionar_palavra':
                $palavra = trim(filter_input(INPUT_POST, 'palavra', FILTER_SANITIZE_STRING));
                $resposta = filter_input(INPUT_POST, 'resposta', FILTER_SANITIZE_STRING);
                $tempo_resposta = filter_input(INPUT_POST, 'tempo_resposta', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]);
                if (!$palavra || !$resposta) {
                    $erro = 'Palavra ou resposta inválida!';
                    break;
                }
                $nova_palavra = [
                    'id' => time() . rand(100, 999),
                    'palavra' => $palavra,
                    'resposta' => $autobot_config['emoji'] . ' ' . $resposta,
                    'ativo' => isset($_POST['ativo']) ? true : false,
                    'contador' => 0,
                    'tempo_resposta' => $tempo_resposta,
                    'criado_em' => date('Y-m-d H:i:s')
                ];
                $palavras_chave[] = $nova_palavra;
                if (is_writable('data/palavras_chave.json')) {
                    file_put_contents('data/palavras_chave.json', json_encode($palavras_chave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $autobot_config['estatisticas']['palavras_ativadas'] = count(array_filter($palavras_chave, fn($p) => $p['ativo']));
                    file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    file_put_contents('data/webhook.log', "[" . date('Y-m-d H:i:s') . "] Palavra-chave adicionada: {$palavra}\n", FILE_APPEND);
                    $sucesso = 'Palavra-chave adicionada com sucesso!';
                } else {
                    $erro = 'Sem permissão para gravar em data/palavras_chave.json!';
                }
                break;
                
            case 'editar_palavra':
                $palavra_id = filter_input(INPUT_POST, 'palavra_id', FILTER_SANITIZE_STRING);
                $palavra = trim(filter_input(INPUT_POST, 'palavra', FILTER_SANITIZE_STRING));
                $resposta = filter_input(INPUT_POST, 'resposta', FILTER_SANITIZE_STRING);
                $tempo_resposta = filter_input(INPUT_POST, 'tempo_resposta', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]);
                if (!$palavra_id || !$palavra || !$resposta) {
                    $erro = 'Campos inválidos!';
                    break;
                }
                foreach ($palavras_chave as &$p) {
                    if ($p['id'] == $palavra_id) {
                        $p['palavra'] = $palavra;
                        $p['resposta'] = $autobot_config['emoji'] . ' ' . $resposta;
                        $p['tempo_resposta'] = $tempo_resposta;
                        break;
                    }
                }
                if (is_writable('data/palavras_chave.json')) {
                    file_put_contents('data/palavras_chave.json', json_encode($palavras_chave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    file_put_contents('data/webhook.log', "[" . date('Y-m-d H:i:s') . "] Palavra-chave editada: {$palavra}\n", FILE_APPEND);
                    $sucesso = 'Palavra-chave editada com sucesso!';
                } else {
                    $erro = 'Sem permissão para gravar em data/palavras_chave.json!';
                }
                break;
                
            case 'toggle_palavra':
                $palavra_id = filter_input(INPUT_POST, 'palavra_id', FILTER_SANITIZE_STRING);
                if (!$palavra_id) {
                    $erro = 'ID de palavra inválido!';
                    break;
                }
                foreach ($palavras_chave as &$p) {
                    if ($p['id'] == $palavra_id) {
                        $p['ativo'] = !$p['ativo'];
                        file_put_contents('data/webhook.log', "[" . date('Y-m-d H:i:s') . "] Palavra-chave {$p['palavra']} " . ($p['ativo'] ? 'ativada' : 'desativada') . "\n", FILE_APPEND);
                        break;
                    }
                }
                if (is_writable('data/palavras_chave.json')) {
                    file_put_contents('data/palavras_chave.json', json_encode($palavras_chave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $autobot_config['estatisticas']['palavras_ativadas'] = count(array_filter($palavras_chave, fn($p) => $p['ativo']));
                    file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } else {
                    $erro = 'Sem permissão para gravar em data/palavras_chave.json!';
                }
                break;
                
            case 'deletar_palavra':
                $palavra_id = filter_input(INPUT_POST, 'palavra_id', FILTER_SANITIZE_STRING);
                if (!$palavra_id) {
                    $erro = 'ID de palavra inválido!';
                    break;
                }
                $palavras_chave = array_values(array_filter($palavras_chave, fn($p) => $p['id'] != $palavra_id));
                if (is_writable('data/palavras_chave.json')) {
                    file_put_contents('data/palavras_chave.json', json_encode($palavras_chave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $autobot_config['estatisticas']['palavras_ativadas'] = count(array_filter($palavras_chave, fn($p) => $p['ativo']));
                    file_put_contents('data/autobot_config.json', json_encode($autobot_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    file_put_contents('data/webhook.log', "[" . date('Y-m-d H:i:s') . "] Palavra-chave ID {$palavra_id} deletada\n", FILE_APPEND);
                    $sucesso = 'Palavra-chave deletada com sucesso!';
                } else {
                    $erro = 'Sem permissão para gravar em data/palavras_chave.json!';
                }
                break;
        }
    }
} elseif ($_POST) {
    $erro = 'Token CSRF inválido!';
}

// Verificar se WhatsApp está configurado
$whatsapp_configurado = !empty($config['whatsapp']['server']) && !empty($config['whatsapp']['instance']) && !empty($config['whatsapp']['apikey']);

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

<!-- Inline CSS for custom components not in assets/style.css -->
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

<?php if (isset($sucesso)): ?>
<div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<?php if (isset($erro)): ?>
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
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <label class="autobot-toggle">
                    <input type="checkbox" <?= $autobot_config['ativo'] ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="slider"></span>
                </label>
                <span style="margin-left: 1rem;">
                    <span class="bot-status <?= $autobot_config['ativo'] ? 'bot-ativo' : 'bot-inativo' ?>">
                        🤖 Bot <?= $autobot_config['ativo'] ? 'ATIVO' : 'INATIVO' ?>
                    </span>
                </span>
            </form>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="cards-grid">
        <div class="stats-card">
            <div class="stats-number"><?= $autobot_config['estatisticas']['mensagens_respondidas'] ?></div>
            <div>Mensagens Respondidas</div>
            <small class="text-light">Total de mensagens automáticas enviadas pelo bot.</small>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?= $autobot_config['estatisticas']['conversas_iniciadas'] ?></div>
            <div>Conversas Iniciadas</div>
            <small class="text-light">Número de novos contatos que iniciaram uma conversa.</small>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?= $autobot_config['estatisticas']['pessoas_respondidas'] ?></div>
            <div>Pessoas Respondidas</div>
            <small class="text-light">Número de contatos únicos que receberam respostas do bot.</small>
        </div>
        <div class="stats-card">
            <div class="stats-number"><?= $autobot_config['estatisticas']['palavras_ativadas'] ?></div>
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
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-group">
                    <label class="form-label">Emoji Padrão</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <select name="emoji" id="emojiSelector" class="form-select" onchange="updateEmojiPreview()">
                            <option value="">Nenhum</option>
                            <?php foreach ($emojis as $emoji => $desc): ?>
                            <option value="<?= htmlspecialchars($emoji) ?>" <?= $autobot_config['emoji'] === $emoji ? 'selected' : '' ?>><?= htmlspecialchars($emoji . ' ' . $desc) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="copyEmoji()">
                            <i class="bi bi-clipboard"></i> Copiar Emoji
                        </button>
                    </div>
                    <small class="form-text text-muted">Escolha um emoji para usar em todas as mensagens automáticas. Clique em "Copiar Emoji" para usá-lo manualmente.</small>
                    <div id="emojiPreview" style="margin-top: 0.5rem; font-size: 1.5rem;"><?= htmlspecialchars($autobot_config['emoji'] ?: 'Nenhum emoji selecionado') ?></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mensagem de Saudação</label>
                    <textarea name="saudacao" class="form-control" rows="5" required><?= htmlspecialchars(preg_replace('/^' . implode('|', array_keys($emojis)) . '\s*/u', '', $autobot_config['saudacao'])) ?></textarea>
                    <small class="form-text text-muted">Enviada quando um usuário inicia uma conversa ou após o tempo de inatividade, dentro do horário de funcionamento (se ativo). O emoji selecionado será adicionado automaticamente.</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Mensagem Padrão</label>
                    <textarea name="mensagem_padrao" class="form-control" rows="5" required><?= htmlspecialchars(preg_replace('/^' . implode('|', array_keys($emojis)) . '\s*/u', '', $autobot_config['mensagem_padrao'])) ?></textarea>
                    <small class="form-text text-muted">Enviada quando nenhuma palavra-chave é encontrada, dentro do horário de funcionamento (se ativo). O emoji selecionado será adicionado automaticamente.</small>
                </div>
                
                <div class="form-group">
                    <div class="form-check">
                        <input type="checkbox" name="horario_ativo" id="horarioAtivo" class="form-check-input" <?= $autobot_config['horario_ativo'] ? 'checked' : '' ?> onchange="toggleHorarioFields()">
                        <label class="form-check-label" for="horarioAtivo">Ativar Horário de Funcionamento</label>
                    </div>
                    <small class="form-text text-muted">Habilite para definir um horário de atendimento. Fora desse horário, a mensagem fora de horário será enviada.</small>
                </div>
                
                <div id="horarioFields" style="display: <?= $autobot_config['horario_ativo'] ? 'block' : 'none' ?>;">
                    <div class="form-group">
                        <label class="form-label">Mensagem Fora de Horário</label>
                        <textarea name="mensagem_fora_horario" class="form-control" rows="5"><?= htmlspecialchars(preg_replace('/^' . implode('|', array_keys($emojis)) . '\s*/u', '', $autobot_config['mensagem_fora_horario'])) ?></textarea>
                        <small class="form-text text-muted">Enviada quando uma mensagem é recebida fora do horário de funcionamento. Use {{horario_inicio}} e {{horario_fim}}. O emoji selecionado será adicionado automaticamente.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Horário de Funcionamento</label>
                        <div style="display: flex; gap: 1rem;">
                            <div style="flex: 1;">
                                <input type="time" name="horario_inicio" class="form-control" value="<?= htmlspecialchars($autobot_config['horario_inicio']) ?>">
                                <small class="form-text text-muted">Início (ex.: 08:00)</small>
                            </div>
                            <div style="flex: 1;">
                                <input type="time" name="horario_fim" class="form-control" value="<?= htmlspecialchars($autobot_config['horario_fim']) ?>">
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
                            <input type="number" name="tempo_inatividade_valor" class="form-control" value="<?= max(1, floor($autobot_config['tempo_inatividade'] / ($autobot_config['tempo_inatividade'] >= 3600 ? 3600 : ($autobot_config['tempo_inatividade'] >= 60 ? 60 : 1)))) ?>" min="1" required>
                            <small class="form-text text-muted">Valor numérico (mínimo: 1).</small>
                        </div>
                        <div style="flex: 1;">
                            <select name="tempo_inatividade_unidade" class="form-select">
                                <option value="segundos" <?= $autobot_config['tempo_inatividade'] < 60 ? 'selected' : '' ?>>Segundos</option>
                                <option value="minutos" <?= $autobot_config['tempo_inatividade'] >= 60 && $autobot_config['tempo_inatividade'] < 3600 ? 'selected' : '' ?>>Minutos</option>
                                <option value="horas" <?= $autobot_config['tempo_inatividade'] >= 3600 ? 'selected' : '' ?>>Horas</option>
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
                        <option value="{{horario_inicio}}">{{horario_inicio}} - Horário de início (ex.: <?= htmlspecialchars($autobot_config['horario_inicio'] ?? '08:00') ?>)</option>
                        <option value="{{horario_fim}}">{{horario_fim}} - Horário de fim (ex.: <?= htmlspecialchars($autobot_config['horario_fim'] ?? '18:00') ?>)</option>
                        <option value="{{nome}}">{{nome}} - Nome do usuário (ex.: João)</option>
                        <option value="{{numero}}">{{numero}} - Número do usuário (ex.: 5599999999999)</option>
                        <option value="{{saudacao}}">{{saudacao}} - Saudação do momento (ex.: Bom dia / Boa tarde / Boa noite)</option>
                        <option value="{{site}}">{{site}} - Site da empresa (ex.: <?= htmlspecialchars($variaveis['site'] ?? 'https://example.com') ?>)</option>
                        <option value="{{instagram}}">{{instagram}} - Instagram (ex.: <?= htmlspecialchars($variaveis['instagram'] ?? '@suaempresa') ?>)</option>
                        <option value="{{whatsapp_grupo}}">{{whatsapp_grupo}} - Link para grupo do WhatsApp (ex.: <?= htmlspecialchars($variaveis['whatsapp_grupo'] ?? 'https://chat.whatsapp.com/xxxx') ?>)</option>
                        <option value="{{mensagem_aleatoria}}">{{mensagem_aleatoria}} - Frase ou versículo motivacional aleatório</option>
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
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
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
                <div class="palavra-item <?= $palavra['ativo'] ? 'palavra-ativa' : 'palavra-inativa' ?>">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <h5 style="margin: 0; color: <?= $palavra['ativo'] ? '#28a745' : '#dc3545' ?>;">
                                <?= htmlspecialchars($palavra['palavra']) ?>
                                <?= $palavra['ativo'] ? '✅' : '❌' ?>
                            </h5>
                            <p style="margin: 0.5rem 0; color: #666;">
                                <?= nl2br(htmlspecialchars(substr($palavra['resposta'], 0, 100))) ?><?= strlen($palavra['resposta']) > 100 ? '...' : '' ?>
                            </p>
                            <small class="text-muted">
                                Usada <?= $palavra['contador'] ?> vezes • Tempo de resposta: <?= $palavra['tempo_resposta'] ?>s • Criada em <?= date('d/m/Y H:i', strtotime($palavra['criado_em'])) ?>
                            </small>
                        </div>
                        <div style="margin-left: 1rem; display: flex; gap: 0.5rem;">
                            <button class="btn btn-sm btn-outline-primary" onclick="editarPalavra(<?= htmlspecialchars(json_encode($palavra)) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_palavra">
                                <input type="hidden" name="palavra_id" value="<?= $palavra['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <button type="submit" class="btn btn-sm <?= $palavra['ativo'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                    <i class="bi bi-<?= $palavra['ativo'] ? 'pause' : 'play' ?>"></i>
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
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
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
    document.getElementById('palavraCampo').value = palavra.palavra;
    document.getElementById('respostaCampo').value = palavra.resposta.replace(/^[\u{1F300}-\u{1F5FF}\u{1F600}-\u{1F64F}\u{1F680}-\u{1F6FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]/u, '').trim();
    document.getElementById('tempoRespostaCampo').value = palavra.tempo_resposta;
    document.getElementById('ativoCampo').checked = palavra.ativo;
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
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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