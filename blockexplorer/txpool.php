<?php
include_once("common.php");

$recordPerPage = 10;
$targetPeer = "{$_SESSION['nodeIp']}:9981";

$results = Network::postToUrl($targetPeer,['query' => 'getTransactionPool','start'=>(int)$_GET['more'], 'limit'=>$recordPerPage]);

$txs = json_decode($results);
$title = "Transaction Pool";
include_once("html_header.php");
?>
<!DOCTYPE html>
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
	<p>
	<table border=1 style='margin-bottom:25px'>
	<?php
	if (@count($txs)) {
		foreach($txs as $tx) {
				
			$totalTxIns = @count($tx->txIns);
			
			$inputs = $outputs = $amounts = "";
			
			echo  "<tr><th colspan=4><b>{$tx->id}</b></th></tr>";

			foreach($tx->txIns as $txIn) {
				$inputs .= $txIn->address . "<br/>";
			}	
			
			foreach($tx->txOuts as $k=>$txOut) {
				$outputs .= "{$txOut->address}<br/>";
				$amounts .= $txOut->amount . "<br/>";
			}	

			echo "<tr>
					<td>{$inputs}</td>
					<td> To </td>
					<td>{$outputs}</td>
					<td>{$amounts}</td>
				  </tr>";
				  
			echo "<tr><td colspan=2 style='text-align:left'>Submit: {$tx->timestamp}</td><td colspan=2 style='text-align:right'>Tx Fees: ".$tx->txFees.", Bytes: ".strlen(Utils::jsonEncode($tx))."</td></tr>";
		}
			
	}
	?>
	</table>
	</p>
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
		
	<H1>Raw Data</H1>
	<div>
		<?php echo $results?>
	</div>
<?php include_once("html_footer.php")?>	