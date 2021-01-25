<?php 

class Chain {

	const BLOCK_GENERATION_INTERVAL = 120;     //by seconds, btc set 10 min
	const DIFFICULTY_ADJUSTMENT_INTERVAL = 10; //by block number, btc set 2016 blocks
	const COINBASE_AMOUNT = 1000000;           //reward, btc set 12.5
	const MAX_BLOCK_DATA_SIZE = 1000000;       //by bytes, btc set 1,000,000 (1MB)
	const AMOUNT_DECIMAL_POINT = 8;
	const AMOUNT_INTEGER_LEN = 9;
	const MAX_SUPPLY = 210000000;
	const ADDRESS_PREFIX = "17";                 //hex, represent A
	const MAGIC_BYTES = "@T|AS";
	const INITIAL_CHAIN_WORK = "4295032833"; #or 100010001 in hex
	
	public function __construct() {
		
	}
	
	public function start() {

		//check genesis block
		$r = DB::query("SELECT blockIndex FROM `blocks` ORDER BY blockIndex ASC LIMIT 1");
		$total = mysqli_num_rows($r);
		@mysqli_free_result($r);
		if (!$total) {
			$genesisBlock = self::getGenesisBlock();
			if (Consensus::isValidBlockTxs($genesisBlock)) {
				DB::beginTransaction();
				
				$this->insertBlock($genesisBlock->blockIndex,$genesisBlock->previousHash,$genesisBlock->timestamp,$genesisBlock->data, $genesisBlock->hash, $genesisBlock->difficulty,$genesisBlock->target,$genesisBlock->chainWork,$genesisBlock->nonce  );
				
				DB::query($sql = "INSERT INTO `fork` SET status='active',chainWork='".$genesisBlock->chainWork."', branchStartAt='".$genesisBlock->hash."', lastFork='".$genesisBlock->hash."', lastBlockIndex='".$genesisBlock->blockIndex."', lastBlockHash='".$genesisBlock->hash."'");
				
				$newForkId = (int)DB::insertID();
				
				Utxo::updateUnspentTxOuts($genesisBlock, $newForkId);
				
				DB::commit();
			} 
		}
		
		//peer discovery
		$peers = Utils::config("addNodes");
		$peers = explode(",", $peers);
	
		foreach($peers as $peer ) {
			try {
				DB::query("INSERT INTO `peers` SET `host`='".DB::esc($peer)."',`lastUpdateDate`='".date("Y-m-d H:i:s")."'");
			} catch (Exception $e) {}
		}
		
		//start server
		//listen for connection
		Network::runServer(Utils::config("runAs"), array($this, "handleRequest"));
		
	}
	
	public function maintainSystem($req) {
		
		$chain = self::getLongestChain();
		
		if ($chain['status'] != 'active') {
			DB::query("UPDATE `fork` SET `status`='valid-fork' WHERE `id`!='".DB::esc($chain['id'])."' AND status='active'");
			DB::query("UPDATE `fork` SET `status`='active' WHERE `id`='".DB::esc($chain['id'])."'");
		} 
		
		$worldVars = Utils::world();
		
		foreach($worldVars as $taskName=>$taskDetails) {
			
			if (preg_match("@^systemTask\.@", $taskName)) {
				//if has running task
				if ($taskDetails) {
					//kill if task idle too long
					if (time() > $taskDetails['lastTimestamp'] + $taskDetails['lifeTime'] ) {
						Utils::world($taskName, []); //kill task
						Utils::printOut("Task `{$taskName}` has been killed");
					} else {
						Utils::printOut("Task `{$taskName}` still running");
					}
				}
			}
		}
	}
	
	public function handleHeaders($req) {
		
		if (strlen($req['myIp'])) {
			if (filter_var($req['myIp'], FILTER_VALIDATE_IP)) {
				if ($req['myIp'] != Utils::config("runAs")) {
					try {
						//here we dun do handshake, just accept for simplicity
						DB::query("INSERT INTO peers SET host='".DB::esc($req['myIp'])."', lastUpdateDate='".date("Y-m-d H:i:s")."'");
					} catch (Exception $e) {}
				}
			}
		}
	}
	
