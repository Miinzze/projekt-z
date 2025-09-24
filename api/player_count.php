<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Deine FiveM Server URL
$fivemUrl = 'https://servers-frontend.fivem.net/api/servers/single/d4l63q';

$response = [
    'success' => false,
    'online' => 0,
    'max' => 64,
    'players' => []
];

try {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fivemUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible)');
    
    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $apiResponse) {
        $data = json_decode($apiResponse, true);
        
        if ($data && isset($data['Data'])) {
            $response['success'] = true;
            $response['online'] = (int)($data['Data']['clients'] ?? 0);
            $response['max'] = (int)($data['Data']['sv_maxclients'] ?? 64);
            
            if (isset($data['Data']['players'])) {
                foreach ($data['Data']['players'] as $player) {
                    $response['players'][] = [
                        'name' => $player['name'] ?? 'Unknown'
                    ];
                }
            }
        }
    }
} catch (Exception $e) {
    // Fehler ignorieren
}

echo json_encode($response);