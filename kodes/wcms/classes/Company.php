<?php 
namespace Kodes\Wcms;


/**
 * 회사 정보 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Company
{
    /** @var Class DB Class */
    protected $db;
    /** @var Class Json Class */
    protected $json;
    /** @var Class Common Class */
    protected $common;
    /** @var Class Auth Class */
    protected $auth;
    /** @var Class Manager Class */
    protected $manager;
    /** @var String Collection Name */
	protected $collection = "company";
    /** @var String media ID */
    protected $coId;

    /**
     * Compnay 생성자 DB 셋팅
     */
    function __construct()
    {
        $this->db = new DB();
        $this->json = new Json();
        $this->common = new Common();
        
        $this->auth = new Auth();
        $this->manager = new Manager();

		$this->coId = $this->common->coId;
	}

    /**
     * 회사 정보 리스트. 관리자가 아닐 경우 회사 정보 수정 화면으로 이동.
     * 
     * @param String [GET] $searchText 검색어
     * @param String [GET] $page 페이지
	 * @param String [GET] $noapp 페이지당 게시물 갯수
     * @return Array 검색 조건에 맞는 리스트 배열
     * @todo 검색 조건 관련 확인 및 수정 필요
     */
	public function list()
    {
        
    	if (!empty($_SESSION['isSuper'])) {
            $data = array();
            $i = 0;
            $filter=[];

            if (!empty($_GET['searchText'])) {
                $filter['name'] = new \MongoDB\BSON\Regex($_GET['searchText'],'i');
            }

            //  전체 게시물 숫자
            $data["totalCount"] = $this->db->count($this->collection,$filter);

            $noapp = empty($_GET['noapp'])?20:$_GET['noapp'];
            $page = empty($_GET["page"])?1:$_GET["page"];
            $pageInfo = new Page;
            $data['page'] = $pageInfo->page(20, 10,$data["totalCount"], $page);
            
            $options = ["skip" => ($page - 1) * $noapp, "limit" => $noapp, 'sort' => ['name' => 1],"projection" => ['_id'=>0] ];
            $data["items"] = $this->db->list($this->collection, $filter, $options);

            return $data;
		} else {
			header("Location: /company/editor");
        }
	}

	/**
     * ID 중복 체크
	 * @param [POST]string $companyId 매체ID
     * @return int ID에 해당하는 회사 정보 수
     */
    public function idCheck()
    {
        try {
            $filter = ['coId' => $_POST['coId']];
            $data = $this->db->count($this->collection, $filter);
        } catch (\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
    }

	/**
     * 회사 입력 및 정보 
     * @return Array 검색 조건에 맞는 리스트 배열
     */
    public function editor()
    {
        try {
            if ($_SESSION['isSuper']) {
                $coId = $_GET['coId'];
            } else {
                $coId = empty($_GET['coId'])?$_SESSION["coId"]:$_GET['coId'];
            }

            $data=[];
            if (!empty($coId)) {
                $filter = ['coId' => $coId];
                $options = [];
                $data = $this->db->item($this->collection, $filter, $options);
                $data['isUse'] = $data['isUse']=="1"?"Y":"N";
                $data['member']['useLoginIdPw'] = $data['member']['useLoginIdPw']=="1"?"Y":"N";
                
                $data["image"]["iconPath"] = empty($data["image"]["icon"])?"":preg_replace("/([.][a-z]+)$/",".120x.0$1",$data["image"]["icon"]);
                $data["image"]["logoPath"] = empty($data["image"]["logo"])?"":preg_replace("/([.][a-z]+)$/",".120x.0$1",$data["image"]["logo"]);
                $data["image"]["footerLogoPath"] = empty($data["image"]["footerLogo"])?"":preg_replace("/([.][a-z]+)$/",".120x.0$1",$data["image"]["footerLogo"]);
                $data["image"]["watermarkPath"] = empty($data["image"]["watermark"])?"":preg_replace("/([.][a-z]+)$/",".120x.0$1",$data["image"]["watermark"]);
            }
        } catch (\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        return $data;
    }

	/**
     * 회사 정보 추가
     * @return Array 입력 결과
     * @todo API Key 생성
     */
    public function insert()
    {
		try {
            $companyInfo = $this->common->covertDataField($_POST, "insert");
            $companyInfo['isUse'] = $companyInfo['isUse']=='Y'?true:false;
            $companyInfo['domain']['pc'] = rtrim($companyInfo['domain']['pc'], '/');
            $companyInfo['domain']['mobile'] = rtrim($companyInfo['domain']['mobile'], '/');
            $companyInfo['ai']['use'] = $companyInfo['ai']['use']=='on'?true:false;
            $companyInfo['webPush']['use'] = $companyInfo['webPush']['use']=='on'?true:false;

            // 슈퍼계정만 설정 가능한 권한
            if (!empty($_SESSION['isSuper'])) {
                $companyInfo['externalMedia']['yna']['use'] = $companyInfo['externalMedia']['yna']['use']=='on'?true:false;
            }

            $result = $this->db->insert($this->collection, $companyInfo);
			// $data = $result->getInsertedCount();

            $this->makeJsonFile($companyInfo['coId']);
            // adx_txt 쓰기
            $this->fileWrite($companyInfo['coId'], "ads.txt", $companyInfo['ads']);
            // robots 쓰기
            $this->fileWrite($companyInfo['coId'], "robots.txt", $companyInfo['robots']);

            // 기본 레이아웃 페이지 생성
            $this->makeLayoutPage($companyInfo['coId']);
            // 기본 권한 생성
            $authInfo = [];
            array_push($authInfo, ["name"=>"사이트 관리자","article" => ["list" => "전체","write"=> true,"delete"=> true,"deleteDB"=> true, "publish"=> true,"pubUpdate"=> true, "pubDelete"=> true, "unEditing"=> true], "menu" =>[] ]);
            array_push($authInfo, ["name"=>"데스크","article" => ["list" => "전체","write"=> true,"delete"=> true,"deleteDB"=> true, "publish"=> true,"pubUpdate"=> true, "pubDelete"=> true, "unEditing"=> true], "menu" =>[] ]);
            array_push($authInfo, ["name"=>"취재기자","article" => ["list" => "전체","write"=> true,"delete"=> true,"deleteDB"=> true, "publish"=> false,"pubUpdate"=> false, "pubDelete"=> false, "unEditing"=> false], "menu" => ["Dashboard","데스크","기사","파일","동영상","사이트관리","카테고리","프로그램","시리즈","Tag"] ]);

            for($i=0; $i < sizeof($authInfo); $i++) {
                $value = $authInfo[$i];
                $authId = $this->makeAuth( $value['name'], $value['article'], $value['menu'] );

                if( $i == 0) {
                    // 사용자 생성
                    $this->makeManager($authId);
                }
            }

            // 히스토리 저장
            (new CollectionHistory)->insert([
                'collection' => $this->collection,
                'coId' => $companyInfo['coId'],
                'id' => $companyInfo['coId'],
                'document' => $this->db->item($this->collection, ['coId' => $companyInfo['coId']], []),
                'comment' => "입력"
            ]);

        } catch (\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
    }
    
    /**
     * 회사 정보 수정
     * @return Array 입력 결과
     */
    public function update()
    {
		try {
            $companyInfo = $this->common->covertDataField($_POST, "update");
            $companyInfo['isUse'] = $companyInfo['isUse']=='Y'?true:false;
            $companyInfo['domain']['pc'] = rtrim($companyInfo['domain']['pc'], '/');
            $companyInfo['domain']['mobile'] = rtrim($companyInfo['domain']['mobile'], '/');
            $companyInfo['ai']['use'] = $companyInfo['ai']['use']=='on'?true:false;
            $companyInfo['webPush']['use'] = $companyInfo['webPush']['use']=='on'?true:false;

            // 슈퍼계정만 설정 가능한 권한
            if (!empty($_SESSION['isSuper'])) {
                $companyInfo['externalMedia']['yna']['use'] = $companyInfo['externalMedia']['yna']['use']=='on'?true:false;
            }

            $filter = ["coId"=>$companyInfo["coId"]];
            $options = ['$set'=>$companyInfo];
            $result = $this->db->update($this->collection, $filter, $options);
			$data = $result->getModifiedCount();

            $this->makeJsonFile($companyInfo['coId']);
            // adx_txt 쓰기
            $this->fileWrite($companyInfo['coId'], "ads.txt", $companyInfo['ads']);
            // robots 쓰기
            $this->fileWrite($companyInfo['coId'], "robots.txt", $companyInfo['robots']);

            (new CollectionHistory)->insert([
                'collection' => $this->collection,
                'coId' => $companyInfo['coId'],
                'id' => $companyInfo['coId'],
                'document' => $this->db->item($this->collection, ['coId' => $companyInfo['coId']], []),
                'comment' => "수정"
            ]);

        } catch (\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
    }

    /**
     * 회사 정보 입력 또는 수정시 JSON 파일을 만든다.
     *  - config 폴더에 회사 전체 list 파일
     *  - config 폴더에 해당 회사 정보 파일
     *  - webData 폴더에 해당 회사 폴더에 회사 정보 파일
     *
     * @param String $coId
     * @return void
     */
    public function makeJsonFile($coId)
    {   
		$data = $this->db->list($this->collection, [], ['projection'=>['_id'=>0]]);
        $this->json->makeJson('/webSiteSource/wcms/config/','company', $data);
        unset($data);

        $data = $this->db->item($this->collection, ['coId'=>$coId], ['projection'=>['_id'=>0]]);
        //$this->json->makeJson('/webSiteSource/wcms/config/', $coId.'_company', $data); // 회사별 정보는 webData에만 저장
        $this->json->makeJson('/webData/'.$coId.'/config', $coId.'_company', $data);
        unset($data);
    }

    /**
     * 회사 정보 입력시 기본적인 layout page를 만든다.
     *
     * @param String $coId
     * @return void
     */
    public function makeLayoutPage($coId)
    {
        // 기본 layout 추가
        $layout = [
            ['id'=>'main','title'=>'메인','type'=>'pageEdit'],
            ['id'=>'newsList','title'=>'뉴스리스트','type'=>'newsList'],
            ['id'=>'photoList','title'=>'포토리스트','type'=>'newsList'],
            ['id'=>'newsView','title'=>'뉴스뷰','type'=>'newsView'],
            ['id'=>'photoView','title'=>'포토뷰','type'=>'newsView'],
            ['id'=>'header','title'=>'헤더','type'=>'header'],
            ['id'=>'footer','title'=>'푸터','type'=>'footer'],
        ];

        // 기본 layout db 저장
        foreach($layout as $key => $val) {
            $item = [];
            $item['coId'] = $coId;
            $item['id'] = $val['id'];
            $item['title'] = $val['title'];
            $item['type'] = $val['type'];
            $item = $this->common->covertDataField($item, "insert", []);
            $result = $this->db->insert('layout', $item);
        }

        // layoutKind 파일 생성
        $filter['coId'] = $coId;
        $options = ['sort' => ['insert.date' => 1]];
        $layoutKind = $this->db->list('layout', $filter, $options);
        $this->json->makeJson("/webData/".$coId."/layout", 'layoutKind', $layoutKind);
    }

    /**
     * 회사 정보 입력시 관리자 권한을 생성한다.
     */
    public function makeAuth($name, $article, $menu)
    {
        $_POST['action'] = 'insert';
        //$_POST['name'] = '사이트 관리자';
        $_POST['name'] = $name;
        $_POST['article'] = $article;
        $_POST['isUse'] = 'Y';
        $_POST['coId'] = $this->coId;
        
		$wcmsMenu = $this->json->readJsonFile('../config', 'wcms_menu');
        $menus = [];
        foreach ($wcmsMenu as $key => &$value) {
            $menus[] = $value['menuId'];

            $item = $value['child'];
            foreach ($item as $key2 => &$value2) {
                $menus[] = $value2['menuId'];
            }
        }

        $_POST['menu'] = !empty($menu)?$menu:$menus;
        $auth = $this->auth->saveProc();
        $authId = $auth['authId'];

        return $authId;
    }

    /**
     * 회사 정보 입력시 관리자 사용자을 생성한다.
     */
    public function makeManager($authId)
    {
        $_POST['id'] = $this->coId.'_admin';
        $_POST['password'] = '1111';
        $_POST['name'] = '관리자('.$this->coId.')';
        $_POST['coId'] = $this->coId;
        $_POST['authId'] = $authId;
        $_POST['action'] = 'insert';
        $this->manager->saveProc();
    }

    /**
     * API SECRET 생성
     */
    public function genApiSecret()
    {
		try {
            $data['apiSecret'] = str_replace(['+','/','='], '', base64_encode(hash('sha512', uniqid('secret_'.$_SESSION['coId'], true), true)));
            $return = $data;
        } catch (\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }

        return $return;
    }

    /**
     * 일반 파일 쓰기
     */
    public function fileWrite($coId, $fileName, $data){
        if( !empty(trim($data))){
            file_put_contents('/webData/'.$coId.'/'.$fileName, nl2br($data));
        }
    }
}