<?php
namespace Kodes\Wcms;

// ini_set('display_errors', 1);

/**
 * 게시판 정보 클래스
 * 
 * @file
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @section LICENSE
 * 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스
 * https://www.kode.co.kr
 */
class BoardInfo
{
    /** const */
	const COLLECTION = 'boardInfo';

    /** @var Class */
    protected $db;
    protected $common;
    protected $json;

    /** @var variable */
    protected $coId;
    protected $parentMenu;

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
        $this->dataPath = $this->common->config['path']['data'].'/'.$this->coId;
        $this->parentMenu = $this->getParentMenu();
    }

    /**
     * 목록
     *
     * @return result
     */
    public function list()
    {
        $result = [];
        try {
            // program select
            $result['program'] = $this->json->readJsonFile($this->dataPath, $this->coId.'_program');
            // program 게시판 권한
            if (!empty($_SESSION['auth']['program']) && is_array($_SESSION['auth']['program']) && count($_SESSION['auth']['program']) > 0) {
                $program = [];
                foreach ($result['program'] as $key => $value) {
                    if (in_array($value['id'], $_SESSION['auth']['program'])) {
                        $program[] = $value;
                    }
                }
                $result['program'] = $program;
            }

            // filter
            $filter = [];
            $filter['coId'] = $this->coId;
            $filter['delete.is'] = ['$ne'=>true];
            if (!empty($_GET['programId'])) {
                // program 게시판 권한
                if (!empty($_SESSION['auth']['program']) && is_array($_SESSION['auth']['program']) && count($_SESSION['auth']['program']) > 0) {
                    if (in_array($_GET['programId'], $_SESSION['auth']['program'])) {
                $filter['programId'] = $_GET['programId'];
                    } else {
                        return $result;
            }
                } else {
                    $filter['programId'] = $_GET['programId'];
                }
            } elseif (!empty($_SESSION['auth']['program']) && is_array($_SESSION['auth']['program']) && count($_SESSION['auth']['program']) > 0) {
                // program 게시판 권한
                $filter['programId'] = ['$in'=>$_SESSION['auth']['program']];
            }

            if (!empty($_GET['searchText'])) {
                $filter['$or'] = [
                    ["id" => new \MongoDB\BSON\Regex($_GET['searchText'],'i')],
                    ["name" => new \MongoDB\BSON\Regex($_GET['searchText'],'i')]
                ];
            }
            // count
            $result["totalCount"] = $this->db->count(self::COLLECTION, $filter);
            // page
            $noapp = empty($_GET['noapp'])?50:$_GET['noapp'];
            $page = empty($_GET["page"])?1:$_GET["page"];
            $pageInfo = new Page();
            $result['page'] = $pageInfo->page(50, 5, $result["totalCount"], $page);
            // option
            $options = ["skip" => ($page - 1) * $noapp, "limit" => $noapp, 'sort' => ['_id' => -1], 'projection'=>['_id'=>0]];
            // list
            $result["items"] = $this->db->list(self::COLLECTION, $filter, $options);

            foreach ($result["items"] as $key => $value) {
                // 프로그램
                if (!empty($value['programId'])) {
                    $result["items"][$key]['program'] = $this->getProgram($value['programId']);
                }
            }

		} catch(\Exception $e) {
            echo "<script>";
            echo "alert('".$this->common->getExceptionMessage($e)."');";
            echo "history.back();";
            echo "</script>";
            exit;
		}

        return $result;
    }
    
    /**
     * 게시판 정보 내용 조회
     *
     * @return array
     */
    public function item($id=null)
    {
        $result = [];
        try {
            $id = empty($id)?$_GET['id']:$id;
            if (empty($id)) {
                return;
            }
            $filter['coId'] = $this->coId;
            $filter['id'] = $id;
            $options = ['projection'=>['_id'=>0]];
            $result = $this->db->item(self::COLLECTION, $filter, $options);
		} catch(\Exception $e) {
			$result['msg'] = $this->common->getExceptionMessage($e);
		}
        
        return $result;
    }

    /**
     * 게시판 정보 조회 페이지
     *
     * @return void
     */
    public function editor()
    {
        $result = [];
        try {
            $result['item'] = $this->item();

            // 기본값
            if (empty($result['item']['id'])) {
                $result['item']['isUse'] = 'Y';
                $result['item']['wcmsSkin'] = 'text';
                $result['item']['pcSkin'] = 'text';
                $result['item']['mobileSkin'] = 'text';
                $result['item']['auth']['manager'] = ['C','R','U','D'];
                $result['item']['auth']['member'] = ['R'];
                // $result['item']['auth']['noLogin'] = ['R'];
            }

            // program 게시판 권한
            if (!empty($_SESSION['auth']['program']) && is_array($_SESSION['auth']['program']) && count($_SESSION['auth']['program']) > 0) {
                if (empty($result['item']['programId']) || !in_array($result['item']['programId'], $_SESSION['auth']['program'])) {
                    throw new \Exception("권한이 없습니다.", 400);
                }
            }

            // 프로그램
            if (!empty($result['item']['programId'])) {
                $result['program'] = $this->getProgram($result['item']['programId']);
            }
            
            $result['parentMenu'] = $this->parentMenu;
        } catch(\Exception $e) {
            echo "<script>";
            echo "alert('".$this->common->getExceptionMessage($e)."');";
            echo "history.back();";
            echo "</script>";
            exit;
        }
        return $result;
    }

    /**
     * 게시판 정보 저장
     */
    public function save($data=null)
    {
        $result = [];
        try {
            if (empty($data)) {
                // requestMethod 체크
                $this->common->checkRequestMethod('POST');
                $data = $_POST;
            }

            $data = $this->setBoardInfo($data);

            $filter = ['coId'=>$this->coId, 'id'=>$data['id']];
            $options = ['$set'=>$data];
            $this->db->upsert(self::COLLECTION, $filter, $options);

            $this->makeJson($data['id']);
            
			$result['msg'] = "저장되었습니다.";
		} catch(\Exception $e) {
			$result['msg'] = $this->common->getExceptionMessage($e);
		}

		return $result;
    }

    /**
     * 저장 시 데이터 설정
     */
	protected function setBoardInfo($data)
    {
        // checkbox
        $data["isUse"] = $data["isUse"];
        $data["isPrivate"] = $data["isPrivate"]=='1'?true:false;
        $data["useEdit"] = $data["useEdit"]=='1'?true:false;
        $data["useCaptcha"] = $data["useCaptcha"]=='1'?true:false;
        $data["useImgZoomin"] = $data["useImgZoomin"]=='1'?true:false;

        $data["useNotice"] = $data["useNotice"]=='1'?true:false;
        $data["useSecret"] = $data["useSecret"]=='1'?true:false;
        $data["useLike"] = $data["useLike"]=='1'?true:false;
        $data["useSearch"] = $data["useSearch"]=='1'?true:false;
        $data["useComment"] = $data["useComment"]=='1'?true:false;
        $data["useReply"] = $data["useReply"]=='1'?true:false;

        // files
        $data['files'] = [];
		foreach($_POST['file_orgName'] as $key => $val) {
			$data['files'][$key]=[
                'orgName' => $val,
                'path' => $_POST['file_path'][$key],
                'ext'=> $_POST['file_ext'][$key],
                'mimeType'=> $_POST['file_mimeType'][$key],
                'type'=> $_POST['file_type'][$key],
                'size'=> $_POST['file_size'][$key],
                'width'=> $_POST['file_width'][$key],
                'height'=> $_POST['file_height'][$key]
			];
		}

        // 관리자 계정
        if (empty($data['manager'])) {
			$data['manager'] = [];
		} else {
			$data['manager'] = json_decode($data['manager'], true);
		}

        // auth
        $data['auth']['manager'] = empty($data['auth']['manager'])?[]:$data['auth']['manager'];
        $data['auth']['member'] = empty($data['auth']['member'])?[]:$data['auth']['member'];

        $removeField = ['file_orgName', 'file_path', 'file_ext', 'file_mimeType', 'file_type', 'file_size', 'file_width', 'file_height'];
        $data = $this->common->covertDataField($data, $data['action'], $removeField);

        // 추가 필드
        $field = [];
        if (!empty($data['field']['id']) && is_array($data['field']['id'])) {
            foreach ($data['field']['id'] as $key => $value) {
                if (empty($data['field']['id'][$key]) || empty($data['field']['name'][$key])) {
                    continue;
                }
                $item = [];
                $item['id'] = $data['field']['id'][$key];
                $item['name'] = $data['field']['name'][$key];
                $item['inputType'] = $data['field']['inputType'][$key];
                $item['codeType'] = $data['field']['codeType'][$key];
                $item['required'] = $data['field']['required'][$key];
                $item['useList'] = boolval($data['field']['useList'][$key]);
                $item['useSearch'] = boolval($data['field']['useSearch'][$key]);
                $field[] = $item;
                unset($item);
            }
        }
        unset($data['field']);
        $data['field'] = $field;

        return $data;
	}

    /**
     * 삭제
     *
     * @return result
     */
	public function delete()
    {
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            if (empty($_POST['id'])) {
                throw new \Exception("삭제할 ID가 없습니다.", 400);
            }

            $ids = explode(",", $_POST['id']);
            $deleteIds = [];
            foreach ($ids as $key => $value) {
                if (empty($value)) continue;
                // 게시글이 있는 게시판은 삭제하지 않음
                $temp = $this->item($value, 'Y');
                if (!empty($temp['count']) && $temp['count'] > 0) {
                    continue;
                } else {
                    $deleteIds[] = $value;
                }
            }

            if (empty($deleteIds)) {
                throw new \Exception("게시글이 존재하는 게시판은 삭제할 수 없습니다.", 400);
            }

            // 게시판 
            $filter = [];
            $filter['id'] = ['$in'=>$deleteIds];
            $this->db->delete(self::COLLECTION, $filter, false);

            $result['msg'] = "삭제 되었습니다.";
		} catch (\Exception $e) {
			$result['msg'] = $this->common->getExceptionMessage($e);
		}

        return $result;
    }


    /**
     * program 조회
     */
    protected function getProgram($id)
    {
        // 프로그램
        $info = $this->json->readJsonFile($this->dataPath.'/list/program', $id.'_info');
        return $info;
    }

    /**
     * parentMenu 조회
     */
    protected function getParentMenu()
    {
        $parentMenu = [];
        $wcms_menu = $this->json->readJsonFile('../config', 'wcms_menu');
        foreach ($wcms_menu as $key => $value) {
            if (!empty($value['useAddChild'])) {
                $parentMenu[] = ['menuId'=>$value['menuId'], 'menuName'=>$value['menuName']];
            }
        }
        return $parentMenu;
    }

    /**
     * info json 생성
     */
    protected function makeJson($id)
    {
       $filter = ['coId'=>$this->coId, 'id'=>$id];
        $options = ['projection'=>['_id'=>0]];
        $data = $this->db->item(self::COLLECTION, $filter, $options);
        if (!empty($data)) {
            $this->json->makeJson($this->dataPath.'/list/board', $id.'_info', $data);
        }

        // WCMS 메뉴 노출용 json 저장
        $filter = ['coId'=>$this->coId, 'isUse'=>'Y'];
        $options = ['projection'=>['_id'=>0]];
        $data = $this->db->list(self::COLLECTION, $filter, $options);
        $boardMenu = [];
        foreach ($this->parentMenu as $menuItem) {
            $menuId = $menuItem['menuId'];
            $boardMenu[$menuId] = [];
            foreach ($data as $key => $value) {
                if ($value['wcmsMenu'] == $menuId) {
                    $boardMenu[$menuId][] = [
                        'menuId'=>$value['id'],
                        'menuName'=>$value['name'],
                        'parent'=>$menuId,
                        'link'=>'/board/list/'.$value['id'],
                        'datalink'=>'/board/view/'.$value['id'].' | /board/editor/'.$value['id'],
                    ];
                }
            }
        }
        $this->json->makeJson($this->dataPath.'/config', 'wcmsBoardMenu', $boardMenu);
        
        $boardList = [];
        foreach ($data as $key2 => $value2) {
            // 회원 권한 확인
            if (in_array('R', $value2['auth']['member'])) {
                $link = '/board/list/'.$value2['id'];
            } elseif (!in_array('R', $value2['auth']['member']) && in_array('C', $value2['auth']['member'])) {
                $link = '/board/edit/'.$value2['id'];
            } 
            // 비로그인 권한 확인 (회원 권한보다 우선함)
            else if (in_array('R', $value2['auth']['noLogin'])) {
                $link = '/board/list/'.$value2['id'];
            } elseif (!in_array('R', $value2['auth']['noLogin']) && in_array('C', $value2['auth']['noLogin'])) {
                $link = '/board/edit/'.$value2['id'];
            } else {
                $link = '';
            }
            
            $boardList[] = [
                'id'=>$value2['id'],
                'name'=>$value2['name'],
                'link'=>$link,
            ];
        }
        $this->json->makeJson($this->dataPath.'/list/board', 'boardList', $boardList);

        // 사용중인 전체 게시판 : 비노출 제외
        $filter = ['coId'=>$this->coId, 'isUse'=>['$ne'=>'N']];
        $options = ['projection'=>['_id'=>0]];
        $data = $this->db->list(self::COLLECTION, $filter, $options);
        $this->json->makeJson($this->dataPath, $this->coId.'_board', $data);
    }
}