<?php
// MyIpConv  (MIC) ver 1.0.0
// IP 地址段转换器 (常用于IP所在地点、对应的ISP等查询)
//
// Target: MIC API
// Author: hightman  (http://www.hightman.cn)
// Create: 2008/03/12
// Update: 
//
// Usage:  
//
// require 'MIC_query.inc.php';
// $mic = new MIC_query('mic.dat');
// $mic->open('/path/to/datafile');
// $ret = $mic->query('210.32.157.3');
// print_r($ret);
// NOT FOUND: return false
// Mathced: returnn array(<AREA1>, <AREA2>);
//
// $mic = & MIC_query::open();
// $mic->close();
//
// $Id: $
//

class MIC_query
{
	var $fd;
	var $inum;
	var $off1;
	var $off2;
	var $flag;
	var $chrono;

	function MIC_query($fpath = '')
	{
		$this->fd = false;
		$this->off1 = $this->off2 = 0;
		if ($fpath != '') $this->open($fpath);
	}

	function & open($fpath = '')
	{
		if (!isset($this))
		{
			$name = defined('__CLASS__') ? __CLASS__ : 'MIC_query';
			$obj = new $name;
			if ($obj->open($fpath))	return $obj;
			return false;
		}
		if ($fpath == '') $fpath = dirname(__FILE__) . '/mic.dat';
		if (!($fd = fopen($fpath, 'rb')))
		{
			trigger_error("Cann't open the MIC data file($fpath)", E_USER_WARNING);
			return false;
		}else{
			echo "open log \n";
		}
		
		fseek($fd, 0, SEEK_SET);
		$tmp = unpack('a4flag/Vinum', fread($fd, 8));
		#var_dump("head",$tmp);
		fseek($fd, $tmp['inum'] * 12 + 8, SEEK_SET);
		$buf = fread($fd, 12);
		if ($tmp['flag'] != 'CTIP' || strlen($buf) != 12)
		{			
			fclose($fd);
			trigger_error("Invalid MIC data file($fpath)", E_USER_WARNING);
			return false;
		}
		$this->fd = $fd;
		$this->flag = $tmp['flag'];
		$this->inum = $tmp['inum'];
		$this->off1 = $tmp['inum'] * 12 + 20;
		
		$tmp = unpack('V3', $buf);
		#var_dump("index end",$tmp);
		$this->off2 = $this->off1 + $tmp[2];
		$this->chrono = $tmp[3];
		return true;
	}

	function close()
	{
		if ($this->fd) fclose($this->fd);
		$this->fd = false;
	}

	function query($ip)
	{
		// check the IP
		$ip0 = ip2long($ip);
		if ($ip0 === false || $ip0 === -1)
		{
			$ip0 = gethostbyname($ip);
			if (!$ip0 || !($ip0 = ip2long($ip0)) || $ip0 == -1)
			{
				trigger_error("Invalid IP Address($ip)", E_USER_WARNING);
				return false;
			}
		}

		// check the FD
		if (!$this->fd && !$this->open())				
			return false;

		// Binary search
		$ip0 = (float) sprintf('%u', $ip0);
		$low = 0;
		$high = $this->inum - 1;
        #echo $high,"\n";
		$ret = false;
		while ($low <= $high)
		{
			$mid = ($low+$high)>>1;
            #echo $mid,"\n";
			$off = $mid * 12 + 8;
			fseek($this->fd, $off, SEEK_SET);
			$buf = fread($this->fd, 16);
			if (strlen($buf) != 16){ 
				echo "length is 16\n";
				break;
			}
			$tmp = unpack('V4', $buf);
			if ($tmp[1] < 0) $tmp[1] = (float) sprintf('%u', $tmp[1]);
			if ($tmp[4] < 0) $tmp[4] = (float) sprintf('%u', $tmp[4]);

            #echo "$tmp[1],\n";
			// compare them
			if ($ip0 < $tmp[1])
			{
				// smaller
				$high = $mid - 1;
			}
			else if ($ip0 >= $tmp[4])
			{
				// bigger
				$low = $mid + 1;
			}
			else
			{
				// matched
                #var_dump($tmp[2],$this->off1);
                #echo "country_offset\n",$this->off1 ,"\n cuo off:", $tmp[2],"\n pos",ftell($this->fd),"\n";
				$ret = array(NULL, NULL);
				if ($tmp[2] >= 0)
				{
					fseek($this->fd, $this->off1 + $tmp[2], SEEK_SET);
					$vlen = ord(fread($this->fd, 1));
                    #echo "vlen:",$vlen,"\n";
					if ($vlen > 0) $ret[0] = fread($this->fd, $vlen);

                    #echo $ret[0],"\n";
				}

                #echo "isp_offset",$this->off2 + $tmp[3],"\n";
				if ($tmp[3] >= 0)
				{
					fseek($this->fd, $this->off2 + $tmp[3], SEEK_SET);
					$vlen = ord(fread($this->fd, 1));
                    #echo "$vlen,last isp offset\n";
					if ($vlen > 0) $ret[1] = fread($this->fd, $vlen);
				}
				break;
			}
		}
		return $ret;
	}

	function version()
	{
		// check the FD
		if (!$this->fd && !$this->open())				
			return false;

		$version = sprintf('%s(Records Num = %d , Date = %s)', 
			$this->flag, $this->inum, date('Y-m-d H:i:s', $this->chrono));
		return $version;
	}
}

?>
