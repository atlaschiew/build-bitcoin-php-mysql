<!DOCTYPE html>
<html>
	<head>
		<title><?php echo $title?> Latest Blocks</title>
		<script
  src="https://code.jquery.com/jquery-2.2.4.min.js"
  integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
  crossorigin="anonymous"></script>
		<script>
			function is_numeric(str){
				return /^\d+$/.test(str);
			}

			jQuery(document).ready(function(){
				$( "input[name=search]" ).click(function(  ) {
					
					var self = $(this);
					var form = self.closest("form");
					var val =  $("input[name=searchtext]",form).val();
					
					val = jQuery.trim(val);
					
					if (is_numeric(val)) {
						document.location = "block.php?blockIndex="+val;
						return true;
					} else if (val.length == 64) {
						document.location = "transaction.php?txId="+val;
						return true;
					} else if (val.length == 34) {
						document.location = "address.php?address="+val;
						return true;
					}
					
					return false;
				});
			});
		</script>
		<style>
			body {
				font-size:10pt;
				font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
				margin:0px;
				padding:0px;
			}

			table {
				
				border-collapse: collapse;
				width: 100%;
			}

			table td, table th {
				border: 1px solid #ddd;
				padding: 8px;
			}

			table tr:nth-child(even){background-color: #f2f2f2;}

			table tr:hover {background-color: #ddd;}

			table th {
				padding-top: 12px;
				padding-bottom: 12px;
				text-align: left;
				background-color: #483D8B;
				color: white;
			}


			table.no_class {
				
				border-collapse: collapse;
				width: 100%;
			}

			table.no_class td, table.no_class th {
				border: 1px solid #ddd;
				padding: 8px;
				vertical-align: text-top;
			}

			table.no_class tr:nth-child(even){background-color: transparent;}

			table.no_class tr:hover {background-color: transparent;}

			table.no_class th {
				padding-top: 12px;
				padding-bottom: 12px;
				text-align: left;
				background-color: #483D8B;
				color: white;
			}

			.bigbutton{ 
				font-weight:bold;
				font-size:10pt;
				padding:10px;
			}
			
			/* The alert message box */
			.success {
				padding: 20px;
				background-color: green; /* Red */
				color: white;
				margin-bottom: 15px;
			}
			
			.alert {
				padding: 20px;
				background-color: #f44336; /* Red */
				color: white;
				margin-bottom: 15px;
			}

			/* The close button */
			.closebtn {
				margin-left: 15px;
				color: white;
				font-weight: bold;
				float: right;
				font-size: 22px;
				line-height: 20px;
				cursor: pointer;
				transition: 0.3s;
			}

			/* When moving the mouse over the close button */
			.closebtn:hover {
				color: black;
			}
		</style>
	</head>
	<body>
<?php
$filename = basename($_SERVER[PHP_SELF]);

$menus = array("index.php"=>"Blockchain", "new_tx.php"=>"New Transaction", "push_tx.php"=>"Push Transaction", "txpool.php"=>"Transaction Pool", "utxo.php"=>"Utxo", "new_address.php"=>"New Address","peers.php"=>"Peers");
echo "<p>";
	echo "<div style='float:left'><form method='post' action='set_conn.php?goback={$filename}'><b>Connection:</b> <input type='text' name='nodeIp' value='{$_SESSION['nodeIp']}'/>";
	echo " <input type='submit' value='Connect'/></form></div>";
	
	
	echo "<div style='float:right;'><form name='search' method='POST' action=''>
	          <input type='text' name='searchtext' placeholder='Search Block Index, Transaction ID or Address' size=100 />
		      <input type='button' name='search' value='Search'/>
		  </form></div>";
	echo "<div style='clear:both;'></div>";
echo "</p>";

ob_start();

foreach($menus as $file=>$menu) {
	if ($file == basename($_SERVER['SCRIPT_FILENAME'])) {
		$style=" style='font-weight:bold;color:white;background-color:DodgerBlue;'";
	} else {
		$style = " style='font-weight:bold;'";
	}
?><a<?php echo $style?> href='<?php echo $file?>'><?php echo $menu?></a> | <?php
}

$html = ob_get_clean();
$html = rtrim($html," | ");
echo "<p>";
echo "<b>Main Menu: </b>";
echo $html;
echo "</p>";
?>
<p>
	<?php 
	if ($errMsg) {
	?>
		<div class="alert">
			<span class="closebtn" onclick="this.parentElement.style.display='none';">&times;</span>
		    <?Php echo $errMsg?>
		</div>
	<?php	
	}
	
	if ($succMsg) {
	?>
		<div class="success">
			<span class="closebtn" onclick="this.parentElement.style.display='none';">&times;</span>
		    <?Php echo $succMsg?>
		</div>
	<?php
	}
	?>
</p>
<hr/>
	<H1><?php echo $title?></H1>