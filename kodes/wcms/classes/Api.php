<?php
/**
 * API 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 * 
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
namespace Kodes\Wcms;

// error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
// ini_set('display_errors', 1);

class Api{

    /** @var DB MongoDB 연결 객체 */
    protected $db;
    
    /** @var Common 공통 유틸리티 객체 */
    protected $common;
    
    /** @var Category 카테고리 클래스 객체 */
    protected $category;
    
    /** @var string 회사 ID */
    protected $coId;
    
    /** @var string API 데이터 컬렉션명 */
    const COLLECTION = 'apiData';
    
    /**
     * 생성자
     */
    public function __construct()
    {
        $this->db = new DB('apiDB');
        $this->common = new Common();
        $this->coId = 'hkp';
        $this->category = new Category();
    }
    
    /**
     * 공통 JSON 응답 출력 함수
     * 
     * @param bool $success 성공 여부
     * @param mixed $data 응답 데이터
     * @param string $error 에러 메시지 (실패 시)
     * @param float $startTime 시작 시간 (성능 측정용)
     */
    private function outputJsonResponse($success, $data = null, $error = '', $startTime = null)
    {
        $response = [
            'success' => $success,
            'data' => $data
        ];
        
        // 에러 메시지가 있으면 추가
        if (!$success && !empty($error)) {
            $response['error'] = $error;
        }
        
        // 실행 시간 측정 및 추가
        if ($startTime !== null) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2); // 밀리초 단위
            $response['executionTime'] = $executionTime . 'ms';
        }
        
        // CORS 헤더 설정 (크로스 도메인 요청 허용)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400'); // 24시간 동안 preflight 캐시
        
        // JSON 헤더 설정
        header('Content-Type: application/json; charset=utf-8');
        
        // JSON 출력
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * API 데이터 조회 (GET 파라미터 사용)
     * 
     * @return array API 데이터 (코드별로 그룹화)
     */
    public function data()
    {
        $startTime = microtime(true); // 성능 측정 시작
        
        try {
            // GET 파라미터에서 값 추출
            $sid = $_GET['sid'] ?? null;
            $id = $_GET['id'] ?? null;
            $categoryId = $_GET['categoryId'] ?? null;
            $name = $_GET['name'] ?? null;
            $startDate = $_GET['startDate'] ?? '';
            $endDate = $_GET['endDate'] ?? date('Y-m-d');
            $grade = $_GET['grade'] ?? '';
            $market = $_GET['market'] ?? '';
            $sortField = $_GET['sortField'] ?? 'date'; // 기본 정렬 필드: date
            $sortOrder = $_GET['sortOrder'] ?? 'asc';  // 기본 정렬 방향: 오름차순(asc), 내림차순(desc) 가능
            // sortOrder 값을 MongoDB에서 사용하는 1(오름차순), -1(내림차순)으로 변환
            $sortDirection = strtolower($sortOrder) === 'desc' ? -1 : 1;

            if (empty($sid) && empty($name) && empty($categoryId) && empty($id) ) {
                throw new \Exception('sid 또는 name 또는 categoryId 또는 id 파라미터가 필요합니다.');
            }
            if (empty($startDate)) {
                throw new \Exception('date 파라미터가 필요합니다.');
            }
            
            // 날짜 형식 검증
            if (!$this->common->isValidDateFormat($startDate)) {
                throw new \Exception('시작일자의 날짜 형식이 올바르지 않습니다. (YYYY-MM-DD)');
            }

            if ( !empty($endDate) && !$this->common->isValidDateFormat($endDate)) {
                throw new \Exception('종료일자의 날짜 형식이 올바르지 않습니다. (YYYY-MM-DD)');
            }
            
            // MongoDB 쿼리 조건 생성 (해당 일자만)
            $filter = [
                'coId' => $this->coId,
                'sid' => $sid,
            ];

            if( !empty($startDate) && !empty($endDate) ){
                $filter['date'] = ['$gte' => $startDate, '$lte' => $endDate];
            }else if( !empty($startDate) ){
                $filter['date'] = $startDate;
            }else{
                throw new \Exception('startDate 파라미터가 필요합니다.');
            }

            if( !empty($market) ){
                $filter['market'] = $market;
            }
            if( !empty($grade) ){
                $filter['grade'] = $grade;
            }

            // 코드/이름을 배열로 분리            
            if( strpos($sid,',') !== false ){
                $sids = array_map('trim', explode(',', $sid));
                $filter['sid'] = ['$in' => $sids];
            }else if( !empty($name) ){
                unset($filter['sid']);
                $names = array_map('trim', explode(',', $name));
                $filter['itemName'] = ['$in' => $names];
            }

            // id로 찾기
            if( !empty($id) ){
                $filter['id'] = $id;
                unset($filter['sid']);
            }

            if( !empty($categoryId) ){
                unset($filter['sid']);
                unset($filter['itemName']);
                
                if(strpos($categoryId,',') !== false){
                    $categoryIds = array_map('trim', explode(',', $categoryId));
                    $exactMatches = [];
                    $prefixPatterns = [];
                    
                    foreach($categoryIds as $catId){
                        $pattern = $this->buildCategoryIdPattern($catId);
                        
                        // 정확한 매치인지 prefix 검색인지 구분
                        if($this->isExactMatchPattern($pattern)){
                            $exactMatches[] = $catId;
                        } else {
                            $prefixPatterns[] = $pattern;
                        }
                    }
                    
                    // MongoDB 쿼리 최적화: 정확한 매치는 $in 연산자 사용 (인덱스 효율적)
                    // prefix 검색은 $regex 사용하되 단일 패턴으로 통합
                    $orConditions = [];
                    
                    if(!empty($exactMatches)){
                        // $in 연산자는 인덱스를 효율적으로 사용하여 O(log n) 복잡도 달성
                        $orConditions[] = ['categoryId' => ['$in' => $exactMatches]];
                    }
                    
                    if(!empty($prefixPatterns)){
                        // 여러 prefix 패턴을 하나의 정규식으로 통합하여 인덱스 효율성 향상
                        // 예: ^(hkp001.*|hkp002.*)$ 형태로 통합
                        $combinedPattern = $this->combineRegexPatterns($prefixPatterns);
                        $orConditions[] = ['categoryId' => ['$regex' => $combinedPattern, '$options' => '']];
                    }
                    
                    // 조건이 하나뿐이면 $or 없이 직접 적용하여 쿼리 단순화
                    if(count($orConditions) === 1){
                        $filter = array_merge($filter, $orConditions[0]);
                    } else {
                        $filter['$or'] = $orConditions;
                    }
                }else{
                    // 단일 카테고리 ID 처리
                    $pattern = $this->buildCategoryIdPattern($categoryId);
                    
                    // 정확한 매치인 경우 $in 연산자 사용 (더 효율적)
                    if($this->isExactMatchPattern($pattern)){
                        $filter['categoryId'] = $categoryId;
                    } else {
                        $filter['categoryId'] = ['$regex' => $pattern, '$options' => ''];
                    }
                    $filter[$sortField] = ['$nin' => ["", null]];
                }
                if(!empty($sortField) && !empty($sortDirection)){
                    $sortOption = [$sortField => $sortDirection];
                }else{
                    $sortOption = ['date' => -1,'grade' => 1,'market' => 1];
                }
            }else{
                if(!empty($sortField) && !empty($sortDirection)){
                    $sortOption = [$sortField => $sortDirection, 'date' => -1];
                }else{
                    $sortOption = ['date' => -1];
                }    
            }

            // 정렬 옵션 (날짜 오름차순)
            $options = [
                'sort' => $sortOption,
                'projection' => ['_id' => 0,'insert'=>0, 'update'=>0,'coId'=>0,'rid'=>0,'id'=>0,'kind'=>0,'grade'=>0,'market'=>0,'gradeCode'=>0]
            ];

            // 데이터 조회
            $rawData = $this->db->list(self::COLLECTION, $filter, $options);

            // 코드별로 그룹화
            $groupedData = $this->groupDataByCode($rawData);

            $responseData = [
                'data' => $groupedData,
                'meta' => [
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'totalRecords' => count($rawData),
                    'groupCount' => count($groupedData)
                ]
            ];

            if(!empty($codes)){
                $responseData['meta']['codes'] = $codes;
            }else if(!empty($names)){
                $responseData['meta']['names'] = $names;
            }

            return $responseData;
            
        } catch (\Exception $e) {
            $this->outputJsonResponse(false, [], $e->getMessage(), $startTime);
        }
    }
    
    /**
     * API ID에 포함된 데이터 조회
     * 
     * GET 파라미터:
     * - apiId: API ID (필수)
     * - limit: 조회 개수 제한 (선택, 기본값: 25)
     * - page: 페이지 번호 (선택, 기본값: 1)
     * 
     * @return array API ID에 해당하는 데이터 목록
     */
    public function getApiData()
    {
        $startTime = microtime(true); // 성능 측정 시작
        
        try {
            // GET 파라미터에서 값 추출
            $apiId = $_GET['apiId'] ?? null;
            $market = $_GET['market'] ?? null;
            $grade = $_GET['grade'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 25;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            
            // apiId 필수 체크
            if (empty($apiId)) {
                throw new \Exception('apiId 파라미터가 필요합니다.');
            }
                       
            // MongoDB 쿼리 조건 생성
            $filter = [
                'coId' => $this->coId,
                'id' => $apiId
            ];

            if( !empty($market) ){
                $filter['market'] = $market;
            }
            if( !empty($grade) ){
                $filter['grade'] = $grade;
            }

            $options = [
                'sort' => ['date' => -1, 'grade' => 1, 'market' => 1],
                'projection' => ['_id' => 0, 'insert' => 0, 'update' => 0, 'coId' => 0,'id'=>0, 'rid'=>0, 'gradeCode'=>0, 'marketCode'=>0, 'categoryId'=>0, 'gradeCode'=>0],
                'limit' => $limit,
                'skip' => ($page - 1) * $limit
            ];

            $totalCount = $this->db->count(self::COLLECTION, $filter);

            // 데이터 조회
            $data = $this->db->list(self::COLLECTION, $filter, $options);

            // 응답 데이터 구성
            $responseData = [
                'apiId' => $apiId,
                'data' => $data,
                'meta' => [
                    'totalCount' => $totalCount,
                    'page' => $page,
                    'limit' => $limit,
                    'haveMarket' => $this->haveMarket($apiId),
                    'haveGrade' => $this->haveGrade($apiId)
                ]
            ];
            
            $this->outputJsonResponse(true, $responseData, '', $startTime);
            
        } catch (\Exception $e) {
            $this->outputJsonResponse(false, [], $e->getMessage(), $startTime);
        }
    }

    function haveMarket($apiId)
    {
        // apiId의 데이터를 확인해 market 을 group by 해서 중복되지 않은 마켓 정보를 나오도록 해줘
        try {
            if (empty($apiId)) {
                throw new \Exception('apiId 파라미터가 필요합니다.');
            }

            // MongoDB aggregate 파이프라인 사용하여 market 필드로 distinct/group by
            $pipeline = [
                ['$match' => ['coId' => $this->coId, 'id' => $apiId]],
                ['$group' => ['_id' => '$market']],
                ['$project' => ['_id' => 0, 'market' => '$_id']]
            ];

            // DB 클래스의 command 메서드 사용
            $result = $this->db->command([
                'aggregate' => self::COLLECTION,
                'pipeline' => $pipeline
            ]);

            $markets = [];
            foreach ($result as $doc) {
                if (isset($doc['market'])) {
                    $markets[] = $doc['market'];
                }
            }

            return $markets;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function haveGrade($apiId){
        try {
            if (empty($apiId)) {
                throw new \Exception('apiId 파라미터가 필요합니다.');
            }

            // MongoDB aggregate 파이프라인 사용하여 grade 필드로 distinct/group by
            $pipeline = [
                ['$match' => ['coId' => $this->coId, 'id' => $apiId]],
                ['$group' => ['_id' => '$grade']],
                ['$project' => ['_id' => 0, 'grade' => '$_id']]
            ];

            // DB 클래스의 command 메서드 사용
            $result = $this->db->command([
                'aggregate' => self::COLLECTION,
                'pipeline' => $pipeline
            ]);

            $grades = [];
            foreach ($result as $doc) {
                if (isset($doc['grade'])) {
                    $grades[] = $doc['grade'];
                }
            }

            return $grades;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function list()
    {
        $startTime = microtime(true); // 성능 측정 시작
        
        try {
            $apis = new Apis();
            $apiList = $apis->list();
            
            // 보안상 민감한 필드 제거
            if (!empty($apiList['items'])) {
                $apiList['items'] = $this->sanitizeApiList($apiList['items']);
            }
            
            $this->outputJsonResponse(true, $apiList, '', $startTime);
            
        } catch (\Exception $e) {
            $this->outputJsonResponse(false, [], $e->getMessage(), $startTime);
        }
    }

    /**
     * API 목록에서 보안상 민감한 필드들을 제거
     * 
     * @param array $apiList 원본 API 목록
     * @return array 정제된 API 목록
     */
    private function sanitizeApiList($apiList)
    {
        $sanitizedList = [];
        
        foreach ($apiList as $api) {
            // 공개 API 목록에서 필요한 필드만 유지
            $sanitizedApi = [
                'id' => $api['id'] ?? '',
                'title' => $api['title'] ?? '',
                'provider' => $api['provider'] ?? '',
                'returnType' => $api['returnType'] ?? '',
                'categoryName' => $api['categoryName'] ?? '',
                'categoryId' => $api['categoryId'] ?? '',
                'isUse' => $api['isUse'] ?? '',
                'isChartUse' => $api['isChartUse'] ?? '',
                'unit' => $api['unit'] ?? '',
                'aliases' => $api['aliases'] ?? '',
                'description' => $api['description'] ?? '',
                'latestDataDate' => $api['latestDataDate'] ?? ''
            ];
            
            $sanitizedList[] = $sanitizedApi;
        }
        
        return $sanitizedList;
    }

    /**
     * 데이터를 코드별로 그룹화
     * 
     * @param array $rawData 원본 데이터
     * @return array 그룹화된 데이터
     */
    private function groupDataByCode($rawData)
    {
        $groupedData = [];
  
        foreach ($rawData as $item) {
            $sid = isset($item['sid']) ? $item['sid'] : '';
            $name = isset($item['name']) ? $item['name'] : '';
            
            // 코드별 그룹 초기화
            if (!isset($groupedData[$sid])) {
                $groupedData[$sid] = [
                    'sid' => $sid,
                    'name' => $name,
                    'categoryId' => isset($item['categoryId']) ? $item['categoryId'] : '',
                    'categoryHierarchy' => $this->category->getCategoryHierarchyString($item['categoryId']),
                    'categoryName'=>'',
                    'data' => []
                ];

                $re = '/([>][ ])([^>]+$)/m';
                preg_match_all($re, $groupedData[$sid]['categoryHierarchy'], $matches, PREG_SET_ORDER, 0);
                $groupedData[$sid]['categoryName'] = $matches[0][2];
            }

            // 데이터 추가 (필요한 필드만 추출)
            $groupedData[$sid]['data'][] = $item;
        }
        
        // 배열 인덱스를 숫자로 변환
        $result = array_values($groupedData);
        
        // 최대 100개까지만 반환
        return array_slice($result, 0, 10);
    }
    
    
    /**
     * 사용 가능한 API 목록 조회
     * 
     * @return array API 목록
     */
    public function getAvailableApis()
    {
        try {
            // 고유한 API ID 목록 조회
            $pipeline = [
                ['$group' => [
                    '_id' => '$id',
                    'name' => ['$first' => '$name'],
                    'coId' => ['$first' => '$coId'],
                    'lastUpdate' => ['$max' => '$date']
                ]],
                ['$sort' => ['_id' => 1]]
            ];
            
            $result = $this->db->command([
                'aggregate' => self::COLLECTION, 
                'pipeline' => $pipeline
            ]);
            
            return [
                'success' => true,
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    /**
     * 특정 API의 사용 가능한 코드 목록 조회
     * 
     * @param string $id API ID
     * @return array 코드 목록
     */
    public function getApiItems()
    {
        try {
            // MongoDB Aggregation Pipeline을 사용하여 특정 API의 코드 목록과 부가 정보를 조회합니다.
            // 최신 데이터 기준으로 name, salesUnit, kind, date 정보를 함께 반환합니다.
            $pipeline = [
                ['$match' => ['coId' => $this->coId,'date'=>['$lte'=>date('Y-m-d'),'$gte'=>date('Y-m-d',strtotime('first day of January last year'))]]],
                ['$sort' => ['sid' => 1, 'date' => -1]], // code 오름차순, date 내림차순(최신 우선)
                ['$group' => [
                    '_id' => '$sid',
                    'itemName' => ['$first' => '$name'],
                    'categoryId' => ['$first' => '$categoryId'],
                    'salesUnit' => ['$first' => '$unit'],
                    'rank' => ['$first' => '$grade'],
                    'marketName' => ['$first' => '$market'],
                    'lastDate' => ['$first' => '$date'],
                ]],                    
                ['$project' => [
                    '_id' => 0,
                    'sid' => '$_id',
                    'itemName' => 1,
                    'categoryId' => 1,
                    'salesUnit' => 1,
                    'rank' => 1,
                    'marketName' => 1,
                    'lastDate' => 1,
                ]],
                ['$sort' => ['itemName' => 1, 'categoryId' => 1, 'rankCode' => 1]]
            ];
            
            $result = $this->db->command([
                'aggregate' => self::COLLECTION, 
                'pipeline' => $pipeline
            ]);
            

            // 카테고리 히어라키 정보 추가
            foreach ($result as $key => $item) {
                $item['categoryHierarchy'] = $this->category->getCategoryHierarchyString($item['categoryId']);
                $result[$key]["categoryHierarchy"] = $item['categoryHierarchy'];
            }

            return [
                'success' => true,
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }
    
    public function categoryItem(){
        $startTime = microtime(true);
        try{
            $categoryId = $_GET['categoryId'] ?? '';
            $result = $this->itemList($categoryId);
            $this->outputJsonResponse(true, $result, '', $startTime);
        } catch (\Exception $e) {
            $this->outputJsonResponse(false, [], $e->getMessage(), $startTime);
        }
    }


    /**
     * 카테고리 API
     * 
     * GET 파라미터:
     * - keyword: 검색 키워드 (있으면 키워드 검색 수행)
     * - categoryId: 카테고리 ID (있으면 해당 카테고리의 하위 분류 조회)
     * 
     * @return array 카테고리 정보
     */
    public function category(){
        $startTime = microtime(true); // 성능 측정 시작
        
        try {
            $category = new Category();
            
            // GET 파라미터 확인 및 보안 검증
            $keyword = $this->common->sanitizeInput($_GET['keyword'] ?? '', 50);
            $categoryId = $this->common->sanitizeInput($_GET['id'] ?? '');

            // 키워드 검색 처리
            if (!empty($keyword)) {
                // 키워드 검색: 검색어를 포함한 카테고리, 품종에 대한 리스트 정보 전달
                $result = $category->searchByKeyword($keyword)['data'];
                foreach($result as $key => $item){
                    // depth가 4이거나, 하위 카테고리가 없는 최하위 카테고리인 경우 item 리스트 포함
                    if($item['depth'] == 4 || !$this->hasChildren($item['id'])){
                        $_GET['returnType']="array";
                        $_GET['categoryId']=$item['id'];
                        $result[$key]["items"] = $this->itemList();

                    }
                }
                $this->outputJsonResponse(true, $result, '', $startTime);
            } elseif (!empty($categoryId)) {
                // 카테고리 ID 처리 및 보안 검증
                $categoryId = $this->common->processCategoryId($categoryId);
                
                // 카테고리별 하위 분류: 특정 카테고리의 조상 그룹 및 자식 정보 호출
                $result = $category->getHierarchy($categoryId)['data'];

                $_GET['categoryId'] = $categoryId;
                $categoryId = preg_replace("/000{1,}$/", "", $categoryId);
                if(strlen($categoryId) === 15){
                    $result = $this->itemList();
                }

                $this->outputJsonResponse(true, $result, '', $startTime);
                
            } else {
                // 기본: 1depth 카테고리만 조회
                $result = $category->getFirstDepth()['data'];
                $this->outputJsonResponse(true, $result, '', $startTime);
            }

            
            if (!empty($categoryId) && strlen($categoryId) === 15) {
                // 4depth 카테고리의 품목 리스트 구하기
               
                // outputJsonResponse로 반환한 $result는 배열(카테고리 정보)임. meta에 sidList를 추가해서 응답해야 함.
                // 이 코드는 outputJsonResponse 전에 넣어야 함. (위 try블럭 내에서 적절히 위치 조정 필요)
                if (is_array($result)) {
                    if (!isset($result['meta'])) { $result['meta'] = []; }
                    $result['meta']['sidList'] = $itemsResult['sidList'] ?? [];
                }
            }
            
        } catch (\Exception $e) {
            $this->outputJsonResponse(false, [], $e->getMessage(), $startTime);
        }
    }

    /**
     * SID 기반 품목 리스트 조회
     * 
     * @return array 품목 리스트 (SID별 그룹화)
     */
    public function itemList()
    {
        $startTime = microtime(true);
        
        try {
            // GET 파라미터에서 값 추출
            $searchKeyword = $_GET['search'] ?? '';
            $categoryId = $_GET['categoryId'] ?? '';
            
            // MongoDB Aggregation Pipeline을 사용하여 SID별 품목 리스트 조회
            $pipeline = [
                // 기본 필터링
                ['$match' => ['coId' => $this->coId]],
                
                // 최신 데이터 기준으로 그룹화
                ['$sort' => ['sid' => 1, 'date' => -1]],
                ['$group' => [
                    '_id' => '$sid',
                    'sid' => ['$first' => '$sid'],
                    'name' => ['$first' => '$name'],
                    'categoryId' => ['$first' => '$categoryId'],
                    'unit' => ['$first' => '$unit'],
                    'grade' => ['$first' => '$grade'],
                    'market' => ['$first' => '$market'],
                    'latestDate' => ['$first' => '$date'],
                    'latestPrice' => ['$first' => '$price'],
                    'dataCount' => ['$sum' => 1]
                ]],
                ['$project' => [
                    '_id' => 0,
                    'sid' => '$_id',
                    'name' => 1,
                    'categoryId' => 1,
                    'unit' => 1,
                    'grade' => 1,
                    'market' => 1,
                    'latestDate' => 1,
                    'latestPrice' => 1,
                    'dataCount' => 1
                ]],
                // 정렬
                ['$sort' => ['name' => 1, 'grade' => 1, 'market' => 1]]
            ];
            
            // 검색 키워드가 있으면 추가 필터링
            if (!empty($searchKeyword)) {
                $pipeline[0]['$match']['$or'] = [
                    ['sid' => ['$regex' => $searchKeyword, '$options' => 'i']],
                    ['itemName' => ['$regex' => $searchKeyword, '$options' => 'i']]
                ];
            }
            
            // 카테고리 ID가 있으면 추가 필터링
            if (!empty($categoryId)) {
                $pipeline[0]['$match']['categoryId'] = $categoryId;
            }
            
            $result = $this->db->command([
                'aggregate' => self::COLLECTION, 
                'pipeline' => $pipeline
            ]);
            
            // 카테고리 히어라키 정보 추가
            $items = [];
            foreach ($result as $item) {
                $item['categoryHierarchy'] = $this->category->getCategoryHierarchyString($item['categoryId']);
                $items[] = $item;
            }

            if($_GET['returnType']=="array"){
                return $items;
            }

            $this->outputJsonResponse(true, [   
                'items' => $items,
                'totalCount' => count($items),
                'searchKeyword' => $searchKeyword,
                'categoryId' => $categoryId
            ], '', $startTime);
            
        } catch (\Exception $e) {
            $this->outputJsonResponse(false, [], $e->getMessage(), $startTime);
        }
    }
    
    /**
     * 카테고리 히어라키 조회
     * 
     * @param string $categoryId 카테고리 ID
     * @return string 카테고리 히어라키 문자열
     */
    private function getCategoryHierarchy($categoryId)
    {
        try {
            if (empty($categoryId)) {
                return '미분류';
            }
            
            $hierarchy = $this->category->getHierarchy($categoryId);
            
            if (isset($hierarchy['data']) && !empty($hierarchy['data'])) {
                $pathParts = [];
                foreach ($hierarchy['data'] as $cat) {
                    $pathParts[] = $cat['name'];
                }
                return implode(' > ', $pathParts);
            }
            
            return '미분류';
            
        } catch (\Exception $e) {
            return '미분류';
        }
    }

    public function ridInfo(){
        $rid = new HkApiId();
        $result = $rid->parseRid($_GET['rid']);
        print_r($result);
        return [
            'success' => true,
            'data' => $result
        ];
    }

    /**
     * categoryId를 3자리씩 분리하여 정규식 패턴 생성
     * 
     * @param string $categoryId 카테고리 ID
     * @return string 정규식 패턴
     * 
     * 예시:
     * - hkp001000000000 → ^hkp001.*
     * - hkp001001000000 → ^hkp001001.*
     * - hkp001002001000 → ^hkp001002001.*
     * - hkp001002001001 → ^hkp001002001001$
     */
    private function buildCategoryIdPattern($categoryId)
    {
        // categoryId가 비어있으면 빈 패턴 반환
        if (empty($categoryId)) {
            return '^.*$';
        }
        
        // 3자리씩 분리
        $chunks = [];
        $remaining = $categoryId;
        
        while (strlen($remaining) >= 3) {
            $chunk = substr($remaining, 0, 3);
            $chunks[] = $chunk;
            $remaining = substr($remaining, 3);
        }
        
        // 마지막에 3자리 미만의 남은 부분이 있으면 추가
        if (!empty($remaining)) {
            $chunks[] = $remaining;
        }
        
        // 패턴 생성
        $pattern = '^';
        $hasNonZeroChunk = false;
        
        foreach ($chunks as $index => $chunk) {
            // 0으로만 구성된 청크인지 확인
            if ($chunk === '000') {
                if (!$hasNonZeroChunk) {
                    // 첫 번째 청크가 000이면 건너뛰기
                    continue;
                } else {
                    // 중간에 000이 나오면 여기서 패턴 종료
                    $pattern .= '.*';
                    break;
                }
            } else {
                $hasNonZeroChunk = true;
                $pattern .= preg_quote($chunk, '/');
                
                // 마지막 청크가 아니면 계속
                if ($index < count($chunks) - 1) {
                    continue;
                }
            }
        }        
        return $pattern;
    }

    /**
     * 정규식 패턴이 정확한 매치인지 확인
     * 
     * @param string $pattern 정규식 패턴
     * @return bool 정확한 매치 여부
     */
    private function isExactMatchPattern($pattern)
    {
        // 패턴이 ^로 시작하고 $로 끝나며 .*가 없으면 정확한 매치
        return preg_match('/^\^[^.*]+\$$/', $pattern) === 1;
    }

    /**
     * 여러 정규식 패턴을 하나로 통합하여 MongoDB 인덱스 효율성 향상
     * 
     * @param array $patterns 정규식 패턴 배열
     * @return string 통합된 정규식 패턴
     */
    private function combineRegexPatterns($patterns)
    {
        if (empty($patterns)) {
            return '^.*$';
        }
        
        if (count($patterns) === 1) {
            return $patterns[0];
        }
        
        // 각 패턴에서 ^와 $ 제거하고 OR 연산으로 결합
        $cleanedPatterns = [];
        foreach ($patterns as $pattern) {
            // ^와 $ 제거
            $cleaned = preg_replace('/^\^|\$$/', '', $pattern);
            $cleanedPatterns[] = $cleaned;
        }
        
        // OR 연산으로 결합: ^(pattern1|pattern2|pattern3)$
        return '^(' . implode('|', $cleanedPatterns) . ')$';
    }

    /**
     * API 문서 생성
     * 
     * @return array API 문서 정보
     */
    public function document()
    {
        $apis = new Apis();
        $apiList = $apis->list();

        $apiCodes = $this->getApiItems();
        $_GET['id'] = $apiList['items'][0]['id'];
        $apiResponse = $apis->editor()['item']['items'];

        // $apiResponse 배열에 각 필드를 push하여 응답 필드 정보를 추가합니다.
        $apiResponse[] = ['field' => 'prevDayPrice', 'remark' => '하루전 데이터','addField'=>1];
        $apiResponse[] = ['field' => 'oneWeekAgoPrice', 'remark' => '일주일전 데이터','addField'=>1];
        $apiResponse[] = ['field' => 'oneMonthAgoPrice', 'remark' => '한달전 데이터','addField'=>1];
        $apiResponse[] = ['field' => 'threeMonthsAgoPrice', 'remark' => '3개월전 데이터','addField'=>1];
        $apiResponse[] = ['field' => 'sixMonthsAgoPrice', 'remark' => '6개월전 데이터','addField'=>1];
        $apiResponse[] = ['field' => 'oneYearAgoPrice', 'remark' => '1년전 데이터','addField'=>1];
        $apiResponse[] = ['field' => 'prevDayChange', 'remark' => '하루전 변동률','addField'=>1];
        $apiResponse[] = ['field' => 'oneWeekAgoChange', 'remark' => '일주일전 변동률','addField'=>1];
        $apiResponse[] = ['field' => 'oneMonthAgoChange', 'remark' => '한달전 변동률','addField'=>1];
        $apiResponse[] = ['field' => 'threeMonthsAgoChange', 'remark' => '3개월전 변동률','addField'=>1];
        $apiResponse[] = ['field' => 'sixMonthsAgoChange', 'remark' => '6개월전 변동률','addField'=>1];
        $apiResponse[] = ['field' => 'oneYearAgoChange', 'remark' => '1년전 변동률','addField'=>1];


        // 현재 접속한 도메인 자동 감지
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'hkprice.kod.es';
        $baseUrl = $protocol . '://' . $host . '/api/';
        
        return [
            'api' => [
                'name' => 'Hankyung Price API',
                'version' => '1.0',
                'description' => '한경닷컴 수집 데이터 조회 API - 농산물 가격, 경제 지표 등 다양한 데이터 제공',
                'baseUrl' => $baseUrl,
                'endpoints' => [
                    [
                        'name' => '데이터 조회',
                        'url' => '/api/data?sid={SID}, /api/data?name={ITEM_NAME}, /api/data?categoryId={CATEGORY_ID}',
                        'method' => 'GET',
                        'description' => '지정된 조건에 맞는 API 데이터를 조회합니다. 농산물 가격, 경제 지표 등 다양한 데이터를 제공합니다.',
                        'parameters' => [
                            [
                                'name' => 'sid',
                                'type' => 'string',
                                'required' => true,
                                'description' => 'API SID (예: 6YNEERP92FQ3Y(배추), 60HA4E3E7SSDG(김))',
                                'example' => '0JTG435STSZRG,6TYGD24T4N4D1'
                            ],
                            [
                                'name' => 'name',
                                'type' => 'string',
                                'required' => false,
                                'description' => '품목명 (쉼표로 구분된 복수개)',
                                'example' => '배추,상추...'
                            ],
                            [
                                'name' => 'categoryId',
                                'type' => 'string',
                                'required' => false,
                                'description' => '카테고리 ID',
                                'example' => 'hkp001002000000,hkp001002001002'
                            ],
                            [
                                'name' => 'startDate',
                                'type' => 'string',
                                'required' => true,
                                'description' => '시작 날짜 (YYYY-MM-DD)',
                                'example' => '2020-09-01'
                            ],
                            [
                                'name' => 'endDate',
                                'type' => 'string',
                                'required' => false,
                                'description' => '종료 날짜 (YYYY-MM-DD)',
                                'example' => '2025-09-17'
                            ],
                            [
                                'name' => 'sortField',
                                'type' => 'string',
                                'required' => false,
                                'description' => '정렬 필드 (예: date, sid, name, prevDayChange, oneWeekAgoChange, prevDayPrice, oneWeekAgoPrice...)',
                                'example' => 'date'
                            ],
                            [
                                'name' => 'sortOrder',
                                'type' => 'string',
                                'required' => false,
                                'description' => '정렬 방향 (예: asc, desc)',
                                'example' => 'asc'
                            ]
                        ],
                        'response' => $apiResponse,
                        'examples' => [
                            [
                                'title' => '해당일 배추 가격 조회',
                                'url' => $baseUrl . 'data?sid=6YNEERP92FQ3Y&startDate=2024-09-01',
                                'description' => '배추의 2024년 9월 1일 부터 2025년 9월까지의 가격 데이터를 조회합니다.'
                            ],
                            [
                                'title' => '특정 카테고리의 농산물 가격 조회',
                                'url' => $baseUrl . 'data?categoryId=hkp001002000000&startDate=2024-09-01',
                                'description' => 'hkp001002000000 카테고리의 2024년 9월 1일 부터 2025년 9월까지의 가격 데이터를 조회합니다.'
                            ],
                            [
                                'title' => '특정 기간 여러 농산물 가격 조회',
                                'url' => $baseUrl . 'data?sid=dailyAgriPrice&code=211,212&startDate=2024-01-01&endDate=2024-12-31',
                                'description' => '배추와 상추의 2024년 전체 가격 데이터를 조회합니다.'
                            ],
                            [
                                'title' => '특정 날짜의 전일 등략률로 소트',
                                'url' => $baseUrl . 'data?sid=dailyAgriPrice&code=211,212,213,214,215,222,223,224,225,232,241,242,243&startDate=2025-09-26&sortField=prevDayChange&sortOrder=desc',
                                'description' => '조회된 코드들을 전일 등략률로 소트합니다.'
                            ],

                        ]
                    ],
                    [
                        'name' => 'API 목록 조회',
                        'url' => '/api/list',
                        'method' => 'GET',
                        'description' => '사용 가능한 API 목록을 조회합니다.',
                        'parameters' => [
                            [
                                'name' => 'id',
                                'type' => 'string',
                                'required' => false,
                                'description' => '특정 API ID (지정하면 해당 API의 코드 목록 조회)',
                                'example' => 'dailyAgriPrice'
                            ]
                        ],
                        'response' => '{
                        "success": true,
                        "data": {
                            "totalCount": 1,
                            "page": {...},
                            "items": [
                            {
                                "id": "dailyAgriPrice",
                                "title": "일일 농산물 가격 정보",
                                "provider": "한국농수산식품유통공사",
                                "returnType": "JSON",
                                "categoryName": "농산물",
                                "isUse": "Y",
                                "unit": "원",
                                "description": "농산물의 일일 가격 정보를 제공합니다.",
                                "latestDataDate": "2025-09-19"
                            }
                            ]
                        },
                        "executionTime": "45.23ms"
                        }',
                        'examples' => [
                            [
                                'title' => 'API 목록 조회',
                                'url' => $baseUrl . 'list',
                                'description' => '사용 가능한 모든 API 목록을 조회합니다.'
                            ],
                            [
                                'title' => '특정 API의 코드 목록 조회',
                                'url' => $baseUrl . 'list?id=dailyAgriPrice',
                                'description' => 'dailyAgriPrice API의 사용 가능한 농산물 목록을 조회합니다.'
                            ]
                        ]
                    ],
                    [
                        'name' => '품목 리스트 조회',
                        'url' => '/api/itemList',
                        'method' => 'GET',
                        'description' => 'SID별로 그룹화된 품목 리스트를 조회합니다. 카테고리 히어라키와 함께 표시됩니다.',
                        'parameters' => [
                            [
                                'name' => 'search',
                                'type' => 'string',
                                'required' => false,
                                'description' => '검색 키워드 (SID 또는 품목명으로 검색)',
                                'example' => '느타리버섯'
                            ],
                            [
                                'name' => 'categoryId',
                                'type' => 'string',
                                'required' => false,
                                'description' => '카테고리 ID (특정 카테고리의 품목만 조회)',
                                'example' => 'hkp001001001001'
                            ]
                        ],
                        'response' => '{
                            "success": true,
                            "data": {
                                "items": [
                                    {
                                        "sid": "0JTG435STSZRG",
                                        "itemName": "느타리버섯",
                                        "categoryId": "hkp001001001001",
                                        "categoryName": "버섯류",
                                        "categoryHierarchy": "농수축산물 > 식량작물 > 버섯류 > 느타리버섯",
                                        "unit": "kg",
                                        "latestDate": "2024-09-26",
                                        "latestPrice": 8500,
                                        "dataCount": 1250
                                    }
                                ],
                                "totalCount": 1,
                                "searchKeyword": "느타리버섯",
                                "categoryId": ""
                            },
                            "executionTime": "15.23ms"
                        }',
                        'examples' => [
                            [
                                'title' => '전체 품목 리스트 조회',
                                'url' => $baseUrl . 'itemList',
                                'description' => '모든 품목의 SID별 리스트를 조회합니다.'
                            ],
                            [
                                'title' => '품목명으로 검색',
                                'url' => $baseUrl . 'itemList?search=버섯',
                                'description' => '품목명에 "버섯"이 포함된 모든 품목을 조회합니다.'
                            ],
                            [
                                'title' => 'SID로 검색',
                                'url' => $baseUrl . 'itemList?search=0JTG435STSZRG',
                                'description' => '특정 SID의 품목 정보를 조회합니다.'
                            ],
                            [
                                'title' => '카테고리별 품목 조회',
                                'url' => $baseUrl . 'itemList?categoryId=hkp001001001001',
                                'description' => '특정 카테고리의 품목들만 조회합니다.'
                            ]
                        ]
                    ],
                    [
                        'name' => '카테고리 조회',
                        'url' => '/api/category',
                        'method' => 'GET',
                        'description' => '카테고리 정보를 조회합니다. 키워드 검색, 특정 카테고리 하위 분류, 전체 카테고리 목록을 지원합니다. 4depth인경우 포함된 품목의 sid 목록 제공',
                        'parameters' => [
                            [
                                'name' => 'keyword',
                                'type' => 'string',
                                'required' => false,
                                'description' => '검색 키워드 (있으면 키워드 검색 수행)',
                                'example' => '산'
                            ],
                            [
                                'name' => 'id',
                                'type' => 'string',
                                'required' => false,
                                'description' => '카테고리 ID (있으면 해당 카테고리의 하위 분류 조회)',
                                'example' => 'hkp001000000000'
                            ]
                        ],
                        'response' => '{
                            "success": true,
                            "data": {
                                "success": true,
                                "data": [
                                {
                                    "id": "hkp001000000000",
                                    "name": "농산물",
                                    "depth": 1,
                                    "parentId": "0",
                                    "path": "농산물",
                                    "isParent": true,
                                    "displayText": "농산물"
                                },
                                {
                                    "id": "hkp001001000000",
                                    "name": "채소류",
                                    "depth": 2,
                                    "parentId": "hkp001000000000",
                                    "path": "농산물 > 채소류",
                                    "isParent": false,
                                    "displayText": "농산물 > 채소류"
                                }
                                ],
                                "meta": {
                                "keyword": "산",
                                "totalResults": 17,
                                "description": "검색어를 포함한 카테고리, 품종에 대한 리스트 정보"
                                }
                            },
                            "executionTime": "1.37ms"
                            }',
                        'examples' => [
                            [
                                'title' => '1depth 카테고리 목록 조회',
                                'url' => $baseUrl . 'category',
                                'description' => '1depth 카테고리와 그 하위 분류들을 모두 조회합니다.'
                            ],
                            [
                                'title' => '특정 카테고리 하위 분류 조회 (실제 예제)',
                                'url' => $baseUrl . 'category?id=hkp001000000000',
                                'description' => '농산물 카테고리의 하위 분류들을 조회합니다.'
                            ],
                            [
                                'title' => '4depth 카테고리 하위 분류 조회 (실제 예제)',
                                'url' => $baseUrl . 'category?id=hkp001002001001',
                                'description' => '농산물 카테고리의 하위 분류들을 조회합니다.'
                            ],
                            [
                                'title' => '카테고리 키워드 검색 (실제 예제)',
                                'url' => $baseUrl . 'category?keyword=산',
                                'description' => '"산"이 포함된 모든 카테고리와 품종을 계층적으로 검색합니다.'
                            ]
                        ],
                    ]
                ],
                'dataTypes' => [
                    [
                        'type' => 'dailyAgriPrice',
                        'name' => '일일농산물가격정보',
                        'description' => '농산물의 일일 가격 정보를 제공합니다.',
                        'fields' => [
                            'name' => '농산물명 (예: 배추, 상추, 무, 양파 등)',
                            'date' => '날짜 (YYYY-MM-DD)',
                            'value' => '가격 (원)',
                            'data' => '가격 데이터'
                        ]
                    ],
                ],
                'apiList' => $apiList,
                'apiCodes' => $apiCodes,
                'errorCodes' => [
                    [
                        'code' => 400,
                        'message' => 'Bad Request',
                        'description' => '필수 파라미터가 누락되었거나 형식이 올바르지 않습니다.'
                    ],
                    [
                        'code' => 404,
                        'message' => 'Not Found',
                        'description' => '요청한 API나 데이터를 찾을 수 없습니다.'
                    ],
                    [
                        'code' => 500,
                        'message' => 'Internal Server Error',
                        'description' => '서버 내부 오류가 발생했습니다.'
                    ]
                ],
                'authentication' => [
                    'type' => 'None',
                    'description' => '현재 인증이 필요하지 않습니다.'
                ]
            ]
        ];
    }
    
    /**
     * 특정 카테고리가 하위 카테고리를 가지고 있는지 확인
     * 
     * @param string $categoryId 카테고리 ID
     * @return bool 하위 카테고리 존재 여부
     */
    private function hasChildren($categoryId)
    {
        try {
            // parentId가 해당 카테고리 ID인 카테고리가 있는지 확인
            $filter = [
                'coId' => $this->coId,
                'parentId' => $categoryId,
                'isUse' => true
            ];
            
            $count = $this->db->count('category', $filter);
            
            return $count > 0;
        } catch (\Exception $e) {
            // 오류 발생 시 안전하게 false 반환
            return false;
        }
    }
}
