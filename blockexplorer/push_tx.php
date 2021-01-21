<?php

include_once("common.php");

$targetPeer = "{$_SESSION['nodeIp']}:9981";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	$sendResult = Network::postToUrl($targetPeer,['query' => 'pushTx','rawTx'=>$_POST['rawTx']]);
	
	$arrSendResult = json_decode($sendResult,true);
	
	if (isset($arrSendResult['error'])) {
		$errMsg .= $arrSendResult['error'];
	} else {
		$succMsg .= print_r($arrSendResult,true);
	} 
	
}
$title = "Push Transaction";
include_once("html_header.php");
?>
	<form method='POST'>
		
		<p>
		
			<textarea name='rawTx' style='width:100%;height:200px;' placeholder='Raw Tx (JSON Format)'><?Php echo $_POST['rawTx']? $_POST['rawTx']: $sendResult?></textarea>
		</p>
		
		<p>
			<center>
				<input class='bigbutton' type='submit' name='btn_get_raw_tx' value='Get Raw Tx'/>
			</center>
		</p>
	</form>
			
<?php include_once("html_footer.php")?>	