	public function handleRequest($req) {
		$response = [];
		$maxRecords = 50;
		
		//maintain system
		$this->maintainSystem($req);
		
		//handle headers
		$this->handleHeaders($req);
		
		try {
			switch($req['query']) {
				
				case 'getPeers':
					$sql = "SELECT * FROM peers";
					$r = DB::query($sql);
					$peers = [];
					while($row = mysqli_fetch_assoc($r)) {
						$peers[] = $row['host'];
					}
					
					$response = $peers;
				break;
				
				case 'generateAddress':
					$result =  Address::newAddress();
					$response = ["privateKey"=>$result['privateKey'], "publicKey"=>$result['publicKey'], "address"=>$result['address']];
				break;
				
				case 'getBlockTemplate': 
					$blockTemplate = $this->getBlockTemplate($req['minerAddress']);
					$response = $blockTemplate;
				break;
				
				case 'addBlock':
					
					$newBlock = Block::parse($req['block']);
					
					$this->addBlock($newBlock);
					
					$latestBlock = Chain::getLatestBlock();
					Network::broadcast(['query'=>"P2P-sendYouBlocks", "myIp"=>Utils::config("runAs"), "myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, "blocks"=>[$newBlock]]);

				break;
				
				case 'getUtxos':
					$chain = self::getLongestChain();
					
					$start = isset($req['start']) ? (int)$req['start'] : null;
					$limit = isset($req['limit']) ? (int)$req['limit'] : $maxRecords;
					$limit = $limit >= $maxRecords ? $maxRecords : $limit;
					$address = trim($req['address']);
					$orderby = $req['orderby'];
					$where = $address ? "address ='".DB::esc($address)."'" : null;
					$unspentTxOuts = Utxo::getAll($chain['id'],$where, $start, $limit, $orderby);
					$response = $unspentTxOuts;
				break;
				
				case 'transaction':
					$chain = self::getLongestChain();
					$txId = $req['txId'];
					$tx = null;
					
					if (($row = Transaction::isTxInActiveChain($txId))!==false) {
						
						//check is in active fork
						$row = mysqli_fetch_assoc(DB::query("SELECT * FROM `blocks` WHERE `hash`='".DB::esc($row['blockHash'])."' LIMIT 1"));
											
						$thisBlock = Block::parse($row);
						$txs = $thisBlock->data;
						
						$tx = Utils::arrayFind($txs,function($thisTx) use ($txId) { return $thisTx->id == $txId; });
						
						if ($tx!==null) {
							//add tx's extra info
							$tx->blockIndex = $thisBlock->blockIndex;
							$count = Chain::totalBlocks();
							$tx->confirmations = (($count-1) - $thisBlock->blockIndex) + 1;
							$tx->confirmedTs = $thisBlock->timestamp;
							
							$tx->txIns = array_map(function($thisTxIn) { 
								$address = @DB::result($r = DB::query("SELECT address FROM `blockTxOuts` WHERE txId='{$thisTxIn->txOutId}' AND txOutIndex='{$thisTxIn->txOutIndex}' LIMIT 1"),0,0);

								$thisTxIn->address = $address;
								
								return $thisTxIn;
							}, $tx->txIns);
							
							
							$tx->txOuts = array_map(function($txOutIndex, $thisTxOut) use ($tx,$chain) { 

								$thisUtxo = Utxo::findUnspentTxOut($tx->id, $txOutIndex,$chain['id']);
								$thisTxOut->hasUnspent = $thisUtxo === false ? 0 : 1;;
								
								return $thisTxOut;
							}, array_keys($tx->txOuts),$tx->txOuts);
							
						}
						
					} else {
						
						$r = DB::query($sql="SELECT * FROM `transactionPool` WHERE txId = '".DB::esc($txId)."' LIMIT 1");
						
						if($row = mysqli_fetch_assoc($r)) {
							$row['id'] = $row['txId'];
							$tx = Transaction::parse($row);
							
							$tx->confirmations = 0;
							$tx->blockIndex = 0;
							
							$tx->txIns = array_map(function($thisTxIn,$chain) { 
								//add txIn's extra info
								$thisUtxo = Utxo::findUnspentTxOut($thisTxIn->txOutId, $thisTxIn->txOutIndex, $chain['id']);
								
								if ($thisUtxo!==false) {
									$thisTxIn->address = $thisUtxo->address;
								}
								
								return $thisTxIn;
							}, $tx->txIns);
						} 
					}
					
					@mysqli_free_result($r);
					
					if ($tx) {
						$response = $tx; 
					}
				break;
				
				case 'blockhash': 
				case 'block': 

					$chain = self::getLongestChain();
					
					$count = Chain::totalBlocks();
					
					$index = (int)$req['blockIndex'];
					$hash = $req['blockHash'];
					if ($hash) {
						$r = DB::query("SELECT * FROM `blocks` WHERE hash='".DB::esc($hash)."' LIMIT 1");
						$block = mysqli_fetch_assoc($r);
					} else {
						$block = Block::isBlockIndexInActiveChain($index);
					}
					
					if ($block) {
						$block = Block::parse($block);
						
						$block->data = array_map(function($thisTx) use ($block,$count, $chain) { 
							//add tx's extra info
							$thisTx->blockIndex = $block->blockIndex;
							$thisTx->confirmedTs = $block->timestamp;
							$thisTx->confirmations = (($count-1) - $thisTx->blockIndex) + 1;
							
							$thisTx->txIns = array_map(function($thisTxIn) { 
								$address = @DB::result($r = DB::query("SELECT address FROM `blockTxOuts` WHERE txId='{$thisTxIn->txOutId}' AND txOutIndex='{$thisTxIn->txOutIndex}' LIMIT 1"),0,0);

								$thisTxIn->address = $address;
								
								return $thisTxIn;
							}, $thisTx->txIns);
							
							
							$thisTx->txOuts = array_map(function($txOutIndex, $thisTxOut) use ($thisTx, $chain) { 

								$thisUtxo = Utxo::findUnspentTxOut($thisTx->id, $txOutIndex,$chain['id']);
								$thisTxOut->hasUnspent = $thisUtxo === false ? 0 : 1;;
								
								return $thisTxOut;
							}, array_keys($thisTx->txOuts),$thisTx->txOuts);
								
							return $thisTx; 
						}, $block->data);
						
						@mysqli_free_result($r);
						
						$response = $block; 
					}
				break;
					
				case 'blocks':
					$start = isset($req['start']) ? (int)$req['start'] : null;
					$limit = isset($req['limit']) ? (int)$req['limit'] : $maxRecords;
					$limit = $limit >= $maxRecords ? $maxRecords : $limit;
					
					if (is_numeric($limit) AND is_numeric($start)) {
						$limitSQL = " LIMIT {$start},{$limit}";
					} else if (is_numeric($limit)) {	
						$limitSQL = " LIMIT {$limit}";
					} else {
						$limitSQL = "";
					}
					
					if ($req['orderby'] == "index ASC") {
						$orderbySQL = "ORDER BY blockIndex ASC";
					} else if ($req['orderby'] == "index DESC") {
						$orderbySQL = "ORDER BY blockIndex DESC";
					} else {
						$orderbySQL = "ORDER BY blockIndex ASC";
					}
					
					$r = DB::query("SELECT * FROM `blocks` {$orderbySQL}{$limitSQL}");
		
					$chains = array();
					while($row = mysqli_fetch_assoc($r)) {
						$chains[] = Block::parse($row);
					}
					
					@mysqli_free_result($r);
					$response = $chains; 
				break;

				case 'getRawTx':
				
					$chain = self::getLongestChain();
					
					$inputs = Utils::jsonDecode($req['inputs']);
					if (json_last_error()!==JSON_ERROR_NONE) {
						throw new Exception("getRawTx: inputs json error", 111);
					}
					
					$outputs = Utils::jsonDecode($req['outputs']);
					if (json_last_error()!==JSON_ERROR_NONE) {
						throw new Exception("getRawTx: outputs json error", 111);
					}
					
					$txOuts = array();	
					$outputAmount = 0;
					foreach($outputs as $k=>$output) {
						
						if (!Utils::isValidDecimal($output[1])) {
							throw new Exception("getRawTx: output[$k] amount error", 111);
						} else {
							$outputAmount = Utils::safeAdd($outputAmount,$output[1]);
							$txOuts[] = new TxOut($output[0], $output[1], $output[2]);
						}
					}

					$utxos = [];
					$utxoAmount = 0;
					foreach($inputs as $k=>$input) {
						
						$myUnspentTxOut = Utxo::findUnspentTxOut($input[0],$input[1], $chain['id']);
						if (!$myUnspentTxOut) {
							throw new Exception("getRawTx: utxo({$input[0]},{$input[1]}) not found", 111);
						} else {
							$utxos[] = $myUnspentTxOut;
							$utxoAmount = Utils::safeAdd($utxoAmount,$myUnspentTxOut->amount);
						}
					}
							
					if (Utils::safeComp($utxoAmount,$outputAmount) < 0) {
						throw new Exception("getRawTx: Total output amounts should not larger than total input amounts", 111);
					}
					
					$unsignedTxIns = array_map(function($unspentTxOut) {$txIn = new TxIn(); $txIn->txOutId = $unspentTxOut->txOutId; $txIn->txOutIndex = $unspentTxOut->txOutIndex;return $txIn;},$utxos);
					
					$tx = new Transaction();
					$tx->txIns = $unsignedTxIns;
					$tx->txOuts = $txOuts;
					$tx->id = Transaction::getTransactionId($tx);
					$tx->timestamp  = date("Y-m-d H:i:s");
					$tx->txFees = Transaction::calcTxFees($tx->txIns, $tx->txOuts,$chain['id']);
					
					//finally, sign this transaction :)
					$tx->txIns = array_map(
									function($txInIndex, $txIn) use ($tx, $inputs) { 
										$privateKey = $inputs[$txInIndex][2];
										$txIn->signature = Transaction::sign($tx->id,$privateKey );
										return $txIn;  
									},array_keys($unsignedTxIns), $unsignedTxIns
								);
								
					$response = $tx;
					
				break;
				
				case 'pushTx':
					
					$chain = self::getLongestChain();
					
					$rawTx = Utils::jsonDecode($req['rawTx']);
					if (json_last_error()!==JSON_ERROR_NONE) {
						throw new Exception("pushTx: tx json error", 111);
					}
					
					$tx = Transaction::parse($rawTx);
					TransactionPool::addToTransactionPool($tx, $chain['id']);
					Utils::printOut("[pushTx][Success] added in tx pool, tx id: {$tx->id}");
					
					$latestBlock = Chain::getLatestBlock();
					Network::broadcast(['query'=>"P2P-addTxPool", "myIp"=>Utils::config("runAs"), "myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, 'txs'=>[$tx] ]);
						
					$response = $tx;
				break;
				
				
				case 'getTransactionPool':
					$chain = self::getLongestChain();
					
					$start = isset($req['start']) ? (int)$req['start'] : null;
					$limit = isset($req['limit']) ? (int)$req['limit'] : $maxRecords;
					$limit = $limit >= $maxRecords ? $maxRecords : $limit;
					$orderby = $req['orderby'];
					
					$pooledTxs = TransactionPool::getAll($start, $limit, $orderby);
					
					foreach($pooledTxs as $pooledTx) {
						$pooledTx->txIns = array_map(function($thisTxIn) use ($chain) { 
							$thisUtxo = Utxo::findUnspentTxOut($thisTxIn->txOutId, $thisTxIn->txOutIndex, $chain['id']);
							
							if ($thisUtxo!==false) {
								$thisTxIn->address = $thisUtxo->address;
							}
							
							return $thisTxIn;
						}, $pooledTx->txIns);
					}
					
					$response = $pooledTxs;
				break;
				
				case 'balance':
					$chain = self::getLongestChain();
					$response = ["result"=>Address::getBalance($req['address'],$chain['id'])];
				break;
				
				case 'addressTx':
				
					$chain = self::getLongestChain();
					
					$start = isset($req['start']) ? (int)$req['start'] : null;
					$limit = isset($req['limit']) ? (int)$req['limit'] : $maxRecords;
					$limit = $limit >= $maxRecords ? $maxRecords : $limit;
					$list = [];
					
					if (is_numeric($limit) AND is_numeric($start)) {
						$limitSQL = " LIMIT {$start},{$limit}";
					} else if (is_numeric($limit)) {	
						$limitSQL = " LIMIT {$limit}";
					} else {
						$limitSQL = "";
					}
					
					$orderbySQL = "ORDER BY blockIndex DESC";
					$address = $req['address'];
					
					$r = DB::query($sql="SELECT txId, blockIndex FROM `blockTxIns` WHERE address='".DB::esc($address)."' UNION DISTINCT SELECT txId,blockIndex from blockTxOuts WHERE address='".DB::esc($address)."' {$orderbySQL}{$limitSQL}");
					
					$count = Chain::totalBlocks();
				
					while($row = mysqli_fetch_assoc($r)) {
						
						$row2 = mysqli_fetch_assoc(DB::query("SELECT * FROM `blocks` WHERE blockIndex='".DB::esc($row['blockIndex'])."' LIMIT 1"));
						$thisBlock = Block::parse($row2);
						
						$tx = Utils::arrayFind($thisBlock->data, function($thisTx) use ($row) {
							
							if ($thisTx->id == $row['txId']) {
								return true;
							} else {
								return false;
							}
						});
						
						//add tx's extra info
						$tx->blockIndex=$thisBlock->blockIndex;
						$tx->confirmedTs=$thisBlock->timestamp ;
						$tx->confirmations = (($count-1) - $thisBlock->blockIndex) + 1;
						
						$tx->txIns = array_map(function($thisTxIn) { 
								$address = @DB::result($r = DB::query("SELECT address FROM `blockTxOuts` WHERE txId='{$thisTxIn->txOutId}' AND txOutIndex='{$thisTxIn->txOutIndex}' LIMIT 1"),0,0);

								$thisTxIn->address = $address;
								
								return $thisTxIn;
							}, $tx->txIns);
							
							
						$tx->txOuts = array_map(function($txOutIndex, $thisTxOut) use ($tx, $chain) { 

								$thisUtxo = Utxo::findUnspentTxOut($tx->id, $txOutIndex, $chain['id']);
								$thisTxOut->hasUnspent = $thisUtxo === false ? 0 : 1;;
								
								return $thisTxOut;
							}, array_keys($tx->txOuts),$tx->txOuts);
						
						$list[$row['txId']] = $tx;
					}
					
					@mysqli_free_result($r);

					$response = $list; 
					
				break;
				
				
				case 'P2P-addTxPool':
				
					$chain = self::getLongestChain();
					$txs = $req['txs'];

					if (!$txs) {
						Utils::printOut('[P2P-addTxPool][Info] no tx received');
					} else {
						foreach($txs as $tx) {
							
							$tx = Transaction::parse($tx);
							
							try {
								TransactionPool::addToTransactionPool($tx,$chain['id']);
								Utils::printOut("[P2P-addTxPool][Success] added, tx id: {$tx->id}");
								
							} catch (Exception $e) {
								Utils::printOut($e->getMessage());
								DB::rollback();
							}
						}
					}
				break;
				
				case "P2P-sendYouPeer":
					
					$peers = $req['peers'];
					
					foreach($peers as $peer ) {
						if ($peer != Utils::config("runAs")) {
							try {
								DB::query("INSERT INTO peers SET host='".DB::esc($peer)."', lastUpdateDate='".date("Y-m-d H:i:s")."'");
							} catch (Exception $e) {}
						}
					}
					
				break;
				
				case "P2P-sendMePeer":
					$sql = "SELECT * FROM peers";
					$r = DB::query($sql);
					$peers = [Utils::config("runAs")];
					
					while($row = mysqli_fetch_assoc($r)) {
						if ($row['host'] != $req['myIp']) {
							$peers[] = $row['host'];
						}
					}

					$latestBlock = Chain::getLatestBlock();
					Network::postToUrl($req['myIp'], ['query'=>"P2P-sendYouPeer", "myIp"=>Utils::config("runAs"),"myBlockIndex"=>$latestBlock->blockIndex, "myBlockHash"=>$latestBlock->hash,"peers"=>$peers], "NO_WAIT");
				break;
				
				case "P2P-sendMeBlocks":
					
					$searchHash = $req['searchHash'];
					$searchType = $req['searchType'];// forward | backward
					$myQuery = $req['myQuery'] ? $req['myQuery'] : "P2P-sendYouBlocks";
					$blockHeaderOnly = is_bool($req['blockHeaderOnly']) ? $req['blockHeaderOnly'] : false;
					
					$returnBlocks = 2;
					
					$blocks = [];
					
					if ($searchType == "forward") {
						$searchHashes = [$searchHash];
						while(@count($searchHashes) > 0 AND @count($blocks) < $returnBlocks) {
							$r = DB::query("SELECT * FROM `blocks` WHERE previousHash IN ('".@Implode("','", $searchHashes)."')");
							$searchHashes = [];
							while($block = mysqli_fetch_assoc($r)) {
								$thisBlock = Block::parse($block);
								
								if ($blockHeaderOnly) {
									unset($thisBlock->data);
								}
								
								$blocks[] = $thisBlock;
								$searchHashes[] = $thisBlock->hash;
							}
						}
					} else {
						//searchType = backward
						$r = DB::query("SELECT * FROM `blocks` WHERE hash='".DB::esc($searchHash)."' LIMIT 1");
						$block = mysqli_fetch_assoc($r);
						$block = Block::parse($block);
						
						while(strlen($block->previousHash) > 0 AND @count($blocks) < $returnBlocks) {
							$r = DB::query("SELECT * FROM `blocks` WHERE hash = '".DB::esc($block->previousHash)."'");
							
							$block = mysqli_fetch_assoc($r);
							$block = Block::parse($block);
							
							if ($blockHeaderOnly) {
								unset($thisBlock->data);
							}
								
							$blocks[] = $block;
						}
					}
				
					@mysqli_free_result($r);
					
					$latestBlock = Chain::getLatestBlock();
					Network::postToUrl($req['myIp'], ['query'=>$myQuery,"myIp"=>Utils::config("runAs"),"myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, "blocks"=>$blocks], "NO_WAIT");
				break;
				
				case "P2P-sendYouBlocks":
				
					Utils::printOut("New blocks receive");
					
					$total = @count($req['blocks']);
					$searchHash = "";
					if ($total > 0) {
												
						foreach($req['blocks'] as $block) {
							$block = Block::parse($block);
							try {
								$this->addBlock($block);
							} catch (Exception $e) {
								DB::rollback();
							}
							
							$searchHash = $block->hash;
						}
					}
					
					if ($searchHash) {
						$dlBlockTaskDetails = Utils::world("systemTask.downloadBlocks");
						if ($dlBlockTaskDetails) {
							$dlBlockTaskDetails['lastTimestamp'] = time();
							Utils::world('systemTask.downloadBlocks', $dlBlockTaskDetails);
							
							$latestBlock = Chain::getLatestBlock();
							Network::broadcast(['query'=>"P2P-sendMeBlocks", "myIp"=>Utils::config("runAs"), "myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, "searchHash"=>$searchHash, "searchType"=>"forward"]);
						}
					}
					
				break;
				
				case "P2P-checkFork":
					Utils::printOut("New blocks receive for systemTask.checkFork");
				
					//check has systemTask.checkFork?
					$cfTaskDetails = Utils::world('systemTask.checkFork');
					
					if ($cfTaskDetails) {
						
						$total = @count($req['blocks']);
						
						if ($total > 0) {
							$newCfTaskDetails = $cfTaskDetails;				
							foreach($req['blocks'] as $block) {
								$block = Block::parse($block);
								
								if ($newCfTaskDetails['block']->previousHash == $block->hash) {
									$newCfTaskDetails['block'] = $block;
								}
							}
							
							if ($newCfTaskDetails['block'] != $cfTaskDetails['block']) {
								//check is last check block connect to any block in db
								$r = DB::query("SELECT * FROM `blocks` WHERE hash = '".DB::esc($newCfTaskDetails['block']->previousHash)."'");
								
								$latestBlock = Chain::getLatestBlock();
								
								if(mysqli_num_rows($r)) {
									$block = mysqli_fetch_assoc($r);
									$block = Block::parse($block);
									
									//ok kill systemTask.checkFork
									Utils::world('systemTask.checkFork', []);
									
									//start download task									
									$dlBlockTaskDetails = [];
									$dlBlockTaskDetails['lastTimestamp'] = time();
									$dlBlockTaskDetails['lifeTime'] = 10; //seconds
			
									Utils::world("systemTask.downloadBlocks", $dlBlockTaskDetails);
									
									Network::broadcast(['query'=>"P2P-sendMeBlocks", "myIp"=>Utils::config("runAs"), "myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, "searchHash"=>$block->hash, "searchType"=>"forward"]);
								} else {

									//update task
									$newCfTaskDetails['lastTimestamp'] = time();
									Utils::world('systemTask.checkFork', $newCfTaskDetails);
									
									//send me header only
									Network::broadcast(['query'=>"P2P-sendMeBlocks", "myIp"=>Utils::config("runAs"), "myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, "myQuery"=>"P2P-checkFork", "searchHash"=>$newCfTaskDetails['block']->hash, "searchType"=>"backward", "blockHeaderOnly"=>true]);
										
									
								}
							}
						}
					}
				break;
			}
		} catch (mysqli_sql_exception $e) {
			Utils::printOut("MYSQL ERROR: " . $e->getMessage());
			DB::rollback();
		} catch (Exception $e) {
			DB::rollback();
			if ($e->getCode() == 111) {
				$response = ['error'=>$e->getMessage()];
			}
		}
	
		return $response;
		
	}
	
	static function totalBlocks() {
		$result = (int)DB::result($r = DB::query("SELECT blockIndex FROM `blocks` ORDER BY blockIndex DESC LIMIT 1"),0,0) + 1;
		return $result;
	}
	
	static function getGenesisTransaction() {
		return new Transaction('d2242cfab411558b6267ad93942686800e679686f9da4639e221ce2e0dc028ac',[new TxIn("",0,"")],[new TxOut("ARTJMsrNdotKGoFYzuqxFGu1WHcE9bwBvo",self::COINBASE_AMOUNT)],"2020-10-23 00:00:00", 0);
	}
	
	static function getGenesisBlock() {
	    return new Block("0", "0", "2020-10-23 00:00:00", [self::getGenesisTransaction()], "84cebee70b89038100b5b8c779ef995a57933589f863f4786243ba1960cf76db","1","00ffff0000000000000000000000000000000000000000000000000000000000", "0000000000000000000000000000000000000000000000000000000100010001", "0");
		
	}

	static function getLatestBlock() {
		
		$chain = self::getLongestChain();
		
		$r = DB::query("SELECT * FROM `blocks` WHERE hash='".DB::esc($chain['lastBlockHash'])."' LIMIT 1");
		$latestBlock = mysqli_fetch_assoc($r);
		$latestBlock = Block::parse($latestBlock);
		
		return $latestBlock;
	}
	
	public function insertBlock($blockIndex, $previousHash, $timestamp, $data, $hash, $difficulty,$target, $chainWork, $nonce) {
		DB::query($sql = "INSERT INTO blocks SET 
				  blockIndex   = '".DB::esc($blockIndex)."', 
				  previousHash = '".DB::esc($previousHash)."',
				  timestamp    = '".DB::esc($timestamp)."',
				  data         = '".DB::esc(Utils::jsonEncode($data))."',
				  hash         = '".DB::esc($hash)."',
				  difficulty   = '".DB::esc($difficulty)."',	
				  target       = '".DB::esc($target)."',	
				  chainWork    = '".DB::esc($chainWork)."',	
				  nonce        = '".DB::esc($nonce)."'
				  ");
		
		if (@count($data)) {
			foreach($data as $tx) {
				DB::query("INSERT INTO blockTxs SET 
					  txId         = '".DB::esc($tx->id)."', 
					  blockHash  = '".DB::esc($hash)."',
					  blockIndex = '".DB::esc($blockIndex)."',
					  timestamp  = '".DB::esc($tx->timestamp)."',
					  txFees     = '".DB::esc($tx->txFees)."'
					  ");
					  

				$multiSQL = "";
				if (@count($tx->txIns)) {
					foreach($tx->txIns as $txIn) {
						
						$address = @DB::result($r = DB::query("SELECT address FROM `blockTxOuts` WHERE txId='{$txIn->txOutId}' AND txOutIndex='{$txIn->txOutIndex}' LIMIT 1"),0,0);
						$multiSQL .= "(
									'".DB::esc($address)."', 
									'".DB::esc($tx->id)."', 
									'".DB::esc($hash)."',
									'".DB::esc($blockIndex)."', 
									'".DB::esc($txIn->txOutId)."',
									'".DB::esc($txIn->txOutIndex)."'),";
					}
				}
				
				$multiSQL = rtrim($multiSQL,",");
				if ($multiSQL) {
					$multiSQL = "INSERT INTO `blockTxIns` (`address`,`txId`,`blockHash`,`blockIndex`,`txOutId`,`txOutIndex`) VALUES {$multiSQL}";
					DB::query($multiSQL);
					
				} 
				
				$multiSQL = "";
				if (@count($tx->txOuts)) {
					foreach($tx->txOuts as $txOutIndex=>$txOut) {
						$multiSQL .= "(
									'".DB::esc($txOutIndex)."', 
									'".DB::esc($tx->id)."', 
									'".DB::esc($hash)."',
									'".DB::esc($blockIndex)."', 
									'".DB::esc($txOut->address)."',
									'".DB::esc($txOut->amount)."'
									),";
					}
				}
				
				$multiSQL = rtrim($multiSQL,",");
				if ($multiSQL) {
					$multiSQL = "INSERT INTO `blockTxOuts` (`txOutIndex`,`txId`,`blockHash`,`blockIndex`,`address`,`amount`) VALUES {$multiSQL}";
					DB::query($multiSQL);
					
				} 
			}
		}
	}
	
	public function addBlock($block) {
		
		$r = DB::query("SELECT * FROM `blocks` WHERE hash='".DB::esc($block->previousHash)."' LIMIT 1");
		$parentBlock = mysqli_fetch_assoc($r);
		$parentBlock = Block::parse($parentBlock);
		
		if (!Consensus::isValidNewBlock($block, $parentBlock)) {
			
			if (!Consensus::isValidNewBlock($block)) { 
				throw new Exception("[addBlock][Failure] invalid new block", 111);
			} else {
				
				DB::query($sql = "INSERT INTO `fork` SET status='valid-headers', chainWork='', lastFork='', branchStartAt='', lastBlockIndex='".DB::esc($block->blockIndex)."',lastBlockHash='".DB::esc($block->hash)."'");
				
				$latestBlock = self::getLatestBlock();
				
				//only 1 system task at the time for simplicity
				if (!Utils::world('systemTask.checkFork')) {
					if (!Utils::world('systemTask.downloadFork')) {
						if ($block->blockIndex > $latestBlock->blockIndex) {
							$r = DB::query("SELECT * FROM `fork` WHERE `lastBlockIndex`='".DB::esc($block->blockIndex)."' LIMIT 1");
							$validHeader = mysqli_fetch_assoc($r);
							
							$taskDetails = Utils::world('systemTask.checkFork');
							
							$taskDetails['lastTimestamp'] = time();
							$taskDetails['block'] = $block;
							$taskDetails['lifeTime'] = 5; //seconds
							
							Utils::world('systemTask.checkFork', $taskDetails);
							
							//send me header only
							Network::broadcast(['query'=>"P2P-sendMeBlocks", "myIp"=>Utils::config("runAs"), "myBlockIndex"=>$latestBlock->blockIndex,"myBlockHash"=>$latestBlock->hash, "myQuery"=>"P2P-checkFork", "searchHash"=>$block->hash, "searchType"=>"backward", "blockHeaderOnly"=>true]);
													
						} 
					}
				}
				throw new Exception("[addBlock][Failure] invalid new block but valid header", 111);
			}
		} 
		
		DB::beginTransaction();
		
		$forkId = self::createIfNewFork($block);
		
		if (!$forkId) {
			$forkId = self::getFork($block);
		}
		
		if (!Consensus::isValidBlockTxs($block, $forkId)) {				
			throw new Exception("[addBlock][Failure] invalid block txs", 111);			
		} else {
			$this->insertBlock($block->blockIndex,$block->previousHash,$block->timestamp,$block->data, $block->hash, $block->difficulty,$block->target,$block->chainWork,$block->nonce);
			
			Utxo::updateUnspentTxOuts($block, $forkId);
			TransactionPool::updateTransactionPool($block);
			
			self::updateFork($forkId, $block);	
		}
		
		DB::commit();
	}
	
	public function getBlockTemplate($minerAddress) {
		//generate next block template
		
		$latestBlock = self::getLatestBlock();
		$nextIndex = $latestBlock->blockIndex + 1;
		$pooledTxs = TransactionPool::getAll();
		
		//sort tx fees desc, miner always interest to higher tx fees
		uasort($pooledTxs, 
			function($a,$b) { 
				return Utils::safeComp($a->txFees,$b->txFees) === -1 ? 1 : -1;
			}
		);
		
		//dummy coinbase tx
		$coinbaseTx  = Transaction::getCoinbaseTransaction($minerAddress, $nextIndex, "99999999.99999999");
		$blockData = [$coinbaseTx];
		$txIndex = 0;
		$txFees = "0";
		while(isset($pooledTxs[$txIndex]) AND strlen(Utils::jsonEncode($blockData)) <= self::MAX_BLOCK_DATA_SIZE) {
			
			$blockData[] = $pooledTxs[$txIndex];
			
			//add tx fees into coinbase tx
			$txFees = Utils::safeAdd($txFees, $pooledTxs[$txIndex]->txFees);
			
			$txIndex++;
		}
		
		array_shift($blockData); //remove dummy coinbase tx
		$coinbaseTx  = Transaction::getCoinbaseTransaction($minerAddress, $nextIndex,$txFees);
		array_unshift($blockData, $coinbaseTx); //readd coinbase tx with correct reward
		
		$difficulty = self::getDifficulty($latestBlock);
		$targetDec = self::getTarget($latestBlock, $difficulty);
		$targetHex = Utils::leftPadding(Utils::bcdechex($targetDec), 64);
		
	    $nextTimestamp = date("Y-m-d H:i:s");
		
		$chainWork = self::getChainWork($latestBlock, $difficulty);
		
		return new Block($nextIndex, $latestBlock->hash, $nextTimestamp, $blockData, "",$difficulty, $targetHex, $chainWork, "");
		
	}
	
	static function solvePOW($index,$previousHash,$timestamp,$data,$targetHex,$nonce) {
		
		$targetDec = Utils::bchexdec($targetHex);
	
		$hash = Block::calculateHash($index,$previousHash,$timestamp, $data, $targetHex, $nonce);
		$hashDec = Utils::bchexdec($hash);
		if (Consensus::isHashMatchTarget($hashDec, $targetDec)) {
			return true;
		} 
		
		return false;
	}
	
	static function getTarget($latestBlock,$difficulty) {
		
		if (Utils::safeComp($latestBlock->difficulty, $difficulty) == 0) {
			$targetDec = Utils::bchexdec($latestBlock->target);
		} else {
			$targetDec = Utils::safeMul(Utils::bchexdec($latestBlock->target), $difficulty);
		}
		
		return $targetDec;
	}
	
	static function getDifficulty($latestBlock) {

		if ($latestBlock->blockIndex % self::DIFFICULTY_ADJUSTMENT_INTERVAL == 0 && $latestBlock->blockIndex != 0) {
			$difficulty = static::getAdjustedDifficulty($latestBlock);
		} else {
			$difficulty = $latestBlock->difficulty;
		}
		
		return $difficulty;
	}
	
	static function getAdjustedDifficulty($latestBlock) {
		
		$prevAdjustmentBlock = null;
		$searchBlockHash = $latestBlock->previousHash;
		$i = 1;
		while($searchBlockHash!= "0" AND $i < self::DIFFICULTY_ADJUSTMENT_INTERVAL - 1) {
			$r = DB::query("SELECT * FROM `blocks` WHERE hash = '".DB::esc($searchBlockHash)."'");
			$prevAdjustmentBlock = mysqli_fetch_assoc($r);
			$prevAdjustmentBlock = Block::parse($prevAdjustmentBlock);
			$searchBlockHash = $prevAdjustmentBlock->previousHash;
			$i++;
		}
		@mysqli_free_result($r);
		
		$timeExpected = self::BLOCK_GENERATION_INTERVAL * self::DIFFICULTY_ADJUSTMENT_INTERVAL;
		$timeTaken = strtotime($latestBlock->timestamp) - strtotime($prevAdjustmentBlock->timestamp);
		
		$newDifficulty = $timeTaken / $timeExpected;
		$newDifficulty = Utils::safeDiv($newDifficulty, "1"); //turn float into string
		
		return $newDifficulty;
		
	}
	
	static function getChainWork($latestBlock,$difficulty) {

		$lastChainWork = Utils::bchexdec($latestBlock->chainWork);
		$chainWork = Utils::safeMul(self::INITIAL_CHAIN_WORK, $difficulty);
		$chainWork = Utils::safeAdd($lastChainWork, $chainWork);
		$chainWork = Utils::leftPadding(Utils::bcdechex($chainWork),64);
		
		return $chainWork;
	}
	
	static function getReward($blockIndex) {
		
		$rewardsUpToPrevBlock = Utils::safeMul(self::COINBASE_AMOUNT, $blockIndex);
		$rewardsUpToPrevBlock = $rewardsUpToPrevBlock == "0" ? self::COINBASE_AMOUNT : $rewardsUpToPrevBlock;
		
		if (Utils::safecomp($rewardsUpToPrevBlock, Chain::MAX_SUPPLY) >= 0 ) {
			$reward = "0";
		} else {
			$reward = Utils::safesub(Chain::MAX_SUPPLY, $rewardsUpToPrevBlock);
			$reward = Utils::safeMin($reward,self::COINBASE_AMOUNT);
		}
		
		return $reward;
	}
	
	static function getFork($block) {
		$parentBlock = $block;
		
		do {
			$r = DB::query("SELECT * FROM `blocks` WHERE hash='".DB::esc($parentBlock->previousHash)."' LIMIT 1");
			$parentBlock = mysqli_fetch_assoc($r);
			$parentBlock = Block::parse($parentBlock);
			
			$r = DB::query("SELECT * FROM `fork` WHERE branchStartAt='".DB::esc($parentBlock->hash)."' LIMIT 1");
			
			if (mysqli_num_rows($r) > 0) {
				$rowFork = mysqli_fetch_assoc($r);
				return $rowFork['id'];
			}
			
		} while($parentBlock->previousHash != "0");
		
		return 0;
	}
	
	static function getLongestChain() {
		$r = DB::query("SELECT * FROM `fork` ORDER BY unhex(chainWork) DESC, id ASC LIMIT 1");
		return mysqli_fetch_assoc($r);
	}
	
	static function updateFork($forkId, $block) {
		DB::query("UPDATE `fork` set chainWork='".DB::esc($block->chainWork)."',lastBlockIndex='".DB::esc($block->blockIndex)."',lastBlockHash='".DB::esc($block->hash)."' WHERE id='".DB::esc($forkId)."' LIMIT 1");
	}
	
	static function createIfNewFork($newBlock) {
		
		//get parent
		$r = DB::query("SELECT * FROM `blocks` WHERE hash='".DB::esc($newBlock->previousHash)."' LIMIT 1");
		$parentBlock = mysqli_fetch_assoc($r);
		$parentBlock = Block::parse($parentBlock);
		
		//check any children from this parent
		$r = DB::query("SELECT * FROM `blocks` WHERE previousHash='".DB::esc($parentBlock->hash)."'");
		$totalChildren = mysqli_num_rows($r);

		if ($totalChildren > 0) {
			
			DB::query("INSERT INTO `fork` SET chainWork='".DB::esc($newBlock->chainWork)."', lastFork='".DB::esc($parentBlock->hash)."', branchStartAt='".DB::esc($newBlock->hash)."', lastBlockIndex='".DB::esc($newBlock->blockIndex)."', lastBlockHash='".DB::esc($newBlock->hash)."'");
			
			$newForkId = (int)DB::insertID();
			
			//build new utxo set for this new fork
			$newUtxoTable = Utxo::getTable($newForkId);
			DB::query("CREATE TABLE {$newUtxoTable} LIKE `unspentTxOuts`");
			
			$removeUtxos = [];
			while($newBlock->previousHash != "0") {
				$r = DB::query("SELECT * FROM `blocks` WHERE hash='".DB::esc($newBlock->previousHash)."' LIMIT 1");
				$newBlock = mysqli_fetch_assoc($r);
				$newBlock = Block::parse($newBlock);
				
				$blockTxs = $newBlock->data;
				
				$addUtxos = array_map(
					function($thisTx) {
						$txId = $thisTx->id;
						return 
							array_map(
								function($index,$txOut) use ($txId) { 
									$newUtxo = new Utxo($txId, $index, $txOut->address, $txOut->amount);
									return $newUtxo ;
								}
							,array_keys($thisTx->txOuts),$thisTx->txOuts);
					}, $blockTxs
				);
				
				//add utxos
				$addUtxos = array_reduce($addUtxos, function($carry, $thisTx) { return array_merge($carry, $thisTx); }, [] );
				
				foreach($addUtxos as $addUtxo) {
					if (Utils::safeComp($addUtxo->amount,0) === 1) {
						
						DB::query("INSERT INTO {$newUtxoTable} SET 
							   txOutId='".DB::esc($addUtxo->txOutId)."',
							   txOutIndex='".DB::esc($addUtxo->txOutIndex)."',
							   address='".DB::esc($addUtxo->address)."',
							   amount='".DB::esc($addUtxo->amount)."',
							   blockHash='".DB::esc($newBlock->hash)."',
							   blockIndex='".DB::esc($newBlock->blockIndex)."'
							   ");
					}
				}
				
				//remove Utxos
				$newRemoveUtxos = array_map(function($t) { return $t->txIns; } ,$blockTxs);
				$newRemoveUtxos = array_reduce($newRemoveUtxos, function($carry, $thisTx) { return array_merge($carry, $thisTx); }, [] );
				$newRemoveUtxos = array_map(function($txIn) { return new Utxo($txIn->txOutId, $txIn->txOutIndex, '', 0); },$newRemoveUtxos);
				
				$removeUtxos = array_merge($removeUtxos, $newRemoveUtxos);
	
				foreach($removeUtxos as $key=>$removeUtxo) {
			
					if (strlen($removeUtxo->txOutId) > 0) {//avoid remove coinbase tx
						$sql = "DELETE FROM {$newUtxoTable} WHERE `txOutId`='".DB::esc($removeUtxo->txOutId)."' AND `txOutIndex`='".DB::esc($removeUtxo->txOutIndex)."'";
						DB::query( $sql );
						
						$affectedRows = DB::affectedRows();
						
						if ($affectedRows > 0) {
							unset($removeUtxos[$key]);
						}
					}
				}
			}

			return $newForkId;
		} else {
			return 0;
		}
	}
}
