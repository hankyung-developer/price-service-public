<?php
include_once("../classes/autoload.php");

// CORS 헤더 설정 (모든 요청에 대해)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // 24시간 동안 preflight 캐시

// Preflight OPTIONS 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// session
//session_set_save_handler(new \Kodes\Wcms\DBSessionHandler(), true); // 세션을 DB에 저장
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
session_start();

header('X-XSS-Protection: 0');

$idx = new Index();