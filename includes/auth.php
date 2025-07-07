
<?php
session_start();

function verificarLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function getUserName() {
    return $_SESSION['user_name'] ?? 'Usuário';
}

function getUserType() {
    return $_SESSION['user_type'] ?? 'user';
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Verificar se foi solicitado logout
if (isset($_GET['logout'])) {
    logout();
}
?>
