<?php
	$host = 'localhost';
	$port = '8080';
	$null = NULL;

	$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
	socket_bind($socket, 0, $port);
	socket_listen($socket);

	$clients = array($socket);

	while (true) {
		$changed = $clients;
		socket_select($changed, $null, $null, 0, 10);

		if (in_array($socket, $changed)) {
			$new_socket = socket_accept($socket);
			$clients[] = $new_socket;

			$header = socket_read($new_socket, 1024);
			perform_handshaking($header, $new_socket, $host, $port);

			socket_getpeername($new_socket, $ip);
			$response = mask(json_encode(array('type' => 'system', 'message' => $ip . ' connected')));
			send_message($response);
		}

		foreach ($changed as $changed_socket) {
			while (socket_recv($changed_socket, $buf, 1024, 0) >= 1) {
				$received_text = unmask($buf);
				$tst_msg = json_decode($received_text);
				$user_name = $tst_msg->username;
				$user_message = $tst_msg->message;

				$response_text = mask(json_encode(array('type' => 'usermsg', 'username' => $user_name, 'message' => $user_message)));
				send_message($response_text);
				break 2;
			}

			$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
			if ($buf === false) {
				$found_socket = array_search($changed_socket, $clients);
				socket_getpeername($changed_socket, $ip);
				unset($clients[$found_socket]);

				$response = mask(json_encode(array'type' => 'system', 'message' => $ip . ' disconnected')));
                send_message($response);
                }
                }
                }socket_close($socket);

                function send_message($msg) {
                    global $clients;
                    foreach($clients as $client) {
                        if ($client !== $socket) {
                            @socket_write($client, $msg, strlen($msg));
                        }
                    }
                }
                
                function unmask($text) {
                    $length = ord($text[1]) & 127;
                    if ($length == 126) {
                        $masks = substr($text, 4, 4);
                        $data = substr($text, 8);
                    }
                    elseif ($length == 127) {
                        $masks = substr($text, 10, 4);
                        $data = substr($text, 14);
                    }
                    else {
                        $masks = substr($text, 2, 4);
                        $data = substr($text, 6);
                    }
                
                    $text = "";
                    for ($i = 0; $i < strlen($data); ++$i) {
                        $text .= $data[$i] ^ $masks[$i%4];
                    }
                    return $text;
                }
                
                function mask($text) {
                    $b1 = 0x80 | (0x1 & 0x0f);
                    $length = strlen($text);
                
                    if ($length <= 125) {
                        $header = pack('CC', $b1, $length);
                    }
                    elseif ($length > 125 && $length < 65536) {
                        $header = pack('CCn', $b1, 126, $length);
                    }
                    elseif ($length >= 65536) {
                        $header = pack('CCNN', $b1, 127, $length);
                    }
                
                    return $header.$text;
                }
                
                function perform_handshaking($receved_header, $client_conn, $host, $port) {
                    $headers = array();
                    $lines = preg_split("/\r\n/", $receved_header);
                    foreach($lines as $line) {
                        $line = chop($line);
                        if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                            $headers[$matches[1]] = $matches[2];
                        }
                    }
                
                    $key = $headers['Sec-WebSocket-Key'];
                    $accept = base64_encode(pack('H*', sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));
                
                    $upgrade = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
                                "Upgrade: websocket\r\n" .
                                "Connection: Upgrade\r\n" .
                                "WebSocket-Origin: $host\r\n" .
                                "WebSocket-Location: ws://$host:$port/\r\n" .
                                "Sec-WebSocket-Accept:$accept\r\n\r\n";
                    socket_write($client_conn, $upgrade, strlen($upgrade));
                }
                
