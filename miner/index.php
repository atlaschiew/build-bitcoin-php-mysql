<?php

include_once("common.php");

$title = "Miner";
$filename = basename($_SERVER[PHP_SELF]);

unset($_SESSION['template']);
?>
<!DOCTYPE html>
<html>
	<head>
		<title><?php echo $title?></title>
		<script
  src="https://code.jquery.com/jquery-2.2.4.min.js"
  integrity="sha256-BbhdlvQf/xTY9gja0Dq3HiwQF8LaCRTXxZKRutelT44="
  crossorigin="anonymous"></script>
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
		<form method='post' action='set_conn.php?goback=<?php echo $filename?>'>
			<b>Connection:</b>
			<input type='text' name='nodeIp' value='<?php echo $_SESSION['nodeIp']?>'/>
			
			<b>Miner Address:</b>
			<input type='text' name='minerAddress' value='<?php echo $_SESSION['minerAddress']?>'/>
			
			<input type='submit' value='Connect'/>
		</form>
		<h1 style="background-color: #483D8B;color:#fff;padding-top:5px;padding-bottom:5px;"><?php echo $title?></h1>
		
		<iframe src="mining.php" style="width:100%;height:500px;"></iframe>
	</body>
</html>


