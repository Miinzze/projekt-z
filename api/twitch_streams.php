<?php
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = [
    'success' => false,
    'live_streamers' => [],
    'error' => null
];

try {
    // Twitch Settings abrufen
    $clientId = getSetting('twitch_client_id', '');
    $clientSecret = getSetting('twitch_client_secret', '');
    $twitchEnabled = getSetting('twitch_enabled', '1') == '1';
    
    if (!$twitchEnabled) {
        $response['error'] = 'Twitch integration disabled';
        echo json_encode($response);
        exit;
    }
    
    if (empty($clientId) || empty($clientSecret)) {
        $response['error'] = 'Twitch API credentials not configured';
        echo json_encode($response);
        exit;
    }
    
    // Aktive Streamer aus Datenbank abrufen
    $stmt = $pdo->query("
        SELECT twitch_username, display_name, order_num 
        FROM streamers 
        WHERE is_active = 1 
        ORDER BY order_num ASC
    ");
    $streamers = $stmt->fetchAll();
    
    if (empty($streamers)) {
        $response['success'] = true;
        echo json_encode($response);
        exit;
    }
    
    // Twitch Access Token abrufen
    $accessToken = getTwitchAccessToken($clientId, $clientSecret);
    
    if (!$accessToken) {
        $response['error'] = 'Failed to get Twitch access token';
        echo json_encode($response);
        exit;
    }
    
    // User IDs von Twitch abrufen
    $usernames = array_column($streamers, 'twitch_username');
    $userIds = getTwitchUserIds($usernames, $clientId, $accessToken);
    
    if (empty($userIds)) {
        $response['success'] = true;
        echo json_encode($response);
        exit;
    }
    
    // Live Streams prüfen
    $liveStreams = getTwitchLiveStreams(array_values($userIds), $clientId, $accessToken);
    
    // Daten zusammenführen
    foreach ($streamers as $streamer) {
        $username = $streamer['twitch_username'];
        
        if (isset($userIds[$username]) && isset($liveStreams[$userIds[$username]])) {
            $streamData = $liveStreams[$userIds[$username]];
            
            $response['live_streamers'][] = [
                'twitch_username' => $username,
                'display_name' => $streamer['display_name'],
                'stream_title' => $streamData['title'] ?? '',
                'game_name' => $streamData['game_name'] ?? '',
                'viewer_count' => $streamData['viewer_count'] ?? 0,
                'thumbnail_url' => $streamData['thumbnail_url'] ?? '',
                'profile_image' => $streamData['profile_image_url'] ?? '',
                'twitch_url' => 'https://twitch.tv/' . $username,
                'order_num' => $streamer['order_num']
            ];
        }
    }
    
    // Nach order_num sortieren
    usort($response['live_streamers'], function($a, $b) {
        return $a['order_num'] - $b['order_num'];
    });
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = 'Server error: ' . $e->getMessage();
}

echo json_encode($response);

// === HILFSFUNKTIONEN ===

function getTwitchAccessToken($clientId, $clientSecret) {
    $url = 'https://id.twitch.tv/oauth2/token';
    $data = [
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    return null;
}

function getTwitchUserIds($usernames, $clientId, $accessToken) {
    if (empty($usernames)) return [];
    
    $url = 'https://api.twitch.tv/helix/users?' . http_build_query(['login' => $usernames]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Client-ID: ' . $clientId,
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $userIds = [];
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['data'])) {
            foreach ($data['data'] as $user) {
                $userIds[$user['login']] = $user['id'];
            }
        }
    }
    
    return $userIds;
}

function getTwitchLiveStreams($userIds, $clientId, $accessToken) {
    if (empty($userIds)) return [];
    
    $url = 'https://api.twitch.tv/helix/streams?' . http_build_query(['user_id' => $userIds]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Client-ID: ' . $clientId,
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $streams = [];
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['data'])) {
            foreach ($data['data'] as $stream) {
                $streams[$stream['user_id']] = $stream;
            }
        }
    }
    
    return $streams;
}
?>