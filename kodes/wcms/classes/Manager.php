<?php
namespace Kodes\Wcms;

// ini_set('display_errors', 1);

/**
 * 관리자 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Manager
{
    /** const */
	const COLLECTION = 'manager';
    const MENU_ID = '관리자';
    
    /** @var Class */
    protected $db;
    protected $common;
    protected $json;

    /** @var variable */
    protected $coId;
    protected $companyList;

    /**
     * 생성자
     */
    function __construct()
    {
        // class
        $this->db = new DB();
        $this->common = new Common();
        $this->json = new Json();

        // variable
        $this->coId = $this->common->coId;

	}

    /**
     * 관리자 목록
     * 
     * @param String [GET] $coId 매체ID
     * @param String [GET] $searchText 검색어
     * @param String [GET] $page 페이지
	 * @param String [GET] $noapp 페이지당 게시물 갯수
     * @return Array 검색 조건에 맞는 리스트 배열
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
            if (!empty($_GET['authId'])) {
                $filter['authId'] = $_GET['authId'];
            }
            if (!empty($_GET['searchText'])) {
                $filter['$or'][]['id'] = new \MongoDB\BSON\Regex($_GET['searchText'],'i');
                $filter['$or'][]['name'] = new \MongoDB\BSON\Regex($_GET['searchText'],'i');
            }
            
            //  count 조회
            $return['totalCount'] = $this->db->count(self::COLLECTION, $filter);

            // paging
            $noapp = empty($_GET['noapp'])?20:$_GET['noapp'];
            $page = empty($_GET['page'])?1:$_GET['page'];
            $pageInfo = new Page;
            $return['page'] = $pageInfo->page(20, 10, $return['totalCount'], $page);
            
            // options
            $options = ['skip' => ($page - 1) * $noapp, 'limit' => $noapp, 'sort'=>['name'=>1], 'projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]];

            // list 조회
            $return['managerList'] = $this->db->list(self::COLLECTION, $filter, $options);

            // 권한 list 조회
            $return['authList'] = $this->db->list('auth', ['coId'=>$this->coId,'isUse'=>true], ['projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]]);

            // authId로 권한명 가져옴
            foreach ($return['managerList'] as $key => $value) {
                $index = array_search($value['authId'], array_column($return['authList'], 'id'));
                if($index !== false){
                    $return['managerList'][$key]['authName'] = $return['authList'][$index]['name'];
                }
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
     * 관리자 입력/수정 화면
     * 
     * @return Array 검색 조건에 맞는 리스트 배열
     * @todo 수정권한 체크
     */
    public function editor()
    {
        $return = [];
        try {
            $id = null;
            if (empty($_GET['action'])) {
                // mypage
                $id = $_SESSION['managerId'];
            } else {
                // 권한 체크
                $this->common->checkAuth(self::MENU_ID);

                if($_GET['action'] == 'insert') {
                } elseif($_GET['action'] == 'update') {
                    if(!empty($_GET['id'])) {
                        $id = $_GET['id'];
                    }
                }
                $return['editor'] = 'admin';
            }
            
            // 관리자 조회
            if (!empty($id)) {
                $filter = ['id' => $id];
                $options = [];
                $return['manager'] = $this->db->item(self::COLLECTION, $filter, $options);
                // manager가 배열이고 _id와 $oid가 존재할 때만 값 할당, 아니면 null 할당
                if (is_array($return['manager']) && isset($return['manager']['_id']['$oid'])) {
                    $return['manager']['_id'] = $return['manager']['_id']['$oid'];
                } else {
                    // 데이터가 없거나 잘못된 경우 null 할당
                    $return['manager']['_id'] = null;
                }
            }

            // 권한 조회
            $return['auth'] = $this->db->list('auth', ['coId'=>$this->coId,'isUse'=>true], ['projection'=>['_id'=>0]]);

            // 부서
            $return['department'] = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$this->coId.'/department', 'all');

            // 기업
            $return["companyList"] = $this->companyList;
            if (!empty($return["companyList"])) {
                foreach ($return["companyList"] as $key => $value) {
                    // 기업 : 부서
                    $return["companyList"][$key]['department'] = $this->json->readJsonFile($this->common->config['path']['data'].'/'.$value['coId'].'/department', 'all');
                    $return["companyList"][$key]['auth'] = $this->db->list('auth', ['coId'=>$value['coId'],'isUse'=>true], ['projection'=>['_id'=>0]]);
                }
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
     * 관리자 저장
	 * 
	 * @param String [POST] $id
     * @param String 입력값들...
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
            $action = $data['action'];

			// trim
            $data['name'] = trim($data['name']);

            // coId
            if (empty($data['coId'])){
                $data['coId'] = $this->coId;
            }

			// 필수값 체크
            if (empty($data['coId'])) {
                throw new \Exception("회사코드가 없습니다.", 400);
            }
            if (empty($data['id'])) {
                throw new \Exception("ID를 입력하세요.", 400);
			} else {
                // 입력인 경우
                if($action == 'insert') {
                    $checkId = $this->checkId($data['id']);
                    if (!empty($checkId['msg'])) {
                        throw new \Exception($checkId['msg'], 400);
                    }
                }
            }
            if (empty($data['name'])) {
                throw new \Exception("이름을 입력하세요.", 400);
			}

            // password
            if ($action == 'insert' || !empty($data['password'])) {
                if (empty($data['password'])) {
                    throw new \Exception("비밀번호를 입력하세요.", 400);
                } else {
                    // 비밀번호 암호화
                    $data['salt'] = $this->getSalt();
                    $data['password'] = $this->encryptPassword($data['password'], $data['salt']);
                }
            } else {
                unset($data['password']);
            }

            // array 없는 경우
            if ($data['editor'] == 'admin') {
                $data['allowCompany'] = empty($data['allowCompany'])?[]:array_values($data['allowCompany']);
                $data['allowCoId'] = [];
                foreach ($data['allowCompany'] as $key => $value) {
                    if (!empty($value['coId'])) {
                        $data['allowCoId'][] = $value['coId'];
                    }
                }
            }

            // DB 미입력 필드 제거
            $removeField = [];
            $removeField[] = 'action';
            $removeField[] = 'editor';
			$data = $this->common->covertDataField($data, $action, $removeField);
			
            $filter['id'] = $data['id'];
			$result = $this->db->upsert(self::COLLECTION, $filter, ['$set'=>$data]);

			unset($data);

			$return['msg'] = "저장되었습니다.";
		} catch(\Exception $e) {
			$return['msg'] = $this->common->getExceptionMessage($e);
		}

		return $return;
    }

    /**
     * 관리자 삭제
     *
     * @param String [POST] $id
     * @return Array $return[msg]
     */
    function deleteProc()
	{
        $return = [];
        try{
            // 삭제를 금지할 경우
            // throw new \Exception("관리자 삭제는 금지되어 있습니다.\n미사용 처리하세요.", 400);

            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            $id = explode("|",$_POST['id']);
			unset($_POST);

            if (empty($id)) {
                throw new \Exception("id가 없습니다.", 400);
            }
            
            // 삭제
            if(is_array($id)){
                foreach($id as $val){
                    $result = $this->db->delete(self::COLLECTION, ['id'=>$val]);
                }
            }else{
                $result = $this->db->delete(self::COLLECTION, ['id'=>$val]);
            }            

            $return['msg'] = "삭제되었습니다.";
        }catch(\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }

        return $return;
    }

    /**
     * 관리자 검색
     * 기사 작성화면에서 기자 검색 시 사용
     *
     * @return array
     */
    function search()
    {
        $return = [];
        $filter = [];
        $options = ['sort'=>['name'=>1], 'projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]];

        $filter['coId'] = $this->coId;     // coId 고정
		// $filter['allowCoId'] = $_SESSION["coId"];   // coId 권한
        $filter['$or'] = [
            ['name' => new \MongoDB\BSON\Regex($_GET['name'])],
            ['id' => new \MongoDB\BSON\Regex($_GET['name'])]
        ];
        $return = $this->db->list(self::COLLECTION, $filter, $options);

        return $return;
    }

	/**
     * ID 체크
     * 
	 * @param String [POST] $id 매체ID
     * @return int 동일ID 수
     */
    public function checkId($id=null)
    {
        $return = [];
        try {
            $id = !empty($id)?$id:$_POST['id'];

            // 길이 체크
            $minLength = 3;
            if (strlen($id) < $minLength) {
                throw new \Exception("ID는 ".$minLength."자 이상이어야 합니다.", 200);
            }

            // ID 허용된 문자 체크
            if (preg_match('/[^A-z0-9_\.\-]/i', $id)) {
                throw new \Exception("ID에 허용되지 않은 문자가 포함되어 있습니다.\n허용된 문자 : 영문, 숫자, 특수문자(. _ -)", 200);
            }

            $filter = ['id' => $id];
            $return['count'] = $this->db->count(self::COLLECTION, $filter);
            if ($return['count'] > 0) {
                throw new \Exception("사용중인 ID 입니다.", 200);
            }
        } catch (\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }

        return $return;
    }

    /**
     * 비밀번호 암호화
     * SHA-512
     * 
     * @param String password
     * @param String salt
     * @return String 암호화된 문자열
     */
    public function encryptPassword($password, $salt='')
    {
        return base64_encode(hash('sha512', $password.$salt, true));
    }

    /**
     * salt 생성
     */
    protected function getSalt()
    {
        return base64_encode(random_bytes(50));
    }

    /**
     * 관리자 json 저장
     *
     * @param string $id
     * @return void
     */
    function makeJson($id)
    {
        $filter = ['id'=>$id];
        $options = ['projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]];
        $data = $this->db->item(self::COLLECTION, $filter, $options);
        $this->json->makeJson($this->common->config['path']['data'].'/'.$this->coId.'/reporter',$id, $data);
        // 편집가능 매체
        if (!empty($data['allowCoId'])) {
            foreach ($data['allowCoId'] as $key => $value) {
                $this->json->makeJson($this->common->config['path']['data'].'/'.$value.'/reporter',$id, $data);
            }
        }
    }

    /**
     * 카테고리 즐겨찾기 추가
     */
    function setFavoritesCategory()
    {
        $return = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_SESSION['managerId'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            $id = $_SESSION['managerId'];

            // 조회 후 업데이트
            $filter = ['id'=>$id];
            $options = ['projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]];
            $item = $this->db->item(self::COLLECTION, $filter, $options);
            if (!empty($item['favoritesCategory'])) {
                $data['favoritesCategory'] = $item['favoritesCategory'];
            }
            $data['favoritesCategory'][$this->coId] = $_POST['favoritesCategory'];
            $result = $this->db->update(self::COLLECTION, ['id'=>$id], ['$set'=>$data]);
    
            $this->makeJson($id);
    
            // 세션 설정
            $_SESSION['favoritesCategory'] = $data['favoritesCategory'][$this->coId];

            $return['msg'] = "";    // 메시지 없음
        } catch(\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }

        return $return;
    }

    /**
     * 시리즈 즐겨찾기 추가
     */
    function setFavoritesSeries()
    {
        $return = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_SESSION['managerId'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            $id = $_SESSION['managerId'];

            // 조회 후 업데이트
            $filter = ['id'=>$id];
            $options = ['projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]];
            $item = $this->db->item(self::COLLECTION, $filter, $options);
            if (!empty($item['favoritesSeries'])) {
                $data['favoritesSeries'] = $item['favoritesSeries'];
            }
            $data['favoritesSeries'][$this->coId] = $_POST['favoritesSeries'];
            $result = $this->db->update(self::COLLECTION, ['id'=>$id], ['$set'=>$data]);
    
            $this->makeJson($id);
    
            // 세션 설정
            $_SESSION['favoritesSeries'] = $data['favoritesSeries'][$this->coId];

            $return['msg'] = "";    // 메시지 없음
        } catch(\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }

        return $return;
    }

    /**
     * 메뉴 즐겨찾기 추가/삭제
     */
    function setFavoritesMenu()
    {
        $return = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_SESSION['managerId'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            $id = $_SESSION['managerId'];
            $data['favoritesMenu'][$this->coId] = [];

            // 조회
            $filter = ['id'=>$id];
            $options = ['projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]];
            $item = $this->db->item(self::COLLECTION, $filter, $options);

            if(!empty($_POST['favoritesMenu'])){
                if (!empty($item['favoritesMenu'][$this->coId])) {
                    $data['favoritesMenu'][$this->coId] = $item['favoritesMenu'][$this->coId];
                }
                
                $index = array_search($_POST['favoritesMenu'], $data['favoritesMenu'][$this->coId]);
                if($index !== false){
                    // 즐겨찾기에 있을 경우 삭제
                    unset($data['favoritesMenu'][$this->coId][$index]);
                    $return['msg'] = "즐겨찾기 메뉴에서 삭제되었습니다.";
                }else{
                    // 즐겨찾기에 없을 경우 추가
                    $data['favoritesMenu'][$this->coId][] = $_POST['favoritesMenu'];
                    $return['msg'] = "즐겨찾기 메뉴에 추가되었습니다.";
                }
            }

            if(!empty($_POST['favoritesArray'])){
                $data['favoritesMenu'][$this->coId] = $_POST['favoritesArray'];
                $return['msg'] = "저장되었습니다.";
            }

            $result = $this->db->update(self::COLLECTION, ['id'=>$id], ['$set'=>$data]);
            
            $this->makeJson($id);
            
            // 세션 설정
            $_SESSION['favoritesMenu'] = $data['favoritesMenu'][$this->coId];

        } catch(\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }

        return $return;
    }

    /**
     * 최조 기본 사이트 관리자 생성
     */
    public function makeDefaultManager(){

    }
}