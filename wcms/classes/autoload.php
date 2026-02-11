<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

// CLI 환경과 웹 환경 모두 지원
if (php_sapi_name() === 'cli') {
    // CLI 환경: 현재 스크립트 경로를 기준으로 설정
    $scriptDir = dirname(__DIR__); // D:\workspace\git\datacms\wcms or /webSiteSource/wcms
    define('APP_ROOT_PATH', dirname($scriptDir) . '/');  // D:\workspace\git\datacms/
    define('COMMON_PATH', dirname($scriptDir) . '/kodes/wcms/');
} else {
    // 웹 환경: DOCUMENT_ROOT 기준
    define('APP_ROOT_PATH', preg_replace('/[^\/]+$/','',$_SERVER['DOCUMENT_ROOT']));
    define('COMMON_PATH', '/webSiteSource/kodes/wcms/');
}

define('CLASSES_PATH', APP_ROOT_PATH.'classes/');

date_default_timezone_set('Asia/Seoul');
// 크롬에서 POST 값을 출력할 수 없게 되어있는 것을 회피하기 위한 해더
// header('X-XSS-Protection: 0');  // ← 주석 처리: index.php에서 session_start() 후 호출
//header('X-XSS-Protection: 1;mode-=block'); 실 서비스 시. 크로스사이트스크립트를 탐지하면 웹페이지를 사용자에게 아예 보여주지도 않음.

// Composer autoload (vendor 경로 확인)
$vendorPath = COMMON_PATH . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require $vendorPath;
} else {
    // Windows 환경에서 절대 경로로 시도
    $altVendorPath = dirname(dirname(__DIR__)) . '/kodes/wcms/vendor/autoload.php';
    if (file_exists($altVendorPath)) {
        require $altVendorPath;
    } else {
        die("Composer autoload not found: {$vendorPath} or {$altVendorPath}\n");
    }
}

spl_autoload_register(function ($class) {
	$classFile = CLASSES_PATH.$class.'.php';
	if (is_file($classFile)) {
		require_once($classFile);
	}
}, true);
