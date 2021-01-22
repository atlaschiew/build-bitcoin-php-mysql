<?php

class Address {
	
	static function getBalance($address,$forkId) {
		$decimalToInt =  bcpow("10", chain::AMOUNT_DECIMAL_POINT);
		
		$utxoTable = Utxo::getTable($forkId);
		
		$balance = DB::result($r = DB::query("SELECT SUM(amount*{$decimalToInt}) FROM {$utxoTable} WHERE address='".DB::esc($address)."' LIMIT 1"),0,0);
		
		$balance = Utils::safeDiv($balance,$decimalToInt);
		@mysqli_free_result($r);
		return $balance;
	}
	
	static function getUtxO($address,$start = null, $limit = null, $orderby=null) {
		
		if (is_numeric($limit) AND is_numeric($start)) {
			$limit_q = " LIMIT {$start},{$limit}";
		} else if (is_numeric($limit)) {	
			$limit_q = " LIMIT {$limit}";
		} else {
			$limit_q = "";
		}
		
		if ($orderby == "amount ASC") {
			$orderby_q = "ORDER BY amount ASC";
		} else if ($orderby == "amount DESC") {
			$orderby_q = "ORDER BY amount DESC";
		} else {
			$orderby_q = "ORDER BY id ASC";
		}
		
		if (strlen($address) == 34) {
			
		} else {
			$address = Address::getAddress($address);
		}
		
		$r = DB::query("SELECT * FROM `unspentTxOuts` WHERE address ='".DB::esc($address)."' {$orderby_q}{$limit_q}");
		
		$utxo = [];
		while($row = mysqli_fetch_assoc($r)) {
			
			unset($row['id'],$row['blockIndex']);
			$utxo[] = $row;
		}
		
		@mysqli_free_result($r);
		return $utxo;
	}
	
	static function newAddress() {
		
		$private = new PrivateKey();
		$point = $private->getPubKeyPoints();
		$derPublicKey=AddressCodec::Compress($point);
		$hash=AddressCodec::Hash($derPublicKey);
		$address = AddressCodec::Encode($hash,Chain::ADDRESS_PREFIX);
		
		return array("address"=>$address, "privateKey" => $private->k,"publicKey" => $derPublicKey);
	}
	
	static function getAddress($private) {
		
		$derPublicKey=self::getPublicKey($private);
		$hash=AddressCodec::Hash($derPublicKey);
		$address = AddressCodec::Encode($hash,Chain::ADDRESS_PREFIX);
		
		return $address;
	}
	
	static function getPublicKey($private) {
		$private = new PrivateKey($private);
		$point = $private->getPubKeyPoints();
		$derPublicKey=AddressCodec::Compress($point);
		
		return $derPublicKey;
	}
}
