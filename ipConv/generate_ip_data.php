#!/usr/local/bin/php -q
<?php
/*
 IP地址转换,包括地区和ISP信息 .该格式转换后，用于 logstash中filter阶段的iplocatio,其中logstash中geo插件不包括国内的isp信息才有个初步转换的信息
 也可参照纯真IP库的格式 
 格式说明:
一行一条记录 
每条记录由 4 个字段组成, 字段之间用TAB分隔
<起始IP> <结束IP> <省.市>  <ISP信息>
比如:ip为ip2long的整数 
202.106.184.123 202.106.184.234 北京北京联通  
*/

/*
Author: hightman  (http://www.hightman.cn) 从该作者处修改。
[CTIP][IndexNUM]

  ...
{                                  }
{ [Start_IP][Area1_off][Area2_off] }
{                                  }
   ...  4+4+4 = (32 bit, little endian byte order)
   ...

{                }
{ [Len][String]  }
{                }
   ...  1+N = ...
   ...

*/
ini_set('memory_limit', '7024M');
set_time_limit(0);

// check the SAPI
if (php_sapi_name() != 'cli' && !strstr(php_sapi_name(), 'cgi'))
{
    echo "ERROR: the script cann't be run using " . strtoupper(php_sapi_name()) . "PHP.\n";
    exit(-1);
}

// get the arguments
$input = (isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : 'sinaip.log.out');
$output = (isset($_SERVER['argv'][2]) ? $_SERVER['argv'][2] : 'sinaip.dat');

// open the file
if (!($fr = fopen($input, "r")))
{
    echo "ERROR: cann't open the input file: $input\n";
    exit(-1);
}

if (!($fw = fopen($output, "wb")))
{
    echo "ERROR: cann't write to the output file: $output\n";
    fclose($fr);
    exit(-1);
}

// 
echo "Starting to convert the IP data (input = $input , output = $output)\n";
echo "Loading the data from input file ... "; flush();
$rec = array();

// Load the data
while ($line = fgets($fr, 1024))
{
    $off = 0;
    $bit = -1;
    $ip1 = _next_part($line, $off);
    $ip2 = _next_part($line, $off);

    $area1 = _next_part($line, $off);
    $area2 = trim(substr($line, $off));

    $ip1 = (float) sprintf('%u', $ip1);
    $ip2 = (float) sprintf('%u', $ip2);
    $rec[] = array($ip1, $ip2, $area1, $area2);
}
fclose($fr);

// sort the rec first time
echo "OK, total records = " . count($rec) . "\nFirstly, sort them ... ";
flush();
usort($rec, '_rec_cmp');


// plain or binary?
if (isset($_SERVER['argv'][3]) && $_SERVER['argv'][3] == 'plain')
{
    foreach ($rec as $tmp)
    {
        $ip1 = long2ip($tmp[0]);
        $ip2 = long2ip($tmp[1] - 1);
        $line = sprintf("%-16.16s%-16.16s %s %s\n", $ip1, $ip2, $tmp[2], $tmp[3]);
        fputs($fw, $line);
    }
}
else
{
    // file header  
    fwrite($fw, 'CTIP' . pack('V', count($rec)), 8);

    // write Index data
    $o1 = $o2 = 0;
    $a1 = $a2 = array();
    $b1 = $b2 = '';
    foreach ($rec as $tmp)
    {
        // Area1
        if ($tmp[2] == '') $_o1 = -1;
        else if (!isset($a1[$tmp[2]]))
        {
            $_o1 = $o1;
            $o1 += strlen($tmp[2]) + 1;
            $a1[$tmp[2]] = $_o1;
            $b1 .= chr(strlen($tmp[2])) . $tmp[2];
        }
        else $_o1 = $a1[$tmp[2]];

        // Area2
        if ($tmp[3] == '') $_o2 = -1;
        else if (!isset($a2[$tmp[3]]))
        {
            $_o2 = $o2;
            $o2 += strlen($tmp[3]) + 1;
            $a2[$tmp[3]] = $_o2;
            $b2 .= chr(strlen($tmp[3])) . $tmp[3];
        }
        else $_o2 = $a2[$tmp[3]];

        // write index
        fwrite($fw, pack('VVV', $tmp[0], $_o1, $_o2), 12);
    }

    // last line (endip + len for a1 + data chrono);
    fwrite($fw, pack('VVV', $tmp[1], $o1, time()), 12);

    // area1, area2
    fwrite($fw, $b1, $o1);
    fwrite($fw, $b2, $o2);
}

// close the file
echo "DONE!\n";
fclose($fw);
exit(0);

///////////////////
// some functions
///////////////////
function _rec_cmp($a, $b)
{
    if ($a[0] == $b[0]) return 0;
    else if ($a[0] > $b[0]) return 1;
    else return -1;
}

function _next_part($buf, &$start)
{   
    while (isset($buf[$start]) && strchr("\t", $buf[$start])) $start++;
    if (!isset($buf[$start])) return '';
    for ($end = $start; isset($buf[$end]) && !strchr("\t", $buf[$end]); $end++);
    $part = substr($buf, $start, $end - $start);
    $start = $end;
    return $part;
}

?>
