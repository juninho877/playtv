
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'BotSystem' ?> - Painel Administrativo</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        /* General styles for sidebar-toggle */
        .sidebar-toggle {
            background: none;
            border: none;
            padding: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-toggle i {
            font-size: 1.5rem;
        }
        /* Sidebar styles */
        .sidebar {
            transition: transform 0.3s ease-in-out;
            background: #fff;
            width: 250px;
        }
        .sidebar.active {
            transform: translateX(0);
        }
        .sidebar-nav {
            padding: 1rem;
        }
        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #333;
            text-decoration: none;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
        }
        .nav-item:hover {
            background-color: #f8f9fa;
        }
        .nav-item.active {
            background-color: #007bff;
            color: white;
        }
        .nav-item i {
            margin-right: 0.5rem;
        }
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            .sidebar-toggle {
                padding: 0.75rem;
                min-width: 44px;
                min-height: 44px;
                touch-action: manipulation;
                border: 1px solid #333;
                border-radius: 0.25rem;
            }
            .sidebar-toggle i {
                font-size: 1.25rem;
            }
            .sidebar {
                position: fixed;
                top: 0;
                left: -250px; /* Hidden off-screen */
                width: 250px;
                height: 100%;
                box-shadow: 2px 0 5px rgba(0,0,0,0.2);
                z-index: 1001;
                overflow-y: auto;
                transition: transform 0.3s ease-in-out;
            }
            .sidebar.active {
                transform: translateX(250px); /* Slide in */
            }
            .main-content {
                margin-left: 0;
            }
            .top-header {
                padding: 0.5rem;
            }
            .nav-item {
                font-size: 1rem;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="content-area">
            <header class="top-header">
                <div class="header-left">
                    <button class="sidebar-toggle" onclick="toggleSidebar()">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="page-title"><?= $page_title ?? 'Dashboard' ?></h1>
                </div>
                <div class="header-right">
                    <span class="user-name">👋 <?= getUserName() ?></span>
                    <a href="?logout=1" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i> Sair
                    </a>
                </div>
            </header>
            <main class="main-content">
    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
            } else {
                console.error('Sidebar element not found');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const toggleButton = document.querySelector('.sidebar-toggle');
            if (toggleButton) {
                toggleButton.addEventListener('click', toggleSidebar);
                toggleButton.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    toggleSidebar();
                });
                toggleButton.addEventListener('touchend', function(e) {
                    e.stopPropagation();
                });
            } else {
                console.error('Sidebar toggle button not found');
            }

            // Close sidebar when tapping outside on mobile
            document.addEventListener('touchstart', function(e) {
                const sidebar = document.querySelector('.sidebar');
                const toggleButton = document.querySelector('.sidebar-toggle');
                if (sidebar && toggleButton && !sidebar.contains(e.target) && !toggleButton.contains(e.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        });
    </script>
</body>
