<?php

require 'MIC_query.inc.php';
$mic = & MIC_query::open('sinaip.dat');

$ip = ($_SERVER['argv'][1] ? $_SERVER['argv'][1] : '202.106.184.175');
echo "MIC version: " . $mic->version() . "\n";
echo "QUERY IP: $ip\n";

$ret = $mic->query($ip);
if (!$ret) $result = "NOT FOUND";
else $result = (is_null($ret[0]) ? "NULL" : $ret[0]) . " " . (is_null($ret[1]) ? "NULL" : $ret[1]);
echo "RESULT: $result\n";

