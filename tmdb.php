<?php
$page_title = 'TMDB - Filmes e Séries';
include 'includes/auth.php';
verificarLogin();

$config = json_decode(file_get_contents('data/config.json'), true) ?: [];
$tmdb_key = $config['tmdb_key'] ?? '';

// Carregar grupos do usuário
$grupos_file = 'data/grupos.json';
$grupos = [];
if (file_exists($grupos_file)) {
    $grupos = json_decode(file_get_contents($grupos_file), true) ?: [];
    if (!is_array($grupos)) {
        $grupos = [];
    }
}
$meus_grupos = array_filter($grupos, function($grupo) {
    return isset($grupo['user_id']) && $grupo['user_id'] === $_SESSION['user_id'];
});

$filmes = [];
$series = [];
$erro = '';
$sucesso = '';
$erro_envio_msg = '';

if (!empty($tmdb_key) && $tmdb_key != 'SUA_CHAVE_TMDB') {
    $url_filmes = "https://api.themoviedb.org/3/trending/movie/week?api_key=" . $tmdb_key . "&language=pt-BR";
    $response_filmes = @file_get_contents($url_filmes);
    
    if ($response_filmes) {
        $data_filmes = json_decode($response_filmes, true);
        $filmes = array_slice($data_filmes['results'] ?? [], 0, 5);
    }
    
    $url_series = "https://api.themoviedb.org/3/trending/tv/week?api_key=" . $tmdb_key . "&language=pt-BR";
    $response_series = @file_get_contents($url_series);
    
    if ($response_series) {
        $data_series = json_decode($response_series, true);
        $series = array_slice($data_series['results'] ?? [], 0, 5);
    }
    
    if (empty($filmes) && empty($series)) {
        $erro = "Erro ao carregar dados do TMDB. Verifique sua chave de API.";
    }
} else {
    $erro = "Chave do TMDB não configurada. <a href='configuracoes.php'>Clique aqui para configurar</a>.";
}

