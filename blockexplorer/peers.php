<?php
include_once("common.php");

$targetPeer = "{$_SESSION['nodeIp']}:9981";

$results = Network::postToUrl($targetPeer,['query' => 'getPeers']);
$peers = json_decode($results,true);

$title = "Peers";
include_once("html_header.php");
?>
<table border=1>
	<tr><th colspan=2><b>Host IP</b></th></tr>
<?php
if (@count($peers)) {
	foreach($peers as $peer) {
		echo "<tr>
				<td>{$peer}</td>
			  </tr>";
	}
}
?>
</table>
<H1>Raw Data</H1>
<div>
	<?php echo $results?>
</div>
		