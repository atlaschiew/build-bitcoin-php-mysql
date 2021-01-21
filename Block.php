<?php

class Block {
	
	public function __construct($index, $previousHash, $timestamp, $data, $hash,$difficulty,$target, $chainWork, $nonce) {
		$this->blockIndex 	= $index;
		$this->previousHash = $previousHash;
		$this->timestamp 	= $timestamp;
		$this->data 		= $data;
		$this->hash 		= $hash;
		$this->difficulty   = $difficulty;
		$this->target       = $target;
		$this->chainWork    = $chainWork;
		$this->nonce        = $nonce;
		
	}

	static function calculateHash($index, $previousHash, $timestamp, $data,$target,$nonce) {
		return hash("sha256", $index.$previousHash.$timestamp.Utils::jsonEncode($data).$target.$nonce);
	}

	public function calculateThisHash() {
		return self::calculateHash( 
			$this->blockIndex,
			$this->previousHash,
			$this->timestamp,
			$this->data,
			$this->target,
			$this->nonce
		);
	}

	static function isBlockIndexInActiveChain($index) {

		$r = DB::query($sql = "SELECT * FROM `blocks` WHERE blockIndex='".DB::esc($index)."'");
		
		if (mysqli_num_rows($r) == 1) {
			return mysqli_fetch_assoc($r);
		} else {
			//solve conflict
			$chain = Chain::getLongestChain();
			$targetBlockHash = $chain['lastBlockHash'];
			
			$searchHashes = [];
			while($row = mysqli_fetch_assoc($r)) {
				if ( $row['hash'] == $targetBlockHash ) {
					return $row;
				}
				$searchHashes = [$row['blockHash']];
				
				while(@count($searchHashes) > 0) {
					$r2 = DB::query("SELECT * FROM `blocks` WHERE previousHash IN ('".@Implode("','", $searchHashes)."')");
					$searchHashes = [];
					while($block = mysqli_fetch_assoc($r2)) {
						
						if ( $block['hash'] == $targetBlockHash ) {
							return $row;
						}
						$searchHashes[] = $block['hash'];
					}
				}
			}
		}
		
		return false;
		
	}
	
	static function parse($arr) {
		
		$datas = $arr['data'];
		if (is_array($datas)) {
			foreach($datas as $k=>$data) {
				$datas[$k] = Transaction::parse($data);
			}
		} else {
			$datas = Utils::jsonDecode($datas);
			if (json_last_error() == JSON_ERROR_NONE) {
				foreach($datas as $k=>$data) {
					$datas[$k] = Transaction::parse($data);	
				}
			} else {
				$datas = $arr['data'];
			}
		}
		
		return new Block(
			$arr['blockIndex'], 
			$arr['previousHash'], 
			$arr['timestamp'], 
			$datas, 
			$arr['hash'],
			$arr['difficulty'],
			$arr['target'],
			$arr['chainWork'],
			$arr['nonce']
		);
	}
}
