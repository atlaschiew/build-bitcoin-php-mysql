<?php

class Utxo {
	public $txOutId = '';
	public $txOutIndex = 0;
	public $address = '';
	public $amount = 0;
	
    public function __construct($txOutId, $txOutIndex, $address, $amount) {
        $this->txOutId = $txOutId;
        $this->txOutIndex = $txOutIndex;
        $this->address = $address;
        $this->amount = $amount;
    }
	
	static function getTable($forkId) {
		if ($forkId==1) {
			return "`unspentTxOuts`";
		} else {
			return "`unspentTxOuts_fork_{$forkId}`";
		}
	}
	
	static function parse($arr) {
		return new Utxo($arr['txOutId'], $arr['txOutIndex'], $arr['address'], ($arr['amount']));
	}
	
	static function getAmount($txOutId, $txOutIndex, $forkId) {
		$decimalToInt =  bcpow("10", chain::AMOUNT_DECIMAL_POINT);
		
		$utxoTable = Utxo::getTable($forkId);
		
		$amount = DB::result($r = DB::query("SELECT SUM(amount*{$decimalToInt}) FROM {$utxoTable} WHERE txOutId='".DB::esc($txOutId)."' AND txOutIndex='".DB::esc($txOutIndex)."' LIMIT 1"),0,0);
		
		$amount = Utils::safeDiv($amount,$decimalToInt);
		@mysqli_free_result($r);
		return $amount;
	}
	
	static function findUnspentTxOut($txOutId, $txOutIndex, $forkId) {
		
		$whereSQL = "1=1";
		
		$utxoTable = Utxo::getTable($forkId);
		
		$r = DB::query("SELECT * FROM {$utxoTable} WHERE `txOutId`='".DB::esc($txOutId)."' AND `txOutIndex`='".DB::esc($txOutIndex)."' AND ({$whereSQL}) LIMIT 1");
		
		if (mysqli_num_rows($r) > 0) {
			$thisUtxo = mysqli_fetch_assoc($r);
			$result = Utxo::parse($thisUtxo);
		} else {
			$result = false;
		}
		
		@mysqli_free_result($r);
		return $result;
	}
	
	static function getAll($forkId, $whereSQL = "1=1", $start = null, $limit = null, $orderby=null) {
		
		$utxoTable = Utxo::getTable($forkId);
		
		$whereSQL = $whereSQL ? $whereSQL : "1=1";
		
		if (is_numeric($limit) AND is_numeric($start)) {
			$limitSQL = " LIMIT {$start},{$limit}";
		} else if (is_numeric($limit)) {	
			$limitSQL = " LIMIT {$limit}";
		} else {
			$limitSQL = "";
		}
		
		if ($orderby == "index ASC") {
			$orderbySQL = "ORDER BY id ASC";
		} else if ($orderby == "index DESC") {
			$orderbySQL = "ORDER BY id DESC";
		} else {
			$orderbySQL = "ORDER BY id DESC";
		}
		
		$unspentTxOuts = [];
		$r = DB::query("SELECT * FROM {$utxoTable} WHERE {$whereSQL} {$orderbySQL}{$limitSQL}");
		while($row = mysqli_fetch_assoc($r)) {
			$row = self::parse($row);
			$unspentTxOuts[] = $row;
		}
		@mysqli_free_result($r);
		return $unspentTxOuts;
	}
	
	static function updateUnspentTxOuts($block, $forkId) {
		
		$utxoTable = Utxo::getTable($forkId);
		
		$blockTxs = $block->data;
		
		$newUnspents = array_map(
			function($t) {
				$tid = $t->id;
				return 
					array_map(
						function($index,$txOut) use ($tid) { 
							$newUtxo = new Utxo($tid, $index, $txOut->address, $txOut->amount);
							return $newUtxo ;
						}
					,array_keys($t->txOuts),$t->txOuts);
			}, $blockTxs
		);
		
		$newUnspents = array_reduce($newUnspents, function($carry, $thisTx) { return array_merge($carry, $thisTx); }, [] );

		$consumedUnspents = array_map(function($t) { return $t->txIns; } ,$blockTxs);
		$consumedUnspents = array_reduce($consumedUnspents, function($carry, $thisTx) { return array_merge($carry, $thisTx); }, [] );
		$consumedUnspents = array_map(function($txIn) { return new Utxo($txIn->txOutId, $txIn->txOutIndex, '', 0); },$consumedUnspents);
	
		foreach($consumedUnspents as $consumedUnspent) {
			//avoid remove coinbase tx
			if (strlen($consumedUnspent->txOutId) > 0) {
				$sql = "DELETE FROM {$utxoTable} WHERE `txOutId`='".DB::esc($consumedUnspent->txOutId)."' AND `txOutIndex`='".DB::esc($consumedUnspent->txOutIndex)."'";
				DB::query( $sql );
			}
		}

		$multiSQL = "";
		foreach($newUnspents as $newUnspent) {
			if (Utils::safeComp($newUnspent->amount,0) === 1) {
				
				$multiSQL .= "('".DB::esc($newUnspent->txOutId)."', 
							'".DB::esc($newUnspent->txOutIndex)."', 
							'".DB::esc($newUnspent->address)."',
							'".DB::esc($newUnspent->amount)."',
							'".DB::esc($block->hash)."',
							'".DB::esc($block->blockIndex)."'),";
			}
		}
		
		$multiSQL = rtrim($multiSQL,",");
		if ($multiSQL) {
			$multiSQL = "INSERT INTO {$utxoTable} (`txOutId`,`txOutIndex`,`address`,`amount`,`blockHash`,`blockIndex`) VALUES {$multiSQL}";
			
			DB::query($multiSQL);
		} 
	}
}
