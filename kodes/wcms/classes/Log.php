<?php
namespace Kodes\Wcms;

/**
 * 로그 출력 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Log
{
	public function __construct()
    {
	}

    /**
     * 로그 파일을 만든다.
     *
     * @param string $coId
     * @param string $msg
     * @param string $flag
     * @return void
     */
	public function writeLog($coId, $msg, $flag="")
    {
		$logDir = "/webData/".$coId."/log/".date('Y').'/'.date('m').'/';
		
		if (!is_dir($logDir)) {
			mkdir($logDir, 0777, true);
			chown($logDir, 'apache');
			chgrp($logDir, 'apache');
		}
		
		$date = date("Y-m-d H:i:s");
		$filename = $logDir.date('Y').date('m').date('d')."_".$flag.'.log';
		file_put_contents($filename, "[".$date."] ".$msg."\n", FILE_APPEND );
		chown($filename, 'apache');
		chgrp($filename, 'apache');
	}
}