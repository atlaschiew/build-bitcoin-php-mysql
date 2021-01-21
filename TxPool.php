<?php

class TransactionPool{ 
	
	static function getAll($start = null, $limit = null, $orderby=null) {
		
		if (is_numeric($limit) AND is_numeric($start)) {
			$limitSQL = " LIMIT {$start},{$limit}";
		} else if (is_numeric($limit)) {	
			$limitSQL = " LIMIT {$limit}";
		} else {
			$limitSQL = "";
		}
		
		if ($orderby == "time ASC") {
			$orderbySQL = "ORDER BY timestamp ASC";
		} else if ($orderby == "time DESC") {
			$orderbySQL = "ORDER BY timestamp DESC";
		} else {
			$orderbySQL = "ORDER BY timestamp ASC";
		}
		
		$pooledTxs = [];
		$r = DB::query("SELECT * FROM `transactionPool` {$orderbySQL}{$limitSQL}");
		while($row = mysqli_fetch_assoc($r)) {
			$row['id'] = $row['txId'];
			$pooledTxs[] = Transaction::parse($row);
		}
		@mysqli_free_result($r);
		return $pooledTxs;
	}
	
	static function addToTransactionPool($tx, $forkId) {
		
		if (!Consensus::isValidTx($tx, $forkId)) {
			throw new Exception("addToTransactionPool(#{$tx->id}) Invalid tx");
		} 
		
		if (!Consensus::isValidTxForPool($tx)) {
			throw new Exception("addToTransactionPool(#{$tx->id}) Duplicate txIns");
		}
		
		DB::beginTransaction();
		
		DB::query( "INSERT INTO transactionPool SET 
						txId        = '".DB::esc($tx->id)."', 
						timestamp = '".DB::esc($tx->timestamp)."', 
						txFees    = '".DB::esc($tx->txFees)."',
						txIns     = '".DB::esc(Utils::jsonEncode($tx->txIns))."', 
						txOuts    = '".DB::esc(Utils::jsonEncode($tx->txOuts))."'");
		
		$multiSQL = "";
		if (@count($tx->txIns)) {
			foreach($tx->txIns as $txIn) {
				$multiSQL .= "(
							'".DB::esc($tx->id)."', 
							'".DB::esc($txIn->txOutId)."',
							'".DB::esc($txIn->txOutIndex)."'),";
			}
		}
		
		$multiSQL = rtrim($multiSQL,",");
		if ($multiSQL) {
			$multiSQL = "INSERT INTO `transactionPoolTxIns` (`txId`,`txOutId`,`txOutIndex`) VALUES {$multiSQL}";
			DB::query($multiSQL);
			
		} 
		
		DB::commit();
			
	}
	
	
	static function updateTransactionPool($block) {
		$blockTxs = $block->data;
		
		$tx_ids = [];
		foreach($blockTxs as $tx) {
			$tx_ids[] = "'".DB::esc($tx->id)."'";
		}
		
		DB::query($sql = "DELETE FROM `transactionPool` WHERE txId IN (".implode(",", $tx_ids).")");
		DB::query($sql = "DELETE FROM `transactionPoolTxIns` WHERE txId IN (".implode(",", $tx_ids).")");
		
	}
}