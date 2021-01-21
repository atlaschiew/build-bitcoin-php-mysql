<?php

include_once("common.php");

$recordPerPage = 10;
$targetPeer = $_SESSION['nodeIp'];

$results = Network::postToUrl($targetPeer,['query' => 'blocks','orderby'=>'index DESC', 'start'=>(int)$_GET['more'], 'limit'=>$recordPerPage]);

$blocks = json_decode($results);
$title = "Blockchain";
include_once("html_header.php");
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

<table border=1 style='text-align:center;width:100%' bgcolor='#f7f7f7' >
	<tr style='background-color:#ccc'>
		<th>Height</th>
		<th>Age</th>
		<th>Transactions</th>
		<th>Total Sent</th>
		<th>Size (Bytes)</th>
		<th>Difficulty</th>
		<th>Target</th>
		<th>Chainwork</th>
		<th>Nonce</th>
		<th>Hash</th>
		<th>Prev Hash</th>
	</tr>
	<?php
	
	if ($totalBlocks = @count($blocks)) {

		foreach($blocks as $block) {
			$totalSent = 0;
			foreach($block->data as $data) {
				foreach($data->txOuts as $txOut) {
					$totalSent += $txOut->amount;
				}
			}
			echo "<tr>";
				echo "<td><a href='block.php?blockHash={$block->hash}'>{$block->blockIndex}</a></td>";
				echo "<td>{$block->timestamp}</td>";
				echo "<td>".count($block->data)."</td>";
				echo "<td>{$totalSent}</td>";
				echo "<td>".strlen(Utils::jsonEncode($block->data))."</td>";
				echo "<td>{$block->difficulty}</td>";
				echo "<td>{$block->target}</td>";
				echo "<td>{$block->chainWork}</td>";
				echo "<td>{$block->nonce}</td>";
				echo "<td>{$block->hash}</td>";
				echo "<td>{$block->previousHash}</td>";
			echo "</tr>";
		}
	}
	?>
</table>
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