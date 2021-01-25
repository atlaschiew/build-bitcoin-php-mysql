<?php

class Consensus {
	
	static function isHashMatchTarget($hashDec, $targetDec) {
		
		if (Utils::safeComp($hashDec, $targetDec) < 0) {
			return true;
		} else {
			return false;
		}
	}
	
	static function isValidTimestamp($newBlock, $previousBlock) {
		
		/*
		To mitigate the attack where a false timestamp is introduced in order to manipulate the difficulty the following rules is introduced:

		- A block is valid, if the timestamp is at most 1 min in the future from the time we perceive.
		- A block in the chain is valid, if the timestamp is at most 1 min in the past of the previous block.
		
		*/
		$prevTS = strtotime($previousBlock->timestamp);
		$newTS = strtotime($newBlock->timestamp);
		
		return ( $prevTS - 60 < $newTS ) && $newTS - 60 < time();
	}
	
	static function isValidNewBlock($newBlock, $previousBlock = null) {
		
		if ((int)DB::result(DB::query("SELECT id FROM `blocks` WHERE hash='".DB::esc($newBlock->hash)."' LIMIT 1"),0,0) > 0) {
			Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) block exist.");
			return false;
		} else if (($sizeCount = strlen($data=Utils::jsonEncode($newBlock->data))) > Chain::MAX_BLOCK_DATA_SIZE) {
			Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) --{$data}-- invalid block size ({$sizeCount})");
			return false;
		//is block hash correct
		} else if (($h1 = $newBlock->calculateThisHash()) !== ($h2 = $newBlock->hash)) {
	        Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) invalid hash: {$h1} vs {$h2}");
	        return false;
		//is hash match target
		} else if (!self::isHashMatchTarget(Utils::bchexdec($newBlock->hash), Utils::bchexdec($newBlock->target))) {
			Utils::printOut('isValidNewBlock(#{$newBlock->blockIndex}) block target not meet');
			return false;
		}
		
		if ($previousBlock !== null) {
			//check block instance
			if (!$previousBlock instanceof Block) {
				Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) previous block missing");
				return false;
			//check block index
			} else if ($previousBlock->blockIndex + 1 != $newBlock->blockIndex) {
				Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) invalid index. (".($previousBlock->blockIndex + 1).":{$newBlock->blockIndex})");
				return false;
			//check is block linked up properly
			} else if ($previousBlock->hash != $newBlock->previousHash) {
				Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) invalid previoushash");
				return false;
			//check block timestamp
			} else if (!self::isValidTimestamp($newBlock, $previousBlock)) {
				Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) invalid timestamp");
				return false;
			//check difficulty
			} else if (bccomp(Chain::getDifficulty($previousBlock), $newBlock->difficulty,8) !== 0) {
				Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) invalid difficulty");
				return false;
			//check target
			}else if (bccomp(Chain::getTarget($previousBlock, $newBlock->difficulty), Utils::bchexdec($newBlock->target))!==0) {
				Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) invalid target");
				return false;
			//check chainwork
			} else if (Chain::getChainWork($previousBlock,$newBlock->difficulty) != $newBlock->chainWork) {
				Utils::printOut("isValidNewBlock(#{$newBlock->blockIndex}) invalid chain work");
				return false;
			}
			
		}
		
	    return true;
	}
	
	static function isValidBlockTxs($block, $forkId) {
		
		$txs = $block->data;
		$coinbaseTx = $txs[0];
		
		if (!Self::isValidCoinbaseTx($coinbaseTx, $block)) {
			Utils::printOut("isValidBlockTxs(#{$block->blockIndex}) invalid coinbase transaction: ".print_r($coinbaseTx,true));
			return false;
		}
		
		//check for duplicate txIns. Each txIn can be included only once
		$txIns = array_reduce($txs, function($carry, $thisTx) { return array_merge($carry, $thisTx->txIns); },[]);
		$txIns = array_map(function($thisTxIn) { return Utils::jsonEncode($thisTxIn); }, $txIns);
		
		//ugly hack
		foreach($txIns as $k=>$txIn) {
			$parts = explode(",\"signature",$txIn);
			$txIns[$k] = $parts[0];
		}
		
		if (@count(array_unique($txIns)) != @count($txIns)) {
			Utils::printOut("isValidBlockTxs(#{$block->blockIndex}) Duplicated txIns");
			return false;
		}
		
		// all but coinbase transactions
		unset($txs[0]);
		$normalTxs = $txs;
		$retVal = array_reduce($normalTxs, function($carry, $thisTx) use ($forkId){ return $carry && Self::isValidTx($thisTx,$forkId); },true);
		
		return $retVal;
	}
	
	static function isValidCoinbaseTx($tx, $block) {
		
		$txs = $block->data;
		$txFees = array_reduce($txs, function($carry, $thisTx) { return Utils::safeAdd($carry, $thisTx->txFees); },"0");
		
		$reward = Utils::safesub($tx->txOuts[0]->amount, $txFees);
		
		$regenTxHash = Transaction::getTransactionId($tx);
		$takenRewards = Utils::safeMul(Chain::COINBASE_AMOUNT, $block->blockIndex);

		$compareReward = Chain::getReward($block->blockIndex);
		
		if (!$tx) {
			Utils::printOut("isValidCoinbaseTx(#{$tx->id}) the first transaction in the block must be coinbase transaction");
			return false;
		}else if ($regenTxHash != $tx->id) {
			Utils::printOut("isValidCoinbaseTx(#{$tx->id}) invalid coinbase tx {$regenTxHash} vs {$tx->id}");
			return false;
		
		}else if (count($tx->txIns) != 1) {
			Utils::printOut("isValidCoinbaseTx(#{$tx->id}) one txIn must be specified in the coinbase transaction");
			return false;
		}else if ($tx->txIns[0]->txOutIndex != $block->blockIndex) {
			Utils::printOut("isValidCoinbaseTx(#{$tx->id}) the txIn signature in coinbase tx must be the block height");
			return false;
		}else if (count($tx->txOuts) != 1) {
			Utils::printOut("isValidCoinbaseTx(#{$tx->id}) invalid number of txOuts in coinbase transaction");
			return false;
		} else if (Utils::safecomp($reward ,$compareReward) !== 0) {
			Utils::printOut("isValidCoinbaseTx(#{$tx->id}) invalid coinbase reward, amount exceeded max supply.");
			return false;
		} else if (Utils::safeComp($tx->txOuts[0]->amount,Utils::safeAdd($reward, $txFees)) !== 0 ) {
			Utils::printOut("isValidCoinbaseTx(#{$tx->id}) invalid coinbase amount in coinbase transaction");
			return false;
		} else {
			return true;
		}
	}
	
	static function isValidTxIn($txIn, $tx, $forkId) {
		
		$thisUtxo = Utxo::findUnspentTxOut($txIn->txOutId,$txIn->txOutIndex, $forkId);
		
		if (!$thisUtxo) {
			Utils::printOut("isValidTxIn(#{$tx->id}) referenced utxo not found: " . print_r($txIn,true));
			return false;
		}
		$address = $thisUtxo->address;
		
		$wallet = new Wallet();
		$wallet->setNetworkPrefix(Chain::ADDRESS_PREFIX);
		$wallet->setNetworkName("Bitcoin");
		
		return $wallet->checkSignatureForMessage($address, $txIn->signature, $tx->id);
		
	}
	
	static function isValidTx($tx, $forkId) {
		
		//is tx id valid
		if (($txId = Transaction::getTransactionId($tx)) != $tx->id) {
			Utils::printOut("isValidTx(#{$tx->id}) invalid tx against {$txId}");
			
			return false;
		}
		
		//is timestamp valid?
		if(!preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/',$tx->timestamp)){
			Utils::printOut("isValidTx(#{$tx->id}) invalid timestamp");
			return false;
		}
		
		//is tx in valid ?
		$allValidTxIns = array_reduce($tx->txIns, function($carry, $thisTxIn) use ($tx, $forkId) { return $carry && self::isValidTxIn($thisTxIn,$tx, $forkId); },true);
		
		if (!$allValidTxIns) {
			Utils::printOut("isValidTx(#{$tx->id}) some of the txIns are invalid");
			return false;
		}

		//is tx out valid?
		$allValidTxOuts = array_reduce($tx->txOuts, function($carry, $thisTxOut) { return $carry && AddressValidation::validateAddress($thisTxOut->address); },true);
		
		if (!$allValidTxOuts) {
			Utils::printOut("isValidTx(#{$tx->id}) some of the address inside txOuts are invalid");
			return false;
		}
		
		//is IO amount tallied?
		$totalTxInValues = array_reduce($tx->txIns, function($carry, $thisTxIn) use ($forkId) { return Utils::safeAdd($carry, Utxo::getAmount($thisTxIn->txOutId, $thisTxIn->txOutIndex, $forkId));},0);
		$totalTxOutValues= array_reduce($tx->txOuts, function($carry, $thisTxOut) { return Utils::safeAdd($carry, $thisTxOut->amount);},0);
		$totalTxOutValues = Utils::safeAdd($totalTxOutValues, $tx->txFees);

		if (Utils::safeComp($totalTxOutValues,$totalTxInValues)!==0) {
			Utils::printOut("isValidTx(#{$tx->id}) totalTxOutValues ({$totalTxOutValues}) != totalTxInValues ({$totalTxInValues})");
			return false;
		}

		return true;
	}

	static function isValidTxForPool($tx) {
		
		$txIns = array();
		$txIns = array_map(function($thisTxIn) { return Utils::jsonEncode($thisTxIn); }, $tx->txIns);
		
		$r = DB::query("SELECT * FROM `transactionPool`");
		while($row = mysqli_fetch_assoc($r)) {
			$thisTxIns = Utils::jsonDecode($row['txIns']);
			$thisTxIns = array_map(function($thisTxIn) { return TxIn::parse($thisTxIn); }, $thisTxIns);
			$txIns = array_merge($txIns, array_map(function($thisTxIn) { return Utils::jsonEncode($thisTxIn); }, $thisTxIns));
		}
		
		@mysqli_free_result($r);
		
		//ugly hack
		foreach($txIns as $k=>$txIn) {
			$parts = explode(",\"signature",$txIn);
			$txIns[$k] = $parts[0];
		}
		
		if (@count(array_unique($txIns)) != @count($txIns)) {
			return false;
		}
		
		return true;
	}

}
