<?php

include_once("common.php");

$_SESSION['nodeIp'] = $_POST['nodeIp'];
$_SESSION['minerAddress'] = $_POST['minerAddress'];

@header("Location: {$_GET['goback']}");
die;