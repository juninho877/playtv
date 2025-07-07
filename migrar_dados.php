<?php
$page_title = 'Migrar Dados AutoBot';
include 'includes/auth.php';
verificarLogin();

$sucesso = '';
$erro = '';

// Função para migrar dados do sistema antigo
if (isset($_POST['migrar_dados'])) {
    try {
        $migrados = 0;
        
        // Verificar se existe o arquivo antigo
        if (file_exists('data/auto_bot_rules.json')) {
            $old_rules = json_decode(file_get_contents('data/auto_bot_rules.json'), true) ?: [];
            
            // Carregar palavras-chave atuais
            $palavras_chave = json_decode(file_get_contents('data/palavras_chave.json'), true) ?: [];
            
            foreach ($old_rules as $id => $rule) {
                // Verificar se já existe uma palavra-chave com o mesmo texto
                $existe = false;
                foreach ($palavras_chave as $palavra) {
                    if (strtolower($palavra['palavra']) === strtolower($rule['keyword'])) {
                        $existe = true;
                        break;
                    }
                }
                
                if (!$existe) {
                    $palavras_chave[] = [
                        'id' => $id,
                        'palavra' => $rule['keyword'],
                        'resposta' => $rule['response'],
                        'ativo' => $rule['enabled'] ?? true,
                        'contador' => 0,
                        'criado_em' => date('Y-m-d H:i:s')
                    ];
                    $migrados++;
                }
            }
            
            // Salvar palavras-chave atualizadas
            file_put_contents('data/palavras_chave.json', json_encode($palavras_chave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Fazer backup do arquivo antigo
            if ($migrados > 0) {
                copy('data/auto_bot_rules.json', 'data/auto_bot_rules.json.backup');
            }
            
            $sucesso = "Migração concluída! $migrados palavras-chave foram migradas.";
        } else {
            $erro = "Arquivo auto_bot_rules.json não encontrado.";
        }
        
    } catch (Exception $e) {
        $erro = "Erro durante a migração: " . $e->getMessage();
    }
}

// Função para criar palavras-chave padrão
if (isset($_POST['criar_padrao'])) {
    try {
        $palavras_chave = json_decode(file_get_contents('data/palavras_chave.json'), true) ?: [];
        
        $palavras_padrao = [
            [
                'palavra' => 'olá,oi,hello,hi',
                'resposta' => 'Olá! 👋 Seja bem-vindo(a)!\n\nComo posso ajudá-lo(a) hoje?\n\nDigite *MENU* para ver nossas opções.'
            ],
            [
                'palavra' => 'menu,cardápio,opções',
                'resposta' => '📋 *MENU PRINCIPAL*\n\n1️⃣ Produtos\n2️⃣ Preços\n3️⃣ Horário de Funcionamento\n4️⃣ Localização\n5️⃣ Falar com Atendente\n\nDigite o número da opção desejada.'
            ],
            [
                'palavra' => 'preço,valor,quanto custa',
                'resposta' => '💰 *PREÇOS*\n\nPara informações sobre preços, por favor me informe qual produto você tem interesse.\n\nOu digite *MENU* para ver todas as opções.'
            ],
            [
                'palavra' => 'horário,funcionamento,aberto,funciona',
                'resposta' => '🕐 *HORÁRIO DE FUNCIONAMENTO*\n\n📅 Segunda a Sexta: 8h às 18h\n📅 Sábado: 8h às 12h\n📅 Domingo: Fechado\n\nEstamos prontos para atendê-lo(a)!'
            ],
            [
                'palavra' => 'localização,endereço,onde fica',
                'resposta' => '📍 *NOSSA LOCALIZAÇÃO*\n\nRua das Flores, 123\nCentro - Cidade/UF\nCEP: 12345-678\n\n🚗 Temos estacionamento gratuito\n🚌 Próximo ao ponto de ônibus central'
            ],
            [
                'palavra' => 'atendente,humano,pessoa',
                'resposta' => '👨‍💼 Vou transferir você para um de nossos atendentes.\n\nPor favor, aguarde alguns instantes que logo alguém irá lhe atender pessoalmente.\n\nObrigado pela preferência! 😊'
            ]
        ];
        
        $adicionados = 0;
        
        foreach ($palavras_padrao as $palavra_padrao) {
            // Verificar se já existe
            $existe = false;
            foreach ($palavras_chave as $palavra) {
                if (stripos($palavra['palavra'], explode(',', $palavra_padrao['palavra'])[0]) !== false) {
                    $existe = true;
                    break;
                }
            }
            
            if (!$existe) {
                $palavras_chave[] = [
                    'id' => time() . rand(100, 999),
                    'palavra' => $palavra_padrao['palavra'],
                    'resposta' => $palavra_padrao['resposta'],
                    'ativo' => true,
                    'contador' => 0,
                    'criado_em' => date('Y-m-d H:i:s')
                ];
                $adicionados++;
            }
        }
        
        file_put_contents('data/palavras_chave.json', json_encode($palavras_chave, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $sucesso = "Palavras-chave padrão criadas! $adicionados palavras foram adicionadas.";
        
    } catch (Exception $e) {
        $erro = "Erro ao criar palavras padrão: " . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<?php if ($sucesso): ?>
<div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<?php if ($erro): ?>
<div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-arrow-repeat"></i>
            Migração de Dados do AutoBot
        </h3>
    </div>
    <div class="card-body">
        <p>Esta página ajuda você a migrar dados do sistema antigo para o novo formato do AutoBot.</p>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-upload"></i> Migrar Dados Antigos</h5>
                    </div>
                    <div class="card-body">
                        <p>Se você tinha um sistema AutoBot anterior, clique abaixo para migrar as palavras-chave.</p>
                        
                        <?php if (file_exists('data/auto_bot_rules.json')): ?>
                        <div class="alert alert-info">
                            <strong>✅ Arquivo encontrado:</strong> auto_bot_rules.json
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="migrar_dados" class="btn btn-primary">
                                <i class="bi bi-arrow-repeat"></i> Migrar Dados
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-warning">
                            <strong>⚠️ Arquivo não encontrado:</strong> auto_bot_rules.json
                        </div>
                        <p>Não há dados antigos para migrar.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="bi bi-plus-circle"></i> Criar Palavras Padrão</h5>
                    </div>
                    <div class="card-body">
                        <p>Crie um conjunto de palavras-chave padrão para começar rapidamente.</p>
                        
                        <div class="alert alert-info">
                            <strong>Incluí:</strong> Saudações, Menu, Preços, Horários, Localização, Atendente
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="criar_padrao" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Criar Palavras Padrão
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-info-circle"></i>
            Status dos Arquivos
        </h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Arquivo</th>
                        <th>Status</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $arquivos = [
                        'data/auto_bot_rules.json' => 'Sistema antigo (para migração)',
                        'data/autobot_config.json' => 'Configurações do novo AutoBot',
                        'data/palavras_chave.json' => 'Palavras-chave do novo sistema',
                        'data/conversas.json' => 'Histórico de conversas'
                    ];
                    
                    foreach ($arquivos as $arquivo => $descricao):
                        $existe = file_exists($arquivo);
                        $status = $existe ? '<span class="text-success">✅ Existe</span>' : '<span class="text-danger">❌ Não existe</span>';
                    ?>
                    <tr>
                        <td><code><?= $arquivo ?></code></td>
                        <td><?= $status ?></td>
                        <td><?= $descricao ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-lightbulb"></i>
            Próximos Passos
        </h3>
    </div>
    <div class="card-body">
        <ol>
            <li><strong>Configurar WhatsApp:</strong> Vá em <a href="configuracoes.php">Configurações</a> e configure sua instância Evolution API</li>
            <li><strong>Ativar AutoBot:</strong> Acesse <a href="autobot.php">AutoBot</a> e ative o sistema</li>
            <li><strong>Configurar Webhook:</strong> Configure o webhook na sua instância Evolution API</li>
            <li><strong>Testar Sistema:</strong> Use <a href="debug_bot.php">Debug Bot</a> para testar</li>
        </ol>
        
        <div class="alert alert-success">
            <strong>💡 Dica:</strong> Após a migração, você pode deletar o arquivo <code>auto_bot_rules.json</code> antigo, pois um backup será criado automaticamente.
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>