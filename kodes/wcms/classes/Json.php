<?php
namespace Kodes\Wcms;

/**
 * JSON 파일을 읽고 만드는데 사용할 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Json
{
    protected $readDir;

    /**
     * Json 생성자
     */
    function __construct()
    {
    }

    /**
     * JSON 파일을 읽는다.
     *
     * @param String $path
     * @param String $fileName
     * @return json Data
     */
	function readJsonFile($path, $fileName)
    {
        $jsonFile = $path.'/'.$fileName.'.json';
        $jsonData = "";
        if(is_file($jsonFile)){
            $str = file_get_contents($jsonFile);
		    $jsonData = json_decode($str,true);
        }
		return $jsonData;
	}

    /**
     * Python JSON 파일을 읽는다.
     *
     * @param String $path
     * @param String $fileName
     * @return json Data
     */
	function readJsonPythonFile($path, $fileName)
    {
        $jsonFile = $path.'/'.$fileName.'.json';
        $jsonData = "";
        if(is_file($jsonFile)){
            $str = file_get_contents($jsonFile);
		    $jsonData = json_decode(json_decode(str_replace("\ufeff", '', json_encode($str))),true);
        }
		return $jsonData;
	}

    /**
     * JSON 파일을 만든다.
     *
     * @param String $path
     * @param String $fileName
     * @param Array $data
     * @return void
     */
	function makeJson($path, $fileName, $data)
    {
        $fileName = $path.'/'.$fileName.'.json';
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);

        //하위 디렉토리 만들기 넣어야함.
        if (!is_dir($path)){
            mkdir($path, 0777, true);
			chgrp($path, "nginx");
			chown($path, "nginx");
        }

        file_put_contents($fileName, $jsonData);
        chmod($fileName, 0777);
        chown($fileName, 'nginx');
        chgrp($fileName, 'nginx');
    }
}