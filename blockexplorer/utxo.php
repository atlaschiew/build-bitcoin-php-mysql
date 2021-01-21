<?php
include_once("common.php");

$recordPerPage = 10;
$targetPeer = "{$_SESSION['nodeIp']}:9981";

if ($_GET['address']) {
	$results = Network::postToUrl($targetPeer,['address'=>$_GET['address'], 'query' => 'getUtxos','start'=>(int)$_GET['more'], 'limit'=>$recordPerPage]);
	
	$utxos = json_decode($results);
	
	
}
$title = "Unspent Transaction Out (UtxO)";
include_once("html_header.php");
?>
	<p>
		<center>
			<form method="GET" style='float:left;'>
				<input type='text' name='address' placeholder='Enter Address'/> Press enter to submit
			</form>
			
			<?php
			if ((int)$_GET['more'] > 0) {
			?>
			<input type='button' value='Previous' onclick="document.location='?<?php echo Utils::cleanQueryString(array('more'))?>&more=<?php echo (int)$_GET['more'] - $recordPerPage?>';"/>
			<?php
			}
			?>
			<input type='button' value='Next' onclick="document.location='?<?php echo Utils::cleanQueryString(array('more'))?>&more=<?php echo (int)$_GET['more'] + $recordPerPage?>';"/>
		</center>
		<div style="clear:both"></div>
	</p>
	
	<?php
	if (@count($utxos)) {
	?>
		<table border=1 style='margin-bottom:25px'>
			<tr style='background-color:#ccc'>
				<th>TxOutId</th>
				<th>TxOutIndex</th>
				<th>Address</th>
				<th>Amount</th>
			</tr>
	<?php
		foreach($utxos as $utxo) {
			echo "<tr>
					<td>".($utxo->txOutId)."</td>
					<td>".($utxo->txOutIndex)."</td>
					<td>".($utxo->address)."</td>
					<td>".($utxo->amount)."</td>
				  </tr>";
		}
		?>
		</table>
		<?php
	}
	?>
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
		<?php echo $results?>
	</div>
<?php include_once("html_footer.php")?>	