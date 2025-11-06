<?php

ini_set('display_errors', 0);

/**
 * Index 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Index
{
    /** @var Class */
    private $tpl;
    private $json;

    /** @var variable */
    private $data = [];
    private $isError = false;
    private $action;
    private $method;
    private $etc;
    private $returnType;

    /**
     * 생성자
     */
    public function __construct()
    {
        // HealthCheck
        if (!empty($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == '/healthCheck') {
            die('OK');
        }

        $this->json = new \Kodes\Wcms\Json();

        // 로그인 상태가 아닌 경우 로그인 페이지로 (로그인 페이지 예외)
        if (empty($_SESSION['managerId'])) {
            // 로그인 없이 접속 가능한 페이지
            $noLoginUrl = [
                '/login',
                '/AIData/assistantApi',
                '/AIData/pressReleaseApi',
                '/AIData/imageMultipleApi',
                '/category/popup',
                '/apis/list',
                '/api/category',
                '/api/document',
                '/api/data',
                '/api/list'
            ];
            $goLogin = true;
            foreach ($noLoginUrl as $key => $value) {
                if (strpos($_SERVER['REQUEST_URI'], $value) !== false) {
                    $goLogin = false;
                }
            }
            if ($goLogin) {
                if (strpos($_SERVER['REQUEST_URI'], '/ajax') === false) {
                    header('Location: /login');
                }
                exit;
            }
        }

		$this->setTemplate();
        $this->setParameter();
        $this->refreshLogin();
        $this->runClass();
        $this->print();
	}

    /**
     * 템플릿 클래스 설정
     */
    private function setTemplate()
    {
        $this->tpl = new \Kodes\Wcms\Template_();
        $this->tpl->compile_dir = '_compile';
        $this->tpl->template_dir = '_template';
    }

    /**
     * 디렉토리 주소를 parameter로 변경
     * 
     * ex) wcms.mojaik.com/$action/$method/$etc
     */
	private function setParameter()
    {
        preg_match_all('/\/([^\/]+)/', $_GET['_url'], $tmp);
        $this->action = isset($tmp[1][0])?$tmp[1][0]:null;
        $this->method = isset($tmp[1][1])?$tmp[1][1]:null;
        $this->etc = isset($tmp[1][2])?$tmp[1][2]:null;
        // returnType
        if (empty($_GET['returnType']) && !empty($this->etc)) {
            $_GET['returnType'] = $this->etc;
        }
        $this->returnType = empty($_GET['returnType'])?null:$_GET['returnType'];
	}

    /**
     * 로그인 정보 갱신
     */
    private function refreshLogin()
    {
        if ($this->returnType != 'ajax' && $_SERVER['REQUEST_METHOD'] == 'GET') {
            // 계정/권한 정보 갱신
            $login = new \Kodes\Wcms\Login();
            $login->refreshLogin();
        }
    }


    /**
     * class/method 실행
     * result : 실행결과
     */
	private function runClass()
    {
        // class
        $className = ucfirst($this->action);
        $class = null;
        if (class_exists($className)) {
            $className = $className;
        } elseif (class_exists('\Kodes\Wcms\\'.$className)) {
            $className = '\Kodes\Wcms\\'.$className;
        } else {
            $className = null;
            $this->isError = true;
            // echo "<!-- no class -->";
        }
        if (!empty($className)) {
            if (!empty($this->etc) && $this->etc != 'ajax') {
                $class = new $className($this->etc);
            } else {
                $class = new $className();
            }
        }

        // method
        if ($class) {
            $methodName = null;
            if ($this->method && method_exists($class, $this->method)) {
                $methodName = $this->method;
            } elseif ($this->etc && method_exists($class, $this->etc)) {
                $methodName = $this->etc;
            } elseif ($this->action && method_exists($class, $this->action)) {
                $methodName = $this->action;
            } else {
                $methodName = null;
                $this->isError = true;
                // echo "<!-- no method -->";
            }
            if (!empty($methodName)) {
                // 실행결과
                $this->data['result'] = $class->$methodName();
            }
        }
	}

    /**
     * 화면 출력
     */
	private function print()
    {
        if ($this->isError) {
            // class, template 모두 없으면
            // http_response_code(404);    // Not found
            // exit;
        }

        $baseTemplate = $this->action.'/'.$this->method.'.html';
        if ($this->returnType != 'ajax') {
            if (!empty($this->data['result']['skin'])) {
                // 개별 skin을 설정한 경우
                $baseTemplate = $this->action.'/'.$this->data['result']['skin'].'.html';
            } elseif (empty($this->method)) {
                // $method 없으면 [$class].html
                $baseTemplate = $this->action.'/'.$this->action.'.html';    // $method 없으면 [$class].html
            }
        }

        if ($this->returnType == 'ajax') {
            // ajax 출력
            header('Content-Type: application/json; charset=utf-8');
            unset($this->data['common']);
			echo json_encode($this->data, JSON_UNESCAPED_UNICODE);
            exit;

        } elseif (is_file($this->tpl->template_dir.'/'.$baseTemplate)) {
            // html 출력
            /**
             * baseTemplate에 포함된 서브 template을 추출하여 등록
                /webSiteSource/www/web/_template/pc/module
                /webSiteSource/www/web/_template/pc/[action]
             */
            $baseTmp = file_get_contents($this->tpl->template_dir.'/'.$baseTemplate);
            preg_match_all('/\{#[ ]*([a-z0-9A-Z ]+)\}/', $baseTmp, $tmp);
            $template['index'] = $baseTemplate;
            foreach ($tmp[1] as $val) {
                if (is_file($_SERVER['DOCUMENT_ROOT'].'/'.$this->tpl->template_dir.'/module/'.$val.'.html')) {
                    // module
                    $template[$val] = 'module/'.$val.'.html';
                } elseif (is_file($_SERVER['DOCUMENT_ROOT'].'/'.$this->tpl->template_dir.'/'.$this->action.'/'.$val.'.html')) {
                    // action
                    $template[$val] = $this->action.'/'.$val.'.html';
                }
            }
            $this->tpl->define($template);
            if (!empty($this->data)) {
                $this->tpl->assign($this->data);
            }
            $this->tpl->print_('index');
        }
    }
}