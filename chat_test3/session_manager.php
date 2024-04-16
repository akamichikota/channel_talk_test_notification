<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'db.php';

header('Content-Type: application/json');  // JSON形式で返すことを宣言
session_start();

// リクエストデータの取得
$data = json_decode(file_get_contents('php://input'), true);
$response = ['success' => false];  // デフォルトの応答

// アクションに応じて関数を呼び出す
if (isset($data['action'])) {
    switch ($data['action']) {
        case 'start':
            $userId = $data['userId'] ?? 1;
            $response = startSession($userId);
            break;
        case 'update':
            $sessionId = $data['session_id'] ?? null;
            $step = $data['step'] ?? null;
            if ($sessionId && $step) {
                $response = updateSession($sessionId, $step);
            }
            break;
        case 'end':
            $sessionId = $data['session_id'] ?? null;
            if ($sessionId) {
                $response = endSession($sessionId);
            }
            break;
    }
}

echo json_encode($response);  // 応答をJSON形式で出力

function startSession($userId) {
    global $conn;
    $start_time = date('Y-m-d H:i:s');
    $sql = "INSERT INTO survey_sessions (user_id, start_time, last_update, status) VALUES (?, ?, ?, 'active')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $start_time, $start_time);
    if ($stmt->execute()) {
        $session_id = $conn->insert_id;
        $_SESSION['survey_started'] = true; // アンケートが開始されたことをセッションに保存
        $_SESSION['session_id'] = $session_id; // セッションIDも保存
        notifyChannelTalk($session_id, "アンケートが開始されました。");
        return ['success' => true, 'session_id' => $session_id];
    }
    return ['success' => false, 'message' => 'Failed to start session: ' . $conn->error];
}


function notifyChannelTalk($session_id, $message) {
    $url = "https://api.channel.io/open/v5/user-chats/661df20311ad25fb4185/messages";
    $headers = [
        'Content-Type: application/json',
        'X-Access-Key: myAccessKey', // アクセスキー
        'X-Access-Secret: myAccessSecret' // アクセスシークレット
    ];

    // セッションIDをメッセージに含める
    $fullMessage = "セッションID {$session_id}: {$message}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["blocks" => [["type" => "text", "value" => $fullMessage]]]));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function updateSession($sessionId, $step) {
    global $conn;
    $last_update = date('Y-m-d H:i:s');
    $sql = "UPDATE survey_sessions SET last_step = ?, last_update = ? WHERE session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $step, $last_update, $sessionId);
    if ($stmt->execute()) {
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Failed to update session: ' . $stmt->error];
    }
}

function endSession($sessionId) {
    global $conn;
    if (!isset($_SESSION['survey_started']) || $_SESSION['survey_started'] !== true) {
        // アンケートが開始されていない場合は何もしない
        return ['success' => false, 'message' => 'No survey was started'];
    }

    $last_update = date('Y-m-d H:i:s');
    $status = 'completed';
    $sql = "UPDATE survey_sessions SET status = ?, last_update = ? WHERE session_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $last_update, $sessionId);
    if ($stmt->execute()) {
        $_SESSION['survey_started'] = false; // アンケート終了のフラグをクリア
        notifyChannelTalk($sessionId, "アンケートが途中で終了されました。");
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Failed to end session: ' . $conn->error];
}

?>
