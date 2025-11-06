<?php 
/**
 * API 관리
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
namespace Kodes\Wcms;

use MongoDB\BSON\Regex;
use Kodes\Wcms\AIManager;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 1);

class Apis
{
    /** @var String Collection Name */
    protected const COLLECTION = "api";
    protected const MakedList_COLLECTION = "makedList";
    protected const CATEGORY_COLLECTION = "category";
    protected const PROVIDER_COLLECTION = "apiProvider"; // API 제공 업체 컬렉션 추가

    /** @var Class */
    protected $db;
    protected $apiDb;
    protected $common;
    protected $json;
    protected $coId;

    /**
     * 생성자
     */
    public function __construct()
    {
        // class
        $this->db = new DB("wcmsDB");
        $this->apiDb = new DB("apiDB");
        $this->common = new Common();
        $this->json = new Json();
		
        // variable
        $this->coId = 'hkp';
	}

    /**
    * Api Collection의 검색조건에 맞는 Row의 갯수를 반환
    * @return Array Api Collection, Page
    */
    public function list()
    {
		try {
            $data = [];
            $i = 0;
            $filter=[];
            
            if(isset($_GET['searchText']) && $_GET['searchText']!=''){
                $filter['title']=new Regex($_GET['searchText'],'i');
            }

            $filter['coId'] = $this->coId;
            
            //  전체 게시물 숫자
            $data["totalCount"] = $this->db->count(self::COLLECTION, $filter);
            
            $noapp = (isset($_GET['noapp']) && $_GET['noapp']!=""?$_GET['noapp']:50);
            $page = (isset($_GET["page"]) && $_GET["page"]!=""?$_GET["page"]:1);
            $pageInfo = new Page;
            $data['page'] = $pageInfo->page($noapp, 10,$data["totalCount"], $page);
            
            $options = ["skip" => ($page - 1) * $noapp, "limit" => $noapp, 'sort' => ['insert.date' => -1]];
            
            $data['items'] = $this->db->list(self::COLLECTION, $filter,$options);
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
		
        return $data;
    }

	public function editor()
    {
        $data = [];
		try {
            $aiModel = new AiSetting();
            $_GET['isUse'] = "Y";
            $_GET['modelType'] = "text";
            $data['aiModel'] = $aiModel->modelList();

            if (!empty($_GET['id'])) {
                $filter=['id'=>$_GET['id']];
                $options=[];
                $data['item'] = $this->db->item(self::COLLECTION, $filter, $options);

                // schedule 필드가 문자열인 경우에만 json_decode 실행
                if (isset($data['item']['schedule']) && is_array($data['item']['schedule'])) {
                    $data['item']['scheduleText'] = json_encode($data['item']['schedule']);
                } else {
                    $data['item']['scheduleText'] = [];
                }
            }
            $data['providerList'] = $this->providerList();
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

		return $data;
	}

	public function insert()
    {
		try {
            $item = $this->covertDataField($_POST, 'insert');
            $item['coId'] = $this->coId;
            $result = $this->db->insert(self::COLLECTION, $item);
			$data = $result->getInsertedCount();
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
		return $data;
	}

	public function modify()
    {
		try {
            $item = $this->covertDataField($_POST, 'update');
            $filter = ['coId'=>$this->coId,'id'=>$item['id']];
            $options = ['$set'=>$item];
            $result = $this->db->update(self::COLLECTION, $filter, $options);
			$data = $result->getModifiedCount();
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
		return $data;
	}

	public function apiLastItemUpdate(){
		$_POST['lastItemUpdate']=date("Y-m-d H:i:s");

		$filter=['id'=>$_POST['id']];
		$result = $this->db->update(self::COLLECTION, $filter, ['$set'=>$_POST]);
	}

    public function covertDataField($data, $action){
        foreach($data['tag'] as $key => $val){
            if(!empty($val) || !empty($data['field'][$key])){
                $data['items'][]=['tag'=>$val,'field'=>$data['field'][$key],'keyField'=>in_array($key, $data['keyField'])?"1":"0",'value'=>$data['value'][$key],'condition'=>$data['condition'][$key],'remark'=>$data['remark'][$key]];
            }
        }
        
        // schedule 배열 처리
        if(isset($data['schedule']) && is_array($data['schedule'])){
            $data['schedule'] = $data['schedule'];
        }
        
        $data = $this->common->covertDataField($data, $action, ['tag','field','keyField','value','condition','remark']);
        return $data;
    }

    public function getLastApiData(){
        
        try{
            $collection = $_GET['collection'] ?? '';

            if(empty($collection)){
                throw new \Exception("잘못된 요청입니다.", 400);
            }
            $lastRow = $this->db->item($collection,[],['projection'=>['_id'=>0,'DATE'=>1],'sort'=>['DATE'=>-1]]);
            
            $data = $this->db->list($collection, $lastRow,['projection'=>['_id'=>0]]);

            return $data;

        }catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
    }


        /**
     * API 제공 업체 삭제
     * @return Array 결과
     */
    public function delete()
    {
        try {            
            $filter = ['coId' => $this->coId, 'id' => $_POST['apiId']];            
            // api 설정 삭제
            $result = $this->db->delete(self::COLLECTION, $filter);
            // apiData 삭제
            $result2 = $this->deleteCollectedItem();

            $data['success'] = true;
            $data['deletedCount'] = $result->getDeletedCount();
            
        } catch(\Exception $e) {
            $data['success'] = false;
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }

    /**
     * 주어진 URL에서 데이터를 읽어와 배열로 반환
     * 지원 포맷: JSON, XML
     * 
     * @param string $url 요청할 API 주소
     * @return array 파싱된 데이터 배열 (실패 시 'msg' 포함)
     */
    public function getUrlData(){
        $data = [];

        try {
            $dateChar = $_POST['dateChar'] ?? '';
            $url = urldecode(trim($_POST['url'] ?? ''));
            $url = str_replace('{key}', $_POST['key'] ?? '', $url);
            // date 관련 처리
            $date = "";
            if(preg_match_all('/{date[^}]*}/', $url, $matches)){
                $index = array_search('{date}',$matches[0]);
                if($index !== false){
                    $date= date($dateChar, strtotime("-7 day"));
                    unset($matches[0][$index]);
                }else{
                    $date = date($dateChar);
                }
                $url = str_replace('{date}', date($dateChar, strtotime($date)), $url);

                foreach($matches[0] as $match){
                    $str = str_replace(['date','{','}'], [$date,'',''], $match);
                    $str = date($dateChar,strtotime($str));
                    $url = str_replace($match, $str, $url);
                }
            }

            $headerParam = $_POST['header'] ?? '';
            $requestHeaders = [];
            if ($headerParam !== '') {
                $normalizedHeader = str_replace(["\r\n", "\r"], "\n", $headerParam);
                foreach (explode("\n", $normalizedHeader) as $headerLine) {
                    $headerLine = trim($headerLine);
                    if ($headerLine === '') {
                        continue;
                    }
                    $requestHeaders[] = $headerLine;
                }
            }

            if(empty($url)){
                throw new \Exception("URL이 비어있습니다.", 400);
            }
            
            // 디버그 로그
            error_log("=== API 호출 시작 ===");
            error_log("URL: " . $url);
            
            // 테스트용 curl 명령어 생성 (헤더 설정 전이므로 나중에 다시 로깅)
            $testCurlCommand = $this->generateCurlCommand($url, $requestHeaders);
            error_log("테스트 명령어: " . $testCurlCommand);
            
            // cURL 초기화
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // 타임아웃 설정 (서버 이전 후 네트워크 환경 변화 대응)
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);  // 연결 타임아웃 30초로 증가
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);  // 전체 타임아웃 60초로 증가
            
            // SSL/TLS 설정 (HTTPS API 대응)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // 인증서 검증 비활성화 (개발용)
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);  // 호스트 검증 비활성화
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);  // TLS 1.2 강제
            
            // Gzip 압축 자동 처리
            curl_setopt($ch, CURLOPT_ENCODING, '');
            
            // 리다이렉트 따라가기
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            
            // User-Agent 설정 (일부 서버에서 차단 방지)
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36');
            
            // HTTP 헤더 설정
            $defaultHeaders = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding: gzip, deflate',
                'Cache-Control: no-cache',
                'Connection: keep-alive'
            ];
            
            // KAMIS API 특별 처리 (kamis.or.kr 도메인)
            if (strpos($url, 'kamis.or.kr') !== false) {
                error_log("KAMIS API 감지 - 특별 설정 적용");
                $defaultHeaders[] = 'Referer: https://www.kamis.or.kr/';
            }
            
            if (!empty($requestHeaders)) {
                // 사용자 정의 헤더 정규화
                $normalizedHeaders = [];
                foreach ($requestHeaders as $headerLine) {
                    if (strpos($headerLine, ':') !== false) {
                        $parts = explode(':', $headerLine, 2);
                        $headerName = trim($parts[0]);
                        $headerValue = trim($parts[1]);
                        $normalizedHeaders[] = $headerName . ': ' . $headerValue;
                    }
                }
                // 사용자 정의 헤더 + 기본 헤더 병합
                curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($normalizedHeaders, $defaultHeaders));
            } else {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);
            }
            
            // DNS 캐시 비활성화 (서버 이전 시 DNS 문제 방지)
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 0);
            
            // IPv4 우선 사용 (일부 서버에서 IPv6 연결 문제 방지)
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            
            // 자세한 디버그 정보 수집
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verboseLog = fopen('php://temp', 'rw+');
            curl_setopt($ch, CURLOPT_STDERR, $verboseLog);
            
            // API 호출 실행
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);
            $curlInfo = curl_getinfo($ch);
            
            // Verbose 로그 읽기
            rewind($verboseLog);
            $verboseLogContent = stream_get_contents($verboseLog);
            fclose($verboseLog);
            
            curl_close($ch);
            
            // 디버그 로그 출력
            error_log("HTTP Code: " . $httpCode);
            error_log("cURL Error: " . $curlError . " (errno: " . $curlErrno . ")");
            error_log("Content-Type: " . $contentType);
            error_log("Total Time: " . ($curlInfo['total_time'] ?? 0) . "s");
            error_log("Response Length: " . strlen($response));
            if ($curlErrno !== 0) {
                error_log("cURL Verbose Log:\n" . $verboseLogContent);
            }

            if ($response === false) {
                // cURL 오류 상세 정보 제공
                $errorDetail = "cURL 오류 (errno: {$curlErrno}): {$curlError}";
                if ($curlErrno == 6) {
                    $errorDetail .= " - DNS 해석 실패. 호스트명을 확인하거나 /etc/hosts, /etc/resolv.conf 설정을 확인하세요.";
                } elseif ($curlErrno == 7) {
                    $errorDetail .= " - 서버 연결 실패. 방화벽이나 네트워크 설정을 확인하세요.";
                } elseif ($curlErrno == 28) {
                    $errorDetail .= " - 연결 타임아웃. 서버 응답이 너무 느립니다.";
                } elseif ($curlErrno == 35) {
                    $errorDetail .= " - SSL 연결 실패. SSL/TLS 설정을 확인하세요.";
                } elseif ($curlErrno == 60) {
                    $errorDetail .= " - SSL 인증서 검증 실패.";
                }
                throw new \Exception($errorDetail, 500);
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new \Exception("HTTP 오류 코드: {$httpCode} - " . substr($response, 0, 200), $httpCode);
            }
            
            // Content-Type이 명확하지 않을 경우가 많아, 데이터 첫번째 문자로 내용으로 판별
            if(preg_match("/^</",trim($response))){ // XML 형식
                $data = json_decode(json_encode(simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOCDATA)), true);
            }else if(preg_match("/^[{|\[]/",trim($response))){ // JSON 형식
                $data = json_decode($response, true);
            }else{
                throw new \Exception("지원하지 않는 데이터 형식입니다. (Content-Type: $contentType)");
            }
        } catch (\Exception $e) {
            $data = ['msg' => $e->getMessage()];
            error_log("=== API 호출 실패 ===");
            error_log("Exception: " . $e->getMessage());
        }
        return $data;
    }
    
    /**
     * cURL 디버그 헬퍼 - 커맨드라인 테스트 명령어 생성
     */
    private function generateCurlCommand($url, $headers = []) {
        $cmd = "curl -v";
        $cmd .= " -X GET";
        $cmd .= " -H 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'";
        $cmd .= " -H 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'";
        $cmd .= " -H 'Accept-Language: ko-KR,ko;q=0.9'";
        
        if (strpos($url, 'kamis.or.kr') !== false) {
            $cmd .= " -H 'Referer: https://www.kamis.or.kr/'";
        }
        
        foreach ($headers as $header) {
            $cmd .= " -H '" . addslashes($header) . "'";
        }
        
        $cmd .= " --connect-timeout 30";
        $cmd .= " --max-time 60";
        $cmd .= " --ipv4";
        $cmd .= " -k";  // SSL 검증 비활성화
        $cmd .= " '" . $url . "'";
        
        return $cmd;
    }


    // ==================== API 제공 업체(Provider) 관리 함수들 ====================

    /**
     * API 제공 업체 목록 조회
     * @return Array 업체 목록과 카테고리 정보
     */
    public function providerList()
    {
        try {
            $data = [];
            $filter = ['coId' => $this->coId];
            
            // 검색 조건 추가
            if (!empty($_GET['searchText'])) {
                $filter['$or'] = [
                    ['name' => new Regex($_GET['searchText'], 'i')],
                    ['description' => new Regex($_GET['searchText'], 'i')]
                ];
            }
            
            if(!empty($_GET['status'])){
                $filter['status']=$_GET['status'];
            }

            // 전체 업체 수
            $data['totalCount'] = $this->db->count(self::PROVIDER_COLLECTION, $filter);
            
            // 페이징 처리
            $noapp = (!empty($_GET['noapp']) ? $_GET['noapp'] : 50);
            $page = (!empty($_GET['page']) ? $_GET['page'] : 1);
            $pageInfo = new Page;
            $data['page'] = $pageInfo->page($noapp, 10, $data['totalCount'], $page);
            
            // 업체 목록 조회
            $options = [
                "skip" => ($page - 1) * $noapp, 
                "limit" => $noapp, 
                'sort' => ['insert.date' => -1]
            ];
            
            $data['items'] = $this->db->list(self::PROVIDER_COLLECTION, $filter, $options);
            
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }

    /**
     * API 제공 업체 상세 정보 조회
     * @return Array 업체 정보
     */
    public function providerEditor()
    {
        $data = [];
        try {
            // 수정 모드인 경우 업체 정보 조회
            if (!empty($_GET['id'])) {
                $filter = ['id' => $_GET['id'], 'coId' => $this->coId];
                $data['item'] = $this->db->item(self::PROVIDER_COLLECTION, $filter, []);
            }
            
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }

    /**
     * API 제공 업체 등록
     * @return Array 결과
     */
    public function providerInsert()
    {
        $data = [];
        try {
            $item = $this->convertProviderData($_POST, 'insert');
            $item['coId'] = $this->coId;
            $item['id'] = uniqid($this->coId);

            $result = $this->db->insert(self::PROVIDER_COLLECTION, $item);
            $data['success'] = true;
            $data['insertedCount'] = $result->getInsertedCount();
        } catch(\Exception $e) {
            $data['success'] = false;
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }

    /**
     * API 제공 업체 수정
     * @return Array 결과
     */
    public function providerUpdate()
    {
        try {
            $item = $this->convertProviderData($_POST, 'update');
            $filter = ['coId' => $this->coId, 'id' => $item['id']];
            $options = ['$set' => $item];

            $result = $this->db->update(self::PROVIDER_COLLECTION, $filter, $options);
            $data['success'] = true;
            $data['modifiedCount'] = $result->getModifiedCount();
            
        } catch(\Exception $e) {
            $data['success'] = false;
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }

    /**
     * API 제공 업체 삭제
     * @return Array 결과
     */
    public function providerDelete()
    {
        try {
            $providerId = $_POST['providerId'];
            
            // 여러 업체 삭제 (|로 구분된 경우)
            if (strpos($providerId, '|') !== false) {
                $providerIds = explode('|', $providerId);
                $providerIds = array_filter($providerIds); // 빈 값 제거
                
                $filter = ['coId' => $this->coId, 'id' => ['$in' => $providerIds]];
            } else {
                $filter = ['coId' => $this->coId, 'id' => $providerId];
            }
            
            // 업체 삭제
            $result = $this->db->delete(self::PROVIDER_COLLECTION, $filter);
            $data['success'] = true;
            $data['deletedCount'] = $result->getDeletedCount();
            
        } catch(\Exception $e) {
            $data['success'] = false;
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }

    /**
     * API 제공 업체 상세 정보 조회 (AJAX용)
     * @return Array 업체 정보와 관련 API 목록
     */
    public function providerDetail()
    {
        try {
            $providerId = $_POST['providerId'];
            $filter = ['id' => $providerId, 'coId' => $this->coId];
            
            $data['provider'] = $this->db->item(self::PROVIDER_COLLECTION, $filter);
            
            // 해당 업체의 API 목록 조회
            $apiFilter = ['providerId' => $providerId, 'coId' => $this->coId];
            $data['apis'] = $this->db->list(self::COLLECTION, $apiFilter, ['sort' => ['lastItemUpdate' => -1]]);
            
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }

    /**
     * 업체 데이터 변환 및 검증
     * @param Array $data POST 데이터
     * @param String $action insert 또는 update
     * @return Array 변환된 데이터
     */
    private function convertProviderData($data, $action)
    {
        $item = $data;
        unset($item['action']);

        // 날짜 정보 추가
        if ($action === 'insert') {
            $item['insert'] = [
                'date' => date('Y-m-d H:i:s'),
                'managerId' => $_SESSION['managerId'] ?? '',
                'managerName' => $_SESSION['managerName'] ?? ''
            ];
        }else{
            $item['update'] = [
                'date' => date('Y-m-d H:i:s'),
                'managerId' => $_SESSION['managerId'] ?? '',
                'managerName' => $_SESSION['managerName'] ?? ''
            ];
        }
        
        return $item;
    }


    /**
     * AI를 사용한 API 자동 분석 및 설정 생성
     * 
     * @return Array 분석 결과
     */
    public function helpAi(){
        // 기본 응답 헤더 설정
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            // 출력 버퍼 정리
            if (ob_get_level()) {
                ob_clean();
            }
            
            // POST 데이터 확인
            $url = $_POST['url'] ?? '';
            $aiModel = $_POST['aiModel'] ?? '';
            $apiData = $_POST['apiData'] ?? '';
            
            if (empty($url)) {
                return ['success' => false, 'msg' => 'URL이 제공되지 않았습니다.'];
            }

            if (empty($aiModel)) {
                return ['success' => false, 'msg' => 'AI 모델이 선택되지 않았습니다.'];
            }
            
            // apiData가 JSON 문자열인 경우 파싱
            if (is_string($apiData) && !empty($apiData)) {
                $decodedApiData = json_decode($apiData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $apiData = $decodedApiData;
                }
            }

            // AI 모델 ID를 모델명으로 변환
            $aiSetting = new AiSetting();
            $_GET['idx'] = $aiModel;
            $aiModelData = $aiSetting->modelEdit();
            
            if (!isset($aiModelData["data"]['item'])) {
                return ['success' => false, 'msg' => 'AI 모델 정보를 찾을 수 없습니다: ' . $aiModel];
            }
            
            $modelInfo = $aiModelData["data"]['item'];
            $modelName = $modelInfo['modelName'] ?? '';
            
            if (empty($modelName)) {
                return ['success' => false, 'msg' => '모델명을 찾을 수 없습니다: ' . $aiModel];
            }

            // 카테고리 정보 로드
            $ctg = new Category();
            $categoryList = $ctg->list('out');
            $category = json_encode($categoryList['categoryList'] ?? []);

            // 프롬프트 생성
            $prompt = $this->createApiAnalysisPrompt($url, $category, $apiData);
            if (empty($prompt)) {
                return ['success' => false, 'msg' => '프롬프트 생성에 실패했습니다.'];
            }

            // AIManager를 사용하여 API 분석
            try {
                $aiManager = new AIManager();
                $result = $aiManager->analyzeApi($prompt, $modelName);
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'msg' => 'AIManager 초기화 또는 실행 중 오류: ' . $e->getMessage(),
                    'error' => 'aimanager_error'
                ];
            } catch (\Error $e) {
                return [
                    'success' => false,
                    'msg' => 'AIManager 치명적 오류: ' . $e->getMessage(),
                    'error' => 'aimanager_fatal_error'
                ];
            }
            
            
            // 모델 정보 추가
            if ($result['success']) {
                $result['model_info']['model_id'] = $aiModel;
                $result['model_info']['model_name'] = $modelName;
            }
            
            return $result;
            
        } catch(\Exception $e) {
            return [
                'success' => false,
                'msg' => 'AI 분석 중 오류가 발생했습니다: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        } catch(\Error $e) {
            return [
                'success' => false,
                'msg' => 'AI 분석 중 치명적 오류가 발생했습니다: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * API 분석을 위한 프롬프트 생성
     * 
     * @param string $url 분석할 API URL
     * @param string $category 카테고리 JSON
     * @param array $apiData 실제 API 응답 데이터
     * @return string GPT 프롬프트
     */
    function createApiAnalysisPrompt($url, $category, $apiData = null){
        // 실제 API 데이터가 있으면 포함 (크기 제한)
        $apiDataJson = '';
        if ($apiData && !empty($apiData)) {
            if (is_array($apiData)) {
                // API 데이터가 너무 크면 샘플만 포함
                $apiDataForPrompt = $apiData;
                if (count($apiData) > 3) {
                    $apiDataForPrompt = array_slice($apiData, 0, 3);
                }
                $apiDataJson = json_encode($apiDataForPrompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
                // 전체 데이터 크기 정보 추가
                $totalSize = strlen(json_encode($apiData));
                $apiDataJson .= "\n**전체 데이터 크기:** " . number_format($totalSize) . " bytes";
                $apiDataJson .= "\n**총 데이터 항목 수:** " . count($apiData) . "개";
            } else {
                $apiDataJson = (string)$apiData;
            }
        }

        return <<<PROMPT
API 분석가로서 다음 URL을 분석하고 JSON 형태로 결과를 반환하세요.

# 핵심 작업
    URL 템플릿화: API키→{key}, 날짜→{date}, user id {userId} 를 url에서 유추해 치환
    실제 데이터 분석: 제공된 API 응답 데이터를 기반으로 정확한 구조 분석
    카테고리 추천: 데이터의 시스템 카테고리에 맞는 카테고리 추천
    구조 분석: 데이터 경로, 필드 타입, 의미 해석 + 신뢰도 평가
    보안 처리: API키 마스킹, 민감정보 [SENSITIVE] 처리
    완전한 폼 채우기: 모든 입력 필드에 적절한 값 자동 생성
    품질 보장: 정확성, 보안성, 재사용성, 효율성
    데이터를 호출하지 말고 "# API 데이터" 를 사용해서 분석해야 함

# 분석 대상 URL
{$url}

# 카테고리
{$category}
    
# API 데이터
{$apiDataJson}
 - field 설명 
# 출력 포맷
    json{
    "api_meta": {
        "template_url": "{치환된 URL}",
        "provider": "{한글 기관명}",
        "response_type": "xml|json",
        "status": "success|error",
        "category": "{카테고리명}",
        "category_id": "{카테고리ID}",
        "api_id": "API주제명을 너무길지 않은 영문으로 변환 하고 카멜 케이스로 표현 예: 일일농산물가격정보 dailyAgriPrice",
        "api_title": "{API 주제명} 한글로 변환",
        "is_use": "Y|N",
        "is_chart_use": "Y|N",
        "api_key": "{API Key}",
        "date_char": "{PHP Date 형식 - 예: Ymd, Y-m-d}",
        "list_tag": "{XML 리스트 태그 경로 - 예: [data][item]}",
        "unit": "{단위 - 예: USD, %, 억원, 만원, 천원, 원, 개}",
        "aliases": "{별칭 - 콤마로 구분}",
        "description": "{설명문}"
    },
    "data_structure": {
        "schema": {
        "field_name": {
            "type": "string|number|date",
            "meaning": "{한글 설명}",
            "primary": "Y|N", 데이터를 식별할 수 있는 필드인지 여부 예: date, 식별코드(code, id, index)가 없는 경우  name 이 주요 필드
        }
    }},
    "fields": { // API 데이터 필드를 분석하여 추가
        "field_name": {
            "tag": "{XML 태그 경로}",
            "field": "{DB 필드명}" 설명을 보고 영문 카멜표현으로 변환 {예: 날짜 -> date, 코드는 code, id, index -> code, 이름 -> name},
            "keyField": "1|0", 데이터를 식별할 수 있는 필드인지 여부 예: date, 식별코드(code, id, index)가 없는 경우  name 이 주요 필드
            "value": "{기본값}", 
            "remark": "{설명}"
        }
        // 데이터가 값이 아니고 설명문 같은 경우 
        //data field가 없는경우 date 필드 추가 value 는 date("Y-m-d", curDate) php형식으로 추가 data_char와 같은 날짜가 표시되도록 처리
        }
    }
    "quality_assessment": {
        "overall_confidence": "high|medium|low",
        "field_coverage": "{분석률}%",
        "warnings": ["{주의사항}"]
    },
    "usage_guide": {
        "required_params": ["{필수값}"],
        "date_format": "{날짜형식}",
        "rate_limit": "{호출제한}",
        "security_notes": ["{보안사항}"]
    }
    }
# 필수 생성 항목
1. API ID: 영문자, 숫자, 하이픈, 언더스코어만 사용 (공백 없음)
2. API 주제명: 명확하고 간결한 제목
3. 제공사: 기관명 또는 서비스 제공자
4. 카테고리: 제공된 카테고리 목록에서 가장 적합한 것 선택
5. API Key: URL에서 추출하거나 추정
6. Date 형식: URL의 날짜 파라미터 분석하여 PHP date 형식으로 변환
7. 단위: 데이터의 단위 (통화, 개수, 퍼센트 등)
8. 별칭: MCP에서 사용되는 별칭 와 유사한 단어들 영어 포함 (콤마로 구분)
9. 설명문: MCP에서 사용되는 설명문
10. 필드 : API데이터에 있는 필드에서 fields 에서 요청사하을 정리하여 처리

품질 보장
- 정확성: 실제 호출 url 호출하고 호출되지 않으면 제공된 #api 데이터를 분석하여 처리 + 신뢰도 점수
- 재사용성: 완전한 템플릿 + 사용가이드

PROMPT;
    }



    /**
     * 필드명을 카멜케이스로 변환
     * 
     * @param string $fieldName 필드명
     * @return string 카멜케이스 필드명
     */
    private function convertToCamelCase($fieldName) {
        // 언더스코어를 제거하고 카멜케이스로 변환
        $words = explode('_', $fieldName);
        $camelCase = $words[0];
        for ($i = 1; $i < count($words); $i++) {
            $camelCase .= ucfirst($words[$i]);
        }
        return $camelCase;
    }

    /**
     * 필드가 키 필드인지 판단
     * 
     * @param string $fieldName 필드명
     * @param array $fieldInfo 필드 정보
     * @return bool 키 필드 여부
     */
    private function isKeyField($fieldName, $fieldInfo) {
        // 일반적으로 코드나 ID 관련 필드가 키 필드
        $keyFieldNames = ['code', 'id', 'key', 'idx', 'no'];
        $fieldNameLower = strtolower($fieldName);
        
        foreach ($keyFieldNames as $keyName) {
            if (strpos($fieldNameLower, $keyName) !== false) {
                return true;
            }
        }
        
        // confidence가 high이고 type이 string인 경우도 키 필드 후보
        if (isset($fieldInfo['confidence']) && $fieldInfo['confidence'] === 'high' && 
            isset($fieldInfo['type']) && $fieldInfo['type'] === 'string') {
            return true;
        }
        
        return false;
    }

    /**
     * API 복제 기능
     * 
     * @return array 복제 결과
     */
    public function copy()
    {
        try {
            $apiId = $_POST['id'] ?? '';
            
            if (empty($apiId)) {
                return [
                    'success' => false,
                    'message' => 'API ID가 필요합니다.'
                ];
            }

            // 원본 API 조회
            $originalApi = $this->db->item(self::COLLECTION, ['id' => $apiId, 'coId' => $this->coId]);
            
            if (!$originalApi) {
                return [
                    'success' => false,
                    'message' => '복사할 API를 찾을 수 없습니다.'
                ];
            }

            // 복제된 API 데이터 생성
            $copiedApi = $originalApi;
            
            // 기존 필드 제거 (MongoDB 자동 생성 필드들)
            unset($copiedApi['_id']);
            unset($copiedApi['insert']);
            unset($copiedApi['update']);
            
            // 새로운 ID 생성 (기존 ID + "_copy" + 타임스탬프)
            $timestamp = date('YmdHis');
            $copiedApi['id'] = $originalApi['id'] . '_copy_' . $timestamp;
            
            // 제목에 "_복제" 접미사 추가
            $copiedApi['title'] = $originalApi['title'] . '_복제';
            
            // 새로운 생성 정보 설정
            $copiedApi['insert'] = [
                'date' => date('Y-m-d H:i:s'),
                'managerId' => $_SESSION['managerId'] ?? 'system',
                'managerName' => $_SESSION['managerName'] ?? 'System'
            ];
            
            $copiedApi['update'] = [
                'date' => date('Y-m-d H:i:s'),
                'managerId' => $_SESSION['managerId'] ?? 'system',
                'managerName' => $_SESSION['managerName'] ?? 'System'
            ];

            // DB에 복제된 API 저장
            $result = $this->db->insert(self::COLLECTION, $copiedApi);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'API가 성공적으로 복사되었습니다.',
                    'data' => [
                        'originalId' => $apiId,
                        'copiedId' => $copiedApi['id'],
                        'copiedTitle' => $copiedApi['title']
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'API 복사 중 오류가 발생했습니다.'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'API 복사 중 오류가 발생했습니다: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Migration 라우팅 메서드
     * @return Array 결과
     */
    public function migration()
    {
        $method = $_GET['method'] ?? '';
        
        switch ($method) {
            case 'start':
                return $this->startMigration();
            case 'status':
                return $this->getMigrationStatus();
            default:
                return [
                    'success' => false,
                    'message' => '잘못된 요청입니다.'
                ];
        }
    }

    /**
     * Migration 시작
     * @return Array 결과
     */
    public function startMigration()
    {
        try {
            $apiId = $_POST['id'] ?? '';
            
            if (empty($apiId)) {
                return [
                    'success' => false,
                    'message' => 'API ID가 필요합니다.'
                ];
            }

            // API 정보 조회
            $filter = ['id' => $apiId];
            $apiInfo = $this->db->item(self::COLLECTION, $filter, []);

            if (!$apiInfo) {
                return [
                    'success' => false,
                    'message' => 'API를 찾을 수 없습니다.'
                ];
            }

            // Migration 파일 생성
            $migrationDir = '/webSiteSource/wcms/cron/migration';
            $migrationFile = $migrationDir . '/' . $apiId . '.txt';

            // migration 디렉토리가 없으면 생성
            if (!is_dir($migrationDir)) {
                mkdir($migrationDir, 0700, true);
            }

            // Migration 파일에 시작 정보 기록
            $migrationData = [
                'apiId' => $apiId,
                'startDate' => date('Y-m-d H:i:s'),
                'status' => 'started',
                'progress' => 0,
                'totalDays' => 0,
                'processedDays' => 0,
                'currentDate' => null,
                'startDateRange' => '2010-01-01',
                'endDateRange' => '2025-10-03'
            ];

            file_put_contents($migrationFile, json_encode($migrationData, JSON_PRETTY_PRINT));

            return [
                'success' => true,
                'message' => 'Migration이 시작되었습니다.',
                'apiId' => $apiId
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Migration 시작 중 오류가 발생했습니다: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Migration 상태 조회
     * @return Array 결과
     */
    public function statusMigration()
    {
        try {
            $apiId = $_GET['id'] ?? '';
            
            if (empty($apiId)) {
                return [
                    'success' => false,
                    'message' => 'API ID가 필요합니다.'
                ];
            }

            $migrationDir = '/webSiteSource/wcms/cron/migration';
            
            // 다양한 확장자 파일들 확인
            $txtFile = $migrationDir . '/' . $apiId . '.txt';
            $runningFile = $migrationDir . '/' . $apiId . '.running';
            $completedFile = $migrationDir . '/' . $apiId . '.completed';
            $failedFile = $migrationDir . '/' . $apiId . '.failed';
            
            $migrationFile = null;
            $status = 'none';
            
            // 파일 존재 여부에 따라 상태 결정
            if (file_exists($runningFile)) {
                $migrationFile = $runningFile;
                $status = 'running';
            } elseif (file_exists($completedFile)) {
                $migrationFile = $completedFile;
                $status = 'completed';
            } elseif (file_exists($failedFile)) {
                $migrationFile = $failedFile;
                $status = 'failed';
            } elseif (file_exists($txtFile)) {
                $migrationFile = $txtFile;
                $status = 'started';
            }
            
            if (!$migrationFile || !file_exists($migrationFile)) {
                return [
                    'success' => true,
                    'migrating' => false,
                    'status' => 'none',
                    'message' => 'Migration이 실행되지 않았습니다.'
                ];
            }

            $migrationData = json_decode(file_get_contents($migrationFile), true);
            
            if (!$migrationData) {
                return [
                    'success' => false,
                    'message' => 'Migration 파일을 읽을 수 없습니다.'
                ];
            }

            return [
                'success' => true,
                'migrating' => true,
                'status' => $status,
                'data' => $migrationData
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Migration 상태 조회 중 오류가 발생했습니다: ' . $e->getMessage()
            ];
        }
    }

    public function deleteCollectedItem()
    {
        try {
            $apiId = $_POST['apiId'] ?? '';

            if (empty($apiId)) {
                return [
                    'success' => false,
                    'message' => 'API ID가 필요합니다.'
                ];
            }

            // apiData 컬렉션에서 apiId와 일치하는 모든 데이터 삭제
            $result = $this->apiDb->delete('apiData', ['coId' => $this->coId, 'id' => $apiId],false);

            return [
                'success' => true,
                'message' => '수집된 데이터가 성공적으로 삭제되었습니다.',
                'deletedCount' => $result->getDeletedCount()
            ];
        }
        catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '수집된 데이터 삭제 중 오류가 발생했습니다: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Migration 정지
     * {apiId}.running 및 {apiId}.txt 파일을 삭제하여 실행 중인 프로세스를 종료합니다.
     */
    public function stopMigration()
    {
        try {
            $apiId = $_POST['id'] ?? '';
            
            if (empty($apiId)) {
                throw new \Exception('API ID가 제공되지 않았습니다.');
            }
            
            $migrationDir = '/webSiteSource/wcms/cron/migration';
            $deletedFiles = [];
            
            // {apiId}.running 파일 삭제
            $runningFile = $migrationDir . '/' . $apiId . '.running';
            if (file_exists($runningFile)) {
                if (unlink($runningFile)) {
                    $deletedFiles[] = $apiId . '.running';
                }
            }
            
            // {apiId}.txt 파일 삭제
            $txtFile = $migrationDir . '/' . $apiId . '.txt';
            if (file_exists($txtFile)) {
                if (unlink($txtFile)) {
                    $deletedFiles[] = $apiId . '.txt';
                }
            }
            
            if (empty($deletedFiles)) {
                $data['success'] = false;
                $data['message'] = 'Migration 파일을 찾을 수 없습니다.';
            } else {
                $data['success'] = true;
                $data['deletedFiles'] = $deletedFiles;
                $data['message'] = 'Migration이 정지되었습니다. (' . implode(', ', $deletedFiles) . ' 삭제됨)';
            }
            
        } catch(\Exception $e) {
            $data['success'] = false;
            $data['message'] = $this->common->getExceptionMessage($e);
        }
        
        return $data;
    }
}