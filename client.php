<?php

require __DIR__ . '/vendor/autoload.php';

/**
 * 接続
 */
function connectServer($connector, $loop) {
    // 接続先情報（適宜変更）
    $url = "wss://echo.websocket.org:443";
    $connector($url)->then(function(Ratchet\Client\WebSocket $conn) use ($connector, $loop) {
        // メッセージ受信イベント
        $conn->on("message", function(\Ratchet\RFC6455\Messaging\MessageInterface $msg) {
            // メッセージ受け取ったときの処理（適宜変更）
            //print_r($msg);
            echo "Received: {$msg}\n";
        });

        // 切断イベント
        $conn->on("close", function($code = null, $reason = null) use ($connector, $loop) {
            $loop->addTimer(1, function() use ($connector, $loop) {
                // 切断されたら自動で再接続させる
                connectServer($connector, $loop);
            });
        });

        // 接続できたらテストでサーバへメッセージ送ってみる （適宜変更）
        sendMessage($conn, "hello world.蘇我入鹿");
    }, function (\Exception $e) use ($loop) {
        // 例外処理（処理停止）
        $loop->stop();
    });
}


/**
 * メッセージ送信
 */
function sendMessage($conn, $msg) {
    $conn->send($msg);
}


/**
 * 本体
 */
$loop = React\EventLoop\Factory::create();
$connector = new Ratchet\Client\Connector($loop);
connectServer($connector, $loop);
$loop->run();
