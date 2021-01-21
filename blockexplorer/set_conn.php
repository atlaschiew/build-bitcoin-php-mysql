<?php

include_once("common.php");

$_SESSION['nodeIp'] = $_POST['nodeIp'];

@header("Location: {$_GET['goback']}");
die;