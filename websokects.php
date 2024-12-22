<?php
$address = '0.0.0.0';
$port = 8080;

$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($server === false) {
    die("socket_create() failed: " . socket_strerror(socket_last_error()));
}

socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);

if (socket_bind($server, $address, $port) === false) {
    die("socket_bind() failed: " . socket_strerror(socket_last_error($server)));
}

if (socket_listen($server, 5) === false) {
    die("socket_listen() failed: " . socket_strerror(socket_last_error($server)));
}

echo "WebSocket server started on {$address}:{$port}\n";

$clients = [$server];
$handshakes = [];

while (true) {
    $read = $clients;
    $write = null;
    $except = null;

    if (socket_select($read, $write, $except, null) === false) {
        die("socket_select() failed: " . socket_strerror(socket_last_error()));
    }

    if (in_array($server, $read)) {
        $client = socket_accept($server);
        if ($client === false) {
            echo "socket_accept() failed: " . socket_strerror(socket_last_error());
        } else {
            $clients[] = $client;
            echo "New client connected.\n";
        }
        $key = array_search($server, $read);
        unset($read[$key]);
    }

    foreach ($read as $clientSocket) {
        $buffer = '';
        $bytes = @socket_recv($clientSocket, $buffer, 2048, 0);

        if ($bytes === false || $bytes === 0) {
            echo "Client disconnected.\n";
            $key = array_search($clientSocket, $clients);
            unset($clients[$key]);
            unset($handshakes[spl_object_id($clientSocket)]);
            socket_close($clientSocket);
            continue;
        }

        $clientId = spl_object_id($clientSocket);

        if (!isset($handshakes[$clientId])) {
            performHandshake($clientSocket, $buffer);
            $handshakes[$clientId] = true;
            echo "Handshake completed with client.\n";
        } else {
            $decodedData = decode($buffer);
            if (!$decodedData) {
                echo "Failed to decode data from client.\n";
                continue;
            }

            $payload = $decodedData['payload'];
            echo "Received message from client: $payload\n";

            // 全クライアントに送信
            foreach ($clients as $targetSocket) {
                if ($targetSocket !== $server) { // サーバー自身は除外
                    $responseText = json_encode([
                        'clientId' => $clientId,
                        'message'  => $payload,
                    ]);

                    $responseText = json_encode([
                        'type' => 'message',
                        'body' => "Client xxxxxxxx{$clientId}: {$payload}", // `body` を文字列形式で統一
                    ]);

                    // デコード済みのデータから 'message' 部分を抽出
                    $decodedPayload = json_decode($payload, true);
                    $message = isset($decodedPayload['message']) ? $decodedPayload['message'] : 'No message';

                    // サーバーから送信するデータを整形
                    $responseText = json_encode([
                        'type' => 'message',
                        'body' => "Client {$clientId}: {$message}", // 'message' 部分のみを挿入
                    ]);

                    $response = encode($responseText);
                    socket_write($targetSocket, $response, strlen($response));
                }
            }
        }
    }
}

socket_close($server);

function performHandshake($client, $headers)
{
    if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $headers, $matches)) {
        $key = trim($matches[1]);
    } else {
        socket_close($client);
        return;
    }

    $acceptKey = base64_encode(
        pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'))
    );

    $upgradeHeader = "HTTP/1.1 101 Switching Protocols\r\n" .
                     "Upgrade: websocket\r\n" .
                     "Connection: Upgrade\r\n" .
                     "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";

    socket_write($client, $upgradeHeader, strlen($upgradeHeader));
}

function decode($data)
{
    $payloadLength = ord($data[1]) & 127;
    $mask = '';
    $payloadOffset = 2;

    if ($payloadLength === 126) {
        $mask = substr($data, 4, 4);
        $payloadOffset = 8;
        $payloadLength = unpack('n', substr($data, 2, 2))[1];
    } elseif ($payloadLength === 127) {
        $mask = substr($data, 10, 4);
        $payloadOffset = 14;
        $payloadLength = unpack('J', substr($data, 2, 8))[1];
    } else {
        $mask = substr($data, 2, 4);
        $payloadOffset = 6;
    }

    $payloadData = substr($data, $payloadOffset);

    $unmaskedPayload = '';
    for ($i = 0; $i < $payloadLength; $i++) {
        $unmaskedPayload .= $payloadData[$i] ^ $mask[$i % 4];
    }

    return [
        'type'    => ord($data[0]) & 0x0F,
        'payload' => $unmaskedPayload,
    ];
}

function encode($payload, $type = 'text', $masked = false)
{
    $frameHead = [];
    $payloadLength = strlen($payload);

    switch ($type) {
        case 'text':
            $frameHead[0] = 129;
            break;
        case 'close':
            $frameHead[0] = 136;
            break;
        case 'ping':
            $frameHead[0] = 137;
            break;
        case 'pong':
            $frameHead[0] = 138;
            break;
        default:
            $frameHead[0] = 129;
            break;
    }

    if ($payloadLength <= 125) {
        $frameHead[1] = ($masked ? 128 : 0) | $payloadLength;
    } elseif ($payloadLength <= 65535) {
        $frameHead[1] = ($masked ? 128 : 0) | 126;
        $frameHead[2] = ($payloadLength >> 8) & 255;
        $frameHead[3] = $payloadLength & 255;
    } else {
        $frameHead[1] = ($masked ? 128 : 0) | 127;
        for ($i = 0; $i < 8; $i++) {
            $frameHead[2 + $i] = ($payloadLength >> (56 - $i * 8)) & 255;
        }
    }

    $frame = '';
    foreach ($frameHead as $byte) {
        $frame .= chr($byte);
    }

    $frame .= $payload;

    return $frame;
}

