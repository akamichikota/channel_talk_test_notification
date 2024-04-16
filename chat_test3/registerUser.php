<?php
// APIキーの設定
$accessKey = '661b221782915c8bc762';
$accessSecret = '3580f8e89e85879c260ab394d1779035';

// リクエストボディからデータを取得
$data = json_decode(file_get_contents('php://input'), true);
$userId = $data['userId'];
$firstMessage = $data['firstMessage'];

// チャットセッションを作成するためのAPIエンドポイント
$userChatsUrl = "https://api.channel.io/open/v5/users/{$userId}/user-chats";

// cURLセッションの初期化と実行
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $userChatsUrl,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Access-Key: ' . $accessKey,
        'X-Access-Secret: ' . $accessSecret
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([]),
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
if (!$response) {
    echo json_encode(['error' => true, 'message' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

$responseDecoded = json_decode($response, true);
if (!isset($responseDecoded['userChat']['id'])) {
    echo json_encode(['error' => true, 'message' => 'Failed to create chat session or retrieve chat ID']);
    curl_close($ch);
    exit;
}

$userChatId = $responseDecoded['userChat']['id'];

// メッセージ送信
$messagesUrl = "https://api.channel.io/open/v5/user-chats/{$userChatId}/messages";
curl_setopt_array($ch, [
    CURLOPT_URL => $messagesUrl,
    CURLOPT_POSTFIELDS => json_encode(["blocks" => [["type" => "text", "value" => $firstMessage]]])
]);

$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo json_encode(['error' => true, 'message' => 'Failed to send message']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Message sent successfully', 'chatId' => $userChatId]);
?>
