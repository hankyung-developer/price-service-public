<?php
namespace Kodes\Wcms;

// ini_set('display_errors', 1);

/**
 * 게시판 클래스
 * 
 * @file
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @section LICENSE
 * 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스
 * https://www.kode.co.kr
 */
class Board
{
    /** const */
	const COLLECTION = 'board';
	const BOARD_INFO_COLLECTION = 'boardInfo';

    /** @var Class */
    protected $db;
    protected $common;
    protected $json;

    /** @var variable */
    protected $coId;
    protected $id;
    protected $category;
    protected $department;

    /**
     * 생성자
     */
    function __construct($id=null)
    {
        // class
        $this->db = new DB();
        $this->common = new Common();
        $this->json = new Json();
		
		// variable
        $this->coId = $this->common->coId;
        $this->id = $id;
        $this->dataPath = $this->common->config['path']['data'].'/'.$this->coId;
        $this->category = $this->json->readJsonFile($this->dataPath, $this->coId.'_category');
		$this->department = $this->json->readJsonFile($this->dataPath.'/department', 'all');
    }

    /**
     * 목록 화면
     */
	public function list()
    {
        $result = [];
        try {
            $id = $this->id;
            if (empty($_GET['id'])) $_GET['id'] = $id;
            else $id = $_GET['id'];
            
            if (empty($id) && $_GET['requester'] != 'layout') {
                throw new \Exception("게시판ID가 없습니다.", 400);
            }

            // boardInfo
            $result['info'] = $this->getBoardInfo($id);

            // filter
            $filter = [];
            $filter['coId'] = $this->coId;
            // $filter['status'] = 'publish';
            // $filter['delete.is'] = ['$ne'=>true];
            if (!empty($id)) $filter['id'] = $id;
            if (!empty($_GET['searchText'])) {
                if (empty($_GET['searchItem']) || $_GET['searchItem'] == 'text') {
                    $filter['$text'] = ['$search' => $_GET['searchText']];
                } elseif ($_GET['searchItem'] == 'insert.memberName') {
                    $filter['$or'][] = ['insert.memberName'=>new \MongoDB\BSON\Regex(preg_quote($_GET['searchText'], '/'),'i')];
					$filter['$or'][] = ['insert.memberId'=>$_GET['searchText']];
                    $filter['$or'][] = ['insert.managerName'=>new \MongoDB\BSON\Regex(preg_quote($_GET['searchText'], '/'),'i')];
					$filter['$or'][] = ['insert.managerId'=>$_GET['searchText']];
                } else {
                    $filter[$_GET['searchItem']] = new \MongoDB\BSON\Regex(preg_quote($_GET['searchText'], '/'),'i');
                }
            }

            // filter : 추가필드
            foreach ($result['info']['field'] as $key => $value) {
                if (empty($value['useSearch'])) continue;
                if (empty($_GET['field_'.$value['id']])) continue;

                if (in_array($value['inputType'], ['text','textarea'])) {
                    $filter['field.'.$value['id']] = new \MongoDB\BSON\Regex(preg_quote($_GET['field_'.$value['id']], '/'),'i');
                } else {
                    $filter['field.'.$value['id']] = $_GET['field_'.$value['id']];
                }
            }
            
            if (!empty($_GET['listType']) && $_GET['listType'] == 'calendar' && !empty($_GET['startDate'])) {
                // 달력 기준 게시물 조회
                $startDate = '';
                $endDate = '';
                if (!empty($_GET['endDate'])) {
                    // 조회 시작일/종료일을 모두 지정한 경우
                    $startDate = $_GET['startDate'];
                    $endDate = $_GET['endDate'];
                } else {
                    $startDate = date('Y-m-d', strtotime('-1 month', strtotime($_GET['startDate'])));
                    $endDate = date('Y-m-d', strtotime('+2 month', strtotime($_GET['startDate'])));
                }
                $filter['$or'][] = ['insert.date' => ['$gte'=>$startDate.' 00:00:00','$lt'=>$endDate.' 00:00:00']];
                $filter['$or'][] = ['startDate' => ['$gte'=>$startDate,'$lt'=>$endDate]];
                $filter['$or'][] = ['endDate' => ['$gte'=>$startDate,'$lt'=>$endDate]];
                $options = ['projection'=>['_id'=>0]];
            } else {
                // count
                $result["totalCount"] = $this->db->count(self::COLLECTION, $filter);

                // page
                $noapp = empty($_GET['noapp'])? ( empty($result['info']['pagePerNum']) ? 15 : $result['info']['pagePerNum']):$_GET['noapp'];
                // print_r($this->deviceType);
                $page = empty($_GET["page"])?1:$_GET["page"];
                $pageInfo = new Page();
                $result['page'] = $pageInfo->page(10, 5, $result["totalCount"], $page);


                // option
                $options = ['skip' => ($page - 1) * $noapp, 'limit' => $noapp, 'sort' => ['isNotice'=>-1,'pno' => -1,'depth' => 1,'no' => 1], 'projection'=>['_id'=>0]];
                if($result['info']['useReply']){
                    $options = ['skip' => ($page - 1) * $noapp, 'limit' => $noapp, 'sort' => ['isNotice'=>-1,'pno' => -1,'sortNum'=>1,'depth' => 1], 'projection'=>['_id'=>0]];
                }
            }

            // list
            $result['items'] = $this->db->list(self::COLLECTION, $filter, $options);

            // 권한
            $result['permission'] = $this->getPermission($result['info']);
            if (!$result['permission']['read']) {
                throw new \Exception("읽기 권한이 없습니다.", 400);
            }

            foreach ($result['items'] as $key => $value) {

                // thumbnail
                $result['items'][$key]['thumbnail'] = $this->common->getThumbnail($value['files']);

                // field
                foreach ($value['field'] as $key2 => $value2) {
                    $info = $this->common->searchArray2D($result['info']['field'], 'id', $key2);
                    
                    if (in_array($info['inputType'], ['select','radio']) && !empty($info['codeType'])) {
                        if ($info['codeType'] == 'category') {
                            $temp = $this->common->searchArray2D($this->category, 'id', $value2);
                            if (!empty($temp['id'])) {
                                $result['items'][$key]['field'][$key2] = $temp['name'];
                            }
                        }
                        if ($info['codeType'] == 'department') {
                            $temp = $this->common->searchArray2D($this->department, 'id', $value2);
                            if (!empty($temp['id'])) {
                                $result['items'][$key]['field'][$key2] = $temp['name'];
                            }
                        }
                    }
                }

                // comment
                $_GET['aid'] = $value['id']."_". $value['no'];
                $_GET['searchText'] = "";
                $comment = new \Kodes\Wcms\Comment();
                $result['items'][$key]['comment'] = $comment->list();

                // 답글 아이콘표기
                if($value['depth'] > 0){
                    $result['items'][$key]['depthSign'] = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-return-right" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M1.5 1.5A.5.5 0 0 0 1 2v4.8a2.5 2.5 0 0 0 2.5 2.5h9.793l-3.347 3.346a.5.5 0 0 0 .708.708l4.2-4.2a.5.5 0 0 0 0-.708l-4-4a.5.5 0 0 0-.708.708L13.293 8.3H3.5A1.5 1.5 0 0 1 2 6.8V2a.5.5 0 0 0-.5-.5z"/></svg>&nbsp;';
                    for($i=0 ; $i < $value['depth'] ; $i++){
                        $result['items'][$key]['depthSign'] = '&nbsp;&nbsp;&nbsp;'.$result['items'][$key]['depthSign'];
                    }
                }
            }

            // 스킨
            if (empty($result['info']['wcmsSkin']) || $result['info']['wcmsSkin'] == 'text') {
            } else {
                $result['skin'] = $result['info']['wcmsSkin'].'List';
            }

            if (empty($_GET['returnType']) || $_GET['returnType'] != 'ajax') {
                $result['category'] = $this->category;
                $result['department'] = $this->department;
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
     * 보기 화면
     */
	public function view()
    {
        $result = [];
        try {
            $id = $this->id;
            if (empty($_GET['id'])) $_GET['id'] = $id;
            else $id = $_GET['id'];

            if (empty($id)) {
                throw new \Exception("게시판ID가 없습니다.", 400);
            }

            // boardInfo
            $result['info'] = $this->getBoardInfo($id);

            $result['item'] = $this->item($id, $_GET['no']);
        
            if (empty($result['item'])) {
                throw new \Exception("존재하지 않는 게시글입니다.", 400);
            }

            // 답글: 상위글 정보
            if(!empty($result['info']['useReply']) &&  $result['info']['useReply']){
                if($result['item']['depth'] > 0){
                    $result['pItem'] = $this->item($id, $result['item']['sno']);
                }
            }

            // 댓글 사용
            if($result['info']['useComment']){
                $_GET['aid']= $_GET['id']."_". $_GET['no'];
                $_GET['limit'] = 1000;
                $comment = new \Kodes\Wcms\Comment();
                $result['item']['comment'] = $comment->list();
            }

            // 권한
            $result['permission'] = $this->getPermission($result['info'], $result['item']);
            // print_r($result['permission']);
            if (!$result['permission']['read']) {
                throw new \Exception("읽기 권한이 없습니다.", 400);
            }

            // 스킨
            // if (empty($result['info']['wcmsSkin']) || $result['info']['wcmsSkin'] == 'text') {
            // } else {
            //     $result['skin'] = $result['info']['wcmsSkin'].'View';
            // }

            $result['category'] = $this->category;
            $result['department'] = $this->department;
            // $result['sheet'] = $this->fieldHistoryexcel();

            // print_r($result);

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
     * 수정 화면
     */
	public function editor()
    {
        $result = [];
        try {
            $id = $this->id;
            if (empty($_GET['id'])) $_GET['id'] = $id;
            else $id = $_GET['id'];

            if (empty($id)) {
                throw new \Exception("게시판ID가 없습니다.", 400);
            }

            // boardInfo
            $result['info'] = $this->getBoardInfo($id);

            if (!empty($_GET['no'])) {
                $result['item'] = $this->item($id, $_GET['no']);
            }

            // 답글: 상위글 정보
            if(!empty($result['info']['useReply']) &&  $result['info']['useReply']){
                if($result['item']['depth'] > 0 || $_GET['depth'] > 0){
                    $result['pItem'] = $this->item($id, $_GET['sno']);
                }
            }

            // 권한
            $result['permission'] = $this->getPermission($result['info'], $result['item']);
            if (!empty($result['item']['no'])) {
                if (!$result['permission']['update']) {
                    throw new \Exception("수정 권한이 없습니다.", 400);
                }
            } elseif (!$result['permission']['insert']) {
                throw new \Exception("입력 권한이 없습니다.", 400);
            }

            $result['category'] = $this->category;
            $result['department'] = $this->department;

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
     * 조회
     */
	public function item($id, $no)
    {
        $filter['coId'] = $this->coId;
        $filter['id'] = $id;
        $filter['no'] = (int) $no;
        // $filter['status'] = 'publish';
        // $filter['delete.is'] = ['$ne'=>true];
        $options = ['projection'=>['_id'=>0]];
        $result = $this->db->item(self::COLLECTION, $filter, $options);
        return $result;
    }

    /**
     * 저장
     */
    public function save($isServer=false)
    {
        $result = [];
        try {
            if(!$isServer){
                // requestMethod 체크
                $this->common->checkRequestMethod('POST');
            }

            $data = $_POST;

            if (empty($data['id'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            if (!empty($data['id']) && !$isServer) {
                // 게시글 조회
                $filter2['coId'] = $this->coId;
                $filter2['id'] = $data['id'];

                $board = $this->db->item(self::BOARD_INFO_COLLECTION, $filter2,[]);
            }

            // 상태
            $data['status'] = 'publish';

            // insert / update
            $action = 'update';
            if (empty($data['no'])) {
                $action = 'insert';
                $data['no'] = $this->generateId($data["id"]);
            }
            $data['no'] = intval($data['no']);

            // 권한 체크
            $info = $this->getBoardInfo($data['id']);
            $item = null;
            if ($action == 'update') {
                $item = $this->item($data['id'], $data['no']);
            }
            $permission = $this->getPermission($info, $item);
            if ($action == 'insert' && !$permission['insert']) {
                throw new \Exception("입력 권한이 없습니다.", 400);
            } elseif ($action == 'update' && !$permission['update']) {
                throw new \Exception("수정 권한이 없습니다.", 400);
            }

            /**
             * - pno(root no) 기본값 : root글일 경우 no와 동일
             * - sno(상위글 no) 기본값 : root글일 경우 no와 동일
             * - depth 기본값 : root글일 경우 0
             */

            // root글
            if (empty($data['pno'])) {
                $data['pno'] = $data['no'];
                $data['sno'] = $data['no'];
                $data['depth'] = 0;
            }
            
            $data['pno'] = intval($data['pno']);
            $data['sno'] = intval($data['sno']);
            $data['depth'] = intval($data['depth']);

            // 에디터 사용 입력시 점리스트, 숫자리스트 개행이 추가되서 치환처리
            if(!empty($data['content'])){
                // 개행 br로 치환
                $data['content'] = preg_replace("/<([\/]*)(br|p)([ ]*[\/]*)>[\n]+/","<$1$2$3>",$data['content']);
                $data['content'] = str_replace(["\r\n","\r","\n"],'<br />',$data['content']);
                // ol, ul 내 br 제거
                $data['content'] = strtr($data['content'], ["</ol>"=>"</ol>\n", "</ul>"=>"</ul>\n"]);
                preg_match_all('/<(ol|ul)[^<>]*>.*<\/(ol|ul)>/im', $data['content'], $matches);
                foreach ($matches as $key => $value) {
                    $data['content'] = str_replace($value, preg_replace('/<br[^<>]*>/','',$value), $data['content']);
                }
                $data['content'] = strtr($data['content'], ["</ol>\n"=>"</ol>", "</ul>\n"=>"</ul>"]);
            }

            // 예약발행
            if ($data['status'] == 'publish') {
                if (empty($data['publishDate'])) {
                    $data['publishDate'] = date('Y-m-d H:i');
                }
            }

            if($action == 'insert' &&  empty($data['sortNum'])){
                $data['sortNum'] = $this->generateSortNum($data['id'], $data['no'], $data['pno'], $data['sno'], $data['depth']);
            }else{
                $data['sortNum'] = intval($data['sortNum']);
            }

            // author 추가.비밀글에 대한 답글일 경우 해당 답글의 root글 작성자에게 답글읽기 권한필요 
            if($data['isSecret'] == 'on'){
                if($data['depth'] > 0 && (empty($data['author']) || !is_set($data['author']))){
                    $author =  $this->item($data['id'], $data['pno'])['insert']['memberId'];
                    if(!empty($author)){
                        $data['author'] = $author;
                    }else{
                        $data['author'] = $this->item($data['id'], $data['pno'])['insert']['managerId'];
                    }
                }
            }

            if(empty($data['isNotice'])) $data['isNotice'] = '0';

            // dropzone 업로드 파일
            $data['files'] = [];
            foreach($_POST['file_orgName'] as $key => $val) {
                $data['files'][$key] = [
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

            $removeField = ['file_orgName', 'file_path', 'file_ext', 'file_mimeType', 'file_type', 'file_size', 'file_width', 'file_height'];

            $data = $this->common->covertDataField($data, $action, $removeField);

            // 첨부파일
            // @todo 필요한가?
            // if ($_FILES['file']['name']) {
            //     $fileId=date("U");
            //     // $storeFolder = '/webSiteSource/wcms/web/data/'.$this->coId.'/upload';
            //     // $storeFolder = str_replace("{coId}",$this->coId,$this->common->path["data"]).'/upload';
            //     $storeFolder = $this->common->path['webData'].$this->coId.'/upload';
            //     $targetFolder = '/data/'.$this->coId.'/upload';
            //     $tempFile = $_FILES['file']['tmp_name'];
            //     $fileName = $_FILES['file']['name'];
            //     move_uploaded_file($tempFile,$storeFolder.'/'.$fileId);
            //     $data["orgFileName"] = $fileName;
            //     $data["fileLink"] = $fileId;
            // }

            $filter = ['coId'=> $this->coId, 'id'=> $data['id'], 'no'=> $data['no']];
            $options = ['$set'=>$data];
            $this->db->upsert(self::COLLECTION, $filter, $options);

			$result['msg'] = "저장되었습니다.";
		} catch(\Exception $e) {
			$result['msg'] = $this->common->getExceptionMessage($e);
		}

        return $result;
    }

    /**
     * 삭제
     */
	public function delete()
    {
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            $data = $_POST;
            if (empty($data['id']) || empty($data['no'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }
            $data['no'] = explode(',', $data['no']);
            foreach ($data['no'] as $key => $value) {
                $data['no'][$key] = intval($value);
            }

            // 권한 체크
            $info = $this->getBoardInfo($data['id']);
            $item = $this->item($data['id'], $data['no']);
            $permission = $this->getPermission($info, $item);
            if (!$permission['delete']) {
                throw new \Exception("삭제 권한이 없습니다.", 400);
            }

             // 답글사용 게시판일 경우 해당 게시글의 no를 sno로하고 depth+1인 게시글이 있으면(답글:최소 1) 삭제 X
             if($info['useReply']){
                $filter2 = ['coId'=> $this->coId, 'id'=> $data['id'], 'no'=> ['$in'=>$data['no']]];
                $options2 = ['projection'=>['_id'=>0]];
                $list = $this->db->list(self::COLLECTION, $filter2, $options2);
                foreach($list as $val){
                    $rFilter['id'] = $val['id'];
                    $rFilter['sno'] = $val['no'];
                    $rFilter['depth'] = $val['depth'] + 1;
                    $rCount = $this->db->count(self::COLLECTION, $rFilter);
                    if($rCount > 0){
                        throw new \Exception("해당글에 대한 답글이 존재하므로 삭제할 수 없습니다.", 400);
                    }
                }
            }

            $filter = ['coId'=> $this->coId, 'id'=> $data['id'], 'no'=> ['$in'=>$data['no']]];
            // data
            $data = [];
            $data['status'] = 'delete';    // 상태
            $data = $this->common->covertDataField($data, 'delete');
            $options = ['$set'=>$data];
            $result = $this->db->update(self::COLLECTION, $filter, $options, true);
            
            //$result['msg'] = "삭제되었습니다.";
        } catch(\Exception $e) {
            $result['msg'] = $this->common->getExceptionMessage($e);
        }

        return $result;
	}

    /**
     * 완전삭제(DB삭제)
     */
    function remove(){
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            $data = $_POST;
            if (empty($data['id']) || empty($data['no'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }
            $data['no'] = explode(',', $data['no']);
            foreach ($data['no'] as $key => $value) {
                $data['no'][$key] = intval($value);
            }

            // 권한 체크
            $info = $this->getBoardInfo($data['id']);
            $item = $this->item($data['id'], $data['no']);


            $permission = $this->getPermission($info, $item);

            if (!$permission['delete'] ) {
                throw new \Exception("삭제 권한이 없습니다.", 400);
            }

            $filter = ['coId'=> $this->coId, 'id'=> $data['id'], 'status'=>'delete', 'no'=> ['$in'=>$data['no']]];
            $result = $this->db->delete(self::COLLECTION, $filter, true);
        } catch(\Exception $e) {
            $result['msg'] = $this->common->getExceptionMessage($e);
        }

        return $result;
    }

    /**
     * 삭제글 복구
     */
    function restore(){
        $result = [];
        try {
            // requestMethod 체크
            $this->common->checkRequestMethod('POST');

            $data = $_POST;
            if (empty($data['id']) || empty($data['no'])) {
                throw new \Exception("유효하지 않은 접근입니다.", 400);
            }

            $info = $this->getBoardInfo($data['id']);
            $item = $this->item($data['id'], $data['no']);
            $permission = $this->getPermission($info, $item);
            if (!$permission['delete'] ) {
                throw new \Exception("복구 권한이 없습니다.", 400);
            }

            $data['no'] = explode(',', $data['no']);
            foreach ($data['no'] as $key => $value) {
                $data['no'][$key] = intval($value);
            }

            $filter = ['coId'=> $this->coId, 'id'=> $data['id'], 'no'=> ['$in'=>$data['no']]];

            $data = [];
            $data['status'] = 'publish';    // 상태
            $data['delete']['is']=false;
            $options = ['$set'=>$data];
            $result['result'] = $this->db->update(self::COLLECTION, $filter, $options, true);
            $result['msg'] = '게시글이 복구되었습니다.';
            
        } catch(\Exception $e) {
            $result['msg'] = $this->common->getExceptionMessage($e);
        }

        return $result;
    }

    /**
     * boardInfo 조회
     */
    protected function getBoardInfo($id)
    {
        // boardInfo
        $info = $this->db->item(self::BOARD_INFO_COLLECTION, ['coId'=>$this->coId, 'id'=>$id], ['projection'=>['_id'=>0]]);
        // 프로그램
        if (!empty($info['programId'])) {
            $info['program'] = $this->json->readJsonFile($this->dataPath.'/list/program', $info['programId'].'_info');
        }
        return $info;
    }

    /**
     * ID 생성
     */
	protected function generateId($id)
    {
        $filter["coId"] = $this->coId;
        $filter["id"] = $id;
        $options = ["sort" => ["no" => -1], "limit" => 1];
        $data = $this->db->list(self::COLLECTION, $filter, $options);
        $lastId = $data[0]["no"];
        if (empty($lastId)) {
            $lastId = 0;
        }
        return ++$lastId;
    }

    /**
     * sortNum 생성. 답글정렬 최신순
     * @param id    (string) : 게시판 id
     * @param pno   (int)    : root글 번호
     * @param sno   (int)    : 상위글 번호
     * @param depth (int)    : 차수
     */
    protected function generateSortNum($id, $no, $pno, $sno, $depth)
    {
        $filter["coId"] = $this->coId;
        $filter["id"] = $id;

        // root글일 경우
        if($depth == 0){
            $options = ["sort" => ["sortNum" => -1], "limit" => 1];
            $data = $this->db->list(self::COLLECTION, $filter, $options);
            $lastSortNum = $data[0]["sortNum"];
            if (empty($lastSortNum) || !$lastSortNum) {
                $newSortNum = 0;
            }else{
                $newSortNum = intval($lastSortNum) + 1;
            }
        }

        // 하위글(답글)일 경우
        if($depth > 0){
            $filter["pno"] = $pno;
            $filter['sno'] = $sno; 
            if($depth == 1){
                $options = ["sort" => ["sortNum" => 1], "limit" => 1];
                $data = $this->db->list(self::COLLECTION, $filter, $options);
                $lastSortNum = $data[0]["sortNum"]; 
                $newSortNum = intval($lastSortNum) + 1;
            }
            if($depth > 1){
                $filter['depth'] = $depth;
                $snoCount = $this->db->count(self::COLLECTION, $filter); 
                if($snoCount == 0){
                    unset($filter['sno'],$filter['depth']);
                    $filter['no'] = $sno;
                    $options = ["sort" => ["sortNum" => 1], "limit" => 1];
                    $data = $this->db->list(self::COLLECTION, $filter, $options);
                    $lastSortNum = $data[0]["sortNum"]; 
                    $newSortNum = intval($lastSortNum) + 1;
                }else{
                    $options = ["sort" => ["sortNum" => 1], "limit" => 1];
                    $data = $this->db->list(self::COLLECTION, $filter, $options);
                    $lastSortNum = $data[0]["sortNum"]; 
                    $newSortNum = intval($lastSortNum);
                }
            }
                            
            // sortNum이 newSortNum보다 크거나 같은 게시글들의 sortNum을 1씩 증가시킨다
            $filter2['coId'] = $this->coId;
            $filter2['id'] = $id;
            $filter2['no'] = ['$ne'=>$no];
            $filter2['sortNum'] = ['$gte'=>$newSortNum];
            $options2 = ['$inc'=>['sortNum'=>1]];
            $this->db->update(self::COLLECTION, $filter2, $options2, true);
        }
        return $newSortNum;
    }

    /**
     * 권한 조회
     */
    public function getPermission($info, $board=null)
    {
        $boardManager = $this->common->searchArray2D($info['manager'], 'id', $_SESSION['managerId']);

        // 권한
        $permission = [];
        $permission['boardManager'] = false;
        $permission['read'] = false;
        $permission['insert'] = false;
        $permission['update'] = false;
        $permission['delete'] = false;
        // 권한 : 관리자
        if (!empty($_SESSION['isSuper']) || !empty($boardManager['id'])) {
            $permission['boardManager'] = true;
            $permission['read'] = true;
            $permission['insert'] = true;
            $permission['update'] = true;
            $permission['delete'] = true;
        }
        // 권한 : 사용자
        if (!empty($info['auth']['manager']) && in_array('R', $info['auth']['manager'])) {
            $permission['read'] = true;
        }
        if (!empty($info['auth']['manager']) && in_array('C', $info['auth']['manager'])) {
            $permission['insert'] = true;
        }
        if (!empty($board)) {
            if (!empty($info['auth']['manager']) && in_array('U', $info['auth']['manager']) && $board['insert']['managerId'] == $_SESSION['managerId']) {
                $permission['update'] = true;
            }
            if (!empty($info['auth']['manager']) && in_array('D', $info['auth']['manager']) && $board['insert']['managerId'] == $_SESSION['managerId']) {
                $permission['delete'] = true;
            }
        }

        return $permission;
    }
    
}