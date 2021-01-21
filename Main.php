<?php

foreach (glob(__DIR__ . "/Vendor/Tuaris/CryptoCurrencyPHP/*.php") as $filename)
{
    include_once $filename;
}

include_once(__DIR__ . "/Config.php");
include_once(__DIR__ . "/Address.php");
include_once(__DIR__ . "/Block.php");
include_once(__DIR__ . "/Chain.php");
include_once(__DIR__ . "/Consensus.php");
include_once(__DIR__ . "/Network.php");
include_once(__DIR__ . "/TxPool.php");
include_once(__DIR__ . "/Transaction.php");
include_once(__DIR__ . "/Utils.php");
include_once(__DIR__ . "/Utxo.php");

set_time_limit(0);

//get arguments from command param
$longOpts = [];
foreach($config as $k=>$v) {
	$longOpts[] = "{$k}::";
}
$args = getopt(null,$longOpts);
$config = $args + $config;

//set global variables
Utils::world("systemTask.checkFork", []);
Utils::world("systemTask.downloadBlocks", []);

try {
	//connect mysql
	DB::connect($config['dbHost'],$config['dbUser'],$config['dbPwd'],$config['dbName']);
	
	//start chain
	$chain = new Chain();
	$chain->start();
} catch (mysqli_sql_exception $e) { 
	DB::rollback();
	echo "Exit with {$e->getMessage()}";
	exit(255);
} catch (Exception $e) {
	echo "Exit with {$e->getMessage()}";
	exit(255);
}



