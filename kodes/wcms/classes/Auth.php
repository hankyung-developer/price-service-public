<?php
namespace Kodes\Wcms;

/**
 * 관리자 권한 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Auth
{
    /** const */
	const COLLECTION = 'auth';
    const MENU_ID = 'WCMS권한';

    /** @var Class */
    protected $db;
    protected $common;
    protected $json;
    
    /** @var variable */
    protected $coId;
	protected $menu;

    /**
     * 생성자
     */
    public function __construct()
    {
        // class
        $this->db = new DB();
        $this->common = new Common();
        $this->json = new Json();
        $this->category = new Category();

        // variable
        $this->coId = $this->common->coId;
        $this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
    }

    /**
     * 권한 목록
     *
     * @param String [GET] searchText 검색어 (option)
     * @param String [GET] coId 회사코드 (option)
     * @return Array $return[authList, page]
     */
    public function list()
    {
        $return = [];

        try {
            // 권한 체크
            $this->common->checkAuth(self::MENU_ID);

            $filter = [];
            $options = [];

            // filter
            $filter['coId'] = !empty($_GET["coId"])?$_GET["coId"]:$this->coId;
            if (!empty($_GET['searchText'])) {
                $filter['name'] = new \MongoDB\BSON\Regex($_GET['searchText'],'i');
            }
            
            //  count 조회
            $return['totalCount'] = $this->db->count(self::COLLECTION, $filter);
            
            // paging
            $pageNavCnt = empty($param['pageNavCnt'])?10:$param['pageNavCnt'];
            $limit = empty($_GET['limit'])?50:$_GET['limit'];
            $page = empty($_GET['page'])?1:$_GET['page'];
            $pageClass = new Page();
            $return['page'] = $pageClass->page($limit, $pageNavCnt, $return["totalCount"], $page);
            
            // options
            $options = ['skip' => ($page - 1) * $limit, 'limit'=>$limit, 'sort'=>['insert.date'=>-1], 'projection'=>['_id'=>0]];

            // list 조회
            $return['authList'] = $this->db->list(self::COLLECTION, $filter, $options);

        } catch (\Exception $e) {
            echo "<script>";
            echo "alert('".$this->common->getExceptionMessage($e)."');";
            echo "history.back();";
            echo "</script>";
            exit;
		}
        
        return $return;
    }

    /**
     * 권한 입력/수정 화면
     *
     * @return Array $return[auth]
     */
    public function editor()
    {
		$return = [];

        try {
            // 권한 체크
            $this->common->checkAuth(self::MENU_ID);

            $id = $_GET['id'];

            // menu
            $return['menu'] = $this->common->getWcmsMenu();

            // category
            $return['categoryTree'] = $this->category->getCategoryTree();

            // program
            $return['program'] = $this->json->readJsonFile($this->siteDocPath, $this->coId.'_program');

            // layoutKind
            $return["layoutKind"] = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$this->coId.'/layout', 'layoutKind');

            // $id가 있으면 조회
            if (!empty($id)) {
                $filter = ['id'=>$id];
                $option = ['projection'=>['_id'=>0]];
                $return['auth'] = $this->db->item(self::COLLECTION, $filter, $option);
            }

        } catch (\Exception $e) {
            echo "<script>";
            echo "alert('".$this->common->getExceptionMessage($e)."');";
            echo "history.back();";
            echo "</script>";
            exit;
		}

        return $return;
    }

    /**
     * 권한 저장
	 * 
	 * @param String [POST] $id
     * @return Array $return[msg]
     */
    public function saveProc()
    {
		$return = [];
        try {
            // requestMethod 체크
            //$this->common->checkRequestMethod('POST');

			$data = $_POST;
			unset($_POST);

			// trim
            $data['name'] = trim($data['name']);

            if( !empty($_SESSION['isSuper']) &&  !empty($data['company']) ){
                $data['coId'] = $data['company'];
                $data['category'] = [];
                $data['program'] = [];
                $this->coId = $data['coId'];
            }

			$action = 'update';
			if (empty($data['id'])) {
				// id 생성
				$data['id'] = $this->generateId();
				$action = 'insert';
			}

			// 필수값
            if (empty($data['coId'])) {
                throw new \Exception("회사코드가 없습니다.", 400);
            }
            if (empty($data['name'])) {
                throw new \Exception("권한 이름을 입력하세요.", 400);
            } else {
				// 권한 이름 중복체크
                $filter = [];
                $filter['coId'] = $this->coId;
				$filter['name'] = $data['name'];
				$filter['id'] = ['$ne'=>$data['id']];
				$option = [];
				$auth = $this->db->item(self::COLLECTION, $filter, $options);
				if (!empty($auth['id'])) {
					throw new \Exception("동일한 이름을 가진 권한이 있습니다.", 400);
				}
			}

			// array : 없으면 초기화
			$data['menu'] = $data['menu']?$data['menu']:[];
			$data['category'] = $data['category']?$data['category']:[];			// int -> bool
			$data['isUse'] = (bool) $data['isUse'];
            $data['ai']['use'] = $data['ai']['use']=='true'?true:false;

			$data = $this->common->covertDataField($data, $action, $removeField);
			
			$filter = [];
            $filter['coId'] = $this->coId;
			$filter['id'] = $data['id'];
            
			$result = $this->db->upsert(self::COLLECTION, $filter, ['$set'=>$data]);
            
			$return['authId'] = $data['id'];
			unset($data);

			$return['msg'] = "저장되었습니다.";
		} catch (\Exception $e) {
			$return['msg'] = $this->common->getExceptionMessage($e);
		}

		return $return;
    }
    
    /**
     * 권한 삭제
     *
     * @return Array $return[msg]
     */
    public function deleteProc()
	{
        $return = [];
        try{
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            $id = $_POST['id'];
			unset($_POST);

            if (empty($id)) {
                throw new \Exception("id가 없습니다.", 400);
            }

            // 해당 권한을 가진 관리자가 있는지 체크
            $filter['authId'] = $id;
            $subCount = (int) $this->db->count('manager', $filter);
            if ($subCount > 0) {
                throw new \Exception("권한을 사용중인 계정이 존재하므로 삭제할 수 없습니다.", 400);
            }

            $result = $this->db->delete(self::COLLECTION, ["id"=>$id]);

            $return['msg'] = "권한이 삭제되었습니다.";
        } catch (\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }

        return $return;
    }

    /**
     * 권한 ID 생성
     */
    public function generateId()
    {
        $filter = ["coId" => $this->coId];
        $options = ["sort" => ["id" => -1], "limit" => 1];
        $cursor = $this->db->item(self::COLLECTION, $filter, $options);
        $lastId = $cursor["id"];
        if (empty($lastId)) {
			$lastId = $this->coId."_AUTH_00000";
        }
        return ++$lastId;
    }

    /**
     * 로그인 시 권한 merge
     * 권한 추가 시 업데이트 해야 함
     */
    public function mergeAuth($items)
    {
        $i = 0;
        foreach ($items as $key => $value) {
            if ($value['isUse']) {
                if ($i == 0) {
                    $result = $value;
                    $i++;
                    continue;
                }
                // menu
                $result['menu'] = array_merge($result['menu'], $value['menu']);
                // category
                if (empty($result['category']) || count($result['category']) == 0) {
                    $result['category'] = [];
                } elseif (empty($value['category']) || count($value['category']) == 0) {
                    $result['category'] = [];
                } else {
                    $result['category'] = array_merge($result['category'], $value['category']);
                }
                // program
                if (empty($result['program']) || count($result['program']) == 0) {
                    $result['program'] = [];
                } elseif (empty($value['program']) || count($value['program']) == 0) {
                    $result['program'] = [];
                } else {
                    $result['program'] = array_merge($result['program'], $value['program']);
                }
                // article
                if (!empty($value['article']['list'])) $result['article']['list'] = $value['article']['list'];
                if (!empty($value['article']['write'])) $result['article']['write'] = $value['article']['write'];
                if (!empty($value['article']['delete'])) $result['article']['delete'] = $value['article']['delete'];
                if (!empty($value['article']['deleteDB'])) $result['article']['deleteDB'] = $value['article']['deleteDB'];
                if (!empty($value['article']['publish'])) $result['article']['publish'] = $value['article']['publish'];
                if (!empty($value['article']['pubUpdate'])) $result['article']['pubUpdate'] = $value['article']['pubUpdate'];
                if (!empty($value['article']['pubDelete'])) $result['article']['pubDelete'] = $value['article']['pubDelete'];
                if (!empty($value['article']['unEditing'])) $result['article']['unEditing'] = $value['article']['unEditing'];
                // layout
                if (!empty($value['layout']['update'])) $result['layout']['update'] = $value['layout']['update'];
                if (!empty($value['layout']['patch'])) $result['layout']['patch'] = array_merge($result['layout']['patch'], $value['layout']['patch']);
                if (!empty($value['layout']['patchType'])) $result['layout']['patchType'] = array_merge($result['layout']['patchType'], $value['layout']['patchType']);
                if (!empty($value['layout']['edit'])) $result['layout']['edit'] = array_merge($result['layout']['edit'], $value['layout']['edit']);
                // ai
                if (!empty($value['ai']['use'])) $result['ai']['use'] = $value['ai']['use'];

                $i++;
            }
        }
        // 중복제거
        if ($i > 0) {
            if (!empty($result['menu'])) $result['menu'] = array_values(array_unique($result['menu']));
            if (!empty($result['category'])) $result['category'] = array_values(array_unique($result['category']));
            if (!empty($result['program'])) $result['program'] = array_values(array_unique($result['program']));
            if (!empty($result['layout']['patch'])) $result['layout']['patch'] = array_values(array_unique($result['layout']['patch']));
            if (!empty($result['layout']['patchType'])) $result['layout']['patchType'] = array_values(array_unique($result['layout']['patchType']));
            if (!empty($result['layout']['edit'])) $result['layout']['edit'] = array_values(array_unique($result['layout']['edit']));
        }

        return $result;
    }

    /**
     * 매체 신규 등록 시 기본 권한 등록
     */
    function makeDefaultAuth(){
            $coId= $_POST['coId'];

            // 관리자
            $data =[
                'coId' => $coId,
                'article' => ['list' => '전체','write' => true,'delete' => true,'deleteDB' => true,'publish' => true,'pubUpdate' => true,'pubDelete' => true,'unEditing' => true],
                'layout' => ['patchType' => ['article','banner','board','graph','program','manualEdit'],'patch' => ['newsList','newsView','main','photoList','photoView','footer','header'],'update' => true,'edit' => []],
                'menu' => ['Dashboard','데스크','기사','외부기사','파일','동영상','차트','페이지관리','면편집','이벤트페이지','배너관리','배너','팝업','사이트관리','카테고리','시리즈','Tag','프로그램','문자지환','회원','기자','부서','권한','전송처관리','회사정보','댓글','코드관리','포털통계','랭킹뉴스','구독정보','로그분석','기사별통계','방문자통계','페이지뷰통계','지역별통계','유입경로통계','시스템통계','게시판','게시판관리','웹푸시','메시지','구독자','GA로그분석','기사별통계','기자별통계','페이지뷰통계','지역별통계','유입경로통계','시스템통계','게시판','게시판관리','웹푸시','메시지','구독자'],
                "name" => "사이트관리자","program" => [],"webPush" => ["use" => true],
                'category' => [],'company' => '','isUse' => true,'id' => $coId."_AUTH_00001",'ai' => ['use' => true],
                'insert' => ['date' => date("Y-m-d H:i:s"),'managerId' => 'admin','managerName' => '관리자','ip' => $this->common->getRemoteAddr()],
            ];
            $result = $this->db->insert(self::COLLECTION, $data);
                
            // 데스크 
            $data = ['coId' => $coId, "id" => $coId."_AUTH_00002","name"=>"데스크", "isUse" => true,
                     "article" => ["list" => "전체","write"=> true,"delete"=> true,"deleteDB"=> true, "publish"=> true,"pubUpdate"=> true, "pubDelete"=> true, "unEditing"=> true],
                     "category" => [],
                     "menu" => ["Dashboard","데스크","기사","파일","동영상","사이트관리","카테고리","프로그램","시리즈","Tag","포털통계","랭킹뉴스","구독정보","로그분석","기사별통계","방문자통계","페이지뷰통계","지역별통계","유입경로통계","시스템통계"],
                     "insert" => ["date" => date("Y-m-d H:i:s"),"managerId" => "admin","managerName" => "관리자","ip" => $this->common->getRemoteAddr()],
                     "layout" => ["update" => true, "edit" =>[], "patch" => ["main","gnb","newsList","newsView","photoList","photoView"],"patchType" => ["article","banner"]],
                    ];
			$result = $this->db->insert(self::COLLECTION, $data);

            // 취재기자 
            $data = ['coId' => $coId, "id" => $coId."_AUTH_00003","name"=>"취재기자", "isUse" => true,
                     "article" => ["list" => "전체","write"=> true,"delete"=> true,"deleteDB"=> true, "publish"=> false,"pubUpdate"=> false, "pubDelete"=> false, "unEditing"=> false],
                     "category" => [],
                     "menu" => ["Dashboard","데스크","기사","파일","동영상","사이트관리","카테고리","프로그램","시리즈","Tag"],
                     "insert" => ["date" => date("Y-m-d H:i:s"),"managerId" => "admin","managerName" => "관리자","ip" => $this->common->getRemoteAddr()],
                     "layout" => ["update" => false, "edit" =>[], "patch" => [],"patchType" => []],
                    ];
			$result = $this->db->insert(self::COLLECTION, $data);
    }
}