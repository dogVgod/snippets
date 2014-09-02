<?php
ini_set('memory_limit', '2024M');
error_reporting(E_ERROR | E_WARNING | E_PARSE); 
require 'MIC_query.inc.php';
$mic = & MIC_query::open('ipinfo.data');

$ip = ($_SERVER['argv'][1] ? $_SERVER['argv'][1] : '210.32.157.3');
$ips =  file("./check.ip");
$n = 0;

foreach($ips as $ip){
    list($ip,$a,$b,$c) = explode("\t",$ip);
    $str = trim($a.$b.$c);
    $str1 = getInfo($mic,$ip);
    if($str == $str1){
        
        echo "MATCH:","#$str#$str1\n";
    }else{
        echo "ERROR:","$ip","#$str#$str1\n";
    }
    $n++;
    if($n == 10){
        #break;
    }
}
function getInfo($mic,$ip){
    $ret = $mic->query($ip);
    if (!$ret) $result = "NOT FOUND";
    else $result = trim($ret[0].$ret[1]);

    return $result;
}
