
<?php
$page_title = 'Criar Enquetes';
include 'includes/auth.php';
verificarLogin();

$bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];

// Processar envio de enquete
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'enviar_enquete') {
    $pergunta = $_POST['pergunta'];
    $opcoes = array_filter([$_POST['opcao1'], $_POST['opcao2'], $_POST['opcao3'], $_POST['opcao4']]);
    $bot_id = $_POST['bot_id'];
    $destino = $_POST['destino'];
    
    // Encontrar bot
    $bot_selecionado = null;
    foreach ($bots as $bot) {
        if ($bot['id'] == $bot_id) {
            $bot_selecionado = $bot;
            break;
        }
    }
    
    if ($bot_selecionado && $bot_selecionado['tipo'] == 'telegram') {
        // Montar enquete para Telegram
        $url = "https://api.telegram.org/bot" . $bot_selecionado['token'] . "/sendPoll";
        
        $data = [
            'chat_id' => $destino,
            'question' => $pergunta,
            'options' => json_encode($opcoes),
            'is_anonymous' => false,
            'allows_multiple_answers' => false
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $response_data = json_decode($response, true);
        curl_close($ch);
        
        if ($response_data['ok'] ?? false) {
            // Log
            $logs = json_decode(file_get_contents('data/logs.json'), true) ?: [];
            $logs[] = [
                'id' => time(),
                'data_hora' => date('Y-m-d H:i:s'),
                'destino' => $destino,
                'bot' => $bot_selecionado['nome'],
                'tipo' => 'Enquete',
                'mensagem' => $pergunta,
                'status' => 'sucesso'
            ];
            file_put_contents('data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));
            
            $sucesso = "Enquete enviada com sucesso!";
        } else {
            $erro = "Erro ao enviar enquete: " . ($response_data['description'] ?? 'Erro desconhecido');
        }
    } else {
        $erro = "Enquetes só funcionam com bots do Telegram!";
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

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-bar-chart"></i>
            Criar Enquete (Telegram)
        </h3>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>Atenção:</strong> Enquetes funcionam apenas no Telegram. Para WhatsApp, use mensagens com opções numeradas.
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="enviar_enquete">
            
            <div class="form-group">
                <label class="form-label">Bot do Telegram</label>
                <select name="bot_id" class="form-select" required>
                    <option value="">Escolha um bot...</option>
                    <?php foreach ($bots as $bot): ?>
                        <?php if ($bot['ativo'] && $bot['tipo'] == 'telegram'): ?>
                        <option value="<?= $bot['id'] ?>">
                            <?= $bot['nome'] ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Destino (Chat ID ou @username)</label>
                <input type="text" name="destino" class="form-control" placeholder="Ex: @meucanal ou -1001234567890" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Pergunta da Enquete</label>
                <input type="text" name="pergunta" class="form-control" placeholder="Ex: Qual seu filme favorito?" required maxlength="300">
            </div>
            
            <div class="form-group">
                <label class="form-label">Opção 1</label>
                <input type="text" name="opcao1" class="form-control" placeholder="Ex: Ação" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label class="form-label">Opção 2</label>
                <input type="text" name="opcao2" class="form-control" placeholder="Ex: Comédia" required maxlength="100">
            </div>
            
            <div class="form-group">
                <label class="form-label">Opção 3 (opcional)</label>
                <input type="text" name="opcao3" class="form-control" placeholder="Ex: Drama" maxlength="100">
            </div>
            
            <div class="form-group">
                <label class="form-label">Opção 4 (opcional)</label>
                <input type="text" name="opcao4" class="form-control" placeholder="Ex: Terror" maxlength="100">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Enviar Enquete
            </button>
            
            <button type="button" class="btn btn-secondary" onclick="limparFormulario()">
                <i class="bi bi-arrow-clockwise"></i> Limpar
            </button>
        </form>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-lightbulb"></i>
            Dicas para WhatsApp
        </h3>
    </div>
    <div class="card-body">
        <p>Para criar "enquetes" no WhatsApp, use mensagens com opções numeradas:</p>
        <div class="row">
            <div class="col-md-6">
                <strong>Exemplo de enquete para WhatsApp:</strong>
                <pre style="background: #f8f9fa; padding: 1rem; border-radius: 5px; font-size: 0.9rem;">📊 ENQUETE: Qual seu gênero de filme favorito?

1️⃣ Ação
2️⃣ Comédia  
3️⃣ Drama
4️⃣ Terror

👆 Responda com o número da sua escolha!</pre>
            </div>
            <div class="col-md-6">
                <strong>Use na página "Enviar":</strong>
                <ul>
                    <li>Vá para a página "Enviar"</li>
                    <li>Escolha um bot do WhatsApp</li>
                    <li>Cole o texto da enquete</li>
                    <li>Envie normalmente</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function limparFormulario() {
    document.querySelector('form').reset();
}
</script>

<?php include 'includes/footer.php'; ?>
