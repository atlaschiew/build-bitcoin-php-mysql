<?php
include_once("common.php");

$recordPerPage = 10;
$targetPeer = "{$_SESSION['nodeIp']}:9981";

$rawBalance = Network::postToUrl($targetPeer,['query' => 'balanceInfo','address'=> $_GET['address']]);
$balance = json_decode($rawBalance,true);
$balance = $balance['result'];

$rawTxs = Network::postToUrl($targetPeer,['limit'=>$recordPerPage,'query' => 'addressTx','address'=> $_GET['address'], 'start'=>(int)$_GET['more']]);

$results = json_decode($rawTxs);
$totalCount = @count($results);

$transTableHTML = "";

if ($totalCount) {
	
	foreach($results as $tx) {
		
		$transTableHTML .= "<table border=1 style='margin-bottom:25px'>";
	
		$totalTxIns = @count($tx->txIns);
		$isCoinbaseTx = ($totalTxIns == 1 AND $tx->txIns[0]->txOutIndex == $tx->blockIndex AND $tx->txIns[0]->txOutId=="");
		$txmore = "";
		if ($isCoinbaseTx) {
			$txmore = "(Coinbase transaction)";
		}
		
		$transTableHTML .= "<tr><td colspan=4><b>{$tx->id} {$txmore}</b> <span style='float:right'>Confirmations: {$tx->confirmations}</span></td></tr>";
		
		$inputs = $outputs = $amounts = "";

		foreach($tx->txIns as $txIn) {
			
			if ($txIn->address) {
				if ($txIn->address == $_GET['address']) {
					$inputs .= "<b><i>{$txIn->address}</i></b><br/>";
				} else {
					$inputs .= "{$txIn->address}<br/>";
				}
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

			if ($txOut->address == $_GET['address']) {
				$outputs .= "{$opentag}<b><i>{$txOut->address}</i></b>{$closetag}<br/>";
			} else {
				$outputs .= "{$opentag}{$txOut->address}{$closetag}<br/>";
			}
			
			$amounts .= $txOut->amount . "<br/>";
		}	
	
		$transTableHTML .=  "<tr>
				<td>".($inputs?$inputs:"No Input")."</td>
				<td> To </td>
				<td>{$outputs}</td>
				<td>{$amounts}</td>
			  </tr>";
			  
		$transTableHTML .=   "<tr><td colspan=2 style='text-align:left'>Submit: {$tx->timestamp}, Confirmed: {$tx->confirmedTs}</td><td colspan=2 style='text-align:right'>Tx Fees: ".($tx->txFees).", Bytes: ".strlen(Utils::jsonEncode($tx))."</td></tr>";
	}
	
	$transTableHTML .= "</table>";
	
}
$title = $_GET['address'];
include_once("html_header.php");
?>
	<table style="width:40%">
		<tr><th colspan=2><b>Summary</b></th></tr>
		<tr><td><b>Address</td><td><?php echo wordwrap($_GET['address'],64,"<br>",true);?></td></tr>
		<tr><td><b>Total Received</b></td><td><?php echo $balance['totalReceived']?></td></tr>
		<tr><td><b>Current Balance</b></td><td><?php echo $balance['balance']?></td></tr>
	</table>
	<H1>Raw Data</H1>
	<div>
		<?php echo $rawBalance?>
		
	</div>
	
	<H1>Transactions</H1>
	<p>
		<center>
		<?php
		if ((int)$_GET['more'] > 0) {
		?>
		<input type='button' value='Previous' onclick="document.location='?<?php echo Utils::cleanQueryString(array('more'))?>&more=<?php echo (int)$_GET['more'] - $recordPerPage?>';"/>
		<?php
		}
		?>
		<input type='button' value='Next' onclick="document.location='?<?php echo Utils::cleanQueryString(array('more'))?>&more=<?php echo (int)$_GET['more'] + $recordPerPage?>';"/>
		</center>
	</p>
	<?php echo $transTableHTML?>
	
	<p>
		<center>
		<?php
		if ((int)$_GET['more'] > 0) {
		?>
		<input type='button' value='Previous' onclick="document.location='?<?php echo Utils::cleanQueryString(array('more'))?>&more=<?php echo (int)$_GET['more'] - $recordPerPage?>';"/>
		<?php
		}
		?>
		<input type='button' value='Next' onclick="document.location='?<?php echo Utils::cleanQueryString(array('more'))?>&more=<?php echo (int)$_GET['more'] + $recordPerPage?>';"/>
		</center>
	</p>
	
	<H1>Raw Data</H1>
	<div>
		<?php echo $rawTxs?>
		
	</div>
<?php include_once("html_footer.php")?>	