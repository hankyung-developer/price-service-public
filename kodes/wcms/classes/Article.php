<?php
namespace Kodes\Wcms;

use MongoDB\BSON\Regex;

// ini_set('display_errors', 1);

/**
 * Article 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Article
{
	/** const */
	const COLLECTION = 'article';
	const TEMP_COLLECTION = 'articleTemp';
	
	// 디렉토리 경로 상수
	const DIR_TEMP_IMAGES = '/temp/images';
	const DIR_TEMP_CHARTS = '/temp/charts';
	const DIR_IMAGES = '/image';
	const DIR_CHARTS = '/chart';
	const DIR_AI_ARTICLES = '/ai_articles';
	
	// 차트 설정 상수
	const CHART_MAX_ITEMS_DEFAULT = 5;
	const CHART_MAX_TOKENS = 3000;
	const ARTICLE_MAX_TOKENS = 6000;
	
	// 차트 색상 팔레트
	const CHART_COLORS = ['#D94A7D', '#9E3A5E', '#E0A0B8', '#C1677C', '#B5516A', '#F0B8D1', '#A85572', '#E8B5CD'];

	/** @var Class 공통 */
	protected $common;
	protected $db;
	protected $json;
	protected $log;
	
	/** @var Class Article 관련 */
	protected $articleHistory;
	protected $articleRelation;
	protected $articlePublish;
	protected $video;
	protected $api;

	/** @var variable */
	protected $coId;
	protected $menu;
	protected $url;
	protected $increasePublishCount = 0;
	protected $siteDocPath;
	protected $data;
	protected $articleEditingPath;
	
	/** @var bool 디버그 모드 */
	protected $debugMode = false;

	/**
	 * 생성자
	 */
	function __construct()
	{
		// class
		$this->common = new Common();
		$this->db = new DB();
		$this->json = new Json();
		$this->log = new Log();

		// variable
		$this->coId = $this->common->coId;
		$this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
		$this->debugMode = !empty($_GET['dev']);
	}
	
	/**
	 * 디버그 로그 출력 (조건부)
	 * 
	 * @param string $message 로그 메시지
	 * @param mixed $data 추가 데이터 (선택)
	 * @param string $level 로그 레벨 (INFO, ERROR, WARNING)
	 */
	protected function debug($message, $data = null, $level = 'INFO')
	{
		if ($this->debugMode) {
			$logMessage = "[{$level}] {$message}";
			if ($data !== null) {
				$logMessage .= " | Data: " . (is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE));
			}
			error_log($logMessage);
		}
	}
	
	/**
	 * 파일 이동 헬퍼 (temp → 최종 경로)
	 * 
	 * @param string $tempPath 임시 파일 경로
	 * @param string $finalPath 최종 파일 경로
	 * @return bool 성공 여부
	 */
	protected function moveFile($tempPath, $finalPath)
	{
		// 상대 경로를 절대 경로로 변환
		if (strpos($tempPath, '/data/') === 0) {
			$tempPath = '.' . $tempPath;
		}

		$this->debug("파일 이동 시도", ['from' => $tempPath, 'to' => $finalPath]);
		
		if (!file_exists($tempPath)) {
			$this->debug("원본 파일 없음", $tempPath, 'ERROR');
			return false;
		}
		
		// 대상 디렉토리 확인 및 생성
		$targetDir = dirname($finalPath);
		if (!is_dir($targetDir)) {
			mkdir($targetDir, 0755, true);
		}

		// 파일 복사 및 원본 삭제
		if (copy($tempPath, $finalPath)) {
			unlink($tempPath);
			$this->debug("파일 이동 성공", $finalPath);
			return true;
		}
		
		$this->debug("파일 복사 실패", ['from' => $tempPath, 'to' => $finalPath], 'ERROR');
		return false;
	}
	
	/**
	 * 디렉토리 경로 생성 헬퍼
	 * 
	 * @param string $type 디렉토리 타입 (image, chart, temp_image, temp_chart)
	 * @param string $dateDir 날짜 디렉토리 (선택, 기본: Y/m/d)
	 * @return string 절대 경로
	 */
	protected function getDirectoryPath($type, $dateDir = null)
	{
		if ($dateDir === null) {
			$dateDir = date('Y/m/d');
		}
		
		$basePath = $this->siteDocPath;
		
		switch ($type) {
			case 'temp_image':
				return $basePath . self::DIR_TEMP_IMAGES;
			case 'temp_chart':
				return $basePath . self::DIR_TEMP_CHARTS;
			case 'image':
				return $basePath . self::DIR_IMAGES . '/' . $dateDir;
			case 'chart':
				return $basePath . self::DIR_CHARTS . '/' . $dateDir;
			case 'ai_article':
				return $basePath . self::DIR_AI_ARTICLES . '/' . $dateDir;
			default:
				return $basePath;
		}
	}

	/******************************************************************************************
	 * CRUD
	 */

	/**
     * 목록
     *
	 * filter
	 * @param String [GET] coId 회사코드 : isSuper만 사용가능
     * @param String [GET] listType 목록유형
	 * @param String [GET] status 기사상태 : phblish
	 * @param String [GET] categoryId 카테고리ID
	 * @param String [GET] reporterId 기자ID
	 * @param String [GET] reporterName 기자명
	 * @param String [GET] seriesId 시리즈ID
	 * @param String [GET] programId 프로그램ID
	 * @param String [GET] tag 태그명
	 * @param String [GET] pubDate 게재일
	 * @param Bool [GET] inVideo 동영상 기사 여부 (0/1)
	 * @param String [GET] dateType 기간검색대상
	 * @param String [GET] startDate, endDate 기간
	 * @param String [GET] publishMediaId 전송매체
	 * @param String [GET] insertManagerId 작성자ID
	 * @param String [GET] updatManagerId 수정자ID
	 * @param String [GET] placeLayoutId, placeBoxId 면편집 위치
	 * @param String [GET] dept 부서
	 * @param String [GET] searchItem 검색대상
     * @param String [GET] searchText 검색어
     * @param String [GET] excludedAid 검색제외 aid
	 * 
	 * options
	 * @param String [GET] sort 정렬
	 * @param Int [GET] order 정렬방향
	 * @param String [GET] projection 조회여부 필드 옵션
	 * 
	 * page
	 * @param String [GET] page 현재 페이지 번호
	 * @param String [GET] noapp 조회 갯수
	 * @param String [GET] pageNavCnt 페이지번호 버튼 수
	 * 
     * @return Array $return[items, page]
     */
	public function list()
	{
        $return = [];
		try {
			$filter = [];
			$options = [];
			$hint = '';  // 힌트 초기화

			// $param 없으면 $_GET으로 조회
			if (empty($param)) {
				$param = $_GET;
			}

			// 기본값
			if (empty($param["order"])) {
				$_GET['sort'] = $param['sort'] = "write.date";
				$_GET['order'] = $param['order'] = -1;
			}

			if (empty($param['dateType'])) {
				$_GET['dateType'] = $param['dateType'] = 'write.date';
			}
			if (empty($param['startDate'])) {
				$_GET['startDate'] = $param['startDate'] = date("Y-m-d",strtotime('-1 year'));
				$_GET['endDate'] = $param['endDate'] = date("Y-m-d");
			}

			// filter start ------------------------------------------------------------------------------------------------

			// coId
			// $filter["coId"] = $this->coId;

			// listType
			if (!empty($param['listType'])) {
				if ($param['listType'] == 'my') {
					// 내기사
					$filter['reporter.id'] = $_SESSION['managerId'];
				} else {
					// status와 같음
					$param['status'] = $param['listType'];
				}
			}

			// status
			if (!empty($param['status'])) {
				$filter['status']['$eq'] = $param['status'];
			}
			// 삭제기사 제외 : status가 없으면
			if (empty($param['status'])) {
				$filter['status']['$ne'] = 'delete';
				// $filter["delete.is"] = ['$ne'=>true];
			}

			// 카테고리
			// 검색 조건으로 카테고리가 있을 경우 카테고리 조건 변경
			if (!empty($param['categoryId'])) {
				$categoryId = $param['categoryId'];
				$categoryId = (preg_match('/([0]{3})+$/', $categoryId) ? new Regex("^".preg_replace('/([0]{3})+$/', '', $categoryId)) : $categoryId);
				$filter["category.id"] = $categoryId;
				if ($categoryId == "/^".$this->coId."/") {
					unset($filter["category.id"]);
					$filter['coId'] = $this->coId;
				}
			} else {
				// 카테고리 권한
				if (!empty($_SESSION['auth']['category']) && is_array($_SESSION['auth']['category']) && count($_SESSION['auth']['category']) > 0) {
					$filter["category.id"] = ['$in'=>$_SESSION['auth']['category']];
				}
			}


			// 기자
			if (!empty($param['reporterId'])) {
				$filter['reporter.id'] = $param['reporterId'];
			}
			if (!empty($param['reporterName'])) {
				$filter['reporter.name'] = new Regex(preg_quote($param['reporterName'], '/'),'i');
			}

			// 태그
			if (!empty($param['tag'])) {
				$filter["tags"] = $param['tag'];
			}

			// 최초전송일
			if (!empty($param['pubDate'])) {
				$filter["firstPublishDate"] = new Regex(substr($param['pubDate'], 0, 10)." *");
			}

			// 기간
			if (!empty($param['startDate']) && !empty($param['dateType'])) {
				$filter[$param['dateType']] = ['$gte'=>$param['startDate'].' 00:00:00','$lte'=>$param['endDate'].' 23:59:59'];
			}


			// 검색어
			if (!empty($param['searchText'])) {
				$searchText = $param['searchText'];
				switch($param['searchItem']) {
					case "text":
						$filter['$text'] = ['$search' => $searchText];
						// $filter['$or'][] = ['title'=>new Regex(preg_quote($searchText, '/'),'i')];
						// $filter['$or'][] = ['content'=>new Regex(preg_quote($searchText, '/'),'i')];
						break;
					case "title":
						$filter['title'] = new Regex(preg_quote($searchText, '/'),'i');
						break;
					case "reporter":
						$filter['$or'][] = ['reporter.name'=>new Regex(preg_quote($searchText, '/'),'i')];
						$filter['$or'][] = ['reporter.id'=>$searchText];
						// $filter['reporter.name'] = new Regex(preg_quote($searchText, '/'),'i');
						break;
					case "aid":
						$filter["aid"] = $searchText;
						break;
					case "oldId":
						$filter["oldId"] = $searchText;
						break;
					default:
						$filter[$param['searchItem']] = new Regex(preg_quote($searchText, '/'),'i');
				}
			}

			// publishMedia
			if (!empty($param['publishMedia'])) {
				$filter['publishMedia'] = [
					'$elemMatch'=>[
						'id'=>$param['publishMedia'], 
						'status'=>['$in'=>['I','U']]
					]
				];
			}

			// 관련기사 검색이 아니면
			if (!empty($param['searchType'])) {
				if ($param['searchType'] != 'relation') {
					// 권한 있는 카테고리만 표출
					if (!empty($_SESSION['auth']['category']) && count($_SESSION['auth']['category'])) {
						$filter['category.id'] = ['$in'=>$_SESSION['auth']['category']];
						// unset($filter["coId"]);
					}
					// 기사 목록 권한
					if ($_SESSION['auth']['article']['list'] == '전체') {
					} elseif ($_SESSION['auth']['article']['list'] == '부서') {
					} elseif ($_SESSION['auth']['article']['list'] == '개인') {
						$filter["reporter.id"] = $_SESSION['managerId'];
					}
				}
			}

			// 제외 기사 aid
			if (!empty($param['excludedAid'])) {
				$filter["aid"] = ['$ne'=>$param['excludedAid']];
			}

			// 부서 별 검색
			if (empty($_GET['returnType']) || $_GET['returnType'] != 'ajax') {
				// 부서
				if (!empty($param['departmentId'])) {
					$filter['departmentId'] = new Regex('^'.$param['departmentId']);
				}
			}

		// count
		$start = microtime(true);
		$return['totalCount'] = (int) $this->db->count(self::COLLECTION, $filter, $hint);
		
		if ($this->debugMode) {
			$return['debug']['count_time'] = round(microtime(true) - $start, 3);
		}

			// paging
			$pageNavCnt = empty($param['pageNavCnt'])?10:$param['pageNavCnt'];
			$noapp = empty($param['noapp'])?20:$param['noapp'];
			$page = empty($param['page'])?1:$param['page'];
			$pageInfo = new Page;
			$return['page'] = $pageInfo->page($noapp, $pageNavCnt, $return["totalCount"], $page);

			// options
			$options = ['skip' => ($page - 1) * $noapp, 'limit' => $noapp, 'sort' => [$param['sort'] => (int)$param['order']]];

			// options : projection
			$options['projection'] = ['_id'=>0];
			if (!empty($param['projection']) && is_array($param['projection'])) {
				$options['projection'] = $param['projection'];
			}

			// options : hint
			if (!empty($hint)) {
				$options['hint'] = $hint;
			}

			// list 조회
			$start = microtime(true);
			$return['items'] = $this->db->list(self::COLLECTION, $filter, $options);
			
			if ($this->debugMode) {
				$return['debug']['list_time'] = round(microtime(true) - $start, 3);
			}

			// list 추가 정보
			foreach ($return['items'] as $key => &$value) {
				// thumbnail
				$value['thumbnail'] = $this->common->getThumbnail($value['files']);
				$value['thumbnailCaption'] = $this->common->getThumbnailCaption($value['files']);
				// content
				$value["content"] = $this->common->convertTextContent($value["content"]);

				// place(면정보)
				$layoutKind = $this->data["layoutKind"];
				if (is_array($layoutKind) && count($layoutKind) > 0) {
					// place.layoutName
					if (!empty($value["place"]["layoutId"])) {
						$searchData = $this->common->searchArray2D($layoutKind, 'id', $value['place']['layoutId']);
						if (!empty($searchData)) {
							$value["place"]["layoutName"] = $searchData['title'];
						}
					}
					// place.boxName
					if (!empty($value["place"]["boxId"])) {
						$layoutInfo = $this->json->readJsonFile($this->siteDocPath.'/layout', $value['place']['layoutId']."Info");
						$searchData = $this->common->searchArray2D($layoutInfo, 'objId', $value['place']['boxId']);
						if (!empty($searchData)) {
							$value["place"]["boxName"] = $searchData["title"];
						}
					}
				}

				// 편집중 정보 조회
				$value['editing'] = $this->getEditing($value['aid']);
			}
			unset($value);

			// 추가 정보 : ajax가 아닌 경우
			if (empty($_GET['returnType']) || $_GET['returnType'] != 'ajax') {
				// list
				$return['categoryList'] = $this->data['categoryList'];
				$return['seriesList'] = $this->data['seriesList'];
				$return['programList'] = $this->data['programList'];
				$return['company'] = $this->data['company'];
				$return['layoutKind'] = $this->data['layoutKind'];

				// @todo 기자 필터 조건 확인 필요
				$filter = ['coId'=>$this->coId];
				$options = ['sort'=>['name'=>1], 'projection'=>['_id'=>0,'password'=>0,'salt'=>0]];
				$return['reporterList'] = $this->db->list('manager', $filter, $options);
			}
		
			$return['listUrl'] = urlencode($_SERVER['REQUEST_URI']);


		} catch (\Exception $e) {
			if (!empty($_GET['returnType']) && $_GET['returnType'] == 'ajax') {
				$return['msg'] = $this->common->getExceptionMessage($e);
			} else {
				echo "<script>";
				echo "alert('".$this->common->getExceptionMessage($e)."');";
				echo "history.back();";
				echo "</script>";
				exit;
			}
		}

		return $return;
	}

	/**
	 * 기사 조회
	 * 기사 미리보기시 사용.
	 */
	public function item()
	{
	 	$return = [];
	 	try {
			$aid = $_GET['aid'];
			$return['article'] = $this->db->item(self::COLLECTION, ["aid" => $aid], ['projection'=>['_id'=>0]]);

			if (!empty($_GET['isText'])) {
				$return['article']['contentText'] = $this->common->convertTextContent($return['article']['content']);
			}
			if (!empty($_GET['isThumbnail'])) {
				$return['article']['thumbnail'] = $this->common->getThumbnail($return['article']['files']);
			}
	 	} catch (\Exception $e) {
	 		$return['msg'] = $this->common->getExceptionMessage($e);
	 	}

	 	return $return;
	}

	/**
     * 기사 ID 생성
	 * 생성 후 DB에 입력하고 중복되어 입력되지 않으면 재귀호출한다.
     *
     * @param String $coId
     * @param String $date
     * @return String 기사 ID
     */
    public function generateId($coId, $date=null)
	{
        $findKey = $coId.(empty($date)?date('Ymd'):date('Ymd', strtotime($date)));
        $filter = ['aid' => new Regex('^'.$findKey, '')];
        $options = ['sort' => ['aid' => -1], 'limit' => 1];
		$cursor = $this->db->item(self::COLLECTION, $filter, $options);
		$lastAid = $cursor['aid'];
        if (empty($lastAid)) {
            $lastAid = $findKey.'0000';
        }
		$data['aid'] = ++$lastAid;
		$result = $this->db->insert(self::COLLECTION, $data);
		if ($result->getInsertedCount() == 0) {
			// 재귀호출
			return $this->generateId($coId, $date);
		}
        
        return $data['aid'];
    }

	/**
	 * 카테고리ID로 카테고리 조회
	 * 
	 * @param String $id 카테고리ID
	 * @return Object $item 카테고리
	 */
	protected function getCategory($id)
	{
		$searchData = $this->common->searchArray2D($this->data['categoryList'], 'id', $id);
		if (empty($searchData)) {
			return null;
		} else {
			return ['id' => $searchData['id'], 'name' => $searchData['name']];
		}
	}

	/******************************************************************************************
	 * 임시저장
	 */

	/**
	 * 임시저장 입력
	 */
	public function setTemp($data)
	{
		$data['saveTemp'] = [
			'date' => date("Y-m-d H:i:s"),
			'managerId' => $_SESSION['managerId'],
			'managerName' => $_SESSION['managerName'],
		];
		$filter = ['aid'=>$data['aid'], 'saveTemp.managerId'=>$_SESSION['managerId']];
        return $this->db->upsert(self::TEMP_COLLECTION, $filter, $data);
    }

	/**
	 * 임시저장 조회
	 */
	public function getTemp($aid)
	{
		$filter = ['aid'=>$aid, 'saveTemp.managerId'=>$_SESSION['managerId']];
		$options = ['projection'=>['_id'=>0]];
        return $this->db->item(self::TEMP_COLLECTION, $filter, $options);
    }

	/**
	 * 임시저장 삭제
	 */
	public function deleteTemp($aid)
	{
		$filter = [];
		$filter['aid'] = $aid;
		$filter['saveTemp.managerId'] = $_SESSION['managerId'];
        return $this->db->delete(self::TEMP_COLLECTION, $filter, false);
    }

	/**
	 * 기사 편집중 정보 조회
	 * 
	 * @param string $aid 기사 ID
	 * @return array 편집중 정보
	 */
	private function getEditing($aid)
	{
		// TODO: 실제 편집중 정보 조회 로직 구현 필요
		// Redis 또는 별도 세션 저장소에서 편집중인 사용자 정보를 조회
		return [];
	}

	public function aiCreate()
	{
			$result = [];
			$ais = new AiSetting();
			$_GET['isUse'] = 'Y';
			$_GET['noapp'] = 1000000;
			
			// 기본 데이터
			$category = new Category();
			$result['category'] = $category->popup();
			$result['prompt'] = $ais->promptList()['items'];
			$result['template'] = $ais->templateList()['items'];
			
			// AI 모델 목록 (에디터의 동작과 유사하게 제공)
			$modelList = $ais->modelList();
			if (!empty($modelList) && !empty($modelList['0'])) {
				// modelList가 배열 형태로 반환되는 경우
				$result['aiModel'] = $modelList;
			} elseif (!empty($modelList['items'])) {
				$result['aiModel'] = $modelList['items'];
			} else {
				$result['aiModel'] = [];
			}
			
			return $result;
	}

	/**
	 * AI 기사 초안 생성 (AJAX)
	 * step2 진입 시 호출되어 제목/부제/본문 등 초안을 생성
	 * 실제 모델 연동 전까지는 간단한 규칙 기반 생성으로 응답
	 *
	 * @param Array [POST] items 선택 품목 배열
	 * @param String [POST] categoryId 카테고리ID
	 * @param Int [POST] templateIdx 템플릿 idx
	 * @param Int [POST] promptIdx 프롬프트 idx
	 * @param Int [POST] modelIdx AI모델 idx
	 * @param String [POST] articlePrompt 사용자 프롬프트
	 * @param String [POST] makeImage generate|no-generate
	 * @param String [POST] makeChart generate|no-generate
	 */
	public function aiDraft()
	{
		$this->common = $this->common ?: new Common();
		$aiModel = 4; //gpt-4o

		try {
			$this->common->checkRequestMethod('POST');
			$items = !empty($_POST['items']) ? (is_array($_POST['items'])? $_POST['items'] : json_decode($_POST['items'], true)) : [];
			$categoryId = $_POST['categoryId'] ?? '';
			$templateIdx = (int)($_POST['templateIdx'] ?? 0);
			$promptIdx = (int)($_POST['promptIdx'] ?? 0);
			$modelIdx = (int)($_POST['modelIdx'] ?? 0);
			$userPrompt = trim($_POST['articlePrompt'] ?? '');
			$makeImage = $_POST['makeImage'] ?? 'no-generate';
			$makeChart = $_POST['makeChart'] ?? 'no-generate';
            $part = $_POST['part'] ?? '';

			$ais = new AiSetting();
			$_GET['idx'] = $promptIdx;
			$articlePrompt = $ais->promptEdit()['item'];

			$_GET['idx'] = $templateIdx;
			$template = $ais->templateEdit($templateIdx)['item'];

			$api = new Api();
			$_GET['sid'] = implode(",", array_column($items,"id"));
			$_GET['startDate']= date("Y-m-d", strtotime("-7 days"));
			$_GET['endDate'] = date("Y-m-d");
			$_GET['sortField'] = 'oneWeekAgoChange';
			$_GET['sortOrder'] = 'desc';
			$chartData = $api->data();

			// 카테고리 정보 가져오기
			$category = new Category();
			$categoryInfo = $category->getHierarchy($categoryId);
			$categoryName = '';
			$firstDepthCategoryId = '';
			
			if (!empty($categoryInfo['data'])) {
				// 1depth 카테고리 ID 가져오기 (첫 번째 항목)
				$firstCategory = reset($categoryInfo['data']);
				$firstDepthCategoryId = isset($firstCategory['id']) ? $firstCategory['id'] : '';
				
				// 계층 구조의 마지막 항목(가장 하위 카테고리) 이름 가져오기
				$lastCategory = end($categoryInfo['data']);
				$categoryName = isset($lastCategory['name']) ? $lastCategory['name'] : '';
			}

			// 카테고리 타입 판별 (1depth 카테고리 ID 기반)
			// hkp001: 농수산물, hkp002: 생필품, hkp003: 축산물, hkp004: 원자재
			$categoryType = '';
			$isAgricultural = false;
			
			if (!empty($firstDepthCategoryId)) {
				if (strpos($firstDepthCategoryId, 'hkp001') === 0) {
					$categoryType = '농수산물';
					$isAgricultural = true;
				} elseif (strpos($firstDepthCategoryId, 'hkp002') === 0) {
					$categoryType = '생필품';
					$isAgricultural = true;
				} elseif (strpos($firstDepthCategoryId, 'hkp003') === 0) {
					$categoryType = '축산물';
					$isAgricultural = true;
				} elseif (strpos($firstDepthCategoryId, 'hkp004') === 0) {
					$categoryType = '원자재';
					$isAgricultural = false;
				}
			}

			// 선택된 품목명 추출
			$itemNames = [];
			foreach ($items as $item) {
				if (isset($item['title'])) {
					$itemNames[] = $item['title'];
				}
			}
			$itemsText = !empty($itemNames) ? implode(', ', $itemNames) . '. ' : '';
			
			// 카테고리별 이미지 프롬프트 가이드
			if ($isAgricultural) {
				// 농수산물: 시장/식품 이미지
				$imagePromptGuide = "{$itemsText} Professional photojournalism, Korean market, fresh produce, bright natural lighting, market scene, natural documentary style, professional food photography, Korean style";
			} else {
				// 원자재: 전문적인 산업/금융 이미지 (시장/식품 이미지 절대 금지)
				$imagePromptGuide = "{$itemsText} Commodity materials, professional industrial photography, high quality product shot, studio lighting, metallic surface, raw material, industrial product, commercial photography for financial news, modern industrial aesthetic, clean composition, professional business photography, NOT market NOT vegetables NOT food NOT produce NOT groceries";
			}

			// AI Prompt 생성 (최적화)
			$chartDataJson = json_encode($chartData, JSON_UNESCAPED_UNICODE);
			
			$prompt = $articlePrompt['content'];
			$prompt .= "\n\n=== 카테고리 ===\n{$categoryName}\n";
			$prompt .= "\n=== 선택된 품목 ===\n" . implode(', ', $itemNames) . "\n";
			$prompt .= "\n=== 템플릿 ===\n제목: {$template['title']}\n본문: {$template['content']}\n";
			$prompt .= "\n=== 시장 데이터 ===\n{$chartDataJson}\n";
			$prompt .= "\n=== 사용자 요청 ===\n{$userPrompt}\n\n";
			
			$prompt .= "=== 작성 요구사항 ===\n";
			$prompt .= "1. 템플릿과 비슷한 분량 (8-12문단, 1200-1800자)\n";
			$prompt .= "2. 본문 마지막에 표 포함 (현재가/1주전가격/1주변동률)\n";
			$prompt .= "3. 표 HTML 스타일:\n";
			$prompt .= "   - <table> 태그 사용\n";
			$prompt .= "   - 품목명: 좌측정렬 (style=\"text-align: left\")\n";
			$prompt .= "   - 가격/변동률: 우측정렬 (style=\"text-align: right\")\n";
			$prompt .= "   - 변동률 색상: 양수 #dc3545(빨강), 음수 #007bff(파랑), 0% #000(검정)\n";
			$prompt .= "4. \\n\\n으로 문단 구분\n\n";
			
			$prompt .= "=== ⚠️ 데이터 사용 규칙 (매우 중요) ===\n";
			$prompt .= "1. **데이터 구조**: 위 '시장 데이터'는 {'data': [품목배열]} 형식이며, 각 품목은 {'name': '품목명', 'data': [날짜별데이터]} 구조입니다\n";
			$prompt .= "2. **표 작성 순서**: 제공된 data 배열을 oneWeekAgoChange 값으로 내림차순 정렬한 후, 상위 5개 품목만 선택하여 표를 작성하세요\n";
			$prompt .= "3. **데이터 추출**: 각 품목의 data 배열에서 첫 번째 항목(data[0])의 값만 사용하세요\n";
			$prompt .= "4. **필드 사용**:\n";
			$prompt .= "   - 현재가: price 필드\n";
			$prompt .= "   - 1주전가격: oneWeekAgoPrice 필드\n";
			$prompt .= "   - 1주변동률: oneWeekAgoChange 필드\n";
			$prompt .= "   - 품목명: itemName 또는 name 필드\n";
			$prompt .= "5. **절대 금지**: JSON에 없는 숫자를 만들거나 계산하지 마세요. 제공된 값을 그대로 사용하세요\n\n";
			$prompt .= "✅ **표 작성 예시**:\n";
			$prompt .= "1. oneWeekAgoChange로 정렬 → 상위 5개 선택\n";
			$prompt .= "2. 각 품목의 data[0]에서: {\"itemName\":\"파\", \"price\":9000, \"oneWeekAgoPrice\":7200, \"oneWeekAgoChange\":25.0}\n";
			$prompt .= "3. 표: 파 | 9,000원 | 7,200원 | +25.0%\n\n";
			$prompt .= "🖼️ **이미지 프롬프트**: 품목명을 간단한 영문 명사로 변환 (예: '깐마늘(국산)' → 'garlic')\n\n";
			
			$prompt .= "=== 출력 형식 ===\n```json\n{\n";
			$prompt .= '  "title": "제목 (10-15자)",'."\n";
			$prompt .= '  "subtitle": "부제목 (20-30자)",'."\n";
			$prompt .= '  "content": "본문 (표 포함, 제공된 실제 데이터만 사용)",'."\n";
			$prompt .= '  "tags": ["태그1", "태그2", "태그3"],'."\n";
			$prompt .= '  "image_prompt": "영문 프롬프트 - 품목명은 반드시 간단한 일반 명사로 변환할 것 (예: 깐마늘(국산) 1kg → garlic, 사과(부사) → apple, 말린 고추 → pepper). 괄호, 단위, 수식어 모두 제거하고 핵심 품목명만 사용. ('.$imagePromptGuide.')"'."\n";
			$prompt .= "}```\n\n";
			
			$prompt .= "⚠️ 주의: JSON 완전히 종료, 중간에 잘리지 않게, 표는 HTML 형식, 모든 수치는 제공된 데이터에서만 가져올 것\n\n";
			$prompt .= "=== 표 HTML 예시 ===\n";
			$prompt .= "<table>\n";
			$prompt .= "  <thead>\n";
			$prompt .= "    <tr>\n";
			$prompt .= "      <th style=\"text-align: left\">품목</th>\n";	
			$prompt .= "      <th style=\"text-align: right\">현재가</th>\n";
			$prompt .= "      <th style=\"text-align: right\">1주전</th>\n";
			$prompt .= "      <th style=\"text-align: right\">변동률</th>\n";
			$prompt .= "    </tr>\n";
			$prompt .= "  </thead>\n";
			$prompt .= "  <tbody>\n";
			$prompt .= "    <tr>\n";
			$prompt .= "      <td style=\"text-align: left\">쌀</td>\n";
			$prompt .= "      <td style=\"text-align: right\">2,850원</td>\n";
			$prompt .= "      <td style=\"text-align: right\">2,800원</td>\n";
			$prompt .= "      <td style=\"text-align: right; color: #dc3545\">+1.8%</td>\n";
			$prompt .= "    </tr>\n";
			$prompt .= "    <tr>\n";
			$prompt .= "      <td style=\"text-align: left\">배추</td>\n";
			$prompt .= "      <td style=\"text-align: right\">1,200원</td>\n";
			$prompt .= "      <td style=\"text-align: right\">1,400원</td>\n";
			$prompt .= "      <td style=\"text-align: right; color: #007bff\">-14.3%</td>\n";
			$prompt .= "    </tr>\n";
			$prompt .= "    <tr>\n";
			$prompt .= "      <td style=\"text-align: left\">사과</td>\n";
			$prompt .= "      <td style=\"text-align: right\">3,500원</td>\n";
			$prompt .= "      <td style=\"text-align: right\">3,500원</td>\n";
			$prompt .= "      <td style=\"text-align: right; color: #000\">0%</td>\n";
			$prompt .= "    </tr>\n";
			$prompt .= "  </tbody>\n";
			$prompt .= "</table>\n";

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

			$aiManager = new AIManager();
            $response = $aiManager->sendPrompt($prompt, [
                'model' => 'gpt-4o',
                'max_tokens' => self::ARTICLE_MAX_TOKENS
            ]);
            
            // AI 응답 실패 시 에러 반환
            if (!$response['success']) {
                $errorMsg = $response['msg'] ?? $response['error'] ?? 'AI 응답 실패';
                throw new \Exception($errorMsg);
            }
            
            // 응답 데이터 확인
            if (empty($response['data'])) {
                throw new \Exception('AI 응답 데이터가 비어있습니다.');
            }
            
            // 응답 데이터에 chart_data 추가
            $response['data']['chart_data'] = $chartData;
            
            $result = [ 'success' => true, 'data' => $response['data'] ];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $result;
			}
			
			header('Content-Type: application/json');
			echo json_encode($result);
			exit;
		} catch (\Exception $e) {
			$errorResult = ['success'=>false, 'msg'=>$this->common->getExceptionMessage($e)];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $errorResult;
			}
			
			header('Content-Type: application/json');
			echo json_encode($errorResult);
			exit;
		}
	}

	/**
	 * AI를 활용한 차트 코드 생성 (AJAX)
	 * 
	 * @method POST
	 * @param array $chartData 차트 데이터 (필수)
	 * @param string $chartType 차트 타입 (line|bar|column|pie|area, 기본: line)
	 * @param string $chartTitle 차트 제목
	 * @param array $options 추가 옵션
	 * 
	 * @return json
	 * {
	 *   "success": true,
	 *   "data": {
	 *     "chart_code": "생성된 AnyChart.js 코드",
	 *     "chart_config": "차트 설정 JSON",
	 *     "chart_url": "iframe으로 사용할 HTML 파일 URL",
	 *     "chart_data": "사용된 차트 데이터"
	 *   }
	 * }
	 */
	public function aiGenerateChartCode()
	{
		try {
			$this->common->checkRequestMethod('POST');
			
			// 차트 데이터 (필수)
			$chartData = $_POST['chartData'] ?? '';
			if (is_string($chartData)) {
				$chartData = json_decode($chartData, true);
			}
			
			if (empty($chartData)) {
				throw new \Exception('차트 데이터가 필요합니다.');
			}
			
			// 차트 옵션
			$chartType = $_POST['chartType'] ?? 'line';
			$chartTitle = $_POST['chartTitle'] ?? '농산물 가격 추이';
			$maxItems = (int)($_POST['maxItems'] ?? self::CHART_MAX_ITEMS_DEFAULT);
			$multiSeries = ($_POST['multiSeries'] ?? 'false') === 'true';
			
			// 차트 타입별로 데이터 최적화
			$optimizedData = $this->optimizeChartData($chartData, $chartType, $maxItems, $multiSeries);
			
			// AIManager 인스턴스 생성
			$aiManager = new AIManager();
			
			// AI 프롬프트 생성 (최적화된 데이터 사용)
			$prompt = $this->buildChartPrompt($optimizedData, $chartType, $chartTitle, $maxItems, $multiSeries);
			
			// AI 호출 (충분한 토큰 확보)
			$response = $aiManager->sendPrompt($prompt, [
				'model' => 'gpt-4o',
				'max_tokens' => self::CHART_MAX_TOKENS
			]);
			
			// 응답 확인
			if (!$response['success'] || empty($response['data'])) {
				throw new \Exception('AI 차트 코드 생성에 실패했습니다.');
			}
			
			// 차트 코드 추출
			$chartCode = $response['data']['chart_code'] ?? '';
			if (empty($chartCode)) {
				throw new \Exception('생성된 차트 코드가 비어있습니다.');
			}
			
		// 차트 코드 검증
		$this->validateChartCode($chartCode);
		
		// HTML 파일 생성 및 저장 (최적화된 데이터 사용!)
		$htmlResult = $this->createChartHtmlFile($chartCode, $chartTitle, $optimizedData);
			
			// 성공 응답 구성
			$result = [
				'success' => true,
				'msg' => '차트가 성공적으로 생성되었습니다.',
				'data' => [
					'chart_code' => $chartCode,
					'chart_config' => $response['data']['chart_config'] ?? '',
					'chart_url' => $htmlResult['url'],
					'chart_path' => $htmlResult['path'],
					'chart_type' => $chartType,
					'chart_title' => $chartTitle,
					'data_summary' => [
						'items_count' => count($optimizedData['data'] ?? []),
						'date_range' => $this->getDateRange($chartData)  // 원본 데이터에서 날짜 범위 추출
					]
				]
			];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $result;
			}
			
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE);
			exit;
			
		} catch (\Exception $e) {
			$errorResult = [
				'success' => false,
				'msg' => $this->common->getExceptionMessage($e)
			];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $errorResult;
			}
			
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($errorResult, JSON_UNESCAPED_UNICODE);
			exit;
		}
	}
	
	/**
	 * 차트 HTML 파일 생성 및 저장
	 * 
	 * @param string $chartCode AI가 생성한 차트 JavaScript 코드
	 * @param string $chartTitle 차트 제목
	 * @param array $chartData 차트 데이터
	 * @return array ['url' => 'URL', 'path' => '파일 경로']
	 */
	private function createChartHtmlFile($chartCode, $chartTitle, $chartData)
	{
		// 저장 디렉토리 설정 (Step 2에서는 temp에 저장)
		$saveDir = $this->getDirectoryPath('temp_chart');
		
		// 디렉토리 생성
		if (!is_dir($saveDir)) {
			mkdir($saveDir, 0755, true);
		}
		
		// 파일명 생성 (타임스탬프 + 랜덤)
		$filename = 'chart_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8) . '.html';
		$filePath = $saveDir . '/' . $filename;
		
		// HTML 템플릿 생성
		$html = $this->buildChartHtmlTemplate($chartCode, $chartTitle, $chartData);
		
		// 파일 저장
		if (file_put_contents($filePath, $html) === false) {
			throw new \Exception('차트 HTML 파일 저장에 실패했습니다.');
		}
		
		// URL 생성 (temp 경로)
		$baseUrl = $this->url;
		if (empty($baseUrl)) {
			$baseUrl = '';
		}
		$relativeUrl = '/data/' . $this->coId . '/temp/charts/' . $filename;
		
		return [
			'url' => $baseUrl . $relativeUrl,
			'path' => $filePath,
			'filename' => $filename,
			'is_temp' => true  // 임시 파일 표시
		];
	}
	
	/**
	 * 차트 HTML 템플릿 생성
	 * 
	 * @param string $chartCode JavaScript 코드
	 * @param string $chartTitle 차트 제목
	 * @param array $chartData 차트 데이터
	 * @return string HTML 문자열
	 */
	private function buildChartHtmlTemplate($chartCode, $chartTitle, $chartData)
	{
		// 차트 데이터를 JSON으로 변환
		$chartDataJson = json_encode($chartData, JSON_UNESCAPED_UNICODE);
		
		$html = <<<HTML
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=625, initial-scale=1.0">
    <title>{$chartTitle}</title>
    <script src="https://indicator.hankyung.com/js/anychart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Malgun Gothic', '맑은 고딕', 'Apple SD Gothic Neo', sans-serif;
            background: #ffffff;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            width: 100vw;
			max-width: 650px;
            height: 100vh;
			min-height: 300px;
            margin: 0 auto;
        }
        #chartWrapper {
            flex: 1;
            display: flex;
            min-height: 0;
        }
        #chartContainer {
            width: 100%;
            height: 100%;
            flex: 1;
            min-height: 285px;
        }
        #logoContainer {
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        #logoContainer img {
            height: 20px;
            width: auto;
            opacity: 0.8;
        }
        .error-message {
            display: none;
            padding: 15px;
            margin: 10px;
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 4px;
            color: #c33;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div id="chartWrapper">
        <div id="chartContainer"></div>
        <div class="error-message" id="errorMessage"></div>
    </div>
    <div id="logoContainer">
        <img src="https://indicator.hankyung.com/image/logo.png" alt="한국경제" />
    </div>
    
    <script>
        // 차트 데이터
        const chartData = {$chartDataJson};
        
        // 에러 처리 함수
        function showError(message) {
            const errorEl = document.getElementById('errorMessage');
            errorEl.textContent = '차트 생성 오류: ' + message;
            errorEl.style.display = 'block';
            console.error('차트 생성 오류:', message);
        }
        
        // 차트 객체를 전역 변수로 저장 (이미지 변환을 위해)
        window.chart = null;
        
        // 차트 생성 함수
        try {
            // AnyChart 라이브러리 로드 확인
            if (typeof anychart === 'undefined') {
                throw new Error('AnyChart 라이브러리를 로드할 수 없습니다.');
            }
            
            // AI 생성 차트 코드 실행
            {$chartCode}
            
            // 차트 그리기 시도
            if (typeof drawChart === 'function') {
                const chartInstance = drawChart('chartContainer', chartData);
                // 차트 객체를 전역으로 노출
                if (chartInstance) {
                    window.chart = chartInstance;
                }
            } else {
                throw new Error('drawChart 함수가 정의되지 않았습니다.');
            }
            
        } catch (error) {
            showError(error.message);
        }
        
        // 반응형 처리
        window.addEventListener('resize', function() {
            try {
                if (typeof drawChart === 'function') {
                    document.getElementById('chartContainer').innerHTML = '';
                    drawChart('chartContainer', chartData);
                }
            } catch (error) {
                console.error('차트 리사이즈 오류:', error);
            }
        });
    </script>
</body>
</html>
HTML;
		
		return $html;
	}
	
	/**
	 * 차트 타입별로 데이터를 최적화
	 * 
	 * @param array $rawData 원본 데이터
	 * @param string $chartType 차트 타입 (column, line, pie)
	 * @param int $maxItems 최대 항목 수
	 * @param bool $multiSeries 다중 시리즈 여부
	 * @return array 최적화된 데이터
	 */
	private function optimizeChartData($rawData, $chartType, $maxItems, $multiSeries = false)
	{
		if (empty($rawData['data'])) {
			return ['data' => []];
		}
		
		$items = $rawData['data'];
		
		// 상위 N개 품목만 선택
		$topItems = array_slice($items, 0, $maxItems);
		
		$optimizedData = ['data' => []];
		
		if ($chartType === 'column' || $chartType === 'pie') {
			// 막대/원 차트: 각 품목의 최신 가격만 필요
			foreach ($topItems as $item) {
				if (empty($item['data'])) continue;
				
				// 날짜순 정렬 (최신순)
				$dailyData = $item['data'];
				usort($dailyData, function($a, $b) {
					return strcmp($b['date'], $a['date']);
				});
				
				$latestData = $dailyData[0];
				
				$optimizedData['data'][] = [
					'name' => $item['name'] ?? '',
					'value' => floatval($latestData['price'] ?? 0),
					'date' => $latestData['date'] ?? '',
					'change' => floatval($latestData['prevDayChange'] ?? 0)
				];
			}
			
		} elseif ($chartType === 'line') {
			// 선 차트: 품목별 일일 데이터 필요
			foreach ($topItems as $item) {
				if (empty($item['data'])) continue;
				
				// 날짜순 정렬 (과거순)
				$dailyData = $item['data'];
				usort($dailyData, function($a, $b) {
					return strcmp($a['date'], $b['date']);
				});
				
				$seriesData = [];
				foreach ($dailyData as $day) {
					$seriesData[] = [
						'date' => $day['date'] ?? '',
						'value' => floatval($day['price'] ?? 0),
						'change' => floatval($day['prevDayChange'] ?? 0)
					];
				}
				
				$optimizedData['data'][] = [
					'name' => $item['name'] ?? '',
					'series' => $seriesData
				];
			}
		}
		
		return $optimizedData;
	}
	
	/**
	 * 차트 타입별 간결한 예시 코드 반환
	 * 
	 * @param string $chartType 차트 타입
	 * @return string 예시 코드
	 */
	private function getChartTemplate($chartType)
	{
		$colors = json_encode(self::CHART_COLORS);
		
		$templates = [
			'column' => "chart.palette(" . $colors . ");\nvar chartData = data.data.map(item => ({x: item.name, value: item.value}));\nvar series = chart.column(chartData);\nseries.tooltip().format(function() { return this.x + '\\n가격: ' + this.value.toLocaleString() + '원'; });",
			
			'pie' => "var chartData = data.data.map(item => ({x: item.name, value: item.value}));\nchart.data(chartData);\nchart.palette(" . $colors . ");\nvar legend = chart.legend();\nlegend.enabled(true);\nlegend.fontSize(13);\nlegend.padding(10);",
			
			'line' => "var colors = " . $colors . ";\ndata.data.forEach(function(item, index) {\n  var seriesData = item.series.map(function(d) { return {x: d.date, value: d.value}; });\n  var line = chart.line(seriesData);\n  line.name(item.name);\n  var color = colors[index % colors.length];\n  line.stroke(color, 2);\n  line.tooltip().format(function() { return this.seriesName + '\\n' + this.x + '\\n가격: ' + this.value.toLocaleString() + '원'; });\n});\nvar legend = chart.legend();\nlegend.enabled(true);\nlegend.fontSize(13);\nlegend.padding(10);\nlegend.position('top');\nlegend.align('center');",
		];
		
		return $templates[$chartType] ?? $templates['column'];
	}
	
	/**
	 * 차트 생성을 위한 AI 프롬프트 구성 (최적화 버전)
	 * 
	 * @param array $chartData 차트 데이터
	 * @param string $chartType 차트 타입
	 * @param string $chartTitle 차트 제목
	 * @param int $maxItems 최대 항목 수
	 * @param bool $multiSeries 다중 시리즈 여부
	 * @return string 프롬프트
	 */
	private function buildChartPrompt($chartData, $chartType, $chartTitle, $maxItems, $multiSeries = false)
	{
		$colors = json_encode(self::CHART_COLORS);
		$dataJson = json_encode($chartData, JSON_UNESCAPED_UNICODE);
		$template = $this->getChartTemplate($chartType);
		
		$dataStructure = ($chartType === 'column' || $chartType === 'pie') 
			? "data: [{name, value, date, change}, ...]" 
			: "data: [{name, series: [{date, value, change}, ...]}, ...]";
		
		$prompt = "AnyChart.js {$chartType} 차트를 생성하세요.\n\n";
		$prompt .= "⚠️ 요구사항:\n";
		$prompt .= "1. 제공된 실제 데이터만 사용 (임의 데이터 생성 금지)\n";
		$prompt .= "2. 데이터는 최적화됨. data.data를 바로 사용\n";
		$prompt .= "3. 완전한 drawChart(containerId, data) 함수 생성\n";
		$prompt .= "4. 모든 괄호/중괄호 완전히 닫기\n";
		$prompt .= "5. 🚫 anychart.enums.ColorType 사용 금지 (에러 발생)\n";
		$prompt .= "6. ✅ 막대/원 차트: chart.palette([...colors]) 사용\n";
		$prompt .= "7. 🔴 라인 차트일 경우: 각 시리즈에 line.name(item.name) 설정 필수\n";
		$prompt .= "8. 🔴 라인 차트일 경우: 각 라인에 line.stroke(colors[index % colors.length], 2) 로 색상 직접 지정\n";
		$prompt .= "9. 🔴 라인 차트일 경우: 범례(legend) 반드시 표시 (position: 'top', align: 'center')\n\n";
		
		$prompt .= "=== 데이터 구조 ===\n{$dataStructure}\n\n";
		$prompt .= "=== 실제 데이터 ===\n{$dataJson}\n\n";
		
		$prompt .= "=== 스타일 ===\n";
		$prompt .= "- 색상: {$colors}\n";
		$prompt .= "- 제목: '{$chartTitle}' (18px, bold, #333)\n";
		$prompt .= "- 배경: #fff, 패딩: 20px\n";
		$prompt .= "- 툴팁: 항목명 + 날짜 + 가격(toLocaleString()으로 포맷)\n";
		$prompt .= "- 라인 차트: 범례 위치 top, 가운데 정렬, 각 라인에 고유 색상 및 항목명 표시\n\n";
		
		$prompt .= "=== 예시 코드 ({$chartType}) ===\n```javascript\n";
		$prompt .= "function drawChart(containerId, data) {\n";
		$prompt .= "  var chart = anychart.{$chartType}();\n";
		$prompt .= "  var title = chart.title();\n";
		$prompt .= "  title.text('" . addslashes($chartTitle) . "');\n";
		$prompt .= "  title.fontSize(18);\n";
		$prompt .= "  chart.background().fill('#fff');\n";
		$prompt .= "  chart.padding(20);\n\n";
		$prompt .= "  // ⚠️ 중요: 데이터 처리 (아래 코드를 정확히 따라하세요)\n";
		$prompt .= "  {$template}\n\n";
		$prompt .= "  chart.container(containerId);\n";
		$prompt .= "  chart.draw();\n";
		$prompt .= "  return chart;\n";
		$prompt .= "}\n```\n\n";
		$prompt .= "⚠️ 주의: 위 예시의 데이터 처리 부분을 정확히 복사하세요!\n";
		$prompt .= "🚫 series.normal().fill() 또는 anychart.enums 사용 금지\n";
		$prompt .= "📊 라인 차트 필수 사항:\n";
		$prompt .= "  - colors 배열을 함수 내부에 정의: var colors = {$colors};\n";
		$prompt .= "  - 각 item.name을 line.name()으로 설정하여 범례에 표시\n";
		$prompt .= "  - 각 라인의 색상을 직접 지정: line.stroke(colors[index % colors.length], 2);\n";
		$prompt .= "  - chart.legend().enabled(true) 설정\n";
		$prompt .= "  - 툴팁에 this.seriesName 포함하여 항목명 표시\n\n";
		
		$prompt .= "=== 출력 형식 ===\n";
		$prompt .= "```json\n{\n";
		$prompt .= '  "chart_code": "완전한 drawChart 함수 (중괄호/괄호 완전히 닫기)",'."\n";
		$prompt .= '  "chart_config": {"type": "' . $chartType . '", "title": "' . addslashes($chartTitle) . '", "colors": ' . $colors . '}' . "\n";
		$prompt .= "}```\n\n";
		
		$prompt .= "⚠️ 주의:\n";
		$prompt .= "- 실제 데이터만 사용 (임의 데이터 생성 금지)\n";
		$prompt .= "- console.log, parseFloat, toLocaleString 사용\n";
		$prompt .= "- 완전한 함수 생성 (중간에 잘리지 않게)\n";
		$prompt .= "- 🚫🚫🚫 anychart.enums.ColorType 절대 사용 금지 (undefined 에러)\n";
		$prompt .= "- ✅ chart.palette([색상배열]) 방식만 사용\n";
		
		return $prompt;
	}
	
	/**
	 * 차트 코드 검증
	 * 
	 * @param string $chartCode 차트 코드
	 * @throws \Exception 코드가 유효하지 않을 경우
	 */
	private function validateChartCode($chartCode)
	{
		$errors = [];
		
		// 1. 빈 코드 체크
		if (empty(trim($chartCode))) {
			throw new \Exception('차트 코드가 비어있습니다.');
		}
		
		// 2. 함수 선언 확인
		if (strpos($chartCode, 'function drawChart') === false && 
		    strpos($chartCode, 'function(') === false) {
			$errors[] = '함수 선언이 없습니다 (function drawChart)';
		}
		
		// 3. 중괄호 매칭 확인
		$openBraces = substr_count($chartCode, '{');
		$closeBraces = substr_count($chartCode, '}');
		
		if ($openBraces !== $closeBraces) {
			$errors[] = "중괄호 불일치 (열림: {$openBraces}, 닫힘: {$closeBraces})";
		}
		
		// 4. 괄호 매칭 확인
		$openParens = substr_count($chartCode, '(');
		$closeParens = substr_count($chartCode, ')');
		
		if ($openParens !== $closeParens) {
			$errors[] = "괄호 불일치 (열림: {$openParens}, 닫힘: {$closeParens})";
		}
		
		// 5. 필수 AnyChart 호출 확인
		if (strpos($chartCode, 'anychart.') === false) {
			$errors[] = 'AnyChart 라이브러리 호출 없음';
		}
		
		// 6. chart.draw() 호출 확인
		if (strpos($chartCode, '.draw()') === false) {
			$errors[] = 'chart.draw() 호출 없음';
		}
		
		// 7. 최소 길이 확인 (너무 짧으면 불완전할 가능성)
		if (strlen($chartCode) < 200) {
			$errors[] = '코드가 너무 짧음 (불완전한 코드 가능성)';
		}
		
		// 에러가 있으면 상세 정보와 함께 예외 발생
		if (!empty($errors)) {
			$codeLength = strlen($chartCode);
			$lastChars = substr($chartCode, -100); // 마지막 100자 확인
			
			$errorMsg = "차트 코드 검증 실패:\n";
			$errorMsg .= "- " . implode("\n- ", $errors) . "\n\n";
			$errorMsg .= "코드 길이: {$codeLength}자\n";
			$errorMsg .= "코드 끝부분: ...{$lastChars}\n\n";
			$errorMsg .= "AI가 생성한 코드가 불완전합니다. 다시 시도해주세요.";
			
			throw new \Exception($errorMsg);
		}
		
		return true;
	}
	
	/**
	 * 차트 데이터에서 날짜 범위 추출
	 * 
	 * @param array $chartData 차트 데이터
	 * @return array 시작일, 종료일
	 */
	private function getDateRange($chartData)
	{
		$dates = [];
		
		if (!empty($chartData['data']) && is_array($chartData['data'])) {
			foreach ($chartData['data'] as $item) {
				if (!empty($item['data']) && is_array($item['data'])) {
					foreach ($item['data'] as $point) {
						if (!empty($point['date'])) {
							$dates[] = $point['date'];
						}
					}
				}
			}
		}
		
		if (empty($dates)) {
			return ['start' => '', 'end' => ''];
		}
		
		sort($dates);
		return [
			'start' => $dates[0],
			'end' => $dates[count($dates) - 1]
		];
	}

	/**
	 * AI 기사 저장 및 전송
	 * - temp 디렉토리의 파일을 최종 경로로 이동
	 * - 차트 HTML을 PNG로 변환
	 * - 이미지 정보를 DB에 저장
	 * 
	 * @method POST
	 * @param string $data JSON 형식의 기사 데이터
	 * @return array 
	 * {
	 *   "success": true,
	 *   "msg": "기사가 성공적으로 저장되었습니다.",
	 *   "data": {
	 *     "aid": "기사 ID",
	 *     "image": {"path": "/image/2025/11/02/xxx.png", ...},
	 *     "chart": {"html": "/chart/2025/11/02/xxx.html", "png": "/chart/2025/11/02/xxx.png", ...}
	 *   }
	 * }
	 */
	public function aiSave()
	{
		try {
			$this->common->checkRequestMethod('POST');
			
			// 전달받은 데이터 파싱
			$jsonData = $_POST['data'] ?? '';
			if (empty($jsonData)) {
				throw new \Exception('저장할 데이터가 없습니다.');
			}
			
			$data = json_decode($jsonData, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new \Exception('잘못된 JSON 형식입니다: ' . json_last_error_msg());
			}
			
			// 필수 데이터 검증
			if (empty($data['title']) || empty($data['body'])) {
				throw new \Exception('제목과 본문은 필수입니다.');
			}

			// 기사 ID 생성 (회사ID + 날짜 + 시간 + 랜덤)
			$aid = $this->generateId($this->coId);
			$this->debug("기사 ID 생성", $aid);
			
			// 날짜별 디렉토리 경로
			$dateDir = date('Y/m/d');
			$imageDir = $this->getDirectoryPath('image', $dateDir);
			$chartDir = $this->getDirectoryPath('chart', $dateDir);
			
			// 디렉토리 생성
			if (!is_dir($imageDir)) {
				mkdir($imageDir, 0755, true);
				// 소유자를 nginx:nginx로 변경
				@chown($imageDir, 'nginx');
				@chgrp($imageDir, 'nginx');
				@chmod($imageDir, 0755);
			}
			if (!is_dir($chartDir)) {
				mkdir($chartDir, 0755, true);
				// 소유자를 nginx:nginx로 변경
				@chown($chartDir, 'nginx');
				@chgrp($chartDir, 'nginx');
				@chmod($chartDir, 0755);
			}
			
			$result = [
				'aid' => $aid,
				'image' => null,
				'chart' => null
			];

			$imageClass = new Image();

			// 1. 이미지 파일 이동 및 DB 저장
			if (!empty($data['image'])) {
				$this->debug("이미지 파일 처리 시작", $data['image']);
				
				
				$newFilename = null;
				$imageProcessed = false;

				$data['image']['url'] = str_replace(' ','',$data['image']['url']);
				
				// URL에서 이미지 다운로드
				if (!empty($data['image']['url'])) {
					$this->debug("이미지 URL에서 다운로드", $data['image']['url']);

					try {
						// URL에서 이미지 데이터 가져오기
						$imageData = @file_get_contents($data['image']['url']);
						
						if ($imageData !== false) {
							// URL에서 확장자 추출 (없으면 png로 기본 설정)
							$urlPath = parse_url($data['image']['url'], PHP_URL_PATH);
							$extension = pathinfo($urlPath, PATHINFO_EXTENSION);
							if (empty($extension) || !in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
								$extension = 'png';
							}
							
							$newFilename = $aid . '_image.' . $extension;
							$newImagePath = $imageDir . '/' . $newFilename;

							// 이미지 파일 저장
							$bytesWritten = @file_put_contents($newImagePath, $imageData);
						
							if ($bytesWritten !== false) {
								$this->debug("URL에서 이미지 다운로드 성공", $newImagePath);
								$imageProcessed = true;
							} else {
								// 에러 정보 가져오기
								$error = error_get_last();
								$this->debug("이미지 파일 저장 실패", $newImagePath, 'ERROR');
							}
						} else {
							$this->debug("URL에서 이미지 다운로드 실패", $data['image']['url'], 'ERROR');
						}
					} catch (\Exception $e) {
						$this->debug("이미지 다운로드 중 오류", $e->getMessage(), 'ERROR');
					}
				}
				// 로컬 경로에서 이미지 이동
				else if (!empty($data['image']['path'])) {
					$tempImagePath = $data['image']['path'];
					$extension = pathinfo($tempImagePath, PATHINFO_EXTENSION);
					$newFilename = $aid . '_image.' . $extension;
					$newImagePath = $imageDir . '/' . $newFilename;
					// 파일 이동 (헬퍼 메서드 사용)
					if ($this->moveFile($tempImagePath, $newImagePath)) {
						$this->debug("로컬 이미지 파일 이동 성공", $newImagePath);
						$imageProcessed = true;
					}
				}

				// 이미지가 성공적으로 처리된 경우에만 DB 저장
				if ($imageProcessed && $newFilename && $newImagePath) {
					// 이미지 정보를 DB에 저장
					$newImagePath =$imageClass->resizeImage($newImagePath,1200);

					$newFileInfo = [
						'path' => $newImagePath,
						'filename' => basename($newImagePath),
						'caption' => $data['title'] ?? '',
						'description' => $data['image_prompt'] ?? '',
						'aid' => $aid
					];

					$imageInfo = $imageClass->saveImageInfo($newFileInfo);
					$this->debug("이미지 DB 저장 완료", $imageInfo);
					
					$result['image'] = [
						'path' =>  $newImagePath,
						'filename' => $newFilename,
						'id' => $imageInfo['id'] ?? null
					];
				} else {
					$this->debug("이미지 처리 실패 - DB 저장 생략", null, 'WARNING');
				}
			}

			// 2. 차트 HTML 파일 이동 및 PNG 이동
			if (!empty($data['chart']) && !empty($data['chart']['url'])) {
				$this->debug("차트 파일 처리 시작", $data['chart']);
				
				$tempChartPath = $data['chart']['url'];
				$newChartFilename = $aid . '_chart.html';
				$newChartHtmlPath = $chartDir . '/' . $newChartFilename;

				// HTML 파일 이동 (헬퍼 메서드 사용)
				if ($this->moveFile($tempChartPath, $newChartHtmlPath)) {
					$result['chart'] = [
						'html' => '/data/' . $this->coId . self::DIR_CHARTS . '/' . $dateDir . '/' . $newChartFilename,
					];

					// 3. 차트 PNG 파일 이동 (브라우저에서 이미 변환됨)
					if (!empty($data['chart']['png_path'])) {
						$tempPngPath = $data['chart']['png_path'];
						$newPngFilename = $aid . '_chart.png';
						$newPngPath = $chartDir . '/' . $newPngFilename;
						
						// PNG 파일 이동 (헬퍼 메서드 사용)
						if ($this->moveFile($tempPngPath, $newPngPath)) {
							$result['chart']['png'] = '/data/' . $this->coId . self::DIR_CHARTS . '/' . $dateDir . '/' . $newPngFilename;
						}
					}
				}
			}
			
			// 4. reviewContent 처리 (이미지/차트 경로 변경 및 텍스트 변환)
			$reviewContentHtml = $data['reviewContent'] ?? '';
			$contentText = '';
			
			if (!empty($reviewContentHtml)) {
				$coIdEscaped = preg_quote((string)$this->coId, '/');
				
				// 4-1. 이미지 경로 변경 (temp → 최종 경로)
				if ($result['image']) {
					// temp 경로를 최종 경로로 변경
					$re = '/"https:\/\/oaidalleapiprodscus.blob.core.windows.net[^"]+/m';
					$subst = '"'.str_replace("/webData","/data",$result['image']['path']);
					$reviewContentHtml = preg_replace(
						$re, 
						$subst,
						$reviewContentHtml
					);
				}
				
				// 4-2. 차트 경로 변경 (temp → 최종 경로)
				if ($result['chart']) {
					// temp 차트 경로를 최종 경로로 변경
					$tempChartPattern = '/\/data\/' . $coIdEscaped . '\/temp\/charts\/[^"\']+\.html/i';
					$reviewContentHtml = preg_replace(
						$tempChartPattern, 
						$result['chart']['html'], 
						$reviewContentHtml
					);

					$reviewContentHtml=str_replace('id="reviewChartFrame"', 'id="reviewChartFrame" data-img="'.$result['chart']['png'].'"', $reviewContentHtml);
				}

				// 4-3. HTML을 텍스트로 변환 (<p> → \n\n)
				$contentText = $this->convertHtmlToText($reviewContentHtml);
			}
			
			// 5. 기사 데이터 저장 (DB 또는 JSON 파일)
			$articleData = [
				'coId' => $this->coId,
				'aid' => $aid,
				'categoryId' => $data['categoryId'] ?? '',
				'title' => $data['title'] ?? '',
				'subtitle' => $data['subtitle'] ?? '',
				'body' => $data['body'] ?? '',  // 원본 본문 (textarea)
				'content' => $contentText,  // reviewContent에서 변환한 텍스트 (이미지/차트 제외)
				'tags' => $data['tags'] ?? [],
				'items' => $data['items'] ?? [],
				'image' => $result['image'],
				'chart' => $result['chart'],
				'write' => [
					'date' => date('Y-m-d H:i:s'),
					'managerId' => $_SESSION['managerId'] ?? '',
					'managerName' => $_SESSION['managerName'] ?? ''
				]
			];
			
			// MongoDB에 저장
			$this->db->upsert(self::COLLECTION, ['aid'=>$aid], $articleData);
			$this->debug("기사 DB 저장 완료", $aid);
			
			// JSON 파일로 백업 저장
			$jsonSaveDir = $this->getDirectoryPath('ai_article', $dateDir);
			if (!is_dir($jsonSaveDir)) {
				mkdir($jsonSaveDir, 0755, true);
			}
			$jsonFilePath = $jsonSaveDir . '/' . $aid . '.json';
			file_put_contents($jsonFilePath, json_encode($articleData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
			$this->debug("기사 JSON 백업 완료", $jsonFilePath);
			
			// 기사 전송
			$this->sendArticle($articleData);
			
			// 성공 응답
			$successResult = [
				'success' => true,
				'msg' => '기사가 성공적으로 저장되었습니다.',
				'aid' => $aid,
				'data' => $result
			];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $successResult;
			}
			
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($successResult, JSON_UNESCAPED_UNICODE);
			exit;
			
		} catch (\Exception $e) {
			$errorResult = [
				'success' => false,
				'msg' => $this->common->getExceptionMessage($e)
			];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $errorResult;
			}
			
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($errorResult, JSON_UNESCAPED_UNICODE);
			exit;
		}
	}

	/**
	 * HTML을 텍스트로 변환
	 * - <p> 태그를 \n\n으로 변환
	 * - 이미지와 iframe(차트) 태그 제거
	 * - HTML 태그 제거하여 순수 텍스트 추출
	 * 
	 * @param string $html HTML 문자열
	 * @return string 변환된 텍스트
	 */
	private function convertHtmlToText($html)
	{
		if (empty($html)) {
			return '';
		}
			
		// 1. <br> 태그를 \n으로 변환
		$html = preg_replace('/<br\s*\/?>/i', "\n", $html);
		
		// 2. </p> 태그를 \n\n으로 변환 (문단 구분)
		$html = preg_replace('/<\/p>/i', "\n\n", $html);
		
		// 3. <p> 태그 제거
		$html = preg_replace('/<p[^>]*>/i', '', $html);
		
		// 4. </p> 태그를 \n\n으로 변환 (문단 구분)
		$html = preg_replace('/<\/div>/i', "\n\n", $html);
				
		// 5. <p> 태그 제거
		$html = preg_replace('/<div[^>]*>/i', '', $html);

		// 6. HTML 엔티티 디코딩
		$html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		
		// 7. 연속된 공백 제거 (3개 이상의 \n을 2개로)
		$html = preg_replace("/\n{3,}/", "\n\n", $html);
		
		// 8. 앞뒤 공백 제거
		$html = trim($html);
		
		return $html;
	}

	/**
	 * 차트 이미지 저장 (브라우저에서 변환된 이미지)
	 * Step 2 → Step 3 전환 시 호출
	 * 
	 * @method POST
	 * @param string $chartImageData Base64 인코딩된 이미지 데이터
	 * @param string $chartFilename 원본 차트 HTML 파일명 (연결을 위해)
	 * @return array
	 * {
	 *   "success": true,
	 *   "data": {
	 *     "png_path": "/data/coId/temp/charts/chart_xxx.png",
	 *     "png_filename": "chart_xxx.png"
	 *   }
	 * }
	 */
	public function aiSaveChartImage()
	{
		try {
			$this->common->checkRequestMethod('POST');
			
			// Base64 이미지 데이터
			$chartImageData = $_POST['chartImageData'] ?? '';
			$chartFilename = $_POST['chartFilename'] ?? '';
			
			if (empty($chartImageData)) {
				throw new \Exception('차트 이미지 데이터가 없습니다.');
			}
			
			// Base64 데이터에서 헤더 제거 (data:image/png;base64, 부분)
			if (preg_match('/^data:image\/(\w+);base64,/', $chartImageData, $matches)) {
				$imageType = $matches[1]; // png, jpeg, etc
				$chartImageData = substr($chartImageData, strpos($chartImageData, ',') + 1);
			} else {
				$imageType = 'png'; // 기본값
			}
			
			// Base64 디코딩
			$imageData = base64_decode($chartImageData);
			if ($imageData === false) {
				throw new \Exception('이미지 데이터 디코딩 실패');
			}
			
			// 저장 디렉토리 (temp)
			$saveDir = $this->getDirectoryPath('temp_chart');
			if (!is_dir($saveDir)) {
				mkdir($saveDir, 0755, true);
			}
			
			// 파일명 생성 (HTML 파일명과 동일하게, 확장자만 .png로)
			if (!empty($chartFilename)) {
				// HTML 파일명에서 확장자를 png로 변경
				$pngFilename = preg_replace('/\.html$/', '.png', $chartFilename);
			} else {
				$pngFilename = 'chart_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8) . '.png';
			}
			
			$pngPath = $saveDir . '/' . $pngFilename;
			
			// 파일 저장
			if (file_put_contents($pngPath, $imageData) === false) {
				throw new \Exception('차트 이미지 저장 실패');
			}
			
			// 성공 응답
			$this->debug("차트 이미지 저장 완료", $pngFilename);
			
			$result = [
				'success' => true,
				'msg' => '차트 이미지가 저장되었습니다.',
				'data' => [
					'png_path' => '/data/' . $this->coId . self::DIR_TEMP_CHARTS . '/' . $pngFilename,
					'png_filename' => $pngFilename,
					'png_size' => filesize($pngPath)
				]
			];
			
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE);
			exit;
			
		} catch (\Exception $e) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode([
				'success' => false,
				'msg' => $this->common->getExceptionMessage($e)
			], JSON_UNESCAPED_UNICODE);
			exit;
		}
	}

	public function aiGenerateArticleImage()
	{
		try {
			$this->common->checkRequestMethod('POST');
			
			// 이미지 프롬프트 (필수)
			$imagePrompt = trim($_POST['imagePrompt'] ?? '');
			
			if (empty($imagePrompt)) {
				throw new \Exception('이미지 프롬프트가 필요합니다.');
			}
			
			// 이미지 옵션
			$imageModel = $_POST['imageModel'] ?? 'dall-e-3';
			$imageSize = $_POST['imageSize'] ?? '1792x1024';  // 625px width에 최적화된 가로형 이미지
			$imageQuality = $_POST['imageQuality'] ?? 'standard';
			$imageStyle = $_POST['imageStyle'] ?? 'vivid';  // vivid | natural
			
			// AIManager 인스턴스 생성
			$aiManager = new AIManager();
			
			// 이미지 생성 옵션
			$imageOptions = [
				'model' => $imageModel,
				'size' => $imageSize
			];
			
			// DALL-E-3 전용 옵션
			if ($imageModel === 'dall-e-3') {
				$imageOptions['quality'] = $imageQuality;
				$imageOptions['style'] = $imageStyle;  // 프론트엔드에서 전달받은 스타일 사용
			}
			
			// 이미지 저장 경로 설정 (Step 2에서는 temp에 저장)
			$savePath = $this->getDirectoryPath('temp_image');
			$filePrefix = 'article_' . date('YmdHis');
			$this->debug("이미지 생성 요청", ['prompt' => $imagePrompt, 'model' => $imageModel]);
			
			// 이미지 생성 및 저장
			$imageResult = $aiManager->generateAndSaveImage(
				$imagePrompt,
				$savePath,
				$filePrefix,
				$imageOptions
			);
			
			// 생성 실패 처리
			if ($imageResult['status'] !== 'success' || !$imageResult['success']) {
				throw new \Exception($imageResult['msg'] ?? '이미지 생성에 실패했습니다.');
			}
			
			// 성공 응답 구성
			$savedFiles = $imageResult['saved_files'] ?? [];
			$firstImage = !empty($savedFiles) ? $savedFiles[0] : null;
			
			$this->debug("이미지 생성 완료", [
				'filename' => $firstImage['filename'] ?? '',
				'size' => $imageSize
			]);
			
			$result = [
				'success' => true,
				'msg' => '이미지가 성공적으로 생성되었습니다.',
				'data' => [
					'image_url' => $firstImage['url'] ?? '',
					'image_path' => $firstImage['path'] ?? '',
					'image_filename' => $firstImage['filename'] ?? '',
					'image_prompt' => $imagePrompt,
					'image_size' => $imageSize,
					'model' => $imageModel,
					'all_images' => $savedFiles
				]
			];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $result;
			}
			
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE);
			exit;
			
		} catch (\Exception $e) {
			$errorResult = [
				'success' => false,
				'msg' => $this->common->getExceptionMessage($e)
			];
			
			// CLI 환경에서는 exit 건너뛰기 (cron job 지원)
			if (php_sapi_name() === 'cli' || defined('CRON_EXECUTION')) {
				return $errorResult;
			}
			
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($errorResult, JSON_UNESCAPED_UNICODE);
			exit;
		}
	}

	/**
	 * 기사를 JSON 형식으로 내보내기
	 * aiSave()로 저장된 기사 데이터를 받아 JSON 포맷으로 /wcms/sendArticle에 저장하고
	 * 이미지는 /wcms/sendArticle/images/로 복사
	 * 차트는 /nas/priceimage에서 HTML과 차트 이미지 둘 다 복사
	 * 
	 * @param Array $articleInfo 기사 정보 배열 (aiSave에서 저장된 구조)
	 *   - aid: 기사 ID (필수)
	 *   - title: 제목
	 *   - body: 원본 본문
	 *   - content: 변환된 텍스트
	 *   - tags: 태그 배열
	 *   - items: PRICE_LIST 매핑 배열
	 *   - image: 이미지 정보 (path, filename, id)
	 *   - chart: 차트 정보 (html, png)
	 *   - categoryId: 카테고리 ID
	 * @return Array 결과 정보
	 */
	public function sendArticle($articleInfo)
	{
		try {
			// 기사 정보 유효성 검증
			if (empty($articleInfo) || !is_array($articleInfo)) {
				throw new \Exception('기사 정보가 올바르지 않습니다.');
			}

			if (empty($articleInfo['aid'])) {
				throw new \Exception('기사 ID(aid)가 필요합니다.');
			}

			$aid = $articleInfo['aid'];
			$this->debug("기사 전송 시작", $aid);

			$this->debug("기사 데이터 확인", [
				'aid' => $articleInfo['aid'] ?? null,
				'title' => $articleInfo['title'] ?? null,
				'has_image' => !empty($articleInfo['image']),
				'has_chart' => !empty($articleInfo['chart'])
			]);

			// 2. sendArticle 디렉토리 생성
			$sendArticleDir = '/webSiteSource/wcms/sendArticle';
			$sendImageDir = $sendArticleDir . '/images';
			
			if (!is_dir($sendArticleDir)) {
				mkdir($sendArticleDir, 0755, true);
				$this->debug("디렉토리 생성", $sendArticleDir);
			}
			if (!is_dir($sendImageDir)) {
				mkdir($sendImageDir, 0755, true);
				$this->debug("이미지 디렉토리 생성", $sendImageDir);
			}

			// 3. 이미지 처리 (aiSave에서 저장된 image 필드)
			$images = [];
			
			if (!empty($articleInfo['image']) && is_array($articleInfo['image'])) {
				$imagePath = $articleInfo['image']['path'] ?? '';
				

				if (!empty($imagePath)) {
					// /data/ 경로를 실제 파일 시스템 경로로 변환
					$imageFullPath = $imagePath;
					if (strpos($imagePath, '/data/') === 0) {
						$imageFullPath = str_replace('/data/', $this->common->config['path']['data'] . '/', $imagePath);
					} elseif (strpos($imagePath, '/webData/') === 0) {
						$imageFullPath = str_replace('/webData/', $this->common->config['path']['data'] . '/', $imagePath);
					}
					
					$this->debug("이미지 파일 확인", [
						'original_path' => $imagePath,
						'full_path' => $imageFullPath,
						'exists' => file_exists($imageFullPath)
					]);
					
					if (file_exists($imageFullPath)) {
						$imageFilename = basename($imageFullPath);
						$newImagePath = $sendImageDir . '/' . $imageFilename;
						
						// 이미지 복사
						if (copy($imageFullPath, $newImagePath)) {
							$images[] = [
								'path' => 'images/' . $imageFilename,
								'caption' => $articleInfo['title'] ?? ''
							];
							$this->debug("이미지 복사 완료", $newImagePath);
						} else {
							$this->debug("이미지 복사 실패", $newImagePath, 'WARNING');
						}
					} else {
						$this->debug("이미지 파일 없음", $imageFullPath, 'WARNING');
					}
				}
			}

			$sendChartDir = '/nas/priceimage/' . date('Y') . date('m') . '/';

			// 4. 차트 처리 (aiSave에서 저장된 chart 필드)
			if (!empty($articleInfo['chart']) && is_array($articleInfo['chart'])) {
				if (!is_dir($sendChartDir)) {
					mkdir($sendChartDir, 0755, true);
					$this->debug("이미지 디렉토리 생성", $sendChartDir);
				}
				// 4-1. 차트 HTML 파일 복사
				if (!empty($articleInfo['chart']['html'])) {
					$chartHtmlPath = $articleInfo['chart']['html'];
					$chartHtmlFullPath = $chartHtmlPath;
					
					if (strpos($chartHtmlPath, '/data/') === 0) {
						$chartHtmlFullPath = str_replace('/data/', '/webData/', $chartHtmlPath);
					}
					
					$this->debug("차트 HTML 파일 확인", [
						'original_path' => $chartHtmlPath,
						'full_path' => $chartHtmlFullPath,
						'exists' => file_exists($chartHtmlFullPath)
					]);


					if (file_exists($chartHtmlFullPath)) {
						$chartHtmlFilename = basename($chartHtmlFullPath);
						$newChartHtmlPath = $sendChartDir. $chartHtmlFilename;
						
						if (copy($chartHtmlFullPath, $newChartHtmlPath)) {
							$this->debug("차트 HTML 복사 완료", $newChartHtmlPath);

							$re = '/[^"]+'.$chartHtmlFilename.'/';
							$subst = 'https://img.hankyung.com/photo/priceimage/'.date('Y').date('m').'/'.$chartHtmlFilename;
							$articleInfo['content'] = preg_replace($re, $subst, $articleInfo['content']);
						}	
					} else {
						$this->debug("차트 HTML 파일 없음", $chartHtmlFullPath, 'WARNING');
					}
				}
				
				// 4-2. 차트 PNG 이미지 복사
				if (!empty($articleInfo['chart']['png'])) {
					$chartPngPath = $articleInfo['chart']['png'];
					$chartPngFullPath = $chartPngPath;
					
					// 여러 경로에서 차트 이미지 확인
					if (strpos($chartPngPath, '/data/') === 0) {
						$chartPngFullPath = str_replace('/data/', $this->common->config['path']['data'] . '/', $chartPngPath);
					}

					
					if (file_exists($chartPngFullPath)) {
						$chartPngFilename = basename($chartPngFullPath);
						$newChartPngPath = $sendChartDir . $chartPngFilename;
						
						// 차트 이미지 복사
						if (copy($chartPngFullPath, $newChartPngPath)) {
							$this->debug("차트 PNG 복사 완료", $newChartPngPath);

							$re = '/[^"]+'.$chartPngFilename.'/';
							$subst = 'https://img.hankyung.com/photo/priceimage/'.date('Y').date('m').'/'.$chartPngFilename;
							$articleInfo['content'] = preg_replace($re, $subst, $articleInfo['content']);
						} else {
							$this->debug("차트 PNG 복사 실패", $newChartPngPath, 'WARNING');
						}
					} else {
						$this->debug("차트 PNG 파일 없음", $chartPngFullPath, 'WARNING');
					}
				}
			}

			// 5. 본문 HTML 생성 (aiSave의 body 필드 사용)
			$textContent = '';
			
			if (!empty($articleInfo['content'])) {
				$contentLines = explode("\n", $articleInfo['content']);
				foreach ($contentLines as $line) {
					$line = trim($line);
					if (!empty($line)) {
						$textContent .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
					}
				}
			}

			// 6. 해시태그 생성 (aiSave의 tags 배열)
			$hashtag = '';
			if (!empty($articleInfo['tags']) && is_array($articleInfo['tags'])) {
				$hashtag = implode(',', $articleInfo['tags']);
			}

			// 7. PRICE_LIST 생성 (aiSave의 items 배열)
			$priceList = [];
			if (!empty($articleInfo['items']) && is_array($articleInfo['items'])) {
				$sids = implode(',',array_column($articleInfo['items'],'id'));
				$_GET['sid'] = $sids;
				$_GET['startDate'] = date('Y-m-d',strtotime('-3 days'));
				$api = new Api();
				$rowData = $api->data();

				// 중복 제거 및 최대 5개 제한
				$addedIds = []; // 이미 추가된 ID 추적
				$maxCount = 5;  // 최대 개수
				
				foreach($rowData['data'] as $item){
					// 최대 개수 체크
					if (count($priceList) >= $maxCount) {
						break;
					}
					
					// ID 생성 (hkp 제거)
					$itemId = str_replace("hkp", '', $item['categoryId']);
					
					// 중복 체크 - 이미 추가된 ID는 스킵
					if (in_array($itemId, $addedIds)) {
						continue;
					}
					
					// priceList에 추가
					$priceList[] = [
						'id' => $itemId,
						'name' => $item['categoryName']
					];
					
					// 추가된 ID 기록
					$addedIds[] = $itemId;
				}
				
				$this->debug("PRICE_LIST 생성 완료", [
					'count' => count($priceList),
					'ids' => $addedIds
				]);
			}

			// 8. JSON 데이터 생성 (hkp202510300001.json 포맷)
			$jsonData = [
				'ORGARTICLEID' => $articleInfo['aid'],
				'TITLE' => $articleInfo['title'] ?? '',
				'SUBTITLE' => $articleInfo['subtitle'] ?? '',
				'TEXTCONTENT' => $articleInfo['content'],
				'CONTENTS_CODE' => '0400',
				'ISEMBARGO' => 'N',
				'EMBARGODATE' => '',
				'ISMATCHING_PHOTO' => 'Y',
				'HASHTAG' => $hashtag,
				'PRICE_LIST' => $priceList,
				'images' => $images
			];

			// 9. JSON 파일 저장
			$jsonFilename = $aid . '.json';
			$jsonFilePath = $sendArticleDir . '/' . $jsonFilename;
			
			$jsonString = json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			file_put_contents($jsonFilePath, $jsonString);
			
			$this->debug("JSON 파일 저장 완료", [
				'path' => $jsonFilePath,
				'size' => strlen($jsonString)
			]);

			// 10. 결과 반환
			$result = [
				'success' => true,
				'msg' => '기사 전송 파일 생성 완료',
				'data' => [
					'aid' => $aid,
					'jsonFile' => $jsonFilename,
					'jsonPath' => $jsonFilePath,
					'imageCount' => count($images),
					'images' => $images,
					'priceListCount' => count($priceList)
				]
			];

			$this->debug("기사 전송 완료", $result);
			return $result;

		} catch (\Exception $e) {
			$this->debug("기사 전송 오류", $e->getMessage(), 'ERROR');
			
			return [
				'success' => false,
				'msg' => $this->common->getExceptionMessage($e)
			];
		}
	}
}