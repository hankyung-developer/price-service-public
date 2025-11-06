<?php
namespace Kodes\Wcms;

// 프로덕션 환경에서는 display_errors를 0으로 설정
ini_set('display_errors', 0);

/**
 * 로그인 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Login
{
    /** const */
	const COLLECTION = 'manager';

    /** @var Class */
    protected $db;
    protected $common;
    protected $json;
    protected $manager;

    /** @var variable */
    protected $coId;
    protected $ip;

    /**
     * 생성자
     */
    function __construct()
    {
        // class
        $this->db = new DB();
        $this->common = new Common();
        $this->json = new Json();
        $this->manager = new Manager();

        // variable
        $this->ip = $this->common->getRemoteAddr();

        $this->coId = $this->common->coId;

        /*
         * google recaptcha 설정
         * https://www.google.com/recaptcha/admin/
         * kodes.asp@gmail.com 계정
         */
        $this->google_recaptcha_siteKey = '6LcbjQgdAAAAADdUHrzb2fdLpI3GoCCmLBkpVqF1';
        $this->google_recaptcha_secretKey = '6LcbjQgdAAAAADUrAl7ACPY8c_S-2hBK1LGRcxjd';
    }

    /**
     * 로그인 화면
     */
    public function login()
    {
        // 로그인 coId
        $coId = empty($_GET['coId'])?'':$_GET['coId'];
        if (!empty($coId)) {
            $return['company'] = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$coId.'/config', $coId.'_company');
        }

        // $return['google_recaptcha_siteKey'] = $this->google_recaptcha_siteKey;
        return $return;
    }

    /**
     * 로그인 체크
     */
    public function check()
    {
        $return = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');
            
            // google recaptcha 검증
            //$this->checkGoogleRecaptcha($_POST['g-recaptcha-response']);

            $id = $_POST['id'];
            $password = $_POST['password'];
            unset($_POST);

            // 관리자 조회
            $filter = ["id" => $id];
            $options = ['projection'=>['_id'=>0]];
            $loginData = $this->db->item(self::COLLECTION, $filter, $options);

            // ID 검증
            if (empty($loginData['id'])) {
                throw new \Exception("로그인 정보가 틀립니다.", 400);
            }
            // 비밀번호 검증
            if ($loginData['password'] != $this->manager->encryptPassword($password, $loginData['salt'])) {
                throw new \Exception("로그인 정보가 틀립니다.", 400);
            }

            // 로그인정보 세션에 등록
            $return['managerId'] = $loginData['id'];
            $return['link'] = $this->setSessionLogin($loginData);

            // 로그인 기록
            $filter = ['id'=>$id];
            $data = ['latestLogin.date'=>date('Y-m-d H:i:s'), 'latestLogin.ip'=>$this->ip];   // @todo 입력값 확인
            $result = $this->db->update(self::COLLECTION, $filter, ['$set'=>$data]);

            $return['msg'] = "로그인 성공";
            $return['loginData'] = $loginData;

        } catch(\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);

        }

        return $return;
    }

    /**
     * google recaptcha 검증
     */
    protected function checkGoogleRecaptcha($captcha)
    {
        $data = array(
            'secret' => $this->google_recaptcha_secretKey,
            'response' => $captcha,
            'ip' => $this->ip
        );
        $parameter = http_build_query($data);
        $url = "https://www.google.com/recaptcha/api/siteverify?".$parameter;

        $response = file_get_contents($url);
        $responseKeys = json_decode($response, true);
        // print_r($responseKeys);
    
        if (empty($responseKeys["success"])) {
            throw new \Exception("recaptcha 검증에 실패하였습니다.", 400);
        }
    }

    /**
     * 로그인정보 세션에 등록
     */
    protected function setSessionLogin($loginData)
    {
        $loginData['currentAuthIds'] = [];
        $loginData['allowDepartmentIds'] = [];
        if (!empty($loginData['authId'])) $loginData['currentAuthIds'][] = $loginData['authId'];
        if (!empty($loginData['departmentId'])) $loginData['allowDepartmentIds'][] = $loginData['departmentId'];
        // 회사정보 처리
        if (!empty($this->coId)) {
            $currentCompany = $this->common->searchArray2D($loginData['allowCompany'], 'coId', $this->coId);
            if (!empty($currentCompany)) {
                if(strpos($loginData['currentAuthIds'][0], $this->coId) === false) {
                    $loginData['currentAuthIds'] = $currentCompany['authId'];
                    $loginData['allowDepartmentIds'] = $currentCompany['departmentId'];
                } else {
                    if (!empty($currentCompany['authId']) && is_array($currentCompany['authId'])) {
                        $loginData['currentAuthIds'] = array_values(array_unique(array_merge($loginData['currentAuthIds'], $currentCompany['authId'])));
                    }
                    if (!empty($currentCompany['departmentId']) && is_array($currentCompany['departmentId'])) {
                        $loginData['allowDepartmentIds'] = array_values(array_unique(array_merge($loginData['allowDepartmentIds'], $currentCompany['departmentId'])));
                    }
                }
            }

            // 현재 부서ID
            if (empty($loginData['currentDepartmentId']) || strpos($loginData['currentDepartmentId'], $this->coId) === false) {
                if (!empty($loginData['allowDepartmentIds'][0])) {
                    $loginData['currentDepartmentId'] = $loginData['allowDepartmentIds'][0];
                } else {
                    $loginData['currentDepartmentId'] = null;
                }
            }

            // 현재 부서 조회
            if (!empty($loginData['currentDepartmentId'])) {
                $loginData['department'] = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$this->coId.'/department', $loginData['currentDepartmentId']);
            }
            // if (!empty($loginData['departmentId'])) {
            //     $loginData['department'] = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$this->coId.'/department', $loginData['departmentId']);
            // }
        }
        
        // 권한 조회
        $filter = ['id'=>['$in'=>$loginData['currentAuthIds']]];
        $options = ['projection'=>['_id'=>0]];
        
        $authItems = $this->db->list('auth', $filter, $options);
        $authClass = new Auth();
        $loginData['auth'] = $authClass->mergeAuth($authItems);  // 권한 merge

        // 카테고리 즐겨찾기
        if (empty($loginData['favoritesCategory'][$this->coId])) {
            $loginData['favoritesCategory'] = [];
        } else {
            $loginData['favoritesCategory'] = $loginData['favoritesCategory'][$this->coId];
        }

        // 시리즈 즐겨찾기
        if (empty($loginData['favoritesSeries'][$this->coId])) {
            $loginData['favoritesSeries'] = [];
        } else {
            $loginData['favoritesSeries'] = $loginData['favoritesSeries'][$this->coId];
        }

        // 세션 처리
        $loginData['managerId'] = $loginData['id'];
        $loginData['managerName'] = $loginData['name'];
        unset($loginData['id']);
        unset($loginData['name']);
        unset($loginData['password']);
        unset($loginData['salt']);
        unset($loginData['insert']);
        unset($loginData['update']);
        
        $company = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$loginData['coId'].'/config', $loginData['coId'].'_company');
        $loginData['action'] = !empty($company['action']) ? $company['action'] : "";
        
        $_SESSION = $loginData;

        if (!empty($_SESSION['isSuper']) && $_SESSION['isSuper']) {
            // isSuper
            return $this->makeLeftMenu([], true);
        } elseif (empty($loginData['auth']["menu"])) {
            return [];
        } else {
            return $this->makeLeftMenu($loginData['auth']["menu"]);
        }
    }

    public function makeLeftMenu($auth_menu, $ignorePermission = false)
    {
		// $ignorePermission = false;

        $menu = $this->common->getWcmsMenu();
        
        $leftMenu =""; 
        $likeMenu =""; // 즐겨찾기메뉴
        $flag = false;
        $link = "";

        // leftmenu
        if (!empty($menu) && is_array($menu)) {
            
            $likeMenu .= '<ul id="likeMenuSortable">'; // 즐겨찾기메뉴

            foreach ($menu as $i => $tmp) {
                if ($ignorePermission || (!empty($auth_menu) && is_array($auth_menu) && in_array($tmp['menuId'], $auth_menu))) {
                    if (empty($link) && $tmp['link']) {
                        $link = $tmp['link'];
                    }

                    if (isset($tmp['child']) && count($tmp['child']) > 0) {
                        $leftMenu .= ' <details>';
                        $leftMenu .= '<summary><i class="'.$tmp['icon'].'"></i>'.$tmp ['menuName'].'</summary>';
                        $leftMenu .= '<ul>';
                        foreach ($tmp['child'] as $j => $child) {
                            if (empty($link) && $child['link']) {
                                $link = $child['link'];
                            }
        
                            if ($ignorePermission || (!empty($auth_menu) && is_array($auth_menu) && in_array($child['menuId'], $auth_menu))) {
                                $leftMenu .= '<li><a href="'.$child['link'].'" id="'.$child['selector'].'" class="secondary" target="'.$child['target'].'" data-link="'.(isset($child['datalink'])?$child['datalink']:'').'">';
                                $leftMenu .= '    <i class="fa-solid fa-period"></i>'.$child['menuName'];
                                $leftMenu .= '    </a>';
                                
                                // 즐겨찾기메뉴 처리
                                $addMenu = '    <span class="add_menu" data-tooltip="즐겨찾기추가"><i class="fa-light fa-star"></i></span>';
                                if(!empty($_SESSION['favoritesMenu'][$this->coId]) && in_array($child['selector'], $_SESSION['favoritesMenu'][$this->coId])){
                                    $addMenu = '    <span class="add_menu" data-tooltip="즐겨찾기삭제"><i class="fa-solid fa-star"></i></span>';
                                    
                                    // 즐겨찾기 메뉴 영역
                                    $idx = array_search($child['selector'], $_SESSION['favoritesMenu'][$this->coId]);
                                    $likeMenu .= '<li class="item" data-idx="'.$idx.'"data-selector="'.$child['selector'].'">';
                                    $likeMenu .= '  <a href="'.$child['link'].'"  target="'.$child['target'].'" >';
                                    $likeMenu .= '      <i class="'.$tmp['icon'].'"></i><span>'.$child['menuName'].'</span>';
                                    $likeMenu .= '  </a>';
                                    $likeMenu .= '</li>';
                                }
                                $leftMenu .= $addMenu;

                                $leftMenu .= '</li>';
                            }
                            
                        }
                        $leftMenu .= '</ul>';
                        $leftMenu .= '</details>';
                        
                    }else{
                        $leftMenu .= '<details><summary class="no-child"><i class="'.$tmp['icon'].'"></i><a href="'.($tmp['link']==""?"javascript:void(0);":$tmp['link']).'" class="secondary" >'.$tmp ['menuName'].'</a></summary></details>';
                    }
                }
            }
            $likeMenu .= '</ul>';//즐겨찾기 메뉴

            // 현재 메뉴의 selector 정보 구하기
            $currentUrl = $_SERVER[ "REQUEST_URI" ];
            $currentUrl = strtok($currentUrl, '?');
            foreach ($menu as $i => $tmp) {
                if( strpos($tmp['link'], $currentUrl) !== false ||  strpos($tmp['datalink'], $currentUrl) !== false ){
                    $_SESSION['selector'] = $tmp['selector'];
                }

                if (isset($tmp['child']) && count($tmp['child']) > 0) {
                    foreach ($tmp['child'] as $j => $child) {
                        if( strpos($child['link'], $currentUrl) !== false ||  strpos($child['datalink'], $currentUrl) !== false ){
                            $_SESSION['selector'] = $child['selector'];
                        }
                    }
                }
            }

        }

        $_SESSION['leftmenu'] = $leftMenu;
        $_SESSION['likemenu'] = $likeMenu;

		return $link;
    }

    /**
     * 계정/권한 정보 갱신
     * 페이지 이동 시 호출
     */
    public function refreshLogin()
    {
        if (empty($_SESSION['managerId'])) {
            return;
        }

        // 계정 조회
        $filter = ["id" => $_SESSION["managerId"]];
        $options = ['projection'=>['_id'=>0]];
        $loginData = $this->db->item(self::COLLECTION, $filter, $options);

        // 페이지 이동 시 유지할 세션 값
        $this->coId = $loginData['coId'] = $_SESSION['coId'];
        if (!empty($_SESSION['currentDepartmentId'])) $loginData['currentDepartmentId'] = $_SESSION['currentDepartmentId'];

        // 로그인정보 세션에 등록
        $link = $this->setSessionLogin($loginData);
        
        return $link;
    }

    /**
     * 세션의 coId 변경
     */
    public function changeSessionCoId()
    {
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_POST['coId'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            if (!empty($_SESSION['isSuper']) || (!empty($_SESSION['allowCoId']) && in_array($_POST['coId'], $_SESSION['allowCoId']))) {
                $_SESSION['coId'] = $_POST['coId'];

                $company = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$_SESSION['coId'].'/config', $_SESSION['coId'].'_company');
                $_SESSION['action'] = !empty($company['action']) ? $company['action'] : "";

                $result['link'] = $this->refreshLogin();
            } else {
                throw new \Exception("권한이 없습니다.", 400);
            }
        } catch (\Exception $e) {
            $result['msg'] = $this->common->getExceptionMessage($e);
        }

        return $result;
    }

    /**
     * 세션의 부서ID 변경
     */
    public function changeSessionDepartmentId()
    {
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_POST['departmentId'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            if (!empty($_SESSION['allowDepartmentIds']) && in_array($_POST['departmentId'], $_SESSION['allowDepartmentIds'])) {
                $_SESSION['currentDepartmentId'] = $_POST['departmentId'];
                // $result['link'] = $this->refreshLogin();
            } else {
                throw new \Exception("권한이 없습니다.", 400);
            }
        } catch (\Exception $e) {
            $result['msg'] = $this->common->getExceptionMessage($e);
        }

        return $result;
    }

    /**
     * 세션의 template 변경
     */
    public function changeSessionTemplate()
    {
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_POST['template'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            $_SESSION['template'] = $_POST['template'];
            $result['template'] = $_SESSION['template'];

        } catch (\Exception $e) {
            $result['msg'] = $this->common->getExceptionMessage($e);
        }

        return $result;
    }

    public function changeSessionLeftMenuOpen()
    {
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_POST['leftMenuOpen'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            $_SESSION['leftMenuOpen'] = $_POST['leftMenuOpen'];
            
            $filter['id'] = $_SESSION['managerId'];
            $data['leftMenuOpen'] = $_SESSION['leftMenuOpen'];
			$result = $this->db->upsert(self::COLLECTION, $filter, ['$set'=>$data]);
        } catch (\Exception $e) {
            $result['msg'] = $this->common->getExceptionMessage($e);
        }

        return $result;

    }
}