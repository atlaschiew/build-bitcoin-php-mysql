<?php

include_once("common.php");

$targetPeer = "{$_SESSION['nodeIp']}:9981";

$maxBatch = 10;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	$inputs = $outputs = array();
	
	foreach(range(0,$maxBatch-1) as $idx) {
		if (strlen($_POST['txOutId'][$idx]) OR strlen($_POST['txOutIndex'][$idx]) OR strlen($_POST['privatekey'][$idx])) {
			if (!(strlen($_POST['txOutId'][$idx]) AND strlen($_POST['txOutIndex'][$idx]) AND strlen($_POST['privatekey'][$idx]))) {
				$errMsg .= "Error At Inputs (".($idx+1)."): to skip a row, please put blank on all of the fields of the row.<br/>";
			} else {
				$inputs[] = array($_POST['txOutId'][$idx],$_POST['txOutIndex'][$idx],$_POST['privatekey'][$idx]);
			}
		}
	}

	foreach(range(0,$maxBatch-1) as $idx) {
		if (strlen($_POST['address'][$idx]) OR strlen($_POST['amount'][$idx])) {
			if (!(strlen($_POST['address'][$idx]) AND strlen($_POST['amount'][$idx]))) {
				$errMsg .= "Error At Outputs (".($idx+1)."): to skip a row, please put blank on all of the fields of the row.<br/>";
			} else {
				$outputs[] = array($_POST['address'][$idx],$_POST['amount'][$idx]);
			}
		}
	}
	
	if (!$errMsg) {
		
		$unsignedTxResult = Network::postToUrl($targetPeer,['query' => 'getRawTx','inputs'=>json_encode($inputs) , 'outputs'=> json_encode($outputs)]);
		
		$unsignedTx = json_decode($unsignedTxResult,true);

		if (isset($unsignedTx['error'])) {
			$errMsg .= $unsignedTx['error'];
		} else {
			
			$tx = Transaction::parse($unsignedTx);
			
			$tx->txIns = array_map(
				function($txInIndex, $txIn) use ($tx, $inputs) { 
					$privateKey = $inputs[$txInIndex][2];
					
					//finally, sign this transaction locally
					$txIn->signature = Transaction::sign($tx->id,$privateKey );
					return $txIn;  
				},array_keys($tx->txIns), $tx->txIns
			);
			
			$succMsg .= Utils::jsonEncode($tx);
		}
	}
}

$title = "New Transaction";
include_once("html_header.php");
?>
	<form method='POST'>
		
		<table style="width:100%">
			<tr><th colspan=3><b>Inputs (Only row with full fill will be processing.)</b></th></tr>
			<tr><th style='width:33%;'>txOutId (Tx ID)</th><th style='width:33%;'>txOutIndex</th><th style='width:33%;'>Private Key</th></tr>
			<?php
			foreach(range(0,$maxBatch-1) as $idx) {
			?>	
				<tr><td ><input type='text' name='txOutId[]' value="<?php echo $_POST['txOutId'][$idx]?>"/></td><td style='width:15%;'><input type='text' name='txOutIndex[]' value="<?php echo $_POST['txOutIndex'][$idx]?>"/></td><td><input type='text' name='privatekey[]' value="<?php echo $_POST['privatekey'][$idx]?>"/></td></tr>
			<?php
			}
			?>
		</table>
		
		<br/>
		<table style="width:100%">
			<tr><th colspan=3><b>Outputs (Only row with full fill will be processing.)</b></th></tr>
			<tr><th>Address</th><th>Amount</th></tr>
			<?php
			foreach(range(0,$maxBatch-1) as $idx) {
			?>	
				<tr>
					<td><input type='text' name='address[]' value="<?php echo $_POST['address'][$idx]?>"/></td>
					<td><input type='text' name='amount[]' value="<?php echo $_POST['amount'][$idx]?>"/></td>
					
				</tr>
			<?php
			}
			?>
			
		</table>
		
		<p>
			<center>
				<input class='bigbutton' type='submit' name='btn_get_raw_tx' value='Get Raw Tx'/>
			</center>
		</p>
	</form>
			
<?php include_once("html_footer.php")?>	
