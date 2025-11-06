<?php
// 출력 버퍼링 시작 (세션 쿠키 전송 문제 방지)
ob_start();

include_once("../classes/autoload.php");


// 프로덕션 환경에서는 에러 표시를 비활성화
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// session
//session_set_save_handler(new \Kodes\Wcms\DBSessionHandler(), true); // 세션을 DB에 저장

// 세션 저장 경로 설정
$sessionPath = '/var/lib/php/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0770, true);
    chmod($sessionPath, 0770);
    @chown($sessionPath, 'nginx');
    @chgrp($sessionPath, 'nginx');
}
session_save_path($sessionPath);

// 세션 쿠키 설정 (구워지지 않는 문제 해결)
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);  // HTTPS면 1로 변경
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_lifetime', 0);  // 브라우저 닫을 때까지
ini_set('session.gc_maxlifetime', 86400); // 24시간

// 세션 쿠키 파라미터 명시적 설정
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',  // 현재 도메인 자동 사용
    'secure' => false,  // HTTPS면 true로 변경
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// 보안 헤더 설정
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$idx = new Index();