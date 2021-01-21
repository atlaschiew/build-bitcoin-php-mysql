<?php
set_time_limit(60);
include_once("common.php");
if (ob_get_level() == 0) ob_start();

$targetPeer = "{$_SESSION['nodeIp']}:9981";

if ($_SESSION['minerAddress']) {
	$minerAddress = $_SESSION['minerAddress'];
} else {
	$minerAddress = "APxau5HuCBdyWJDAW1W5jGoPEsjuzJiSHy";
}

$blockTemplateRaw = Network::postToUrl($targetPeer,['query' => 'getBlockTemplate','minerAddress'=>$minerAddress]);
$newBlockTemplate = json_decode($blockTemplateRaw,true);

if ($_SESSION['template'] != "") {
	$currBlockTemplate = json_decode($_SESSION['template'],true);
	
	if ($currBlockTemplate['blockIndex'] < $newBlockTemplate['blockIndex']) {
		$nextBlockTemplate = $newBlockTemplate;
	} else {
		$nextBlockTemplate = $currBlockTemplate;
	}
} else {
	$nextBlockTemplate = $newBlockTemplate;
}

$_SESSION['template'] = Utils::jsonEncode($nextBlockTemplate);

$blockIndex   = $nextBlockTemplate['blockIndex'];
$previousHash = $nextBlockTemplate['previousHash'];
$timestamp    = $nextBlockTemplate['timestamp'];
$data         = $nextBlockTemplate['data'];
$targetHex    = $nextBlockTemplate['target'];



echo "<p>Grab next block</p>";
echo "<p>".$_SESSION['template']. "</p>";
ob_flush();
flush();
$maxAttempt = 10000;
$attempt = 0;
while($attempt < $maxAttempt) {
	
	$nonce = rand(0,2147483647);
	$result = Chain::solvePOW($blockIndex,$previousHash,$timestamp,$data,$targetHex, $nonce);
	if ($result !== false) {
		
		$nextBlockTemplate['hash'] = Block::calculateHash($blockIndex,$previousHash,$timestamp, $data, $targetHex, $nonce);
		$nextBlockTemplate['nonce'] = $nonce;
		
		$newBlock = Utils::jsonEncode($nextBlockTemplate);
		
		echo "<p>New block mined!</p>";
		echo "<p>".$newBlock . "</p>";
		ob_flush();
		flush();
		
		Network::postToUrl($targetPeer,['query' => 'addBlock', 'block'=>$nextBlockTemplate]);
		
		$startNonce = 0;
		$_SESSION['template'] = "";
		break;
	}
	echo $nonce . " attempted!<br/>";
	ob_flush();
	flush();
	$attempt++;
	
}

?>
<html>
	<head>
		<meta http-equiv="refresh" content="3;url=" />
	</head>
	<body>
	
	</body>
</html>