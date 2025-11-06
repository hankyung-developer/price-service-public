<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

define('APP_ROOT_PATH', preg_replace('/[^\/]+$/','',$_SERVER['DOCUMENT_ROOT']));
define('COMMON_PATH', '/webSiteSource/kodes/api/');
define('CLASSES_PATH', APP_ROOT_PATH.'classes/');

date_default_timezone_set('Asia/Seoul');
// 크롬에서 POST 값을 출력할 수 없게 되어있는 것을 회피하기 위한 해더
header('X-XSS-Protection: 0');
//header('X-XSS-Protection: 1;mode-=block'); 실 서비스 시. 크로스사이트스크립트를 탐지하면 웹페이지를 사용자에게 아예 보여주지도 않음.

require COMMON_PATH . '/vendor/autoload.php';

spl_autoload_register(function ($class) {
	$classFile = CLASSES_PATH.$class.'.php';
	if (is_file($classFile)) {
		require_once($classFile);
	}
}, true);
