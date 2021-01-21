<?php
include_once("common.php");

$targetPeer = "{$_SESSION['nodeIp']}:9981";

$rawAddress = Network::postToUrl($targetPeer,['query' => 'generateAddress']);
$address = json_decode($rawAddress,true);
$title = "New Address";
include_once("html_header.php");
?>
	<table style="width:40%">
		<tr><th colspan=2><b>Summary</b></th></tr>
		<tr><td><b>Address</b></td><td><?php echo $address['address']?></td></tr>
		<tr><td><b>Private Key</b></td><td><?php echo $address['privateKey'];?></td></tr>
		<tr><td><b>Public Key</b></td><td><?php echo $address['publicKey'];?></td></tr>
	</table>
		
	<H1>Raw Data</H1>
	<div>
		<?php echo $rawAddress?>
	</div>
<?php include_once("html_footer.php")?>