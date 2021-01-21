<?Php

foreach (glob(__DIR__ . "/../Vendor/Tuaris/CryptoCurrencyPHP/*.php") as $filename)
{
    include_once $filename;
}

include_once(__DIR__ . "/../Config.php");
include_once(__DIR__ . "/../Address.php");
include_once(__DIR__ . "/../Block.php");
include_once(__DIR__ . "/../Chain.php");
include_once(__DIR__ . "/../Consensus.php");
include_once(__DIR__ . "/../Network.php");
include_once(__DIR__ . "/../TxPool.php");
include_once(__DIR__ . "/../Transaction.php");
include_once(__DIR__ . "/../Utils.php");
include_once(__DIR__ . "/../Utxo.php");

session_start();