if ($_POST && isset($_POST['action']) && $_POST['action'] == 'enviar_tmdb') {
    $item_id = $_POST['item_id'];
    $tipo_item = $_POST['tipo_item'];
    $bot_id = $_POST['bot_id'] ?? '';
    $destino = $_POST['destino'];
    $tipo_envio = $_POST['tipo_envio'];
    $marcar_lancamento_manual = isset($_POST['marcar_lancamento_manual']) && $_POST['marcar_lancamento_manual'] == '1';
    $tipo_mensagem_selecionada = $_POST['tipo_mensagem'] ?? 'auto';
    $mensagem_personalizada_texto = $_POST['mensagem_personalizada'] ?? '';
    
    $url_detalhes = "https://api.themoviedb.org/3/{$tipo_item}/{$item_id}?api_key={$tmdb_key}&language=pt-BR";
    $response_detalhes = @file_get_contents($url_detalhes);
    
    $mensagem = '';

    if ($response_detalhes) {
        $item = json_decode($response_detalhes, true);
        
        if ($tipo_mensagem_selecionada == 'personalizada') {
            $mensagem = $mensagem_personalizada_texto;
            if ($marcar_lancamento_manual && strpos(strtolower($mensagem), 'lançamento já disponível') === false) {
                $mensagem .= "\n\n🆕 LANÇAMENTO JÁ DISPONÍVEL";
            }
        } else {
            $url_videos = "https://api.themoviedb.org/3/{$tipo_item}/{$item_id}/videos?api_key={$tmdb_key}";
            $response_videos = @file_get_contents($url_videos);
            $trailer_url = '';
            
            if ($response_videos) {
                $videos = json_decode($response_videos, true);
                foreach ($videos['results'] ?? [] as $video) {
                    if ($video['type'] == 'Trailer' && $video['site'] == 'YouTube') {
                        $trailer_url = "https://www.youtube.com/watch?v=" . $video['key'];
                        break;
                    }
                }
            }
            
            $titulo = $item['title'] ?? $item['name'];
            $sinopse = $item['overview'] ?? 'Sinopse não disponível';
            $avaliacao = number_format($item['vote_average'], 1);
            $data_lancamento = $item['release_date'] ?? $item['first_air_date'];
            $generos = implode(', ', array_column($item['genres'] ?? [], 'name'));
            
            $estrelas_num = round($avaliacao / 2);
            $estrelas = str_repeat('⭐', $estrelas_num);
            
            $eh_lancamento_automatico = false;
            if ($data_lancamento) {
                $dias_desde_lancamento = (time() - strtotime($data_lancamento)) / (60 * 60 * 24);
                $eh_lancamento_automatico = $dias_desde_lancamento <= 10;
            }

            $eh_lancamento_final = $marcar_lancamento_manual || $eh_lancamento_automatico;
            
            $icone = $tipo_item == 'movie' ? '🎬' : '📺';
            $tipo_nome = $tipo_item == 'movie' ? 'Filme' : 'Série';
            
            $mensagem = "{$icone} {$titulo}";
            if ($eh_lancamento_final) {
                $mensagem .= "\n\n🆕 LANÇAMENTO JÁ DISPONÍVEL";
            }
            $mensagem .= "\n\n📝 Sinopse:\n{$sinopse}\n\n";
            $mensagem .= "⭐ Avaliação: {$avaliacao}/10 {$estrelas}\n\n";
            $mensagem .= "🎭 Gêneros: {$generos}\n\n";
            if ($data_lancamento) {
                $mensagem .= "📅 Lançamento: " . date('d/m/Y', strtotime($data_lancamento)) . "\n\n";
            }
            if ($trailer_url) {
                $mensagem .= "🎥 Trailer: {$trailer_url}\n\n";
            }
            $mensagem .= "🤖 Sugestão automática via BotSystem";
        }
        
        $bot_selecionado = null;
        if ($tipo_envio == 'whatsapp') {
            if (isset($config['whatsapp']['server']) && isset($config['whatsapp']['instance']) && isset($config['whatsapp']['apikey'])) {
                $bot_selecionado = [
                    'tipo' => 'whatsapp',
                    'nome' => 'WhatsApp Configurado',
                    'ativo' => true
                ];
            }
        } else {
            $bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];
            foreach ($bots as $bot) {
                if ($bot['id'] == $bot_id && $bot['ativo']) {
                    $bot_selecionado = $bot;
                    break;
                }
            }
        }
        
        if ($bot_selecionado) {
            $sucesso_envio = false;
            $erro_envio = '';
            
            if (!empty($item['poster_path'])) {
                $poster_url = "https://image.tmdb.org/t/p/w500" . $item['poster_path'];
            } else {
                $poster_url = null;
            }

            if ($bot_selecionado['tipo'] == 'whatsapp') {
                $base_url = rtrim($config['whatsapp']['server'], '/');
                $url = $base_url . '/message/sendMedia/' . $config['whatsapp']['instance'];

                $data = [
                    'number' => $destino,
                    'media' => $poster_url,
                    'caption' => $mensagem,
                    'mediatype' => 'image'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . $config['whatsapp']['apikey']
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                if ($curl_error) {
                    $erro_envio = "Erro cURL: " . $curl_error;
                } elseif ($httpCode >= 200 && $httpCode < 300) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['key']) || isset($response_data['status']) || $httpCode == 200) {
                        $sucesso_envio = true;
                    } else {
                        $erro_envio = "Resposta WhatsApp inválida: " . $response;
                    }
                } else {
                    $erro_envio = "Erro WhatsApp HTTP $httpCode: " . $response;
                }
                
            } elseif ($bot_selecionado['tipo'] == 'telegram') {
                $url = "https://api.telegram.org/bot" . $bot_selecionado['token'] . "/sendPhoto";
                
                $data = [
                    'chat_id' => $destino,
                    'photo' => $poster_url,
                    'caption' => $mensagem,
                    'parse_mode' => 'HTML'
                ];
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);
                
                if ($curl_error) {
                    $erro_envio = "Erro cURL: " . $curl_error;
                } elseif ($httpCode == 200) {
                    $response_data = json_decode($response, true);
                    if (isset($response_data['ok']) && $response_data['ok'] === true) {
                        $sucesso_envio = true;
                    } else {
                        $erro_envio = "Erro Telegram: " . ($response_data['description'] ?? 'Erro desconhecido');
                    }
                } else {
                    $erro_envio = "Erro HTTP Telegram $httpCode: " . $response;
                }
            }
            
            $logs = json_decode(file_get_contents('data/logs.json'), true) ?: [];
            $logs[] = [
                'id' => time(),
                'data_hora' => date('Y-m-d H:i:s'),
                'destino' => $destino,
                'bot' => $bot_selecionado['nome'],
                'tipo' => "TMDB - {$tipo_nome}",
                'mensagem' => $titulo,
                'status' => $sucesso_envio ? 'sucesso' : 'erro'
            ];
            file_put_contents('data/logs.json', json_encode($logs, JSON_PRETTY_PRINT));
            
            if ($sucesso_envio) {
                $sucesso = "Conteúdo TMDB enviado com sucesso!";
            } else {
                $erro_envio_msg = $erro_envio ?: "Erro desconhecido ao enviar conteúdo TMDB.";
            }
        } else {
            $erro_envio_msg = "Nenhum bot ativo encontrado para o envio.";
        }
    } else {
        $erro_envio_msg = "Erro ao buscar detalhes do item no TMDB.";
    }
}

