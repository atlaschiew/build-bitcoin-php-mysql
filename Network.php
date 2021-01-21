<?php

class Network {
	
	static $antiSpam = [];
	
	static function broadcast($data) {
		
		$jsonString = Utils::jsonEncode($data);
		$md5String = md5($jsonString);
		
		if (isset(self::$antiSpam[$md5String])) {
			if (self::$antiSpam[$md5String] + 2 > time()) {
				Utils::printOut("Broadcast denied. Anti spam triggered");
				return false;
			}
		}
		
		self::$antiSpam[$md5String] = time();
		
		Utils::printOut("Broadcast ".$jsonString);
		$sql = "SELECT * FROM peers";
		$r = DB::query($sql);
		while($row = mysqli_fetch_array($r)) {
			if ($row['host'] != Utils::config("runAs"))
			{ 
				$sendResult = self::postToUrl($row['host'].":9981", $data, "NO_WAIT"); 
			}
		}
		
		return true;
	}

	static function runServer($runAs, $callback) {
		$host = $runAs;
		$port = 9981;

		$socket = @stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
		$clients = [];
		stream_set_blocking($socket, 0);//non blocking
		if (!$socket) {
			Throw new Exception("{$errstr} ({$errno})");
		} else {
			Utils::printOut("Server is running on tcp://{$host}:{$port}");
			
			$latestBlock = Chain::getLatestBlock();
			
			//send me new peers
			Network::broadcast(['query'=>"P2P-sendMePeer", "myIp"=>Utils::config("runAs"),"myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash]);
		
			//send me new blocks
			Network::broadcast(['query'=>"P2P-sendMeBlocks", "myIp"=>Utils::config("runAs"), "myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, "searchHash"=>$latestBlock->hash, "searchType"=>"forward"]);
			
			//add download block task
			$taskDetails = [];
			$taskDetails['lastTimestamp'] = time();
			$taskDetails['lifeTime'] = 5; //seconds
			
			Utils::world("systemTask.downloadBlocks", $taskDetails);
			
			while(true) {
				$clientInfo = null;
				
				$conn = @stream_socket_accept($socket, empty($clients) ? -1 : 0, $clientInfo);
				if ($conn!==false) {
					Utils::printOut("Accept connection from {$clientInfo}.");
					$clients[ $clientInfo ] = $conn;
				}

				$writes = $clients;
				$writeResponse = [];
				$reads  = $clients;
				$except = null;
				if (stream_select($reads, $writes, $except, 5)) {
					
					foreach ($reads as $read) {
						
						$peer = stream_socket_get_name($read, true);
						$request = self::freadStream($read);
						if (strlen($request) > 0) {
							Utils::printOut("Receive Request: {$request} from {$peer}");
							
							$request = Utils::jsonDecode($request);
							$response = call_user_func($callback, $request);
							
							if (is_array($response) or is_object($response)) {
								$response = Utils::jsonEncode($response). chain::MAGIC_BYTES;
								$writeResponse[$peer] = $response;
							} else {
								@fclose($read);
								unset($clients[$peer]);
							}
						}
					}
					
					foreach($writes as $write) {
						
						$peer = stream_socket_get_name($write, true);
						if (isset($writeResponse[$peer])) {
							self::fwriteStream($write, $writeResponse[$peer]);
							
							unset($clients[$peer]);
							unset($writeResponse[$peer]);
							@fclose($write);
						}
					}

				}
				
			}
			@fclose($socket);
		}
	}
	
	static function freadStream($handler, $timeoutSec = 30) {
		$ret = "";
		$startTime = microtime(true);
		$magicLen = strlen(chain::MAGIC_BYTES);
		$full = false;
		while(true AND !feof($handler)) {
			$ret .= @stream_get_contents($handler, 1);
			$endTime = microtime(true);
			$timeUsed = $endTime - $startTime;

			if (substr($ret,-$magicLen) == chain::MAGIC_BYTES) {
				$full = true;
				break;
			} else if ($timeUsed >= $timeoutSec) {
				break;
			}
		}

		if ($full) {
			return substr($ret, 0, strlen($ret)-$magicLen);
		} else {
			return "";
		}
	}
		
	static function fwriteStream($fp, $string) {
		for ($written = 0; $written < strlen($string); $written += $fwrite) {
			$fwrite = fwrite($fp, substr($string, $written));
			if ($fwrite === false) {
				return $written;
			}
		}
		return $written;
	}

	static function postToUrl($url, $data, $type = "WAIT_RESPONSE", &$err ) {
		
		$host = $url;
		$port = 9981;
		
		$timeout = 5;
		$sendData = Utils::jsonEncode($data).chain::MAGIC_BYTES;
		$maxFread = 8192; //max bytes per one fread = 8192 bytes
	
		if (($dataLen = strlen($sendData)) > $maxFread) {
			$err = "Oversized send packet detected. Send length is {$dataLen}.";
			return false;
		} else if (!($client = @stream_socket_client("tcp://{$host}:{$port}", $errno, $error,$timeout))) {
			$err = "Error connecting to blockchain. {$error}({$errno})";
			return false;
		} else {
			
			if ($type == 'WAIT_RESPONSE') {
				@self::fwriteStream($client, $sendData);
				$ret = self::freadStream($client,$timeout);
			} else {
				//no wait
				$ret = @self::fwriteStream($client, $sendData);
				
				if ($ret == strlen($sendData)) {
					$ret = true;
				} else {
					$ret = false;
				}
			}
			@fclose($client);
			
			
			return $ret;
		}
		
	}
}
