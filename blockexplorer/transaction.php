<?php
include_once("common.php");

$targetPeer = "{$_SESSION['nodeIp']}:9981";

$results = Network::postToUrl($targetPeer,['query' => 'transaction','txId'=> $_GET['txId']]);

$tx = json_decode($results);
$title = $_GET['txId'];
include_once("html_header.php");
?>
	<table border=1 style='margin-bottom:25px;'>
	<?php
	
		$totalTxIns = @count($tx->txIns);
		$isCoinbaseTx = ($totalTxIns == 1 AND $tx->txIns[0]->txOutId=="");
		$txmore = "";
		
		$totalRewards = 0;
		if ($isCoinbaseTx) {
			$txmore = "(Coinbase transaction)";
		}
		
		echo  "<tr><th colspan=4><b>{$tx->id} {$txmore}</b> <span style='float:right'>Confirmations: {$tx->confirmations}</span></th></tr>";
		$inputs = $outputs = $amounts = "";

		foreach($tx->txIns as $txIn) {
			if ($txIn->address){
				$inputs .= $txIn->address . "<br/>";
			}
		}	
		
		foreach($tx->txOuts as $k=>$txOut) {
			
			if ($tx->confirmations == 0) {
				$opentag = $closetag = "";
			} else if ($txOut->hasUnspent) {
				$opentag = "<font color=green>";
				$closetag = "</font>";
			} else {
				$opentag = "<font color=red>";
				$closetag = "</font>";
			}
			
			$outputs .= "{$opentag}{$txOut->address}{$closetag}<br/>";
			$amounts .= ($txOut->amount) . "<br/>";
			
			if ($isCoinbaseTx) {
				$totalRewards += (float)$txOut->amount;
			}
		}	
	
		echo "<tr>
				<td>".($inputs?$inputs:"No Input")."</td>
				<td> To </td>
				<td>{$outputs}</td>
				<td>{$amounts}</td>
			  </tr>";
		
		echo "<tr><td colspan=2 style='text-align:left'>Submit: {$tx->timestamp}, Confirmed: {$tx->confirmedTs}</td><td colspan=2 style='text-align:right'>Tx Fees: ".$tx->txFees.", Bytes: ".strlen(Utils::jsonEncode($tx))."</td></tr>";
	
	?>
	</table>
	<H1>Raw Data</H1>
	<div>
		<?php echo $results?>
	</div>
	<H1>Summary</H1>
	<table style="width:40%">
		<tr><th colspan=2><b>Summary</b></th></tr>
		<tr><td><b>Size (Bytes)</b></td><td><?php echo strlen($results);?></td></tr>
		<tr><td><b>Submit Time</b></td><td><?php echo $tx->timestamp;?></td></tr>
		<tr><td><b>Tx Fees</b></td><td><?php echo $tx->txFees;?></td></tr>
		<?php
		if ($tx->confirmations > 0) {
		?>
		<tr><td><b>Confirmed Time</b></td><td><?php echo $tx->confirmedTs?></td></tr>
		<?php
		}
		
		if ($isCoinbaseTx) {
		?>
		<tr><td><b>Reward From Block</b></td><td><?php echo $totalRewards?></td></tr>
		<?php
		}
		?>
		<tr><td><b>Confirmations</b></td><td><?php echo $tx->confirmations?></td></tr>
	</table>
<?php include_once("html_footer.php")?>
