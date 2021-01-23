<?php

class Transaction {
	
    public $id = "";
    public $txIns = [];
    public $txOuts = [];
	
	public function __construct($id = '', $txIns = [], $txOuts = [],$timestamp="", $txFees = 0) {
		$this->id = $id;
		$this->txIns = $txIns;
		$this->txOuts = $txOuts;
		$this->timestamp = $timestamp;
		$this->txFees = $txFees;
	}
	
	static function isTxInActiveChain($txId) {
		
		$chain = Chain::getLongestChain();
		$targetBlockHash = $chain['lastBlockHash'];
		
		$r = DB::query($sql="SELECT * FROM `blockTxs` WHERE txId = '".DB::esc($txId)."'");
		
		if (mysqli_num_rows($r) == 1) {
			return mysqli_fetch_assoc($r);
		} else {
			//solve conflict
			$chain = Chain::getLongestChain();
			$targetBlockHash = $chain['lastBlockHash'];
			
			$searchHashes = [];
			
			while($blockTxRow = mysqli_fetch_assoc($r)) {
				
				if ( $blockTxRow['blockHash'] == $targetBlockHash ) {
					return $blockTxRow;
				}
				
				$searchHashes = [$blockTxRow['blockHash']];
				
				while(@count($searchHashes) > 0) {
					$r2 = DB::query("SELECT * FROM `blocks` WHERE previousHash IN ('".@Implode("','", $searchHashes)."')");
					$searchHashes = [];
					while($block = mysqli_fetch_assoc($r2)) {
						
						if ( $block['hash'] == $targetBlockHash ) {
							return $blockTxRow;
						}
						
						$searchHashes[] = $block['hash'];
					}
				}
			}
		}
		
		return false;
		
	}
	
	static function parse($arr) {

		if (!(is_array($arr['txIns']) or is_object($arr['txIns']))) {
			$arr['txIns'] = Utils::jsonDecode($arr['txIns']);
		}
		
		if (!(is_array($arr['txOuts']) or is_object($arr['txOuts']))) {
			$arr['txOuts'] = Utils::jsonDecode($arr['txOuts']);
		}
		
		$arr['txIns'] = array_map(function($thisTxIn) { return TxIn::parse($thisTxIn); }, $arr['txIns']);
		$arr['txOuts'] = array_map(function($thisTxOut) { return TxOut::parse($thisTxOut); }, $arr['txOuts']);
		
		return new Transaction(
			$arr['id'], 
			$arr['txIns'], 
			$arr['txOuts'],
			$arr['timestamp'],
			$arr['txFees']
		);
	}
	
	static function calcTxFees($txIns = [], $txOuts = [], $forkId) {
		
		$txInAmount = array_reduce($txIns, function($carry, $thisTxIn) use ($forkId) { return Utils::safeAdd($carry, Utxo::getAmount($thisTxIn->txOutId, $thisTxIn->txOutIndex, $forkId)); }, 0);
		
		$txOutAmount = array_reduce($txOuts, function($carry, $thisTxOut) { return Utils::safeAdd($carry, $thisTxOut->amount); } , 0);
		
		return Utils::safeSub($txInAmount, $txOutAmount);
	}
	
	static function getTransactionId($transaction) {
		
		$txIns = $transaction->txIns;
		$txOuts = $transaction->txOuts;
		
		$txInContent = array_reduce($txIns, function($carry, $thisTxIn) { return $carry. $thisTxIn->txOutId . $thisTxIn->txOutIndex; }, "");
		$txOutContent = array_reduce($txOuts, function($carry, $thisTxOut) { return $carry . $thisTxOut->address . $thisTxOut->amount; } , "");
		
		$id = hash("sha256",$txInContent . $txOutContent);
		
		return $id;
	}
	
	static function sign($dataToSign, $privateKey) {
		
		$private = new PrivateKey($privateKey);
		$wallet = new Wallet($private);
		$wallet->setNetworkPrefix(Chain::ADDRESS_PREFIX);
		$wallet->setNetworkName($networkName = "Bitcoin");
		
		$signature =  $wallet->signMessage($dataToSign);
		
		//extract signature in base64 format
		preg_match_all("#\n-----BEGIN SIGNATURE-----\n(.{0,})\n(.{0,})\n-----END " . strtoupper($networkName) . " SIGNED MESSAGE-----#USi", $signature, $out);
        $signature = $out[2][0];
		
		return $signature;
	}
	
	static function getCoinbaseTransaction($address, $blockIndex) {
		
		$reward =  Chain::getReward($blockIndex);
		
		$t = new Transaction();
		
		$txIn = new TxIn();
		$txIn->signature = '';
		$txIn->txOutId = '';
		$txIn->txOutIndex = $blockIndex;

		$t->txIns = array($txIn);
		$t->txOuts = array(new TxOut($address, $reward));
		$t->id = Transaction::getTransactionId($t);
		$t->timestamp = date("Y-m-d H:i:s");
		
		return $t;
	}
	
}

class TxOut  {
	public $address = '';
	public $amount = 0;
	
    public function __construct($address, $amount) {
        $this->address = $address;
        $this->amount = ($amount);
    }

	static function parse($arr) {
		return new TxOut($arr['address'], ($arr['amount']));
	}
}

class TxIn {
    public $txOutId = "";
	public $txOutIndex = 0;
	public $signature = "";
	
	public function __construct($txOutId = '', $txOutIndex = 0, $signature = '') {
		$this->txOutId = $txOutId;
		$this->txOutIndex = $txOutIndex;
		$this->signature = $signature;
	}
	
	static function parse($arr) {
		return new TxIn($arr['txOutId'], $arr['txOutIndex'], $arr['signature']);
	}
}