include 'includes/header.php';
?>

<style>
.tmdb-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem;
}
.tmdb-actions .btn {
    width: 100%;
    margin: 0;
}
#searchResults .tmdb-card {
    margin-bottom: 1rem;
}
#previewModal pre {
    white-space: pre-wrap;
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 5px;
}
.search-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    padding: 1rem;
}
.search-toggle {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.search-toggle .btn {
    border-radius: 20px;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
    min-width: 80px;
    transition: all 0.2s ease-in-out;
}
.search-toggle .btn.active {
    background-color: #007bff;
    color: white;
}
.search-toggle .btn:not(.active) {
    background-color: #f8f9fa;
    color: #333;
}
.input-group {
    border-radius: 20px;
    overflow: hidden;
}
.input-group .form-control {
    border: none;
    box-shadow: none;
}
.input-group .btn-primary {
    border-radius: 0 20px 20px 0;
}
#envioModal .form-group {
    margin-bottom: 1rem;
}
#envioModal .form-select {
    width: 100%;
}
.option-highlight {
    padding: 0.5rem 1rem;
    border-radius: 5px;
    border: 1px solid #dee2e6;
    margin-bottom: 0.5rem;
    cursor: pointer;
    background-color: #f8f9fa;
    transition: all 0.2s ease-in-out;
}
.option-highlight.active {
    background-color: #e2f0ff;
    border-color: #007bff;
    font-weight: bold;
}
.option-highlight:hover {
    background-color: #e9ecef;
}
.option-highlight input[type="radio"],
.option-highlight input[type="checkbox"] {
    margin-right: 0.5rem;
}
.option-container {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
@media (max-width: 768px) {
    .search-toggle {
        flex-wrap: wrap;
        justify-content: center;
    }
    .search-toggle .btn {
        padding: 0.6rem 1.2rem;
        font-size: 0.85rem;
        min-width: 90px;
        touch-action: manipulation;
    }
    .search-toggle .btn.active {
        transform: scale(1.05);
    }
    .search-container {
        padding: 0.75rem;
    }
    .input-group .form-control {
        font-size: 0.9rem;
    }
    .input-group .btn-primary {
        padding: 0.5rem;
    }
}
</style>

<div class="card search-container" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-search"></i> Pesquisar Filmes e Séries
        </h3>
    </div>
    <div class="card-body">
        <form id="searchForm">
            <div class="search-toggle mb-3">
                <button type="button" class="btn toggle-btn active" data-type="multi">Ambos</button>
                <button type="button" class="btn toggle-btn" data-type="movie">Filmes</button>
                <button type="button" class="btn toggle-btn" data-type="tv">Séries</button>
            </div>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="searchInput" class="form-control" placeholder="Digite o nome do filme ou série..." autocomplete="off">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Pesquisar</button>
            </div>
        </form>
        <div id="searchResults" class="tmdb-grid" style="margin-top: 1rem;"></div>
    </div>
</div>

<?php 
if (!empty($sucesso)) {
    echo '<div class="alert alert-success">' . $sucesso . '</div>';
} elseif (!empty($erro_envio_msg)) {
    echo '<div class="alert alert-danger">' . $erro_envio_msg . '</div>';
}

if (!empty($erro)) {
    echo '<div class="alert alert-info">' . $erro . '</div>';
}
?>

<?php if (empty($erro)): ?>

<?php if (!empty($filmes)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-film"></i>
            🔥 Filmes em Alta da Semana
        </h3>
    </div>
    <div class="card-body">
        <div class="tmdb-grid">
            <?php foreach ($filmes as $filme): ?>
            <?php 
                $eh_lancamento = false;
                if ($filme['release_date']) {
                    $dias_desde_lancamento = (time() - strtotime($filme['release_date'])) / (60 * 60 * 24);
                    $eh_lancamento = $dias_desde_lancamento <= 10;
                }
                $estrelas_num = round($filme['vote_average'] / 2);
                $estrelas = str_repeat('⭐', $estrelas_num);
            ?>
            <div class="tmdb-card">
                <img src="https://image.tmdb.org/t/p/w500<?= $filme['poster_path'] ?>" alt="<?= $filme['title'] ?>" class="tmdb-poster">
                <div class="tmdb-content">
                    <h4 class="tmdb-title"><?= $filme['title'] ?></h4>
                    
                    <div class="tmdb-tags">
                        <span class="tag">🎬 Filme</span>
                        <?php if ($eh_lancamento): ?>
                        <span class="tag launch">🆕 LANÇAMENTO</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tmdb-rating">
                        <strong><?= number_format($filme['vote_average'], 1) ?>/10</strong>
                        <span class="stars"><?= $estrelas ?></span>
                    </div>
                    
                    <p class="tmdb-synopsis"><?= substr($filme['overview'], 0, 150) ?>...</p>
                    
                    <div class="tmdb-info">
                        <strong>Lançamento:</strong> <?= date('d/m/Y', strtotime($filme['release_date'])) ?>
                    </div>
                    
                    <div class="tmdb-actions">
                        <button class="btn btn-success btn-sm" onclick="enviarTMDB(<?= $filme['id'] ?>, 'movie', '<?= addslashes($filme['title']) ?>', 'whatsapp')">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="enviarTMDB(<?= $filme['id'] ?>, 'movie', '<?= addslashes($filme['title']) ?>', 'telegram')">
                            <i class="bi bi-telegram"></i> Telegram
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="agendarTMDB(<?= $filme['id'] ?>, 'movie', '<?= addslashes($filme['title']) ?>')">
                            <i class="bi bi-calendar"></i> Agendar
                        </button>
                        <button class="btn btn-info btn-sm" onclick="visualizarTMDB(<?= $filme['id'] ?>, 'movie', '<?= addslashes($filme['title']) ?>', <?= $eh_lancamento ? 'true' : 'false' ?>)">
                            <i class="bi bi-eye"></i> Visualizar
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($series)): ?>
<div class="card" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="bi bi-tv"></i>
            📺 Séries em Alta da Semana
        </h3>
    </div>
    <div class="card-body">
        <div class="tmdb-grid">
            <?php foreach ($series as $serie): ?>
            <?php 
                $eh_lancamento = false;
                if ($serie['first_air_date']) {
                    $dias_desde_lancamento = (time() - strtotime($serie['first_air_date'])) / (60 * 60 * 24);
                    $eh_lancamento = $dias_desde_lancamento <= 10;
                }
                $estrelas_num = round($serie['vote_average'] / 2);
                $estrelas = str_repeat('⭐', $estrelas_num);
            ?>
            <div class="tmdb-card">
                <img src="https://image.tmdb.org/t/p/w500<?= $serie['poster_path'] ?>" alt="<?= $serie['name'] ?>" class="tmdb-poster">
                <div class="tmdb-content">
                    <h4 class="tmdb-title"><?= $serie['name'] ?></h4>
                    
                    <div class="tmdb-tags">
                        <span class="tag">📺 Série</span>
                        <?php if ($eh_lancamento): ?>
                        <span class="tag launch">🆕 LANÇAMENTO</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tmdb-rating">
                        <strong><?= number_format($serie['vote_average'], 1) ?>/10</strong>
                        <span class="stars"><?= $estrelas ?></span>
                    </div>
                    
                    <p class="tmdb-synopsis"><?= substr($serie['overview'], 0, 150) ?>...</p>
                    
                    <div class="tmdb-info">
                        <strong>Estreia:</strong> <?= date('d/m/Y', strtotime($serie['first_air_date'])) ?>
                    </div>
                    
                    <div class="tmdb-actions">
                        <button class="btn btn-success btn-sm" onclick="enviarTMDB(<?= $serie['id'] ?>, 'tv', '<?= addslashes($serie['name']) ?>', 'whatsapp')">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="enviarTMDB(<?= $serie['id'] ?>, 'tv', '<?= addslashes($serie['name']) ?>', 'telegram')">
                            <i class="bi bi-telegram"></i> Telegram
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="agendarTMDB(<?= $serie['id'] ?>, 'tv', '<?= addslashes($serie['name']) ?>')">
                            <i class="bi bi-calendar"></i> Agendar
                        </button>
                        <button class="btn btn-info btn-sm" onclick="visualizarTMDB(<?= $serie['id'] ?>, 'tv', '<?= addslashes($serie['name']) ?>', <?= $eh_lancamento ? 'true' : 'false' ?>)">
                            <i class="bi bi-eye"></i> Visualizar
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<div id="envioModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3 id="modalTitle">Enviar Conteúdo</h3>
        <form method="POST" id="envioForm">
            <input type="hidden" name="action" value="enviar_tmdb">
            <input type="hidden" name="item_id" id="modal_item_id">
            <input type="hidden" name="tipo_item" id="modal_tipo_item">
            <input type="hidden" name="tipo_envio" id="modal_tipo_envio">
            
            <div class="form-group" id="botSelectGroup" style="display: none;">
                <label class="form-label">Bot Telegram</label>
                <select name="bot_id" class="form-select" id="bot_id">
                    <option value="">Escolha um bot...</option>
                    <?php 
                    $bots = json_decode(file_get_contents('data/bots.json'), true) ?: [];
                    foreach ($bots as $bot): 
                        if ($bot['ativo'] && $bot['tipo'] == 'telegram'):
                    ?>
                    <option value="<?= $bot['id'] ?>"><?= $bot['nome'] ?> (Telegram)</option>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Destino</label>
                <select name="destino" class="form-select" id="destinoSelect" required>
                    <option value="">Selecione um grupo...</option>
                    <?php foreach ($meus_grupos as $grupo): ?>
                    <option value="<?= htmlspecialchars($grupo['id_externo']) ?>" data-tipo="<?= $grupo['tipo'] ?>">
                        <?= htmlspecialchars($grupo['nome']) ?> (<?= ucfirst($grupo['tipo']) ?>: <?= htmlspecialchars($grupo['id_externo']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Opções de Mensagem</label>
                <div class="option-container">
                    <div class="option-highlight active" id="optionAuto">
                        <input class="form-check-input" type="radio" name="tipo_mensagem" id="msgAuto" value="auto" checked>
                        <label class="form-check-label" for="msgAuto">Mensagem Automática TMDB</label>
                    </div>
                    <div class="option-highlight" id="optionPersonalizada">
                        <input class="form-check-input" type="radio" name="tipo_mensagem" id="msgPersonalizada" value="personalizada">
                        <label class="form-check-label" for="msgPersonalizada">Personalizar Mensagem</label>
                    </div>
                    <textarea class="form-control mt-2" id="mensagemPersonalizada" name="mensagem_personalizada" rows="5" style="display: none;" placeholder="Digite sua mensagem personalizada aqui..."></textarea>
                </div>
            </div>

            <div class="form-group">
                <div class="form-check option-highlight" id="optionLancamento">
                    <input class="form-check-input" type="checkbox" value="1" id="marcarLancamentoManual" name="marcar_lancamento_manual">
                    <label class="form-check-label" for="marcarLancamentoManual">
                        Marcar como "🆕 **LANÇAMENTO JÁ DISPONÍVEL**"
                    </label>
                </div>
            </div>
            
            <div style="text-align: right; margin-top: 1rem;">
                <button type="button" onclick="fecharModalEnvio()" class="btn btn-secondary">Cancelar</button>
                <button type="submit" class="btn btn-primary">Enviar</button>
            </div>
        </form>
    </div>
</div>

<div id="previewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 10px; width: 90%; max-width: 500px;">
        <h3 id="previewModalTitle">Pré-visualizar Mensagem</h3>
        <pre id="previewContent"></pre>
        <div style="text-align: right; margin-top: 1rem;">
            <button type="button" onclick="fecharModalPreview()" class="btn btn-secondary">Fechar</button>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let searchTimeout;
const tmdbKey = '<?= $tmdb_key ?>';

function enviarTMDB(itemId, tipoItem, titulo, tipoEnvio) {
    document.getElementById('modal_item_id').value = itemId;
    document.getElementById('modal_tipo_item').value = tipoItem;
    document.getElementById('modal_tipo_envio').value = tipoEnvio;
    document.getElementById('modalTitle').textContent = 'Enviar: ' + titulo;
    
    const botSelectGroup = document.getElementById('botSelectGroup');
    const destinoSelect = document.getElementById('destinoSelect');
    botSelectGroup.style.display = tipoEnvio === 'telegram' ? 'block' : 'none';
    
    Array.from(destinoSelect.options).forEach(option => {
        if (option.value === '') return;
        const tipoGrupo = option.dataset.tipo;
        option.style.display = tipoGrupo === tipoEnvio ? 'block' : 'none';
        option.disabled = tipoGrupo !== tipoEnvio;
    });
    
    const validOptions = Array.from(destinoSelect.options).filter(opt => !opt.disabled && opt.value !== '');
    if (validOptions.length > 0) {
        destinoSelect.value = validOptions[0].value;
    } else {
        destinoSelect.value = '';
    }
    
    document.getElementById('marcarLancamentoManual').checked = false;
    document.getElementById('optionLancamento').classList.remove('active');
    
    document.getElementById('msgAuto').checked = true;
    document.getElementById('optionAuto').classList.add('active');
    document.getElementById('msgPersonalizada').checked = false;
    document.getElementById('optionPersonalizada').classList.remove('active');
    document.getElementById('mensagemPersonalizada').style.display = 'none';
    document.getElementById('mensagemPersonalizada').value = '';
    document.getElementById('mensagemPersonalizada').removeAttribute('required');
    
    document.getElementById('envioModal').style.display = 'block';
}

function fecharModalEnvio() {
    document.getElementById('envioModal').style.display = 'none';
    document.getElementById('envioForm').reset();
    document.getElementById('botSelectGroup').style.display = 'none';
    document.getElementById('modal_tipo_envio').value = '';
}

function agendarTMDB(itemId, tipoItem, titulo) {
    window.location.href = 'agendar.php?tmdb_id=' + itemId + '&tipo=' + tipoItem + '&titulo=' + encodeURIComponent(titulo);
}

function visualizarTMDB(itemId, tipoItem, titulo, ehLancamentoAtual = false) {
    $.ajax({
        url: `https://api.themoviedb.org/3/${tipoItem}/${itemId}?api_key=${tmdbKey}&language=pt-BR`,
        method: 'GET',
        success: function(item) {
            $.ajax({
                url: `https://api.themoviedb.org/3/${tipoItem}/${itemId}/videos?api_key=${tmdbKey}`,
                method: 'GET',
                success: function(videos) {
                    let trailer_url = '';
                    for (let video of videos.results || []) {
                        if (video.type === 'Trailer' && video.site === 'YouTube') {
                            trailer_url = `https://www.youtube.com/watch?v=${video.key}`;
                            break;
                        }
                    }
                    
                    const titulo = item.title || item.name;
                    const sinopse = item.overview || 'Sinopse não disponível';
                    const avaliacao = parseFloat(item.vote_average).toFixed(1);
                    const data_lancamento = item.release_date || item.first_air_date;
                    const generos = (item.genres || []).map(g => g.name).join(', ');
                    const estrelas_num = Math.round(avaliacao / 2);
                    const estrelas = '⭐'.repeat(estrelas_num);
                    
                    let eh_lancamento_preview = ehLancamentoAtual;
                    
                    const icone = tipoItem === 'movie' ? '🎬' : '📺';
                    
                    let mensagem = `${icone} ${titulo}`;
                    if (eh_lancamento_preview) {
                        mensagem += ' 🆕 LANÇAMENTO JÁ DISPONÍVEL';
                    }
                    mensagem += `\n\n📝 Sinopse:\n${sinopse}\n\n`;
                    mensagem += `⭐ Avaliação: ${avaliacao}/10 ${estrelas}\n\n`;
                    mensagem += `🎭 Gêneros: ${generos}\n\n`;
                    if (data_lancamento) mensagem += `📅 Lançamento: ${new Date(data_lancamento).toLocaleDateString('pt-BR')}\n\n`;
                    if (trailer_url) mensagem += `🎥 Trailer: ${trailer_url}\n\n`;
                    mensagem += '🤖 Sugestão automática via BotSystem';
                    
                    document.getElementById('previewModalTitle').textContent = `Pré-visualizar: ${titulo}`;
                    document.getElementById('previewContent').textContent = mensagem;
                    document.getElementById('previewModal').style.display = 'block';
                },
                error: function() {
                    alert('Erro ao carregar vídeos do item.');
                }
            });
        },
        error: function() {
            alert('Erro ao carregar detalhes do item.');
        }
    });
}

function fecharModalPreview() {
    document.getElementById('previewModal').style.display = 'none';
}

$(document).ready(function() {
    let searchType = 'multi';

    $('.toggle-btn').on('click touchstart', function(e) {
        e.preventDefault();
        $('.toggle-btn').removeClass('active');
        $(this).addClass('active');
        searchType = $(this).data('type');
        searchTMDB();
    });

    $('.toggle-btn').on('touchend', function(e) {
        e.stopPropagation();
    });

    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        searchTMDB();
    });

    $('#searchInput').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(searchTMDB, 300);
    });

    function searchTMDB() {
        const query = $('#searchInput').val().trim();
        if (!query) {
            $('#searchResults').empty();
            return;
        }

        const endpoint = searchType === 'multi' ? 'search/multi' : `search/${searchType}`;
        $.ajax({
            url: `https://api.themoviedb.org/3/${endpoint}?api_key=${tmdbKey}&language=pt-BR&query=${encodeURIComponent(query)}`,
            method: 'GET',
            success: function(data) {
                $('#searchResults').empty();
                const results = data.results.slice(0, 5);
                results.forEach(item => {
                    const tipoItem = item.media_type === 'movie' || searchType === 'movie' ? 'movie' : 'tv';
                    const titulo = item.title || item.name;
                    const data_lancamento_item = item.release_date || item.first_air_date;
                    const eh_lancamento = data_lancamento_item ? (Date.now() - new Date(data_lancamento_item).getTime()) / (1000 * 60 * 60 * 24) <= 10 : false;
                    const estrelas_num = Math.round(item.vote_average / 2);
                    const estrelas = '⭐'.repeat(estrelas_num);
                    const tipo_nome = tipoItem === 'movie' ? 'Filme' : 'Série';
                    const data_lancamento_formatada = data_lancamento_item ? new Date(data_lancamento_item).toLocaleDateString('pt-BR') : 'N/A';

                    $('#searchResults').append(`
                        <div class="tmdb-card">
                            <img src="https://image.tmdb.org/t/p/w500${item.poster_path || '/placeholder.jpg'}" alt="${titulo}" class="tmdb-poster">
                            <div class="tmdb-content">
                                <h4 class="tmdb-title">${titulo}</h4>
                                <div class="tmdb-tags">
                                    <span class="tag">${tipoItem === 'movie' ? '🎬 Filme' : '📺 Série'}</span>
                                    ${eh_lancamento ? '<span class="tag launch">🆕 LANÇAMENTO</span>' : ''}
                                </div>
                                <div class="tmdb-rating">
                                    <strong>${parseFloat(item.vote_average).toFixed(1)}/10</strong>
                                    <span class="stars">${estrelas}</span>
                                </div>
                                <p class="tmdb-synopsis">${item.overview ? item.overview.substring(0, 150) + '...' : 'Sinopse não disponível'}</p>
                                <div class="tmdb-info">
                                    <strong>${tipoItem === 'movie' ? 'Lançamento' : 'Estreia'}:</strong> ${data_lancamento_formatada}
                                </div>
                                <div class="tmdb-actions">
                                    <button class="btn btn-success btn-sm" onclick="enviarTMDB(${item.id}, '${tipoItem}', '${titulo.replace(/'/g, "\\'")}', 'whatsapp')">
                                        <i class="bi bi-whatsapp"></i> WhatsApp
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="enviarTMDB(${item.id}, '${tipoItem}', '${titulo.replace(/'/g, "\\'")}', 'telegram')">
                                        <i class="bi bi-telegram"></i> Telegram
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="agendarTMDB(${item.id}, '${tipoItem}', '${titulo.replace(/'/g, "\\'")}')">
                                        <i class="bi bi-calendar"></i> Agendar
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="visualizarTMDB(${item.id}, '${tipoItem}', '${titulo.replace(/'/g, "\\'")}', ${eh_lancamento ? 'true' : 'false'})">
                                        <i class="bi bi-eye"></i> Visualizar
                                    </button>
                                </div>
                            </div>
                        </div>
                    `);
                });
            },
            error: function(xhr, status, error) {
                console.error("Erro na busca TMDB:", status, error);
                $('#searchResults').html('<div class="alert alert-warning">Erro ao buscar filmes/séries. Tente novamente.</div>');
            }
        });
    }

    $('input[name="tipo_mensagem"]').on('change', function() {
        $('.option-highlight').removeClass('active');
        if (this.value === 'auto') {
            $('#optionAuto').addClass('active');
            $('#mensagemPersonalizada').slideUp();
            $('#mensagemPersonalizada').removeAttr('required');
        } else {
            $('#optionPersonalizada').addClass('active');
            $('#mensagemPersonalizada').slideDown();
            $('#mensagemPersonalizada').attr('required', 'required');
        }
    });

    $('#marcarLancamentoManual').on('change', function() {
        if ($(this).is(':checked')) {
            $('#optionLancamento').addClass('active');
        } else {
            $('#optionLancamento').removeClass('active');
        }
    });
});
</script>