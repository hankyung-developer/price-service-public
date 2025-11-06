<?php 
/**
 * AI 설정 관리 클래스
 * 
 * AI 템플릿, 프롬프트, 스케줄, 로그를 관리하는 클래스입니다.
 * MongoDB를 사용하여 데이터를 저장하고 관리합니다.
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
namespace Kodes\Api;

class AiSetting
{
    // MongoDB 컬렉션 상수 정의
    const AI_TEMPLATE_COLLECTION = 'aiTemplate';    // AI 템플릿 컬렉션
    const AI_PROMPT_COLLECTION = 'aiPrompt';        // AI 프롬프트 컬렉션
    const AI_SCHEDULE_COLLECTION = 'aiSchedule';    // AI 스케줄 컬렉션
    const AI_LOG_COLLECTION = 'aiLog';              // AI 로그 컬렉션
    const AI_MODEL_COLLECTION = 'aiModel';          // AI 모델 컬렉션

    /** @var Class 공통 */
    protected $common;
    protected $json;
    protected $db;

    private $siteDocPath;
    private $coId;
    private $collection;

    /**
     * 생성자
     * 공통 클래스들을 초기화하고 기본 설정을 로드합니다.
     */
    public function __construct()
    {
        $this->common = new Common();
        $this->json = new Json();
        $this->db = new DB();

        $this->coId = $this->common->coId;
        $this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
        $company = $this->json->readJsonFile($this->siteDocPath.'/config', $this->coId.'_company');
    }

    // ========================================
    // 템플릿 관련 메서드
    // ========================================

    /**
     * AI 템플릿 목록 조회
     * 템플릿 내용을 50자로 제한하여 반환합니다.
     * 
     * @return array 템플릿 목록과 페이지 정보
     */
    public function templateList(){
        $this->collection = self::AI_TEMPLATE_COLLECTION;
        if(!empty($_GET['isUse']) && $_GET['isUse'] == 'Y'){
            $filter['isUse'] = true;
        }
        
        $return = $this->list($filter);
        
        // 템플릿 내용을 50자로 제한
        foreach($return['items'] as $key => $item){
            $return['items'][$key]['content'] = mb_substr($item['content'],0,50);
        }

        return $return;
    }

    /**
     * AI 템플릿 수정 페이지 데이터 조회
     * 
     * @return array 템플릿 상세 정보
     */
    public function templateEdit(){
        if(!empty($_GET['idx'])){
            $this->collection = self::AI_TEMPLATE_COLLECTION;
            $return = $this->view();
        }
        $return['success'] = true;
        return $return;
    }

    /**
     * AI 템플릿 등록
     * 
     * @return array 등록 결과
     */
    public function templateInsert(){
        $this->collection = self::AI_TEMPLATE_COLLECTION;
        $request = $_POST;

        // 사용 여부를 boolean으로 변환
        $request['isUse'] = $_POST['isUse']=='Y'?true:false;

        unset($request['action']);

        return $this->insert($request);
    }

    /**
     * AI 템플릿 수정
     * 
     * @return array 수정 결과
     */
    public function templateModify(){
        $this->collection = self::AI_TEMPLATE_COLLECTION;

        $request = $_POST;
        $request['isUse'] = $_POST['isUse']=='Y'?true:false;

        return $this->update($request);
    }

    // ========================================
    // 프롬프트 관련 메서드
    // ========================================

    /**
     * AI 프롬프트 목록 조회
     * 
     * @return array 프롬프트 목록과 페이지 정보
     */
    public function promptList(){
        $this->collection = self::AI_PROMPT_COLLECTION;
        $return = $this->list();
        return $return;
    }

    /**
     * AI 프롬프트 수정 페이지 데이터 조회
     * 
     * @return array 프롬프트 상세 정보
     */
    public function promptEdit(){
        if(!empty($_GET['idx'])){
            $this->collection = self::AI_PROMPT_COLLECTION;
            $return = $this->view();
        }
        return $return;
    }

    /**
     * AI 프롬프트 등록
     * 
     * @return array 등록 결과
     */
    public function promptInsert(){
        $this->collection = self::AI_PROMPT_COLLECTION;
        $request = $_POST;
        $request['isUse'] = $_POST['isUse']=='Y'?true:false;
        unset($request['action']);

        return $this->insert($request);
    }

    /**
     * AI 프롬프트 수정
     * 
     * @return array 수정 결과
     */
    public function promptModify(){
        $this->collection = self::AI_PROMPT_COLLECTION;

        $request = $_POST;
        $request['isUse'] = $_POST['isUse']=='Y'?true:false;
        return $this->update($request);
    }

    // ========================================
    // 스케줄 관련 메서드
    // ========================================

    /**
     * AI 스케줄 목록 조회
     * 
     * @return array 스케줄 목록과 페이지 정보
     */
    public function scheduleList(){
        $this->collection = self::AI_SCHEDULE_COLLECTION;
        $return = $this->list();
        return $return;
    }

    /**
     * AI 스케줄 수정 페이지 데이터 조회
     * 
     * @return array 스케줄 상세 정보
     */
    public function scheduleEdit(){
        if(!empty($_GET['idx'])){
            $this->collection = self::AI_SCHEDULE_COLLECTION;
            $return = $this->view();
        }

        $return['template'] = $this->templateList()['items'];
        $return['prompt'] = $this->promptList()['items'];

        return $return;
    }

    /**
     * AI 스케줄 등록
     * 
     * @return array 등록 결과
     */
    public function scheduleInsert(){
        $this->collection = self::AI_SCHEDULE_COLLECTION;
        $request = $_POST;
        $request['isUse'] = $_POST['isUse']=='Y'?true:false;
        unset($request['action']);

        return $this->insert($request);
    }

    /**
     * AI 스케줄 수정
     * 
     * @return array 수정 결과
     */
    public function scheduleModify(){
        if(!empty($_GET['idx'])){
            $this->collection = self::AI_SCHEDULE_COLLECTION;
            $return = $this->view();
        }
        return $return;
    }

    // ========================================
    // 사용량/로그 관련 메서드
    // ========================================

    /**
     * AI 사용량/로그 목록 조회
     * 
     * @return array 사용량/로그 목록과 페이지 정보
     */
    public function usageList(){
        $aiLog = new AiLog();
        $return = $aiLog->list();
        return $return;
    }

    // ========================================
    // 공통 CRUD 메서드
    // ========================================

    /**
     * 목록 조회 (공통)
     * 검색, 필터링, 페이징 기능을 포함한 목록 조회
     * 
     * @param array|null $request 요청 파라미터 (기본값: $_GET)
     * @return array 목록 데이터와 페이지 정보
     */
    private function list($request=null){
        try {
            $request = empty($request)?$_GET:$request;

            $return = [];
            $filter = [];
            $options = [];

            // 기본 필터 설정
            $filter['coId'] = $this->coId;
            $filter['delete.is'] = ['$ne'=>true];
            
            // 검색어 필터
            if (!empty($request['searchText'])) {
                $filter['name'] = new \MongoDB\BSON\Regex($request['searchText'],'i');
            }
            
            // 미디어 타입 필터
            if (!empty($request['mediaType'])) {
                $filter['mediaType'] = $request['mediaType'];
            }
            
            // 사용 여부 필터
            if (!empty($request['isUse'])) {
                $filter['isUse'] = true;
            }

            // 정렬 및 프로젝션 설정
            $sort = empty($request['sort'])?['insert.date'=>-1]:$request['sort'];
            $projection = empty($request['projection'])?['_id'=>0]:$request['projection'];

            // 전체 개수 조회
            $return['totalCount'] = $this->db->count($this->collection, $filter);
            
            // 페이징 설정
            $noapp = empty($request['noapp'])?20:$request['noapp'];
            $page = empty($request['page'])?1:$request['page'];
            $pageInfo = new Page;
            $return['page'] = $pageInfo->page(20, 5, $return['totalCount'], $page);
            
            // MongoDB 옵션 설정
            $options = [
                'skip' => ($page - 1) * $noapp, 
                'limit' => $noapp, 
                'sort' => $sort, 
                'projection' => $projection
            ];
            
            // 목록 조회
            $return['items'] = $this->db->list($this->collection, $filter, $options);

        } catch(\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }
        return $return;
    }

    /**
     * 상세 조회 (공통)
     * 
     * @param array|null $request 요청 파라미터
     * @return array 상세 데이터
     */
    private function view($request=null){
        $return['item'] = $this->db->item(
            $this->collection, 
            ['idx'=>(int)$_GET['idx']], 
            ['projection'=>['_id'=>0]]
        );
        return $return;
    }

    /**
     * 데이터 등록 (공통)
     * 
     * @param array $request 등록할 데이터
     * @return array 등록 결과
     */
    private function insert($request){
        try {
            $return = [];
            $filter = $request;
            $filter['idx'] = $this->getNextId();

            // 등록 정보 추가
            $filter['insert']['managerId'] = $_SESSION['managerId'];
            $filter['insert']['managerName'] = $_SESSION['managerName'];
            $filter['insert']['date'] = date('Y-m-d H:i:s');            

            $this->db->insert($this->collection, $filter);
            $return['idx'] = $filter['idx'];

        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        return $return;
    }

    /**
     * 데이터 수정 (공통)
     * 
     * @param array $request 수정할 데이터
     * @return array 수정 결과
     */
    private function update($request){
        try {
            $return = [];
            $filter = ['coId'=>$this->coId, 'idx'=>(int)$request['idx']];
            unset($request['idx'], $request['coId']);

            // 수정 정보 추가
            $request['update']['managerId'] = $_SESSION['managerId'];
            $request['update']['managerName'] = $_SESSION['managerName'];
            $request['update']['date'] = date('Y-m-d H:i:s');

            $object['$set'] = $request;

            $return = $this->db->update($this->collection, $filter, $object);
        } catch(\Exception $e) {
            $return['msg'] = $this->common->getExceptionMessage($e);
        }
        return $return;
    }

    /**
     * 다음 ID 생성
     * 현재 컬렉션의 최대 idx 값에 1을 더한 값을 반환
     * 
     * @return int 다음 사용할 idx 값
     */
    private function getNextId(){
        try {
            $return = [];
            $filter = ['coId'=>$this->coId];

            $return = $this->db->list($this->collection, $filter, ['sort'=>['idx'=>-1], 'limit'=>1]);
            $return = $return[0]['idx'] + 1;
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        return $return;
    }

    /**
     * AI 모델 목록 조회 (AJAX 호출용)
     * 
     * @return array JSON 응답 데이터
     */
    public function modelList(){
        try {
            $this->collection = self::AI_MODEL_COLLECTION;
            $filter['coId'] = $this->coId;
            $filter['delete.is'] = ['$ne'=>true];

            if(!empty($_GET['isUse']) && $_GET['isUse'] !== 'all'){
                $filter['isUse'] = $_GET['isUse'];
            }

            if(!empty($_GET['modelType'])){
                $filter['modelType'] = $_GET['modelType'];
            }

            // 검색 조건 추가
            if(!empty($_GET['searchText'])) {
                $filter['name'] = new \MongoDB\BSON\Regex($_GET['searchText'], 'i');
            }
            // AI 모델을 gpt, gemini, claude 순서로 정렬
            // MongoDB의 $expr과 $switch를 사용하여 modelType 또는 name 기준으로 우선순위 부여
            $options = ['sort' => ['company'=>-1, 'sort' => 1, 'insert.date' => 1]];

            $return = $this->db->list($this->collection, $filter, $options);   
        } catch(\Exception $e) {
            $return = [
                'success' => false,
                'msg' => $this->common->getExceptionMessage($e)
            ];
        }
        return $return;
    }

    /**
     * AI 모델 상세 조회 (AJAX 호출용)
     * 
     * @return array JSON 응답 데이터
     */
    public function modelEdit(){
        try {
            if(!empty($_GET['idx'])){
                $this->collection = self::AI_MODEL_COLLECTION;
                $result = $this->view();
                $return = [
                    'success' => true,
                    'data' => $result
                ];
            } else {
                $return = [
                    'success' => false,
                    'msg' => '필수 파라미터가 누락되었습니다.'
                ];
            }
        } catch(\Exception $e) {
            $return = [
                'success' => false,
                'msg' => $this->common->getExceptionMessage($e)
            ];
        }
        return $return;
    }
    
    /**
     * AI 모델 등록 (AJAX 호출용)
     * 
     * @return array JSON 응답 데이터
     */
    public function modelInsert(){
        try {
            $this->collection = self::AI_MODEL_COLLECTION;
            $request = $_POST;
            $request['coId'] = $this->coId;        
            
            unset($request['action']);
            
            $writeResult = $this->insert($request);

            if($writeResult['idx'] !== null){
                $return = [
                    'success' => true,
                    'idx' => $writeResult['idx'],
                    'msg' => '모델이 성공적으로 등록되었습니다.'
                ];
            } else {
                $return = [
                    'success' => false,
                    'msg' => '모델 등록에 실패했습니다.'
                ];
            }
        } catch(\Exception $e) {
            $return = [
                'success' => false,
                'msg' => $this->common->getExceptionMessage($e)
            ];
        }
        return $return;
    }
    
    /**
     * AI 모델 수정 (AJAX 호출용)
     * 
     * @return array JSON 응답 데이터
     */
    public function modelModify(){
        try {
            $this->collection = self::AI_MODEL_COLLECTION;
            $request = $_POST;
            $request['coId'] = $this->coId;
            
            $writeResult = $this->update($request);

            if ($writeResult->getModifiedCount() == 1) {
                $return = [
                    'success' => true,
                    'msg' => '모델이 성공적으로 수정되었습니다.'
                ];
            } else {
                $return = [
                    'success' => false,
                    'msg' => '모델 수정에 실패했습니다.'
                ];
            }
        } catch(\Exception $e) {
            $return = [
                'success' => false,
                'msg' => $this->common->getExceptionMessage($e)
            ];
        }
        return $return;
    }
    
    /**
     * AI 모델 삭제 (AJAX 호출용)
     * 
     * @return array JSON 응답 데이터
     */
    public function modelDelete(){
        try {
            $this->collection = self::AI_MODEL_COLLECTION;
            $request = $_POST;
            $request['coId'] = $this->coId;
            $request['delete']['is'] = true;
            
            $result = $this->update($request);
            
            $return = [
                'success' => true,
                'msg' => '모델이 성공적으로 삭제되었습니다.',
                'data' => $result
            ];
        } catch(\Exception $e) {
            $return = [
                'success' => false,
                'msg' => $this->common->getExceptionMessage($e)
            ];
        }
        
        return $return;
    }

    public function modelSort(){
        try {
            $this->collection = self::AI_MODEL_COLLECTION;
            $request = $_POST;
            $request['coId'] = $this->coId;
            $request['company'] = $request['company'];
            $request['sort'] = json_decode($request['sortData'], true);

            foreach($request['sort'] as $key => $value) {
                $filter['company'] = $request['company'];
                $filter['idx'] = (int)$value['idx'];
                $object['$set']= ['sort'=>(int)$value['sortOrder']];

                $this->db->update($this->collection, $filter, $object);
            }

            $return = [
                'success' => true,
                'msg' => '모델 정렬 순서가 저장되었습니다.'
            ];
            
        } catch(\Exception $e) {
            $return = [
                'success' => false,
                'msg' => $this->common->getExceptionMessage($e)
            ];
        }
        return $return;
    }


}