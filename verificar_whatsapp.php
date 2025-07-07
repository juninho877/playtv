<?php
header('Content-Type: application/json');

// Carregar configurações
$config = json_decode(file_get_contents('data/config.json'), true) ?: [];

if (empty($config['whatsapp']['server']) || empty($config['whatsapp']['instance']) || empty($config['whatsapp']['apikey'])) {
    echo json_encode(['conectado' => false, 'status' => 'Configuração incompleta']);
    exit;
}

$base_url = rtrim($config['whatsapp']['server'], '/');
$url = $base_url . '/instance/connectionState/' . $config['whatsapp']['instance'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
    echo json_encode(['conectado' => false, 'status' => 'Erro de conexão: ' . $curl_error]);
    exit;
}

if ($httpCode == 200) {
    $data = json_decode($response, true);
    $connection_state = $data['instance']['state'] ?? $data['state'] ?? 'unknown';
    
    if ($connection_state === 'open') {
        echo json_encode(['conectado' => true, 'status' => $connection_state]);
    } else {
        echo json_encode(['conectado' => false, 'status' => $connection_state]);
    }
} else {
    echo json_encode(['conectado' => false, 'status' => 'HTTP ' . $httpCode]);
}
?>