
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="logo-icon">🤖</span>
            <span class="logo-text">BotSystem</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="bots.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'bots.php') ? 'active' : '' ?>">
            <i class="bi bi-cpu me-1"></i>
            <span>Adicionar Bots</span>
        </a>
        
        <a href="enviar.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'enviar.php') ? 'active' : '' ?>">
            <i class="bi bi-send"></i>
            <span>Enviar</span>
        </a>
        
        <a href="enquete.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'enquete.php') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart"></i>
            <span>Enquetes</span>
        </a>
        
        <a href="tmdb.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'tmdb.php') ? 'active' : '' ?>">
            <i class="bi bi-film"></i>
            <span>TMDB</span>
        </a>
        
        <a href="agendar.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'agendar.php') ? 'active' : '' ?>">
            <i class="bi bi-calendar-event"></i>
            <span>Agendar</span>
        </a>
        
        <a href="auto_bot.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'auto_bot.php') ? 'active' : '' ?>">
            <i class="bi bi-robot"></i>
            <span>Auto Bot</span>
        </a>
        
        <a href="relatorio.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'relatorio.php') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart"></i>
            <span>Relatórios</span>
        </a>
        
        <a href="meus_dados.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'meus_dados.php') ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>Meus Dados</span>
        </a>
        
        <a href="configuracoes.php" class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'configuracoes.php') ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            <span>Configurações</span>
        </a>
    </nav>
</aside>
