<?php
include_once("common.php");

$value = $_GET['blockIndex'] or $value = $_GET['blockHash'];

if (is_numeric($value)) {
	$key = "blockIndex";
	$query = "block";
} else {
	$key = "blockHash";
	$query = "blockhash";
}
$targetPeer = "{$_SESSION['nodeIp']}:9981";
$results = Network::postToUrl($targetPeer,['query' => $query,$key=>$value]);

$tempBlock = json_decode($results);

if (@count($tempBlock->data)) {
	foreach($tempBlock->data as $k=>$tx) {
		unset($tx->blockIndex,$tx->confirmedTs,$tx->confirmations);
		$tempBlock->data[$k] = $tx;
	}
}

$block = json_decode($results); //reassign to avoid object reference behaviour
$title = "Block #{$block->blockIndex}";
include_once("html_header.php");
?>

	<table border=1 style='text-align:center;'>
		<tr style='background-color:#ccc'>
			<th>Height</th>
			<th>Age</th>
			<th>Transactions</th>
			<th>Total Sent</th>
			<th>Size (Bytes)</th>
			<th>Difficulty</th>
			<th>Nonce</th>
			<th>Hash</th>
			<th>Prev Hash</th>
		</tr>
		<?php
	
			$total_sent = 0;
			foreach($block->data as $data) {
				foreach($data->txOuts as $txOut) {
					$total_sent += $txOut->amount;
				}
			}
			
			echo "<tr>";
				echo "<td><a href='block.php?blockIndex={$block->blockIndex}'>{$block->blockIndex}</a></td>";
				echo "<td>{$block->timestamp}</td>";
				echo "<td>".count($block->data)."</td>";
				echo "<td>{$total_sent}</td>";
				echo "<td>".strlen(Utils::jsonEncode($tempBlock->data))."</td>";
				echo "<td>{$block->difficulty}</td>";
				echo "<td>{$block->nonce}</td>";
				echo "<td>{$block->hash}</td>";
				echo "<td>{$block->previousHash}</td>";
			echo "</tr>";
		
		?>
	</table>

	<H1>Transactions</H1>
	<?php
	if (@count($block->data)) {
		foreach($block->data as $tx) {
		?>
		
			<table border=1 style='margin-bottom:25px'>
			<?php
				$totalTxIns = @count($tx->txIns);
				$is_coinbase_tx = ($totalTxIns == 1 AND $tx->txIns[0]->txOutIndex == $block->blockIndex AND $tx->txIns[0]->txOutId=="");
				$txmore = "";
				if ($is_coinbase_tx) {
					$txmore = "(Coinbase transaction)";
				}
				echo  "<tr><th colspan=4><b>{$tx->id} {$txmore}</b> <span style='float:right'>Confirmations: {$tx->confirmations}</span></th></tr>";
				
				$inputs = $outputs = $amounts = "";
				
				foreach($tx->txIns as $txIn) {
					if ($txIn->address) {
						$inputs .= $txIn->address . "<br/>";
					}
				}	
				
				foreach($tx->txOuts as $k=>$txOut) {
					
					if ($txOut->hasUnspent) {
						$opentag = "<font color=green>";
						$closetag = "</font>";
					} else {
						$opentag = "<font color=red>";
						$closetag = "</font>";
					}
					
					$outputs .= "{$opentag}{$txOut->address}{$closetag}<br/>";
					
					$amounts .= ($txOut->amount) . "<br/>";
				}	

				echo "<tr>
						<td>".($inputs?$inputs:"No Input")."</td>
						<td> To </td>
						<td>{$outputs}</td>
						<td>{$amounts}</td>
					  </tr>";

				echo "<tr><td colspan=2 style='text-align:left'>Submit: {$tx->timestamp}, Confirmed: {$tx->confirmedTs}</td><td colspan=2 style='text-align:right'>Tx Fees: ".$tx->txFees.", Bytes: ".strlen(Utils::jsonEncode($tx))."</td></tr>";
			}
			?>
			</table>
		<?php
	}
	?>
	
	<H1>Raw Data</H1>
	<div>
		<?php echo $results?>
	</div>
<?php include_once("html_footer.php")?>
