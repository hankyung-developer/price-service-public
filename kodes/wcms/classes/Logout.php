<?php
namespace Kodes\Wcms;

/**
 * 로그아웃 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Logout
{
    /**
     * 생성자
     * 로그아웃 처리
     */
    function logout()
    {
        session_destroy();
        header("Location: /login");
    }
}