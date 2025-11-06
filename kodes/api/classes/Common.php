<?php 
namespace Kodes\Api;

class Common
{
	/** @var Class */
    protected $json;
	protected $log;

	/** @var variable */
	public $config;
	public $coId;
	public $dataDir;
	public $device;
	protected $reporters;	

	/**
     * 생성자
     */
	public function __construct()
	{
		$this->json = new Json();
		$this->log = new Log();
		$this->coId = empty($_SESSION['coId'])?'hkp':$_SESSION['coId'];
		$this->config = $this->getConfigCommon();
		$this->dataDir = $this->config['path']['data'].'/'.$this->coId;
		$this->device = empty($GLOBALS['deviceType'])?'pc':$GLOBALS['deviceType'];
		$this->reporters = [];
	}

    /**
     * 템플릿 class 객체 생성
     */
    public function setTemplate($template_dir=null, $compile_dir=null)
    {
        $tpl = new Template_();
		if (empty($template_dir)) {
			$tpl->template_dir = '_template';
		} else {
			$tpl->template_dir = $template_dir;
		}
		if (empty($compile_dir)) {
			$tpl->compile_dir = '_compile';
		} else {
			$tpl->compile_dir = $compile_dir;
		}
		if (!is_dir($tpl->compile_dir)) {
			mkdir($tpl->compile_dir, 0777, true);
		}
		return $tpl;
    }

	/**
	 * common
	 */
	public function getConfigCommon()
	{
		if (empty($GLOBALS['common'])) {
			$GLOBALS['common'] = $this->json->readJsonFile('/webSiteSource/kodes/api/config', 'common');
		}
		return $GLOBALS['common'];
	}

	/**
	 * 회사
	 */
	public function getCompany()
	{
		if (empty($GLOBALS['company'])) {
			$GLOBALS['company'] = $this->json->readJsonFile($this->dataDir.'/config', $this->coId."_company");
		}
		return $GLOBALS['company'];
	}

	/**
	 * 카테고리
	 */
	public function getCategory()
	{
		if (empty($GLOBALS['category'])) {
			$GLOBALS['category'] = $this->json->readJsonFile($this->dataDir, $this->coId."_category");
		}
		return $GLOBALS['category'];
	}

	/**
	 * 카테고리 Tree
	 */
	public function getCategoryTree()
	{
		if (empty($GLOBALS['categoryTree'])) {
			$GLOBALS['categoryTree'] = $this->json->readJsonFile($this->dataDir, $this->coId."_categoryTree");
		}
		return $GLOBALS['categoryTree'];
	}

	/**
	 * 게시판 정보 목록
	 */
	public function getBoardInfoList()
	{
		if (empty($GLOBALS['boardInfoList'])) {
			$GLOBALS['boardInfoList'] = $this->json->readJsonFile($this->dataDir, $this->coId."_board");
		}
		return $GLOBALS['boardInfoList'];
	}

	/**
	 * 프로그램 정보 목록
	 */
	public function getProgramList()
	{
		if (empty($GLOBALS['programList'])) {
			$GLOBALS['programList'] = $this->json->readJsonFile($this->dataDir, $this->coId."_program");
		}
		return $GLOBALS['programList'];
	}

	/**
	 * 방송타입
	 */
	public function getBroadcastType()
	{
		if (empty($GLOBALS['broadcastType'])) {
			$GLOBALS['broadcastType'] = [
				['id'=>'news', 'name'=>'NEWS'],
				['id'=>'tv', 'name'=>'TV'],
				['id'=>'radio', 'name'=>'RADIO'],
			];
		}
		return $GLOBALS['broadcastType'];
	}

	/**
	 * 프로그램 게시판 타입
	 */
	public function getProgramBoardType()
	{
		if (empty($GLOBALS['programBoardType'])) {
			$GLOBALS['programBoardType'] = [
				['id'=>'notice', 'name'=>'공지사항'],
				['id'=>'replay', 'name'=>'다시보기'],
			];
		}
		return $GLOBALS['programBoardType'];
	}

	/**
	 * 데이터를 변환시킨다.
	 * 
	 * @param $val Array 변환시킬 데이터
	 * @param $flag 입력/수정/삭제
	 * @param $removeField 제거시킬 필드
     * @return Array 변환된 데이터
	 */
	public function covertDataField($val, $flag, $removeField=[])
	{
		$ip = $this->getRealClientIp();
		if ($flag == "insert") {
			$val["insert"]["date"]			= date('Y-m-d H:i:s');
			$val["insert"]["managerId"]		= $_SESSION["managerId"];
			$val["insert"]["managerName"]	= $_SESSION["managerName"];
			$val["insert"]["ip"]			= $ip;
		} elseif ($flag == "update") {
			$val["update"]["date"]			= date('Y-m-d H:i:s');
			$val["update"]["managerId"]		= $_SESSION["managerId"];
			$val["update"]["managerName"]	= $_SESSION["managerName"];
			$val["update"]["ip"]			= $ip;
		} elseif ($flag == "delete") {
			$val["delete"]["is"]			= true;
			$val["delete"]["date"]			= date('Y-m-d H:i:s');
			$val["delete"]["managerId"]		= $_SESSION["managerId"];
			$val["delete"]["managerName"]	= $_SESSION["managerName"];
			$val["delete"]["ip"]			= $ip;
		}

		// 필드 제거
		foreach ($removeField as $key => $value) {
			unset($val[$value]);
		}

		return $val;
	}

	/**
	 * 클라이언트 IP 주소를 가져온다.
	 * 프록시 환경을 고려한 안전한 IP 추출
	 */
	public function getRealClientIp()
	{
		// IP 우선순위 배열로 성능 개선
		$ipHeaders = [
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		];

		foreach ($ipHeaders as $header) {
			if (!empty($_SERVER[$header])) {
				$ip = $_SERVER[$header];
				// 여러 IP가 콤마로 구분된 경우 첫 번째 IP 사용
				if (strpos($ip, ',') !== false) {
					$ip = trim(explode(',', $ip)[0]);
				}
				// IP 형식 검증
				if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
					return $ip;
				}
			}
		}

		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	/**
	 * requestMethod 체크
	 * 
	 * @param String http method
	 * @return void
	 */
	public function checkRequestMethod($requestMethod)
	{
		if ($requestMethod != $_SERVER['REQUEST_METHOD']) {
            throw new \Exception('허용되지 않는 요청입니다. (Method Not Allowed)', 405);
        }
	}

	/**
	 * 예외 메시지 처리 및 로그 출력
	 * 
	 * @param \Throwable $th 예외 객체
	 * @return String $msg 사용자에게 전달 할 메시지
	 */
	public function getExceptionMessage(\Throwable $th)
	{
		http_response_code($th->getCode());
		$msg = '';
		if ($th->getCode() < 500) {
			$msg = $th->getMessage();
		} else {
			$msg = '요청 처리에 실패하였습니다.';
			$this->log->writeLog($this->coId, $th->getCode().' : '.$th->getMessage(), 'Exception');
		}
		return $msg;
	}

	/*public function checkBool($param)
	{
		$boolval = ( is_string($val) ? filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $val );
		return ( $boolval===null ? false : $boolval );
	}*/

	/**
	 * ip 가져옴
	 */
	public function getRemoteAddr()
	{
		return !empty($_SERVER['HTTP_X_FORWARDED_FOR'])?$_SERVER['HTTP_X_FORWARDED_FOR']:$_SERVER['REMOTE_ADDR'];
	}

	/**
	 * 2차원 배열 내 값을 key/value로 검색하여, 일치하는 첫번째 row를 반환
	 * 검색하려는 key가 row에 존재해야 함
	 * 
	 * @param Array $array 검색대상 배열
	 * @param String $key 검색할 필드명
	 * @param String $value 검색할 값
	 * @return Array|null 검색결과 row
	 */
	public function searchArray2D($array, $key, $value)
	{
		if (empty($array) || !is_array($array) || empty($key) || empty($value)) {
			return null;
		}
		
		// 성능 최적화: foreach 루프로 직접 검색
		foreach ($array as $row) {
			if (isset($row[$key]) && $row[$key] === $value) {
				return $row;
			}
		}
		
		return null;
	}

	/**
	 * strpos에서 needle을 배열로 사용한 함수
	 * 
	 * @param String $str 문자열
	 * @param Array $needleArray 배열
	 * @return Bool 결과 true/false
	 */
	public function strposArray($str, $needleArray)
	{
		if (empty($str)) {
			return true;
		}
		if (empty($needleArray) || !is_array($needleArray)) {
			return false;
		}
		
		// 성능 최적화: foreach 루프에서 early return 사용
		foreach ($needleArray as $value) {
			if (!empty($value) && strpos($str, $value) !== false) {
				return true;
			}
		}
		
		return false;
	}

	/**
     * php://input 처리
     * php://input 으로 수신되는 변수를 일반적인 파라미터(get, post) 배열로 변환
     * 
     * ajax로 전송 시 data를 다음과 같이 설정해야 한다.
     * data: JSON.stringify($('#form').serializeArray()),
     */
    public function convertRequestByInput()
    {
        $datas = [];
        $input = json_decode(file_get_contents('php://input'), true);
        foreach ($input as $key => $value) {
            $temp = preg_match('/^([^\[\]]+)(\[.*\])?$/', $value['name'], $maches);
            if (empty($maches[2])) {
                $datas[$value['name']] = $value['value'];
            } else {
                $temp2 = preg_match_all('/\[([^\[\]]*)\]/', $maches[2], $maches2);
                if (!empty($maches2[1])) {
                    $curArray = &$datas[$maches[1]];  // 현재 배열을 $datas로 설정
                    foreach ($maches2[1] as $key2 => $value2) {
                        if (empty($value2) && $value2 != '0') {
                            $curArray[] = [];  // 새로운 다차원 배열 생성
                            $curArray = &$curArray[count($curArray)-1];  // 현재 배열을 새로운 다차원 배열로 변경
                        } else {
                            if (is_numeric($value2)) $value2 = intval($value2);
                            if (empty($curArray[$value2])) $curArray[$value2] = [];  // 새로운 다차원 배열 생성
                            $curArray = &$curArray[$value2];  // 현재 배열을 새로운 다차원 배열로 변경
                        }
                    }
                    $curArray = $value['value'];
                }
            }
        }
        return $datas;
    }

	/**
	 * 기사 상태명
	 */
    public function getStatusName($status)
	{
		$val = '';
        switch ($status) {
            case 'save':
                $val = '저장';
                break;
            case 'desk':
                $val = '데스크';
                break;
			case 'deskReject':
				$val = '데스킹 반려';
				break;
			case 'deskComplete':
				$val = '데스킹 완료';
				break;
            case 'embargo':
                $val = '예약전송';
                break;
            case 'publish':
                $val = '발행';
                break;
            case 'delete':
                $val = '삭제';
                break;
			case 'auto':
				$val = '저장(자동)';
				break;
            default:
                $val = '저장';
                break;
        }

        return $val;
    }

	/**
	 * 본문을 Text형태로 변환
	 */
	public function convertTextContent($content)
	{
		if (empty($content)) return $content;
		return trim(str_replace(["&nbsp;", "\r", "\n"], [" ", "", " "], strip_tags(preg_replace('/<(figure).*?<\/\1>/s', '', $content))));
	}

	/**
	 * 파일 목록에서 첫 번째 이미지 정보를 가져옴
	 * 
	 * @param array $files 파일 배열
	 * @param string $field 반환할 필드명 ('path', 'caption' 등)
	 * @return string|null 해당 필드값
	 */
	public function getFirstImageField($files, $field = 'path')
	{
		if (empty($files) || !is_array($files)) {
			return null;
		}
		
		foreach ($files as $file) {
			if (!empty($file['type']) && $file['type'] === 'image') {
				return $file[$field] ?? null;
			}
		}
		
		return null;
	}

	/**
	 * 파일 목록에서 썸네일(첫번째 이미지) 가져옴
	 */
	public function getThumbnail($files)
	{
		return $this->getFirstImageField($files, 'path');
	}

	/**
	 * 파일 목록에서 썸네일 캡션(첫번째 이미지) 가져옴
	 */
	public function getThumbnailCaption($files)
	{
		return $this->getFirstImageField($files, 'caption');
	}

	/**
	 * 프로그램 썸네일
	 * 썸네일 노출 우선순위를 설정
	 */
	public function getProgramThumbnail($image)
	{
		$thumbnail = '';
		if (!empty($image['posterWidth'])) $thumbnail = $image['posterWidth'];
		elseif (!empty($image['posterHeight'])) $thumbnail = $image['posterHeight'];
		elseif (!empty($image['background'])) $thumbnail = $image['background'];
		return $thumbnail;
	}

	/**
	 * url에 도메인을 추가
	 * cdn 도메인 설정에 사용
	 */
	public function applyDomainUrl($domain, $url)
	{
		// 도메인이 없는 경우에만 적용
		if (!empty($domain) && !empty($url) && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '//')) {
			return rtrim($domain, '/').$url;
		}
		return $url;
	}

	/**
	 * 진행 상태를 구해온다.
	 *
	 * @param string $startTime 시작시간
	 * @param string $endTime 종료시간
	 * @return string $status 상태값
	 */
	public function checkProgress($startTime, $endTime)
	{
		$status = match(true){
			empty($endTime) => "success",
			$endTime < date("Y-m-d H:i:s") => "danger",
			$startTime > date("Y-m-d H:i:s") => "info",
			default => "success"
		};
		return $status;
	}

	/**
     * 지역 코드에 맞는 지역명을 반환한다.
     *
     * @param string $code 지역 코드
     * @return string 지역명
     */
	public function setCountry($code)
	{
		// 성능 최적화: 정적 배열로 한 번만 생성
		static $mapping = null;
		if ($mapping === null) {
			$mapping = [
				'KR-11' => '서울특별시',
				'KR-26' => '부산광역시',
				'KR-27' => '대구광역시',
				'KR-28' => '인천광역시',
				'KR-29' => '광주광역시',
				'KR-30' => '대전광역시',
				'KR-31' => '울산광역시',
				'KR-41' => '경기도',
				'KR-42' => '강원도',
				'KR-43' => '충청북도',
				'KR-44' => '충청남도',
				'KR-45' => '전라북도',
				'KR-46' => '전라남도',
				'KR-47' => '경상북도',
				'KR-48' => '경상남도',
				'KR-49' => '제주특별자치도',
				'KR-50' => '세종특별자치시'
			];
		}

		return $mapping[$code] ?? '기타';
	}

	/**
	 * 권한 체크
	 */
	public function checkAuth($menu_id)
	{
		if (!empty($_SESSION['isSuper'])) return;	// 슈퍼유저
		if (!empty($_GET['returnType']) && $_GET['returnType'] == 'ajax') return;
		if (empty($menu_id)) throw new \Exception('메뉴ID가 없습니다.', 400);
		if (empty($_SESSION['auth']['menu']) || !in_array($menu_id, $_SESSION['auth']['menu'])) {
			throw new \Exception('권한이 없습니다.', 400);
		}
	}

	/**
	 * WCMS 메뉴 조회
	 */
	public function getWcmsMenu()
	{
		if (empty($GLOBALS['wcms_menu'])) {
			$wcms_menu = $this->json->readJsonFile('../config', 'wcms_menu');

			if (!empty($this->coId)) {
				$wcmsBoardMenu = $this->json->readJsonFile($this->config['path']['data'].'/'.$this->coId.'/config', 'wcmsBoardMenu');	// 게시판 메뉴
				// 게시판 메뉴 추가 처리
				if (!empty($wcmsBoardMenu) && is_array($wcmsBoardMenu)) {
					foreach ($wcms_menu as $key => &$value) {
						$item = empty($wcmsBoardMenu[$value['menuId']])?null:$wcmsBoardMenu[$value['menuId']];
						$childTemplate = empty($value['childTemplate'])?'':$value['childTemplate'];
						if (!empty($item) && !empty($childTemplate)) {
							foreach ($item as $key2 => &$value2) {
								if (!empty($value['menuId']) && !empty($value2['menuName'])) {
									$depth2 = $childTemplate;
									$depth2['menuId'] = $value2['menuId'];
									$depth2['menuName'] = $value2['menuName'];
									$depth2['parent'] = $value2['parent'];
									$depth2['link'] = $value2['link'];
									$depth2['datalink'] = $value2['datalink'];
									$depth2['selector'] = $value2['menuId'];
									$value['child'][] = $depth2;
									unset($depth2);
								}
							}
							unset($value2);
						}
					}
					unset($value);
				}
			}

			$GLOBALS['wcms_menu'] = $wcms_menu;
		}
		return $GLOBALS['wcms_menu'];
	}

	/**
     * 숫자로 되어 있는 값을 년, 년 분기, 년 월, 년 월 일 로 형식을 변경하여 리턴
	 * @param string $data 날짜 데이터
     * @return string 변경된 날짜 형식
     */
	public function changeDateFormat($data)
	{
		if (empty($data) || !is_numeric(str_replace('-', '', $data))) {
			return $data;
		}
		
		$cleanData = str_replace('-', '', $data);
		$length = strlen($cleanData);

		// 성능 최적화: switch 대신 배열 매핑 사용
		$formatters = [
			4 => function($data) { return $data . "년"; },
			5 => function($data) { return substr($data, 0, 4) . "년" . substr($data, 4, 1) . "분기"; },
			6 => function($data) { return substr($data, 0, 4) . "년" . substr($data, 4, 2) . "월"; },
			8 => function($data) { return substr($data, 0, 4) . "년" . substr($data, 4, 2) . "월" . substr($data, 6, 2) . "일"; },
			10 => function($data) { return date("Y년 m월 d일", strtotime($data)); }
		];

		if (isset($formatters[$length])) {
			return $formatters[$length]($data);
		}

		return $data;
	}

	/*
	 * yyyy-mm-dd 형식의 날짜를 받아서 해당 기간안의 날짜를 구해온다.
	 */
	public function getDatesStartToLast($startDate, $lastDate)
	{
		$regex = "/^\d{4}-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[0-1])$/";
		if(!(preg_match($regex, $startDate) && preg_match($regex, $lastDate))){
			return [];
		}
		$period = new \DatePeriod( new \DateTime($startDate), new \DateInterval('P1D'), new \DateTime($lastDate." +1 day"));
		$dates = [];
		foreach ($period as $date){
			array_push($dates, $date->format("Y-m-d"));
		}
		return $dates;
	}

	/**
	 * 영상주소 추출
	 * 
	 * - youtube thumbnail
		default(120x90) : https://img.youtube.com/vi/NsI4Z3hC5aE/default.jpg
		medium(320x180) : https://img.youtube.com/vi/NsI4Z3hC5aE/mqdefault.jpg
		high(480x360) : https://img.youtube.com/vi/NsI4Z3hC5aE/hqdefault.jpg
	 * - 네이버TV 영상정보 조회 : thumbnail_url을 제공하지 않는 영상도 있음 (오래된 영상의 경우)
		https://tv.naver.com/oembed?url=https://tv.naver.com/v/23925139&format=json
	 * - 카카오TV : 썸네일 제공 안함
	 */
	public function getVideoInfo($link)
	{
		if (empty($link)) {
			return [
				'link' => $link,
				'provider' => null,
				'id' => null,
				'thumbnail' => null,
			];
		}

		// 성능 최적화: 정규식 패턴을 배열로 관리
		$patterns = [
			'youtube' => [
				'/youtu\.be\/([^\/? ]*)/i',
				'/youtube\.com\/[^\/? ]*\?v=([^\/ ]*)/i',
				'/youtube\.com\/shorts\/([^\/? ]+)/i'
			],
			'navertv' => [
				'/tv\.naver\.com\/v\/([^\/ ]*)/i'
			],
			'kakaotv' => [
				'/tv\.kakao\.com\/v\/([^\/ ]*)/i'
			]
		];

		$provider = null;
		$id = null;

		foreach ($patterns as $providerName => $providerPatterns) {
			foreach ($providerPatterns as $pattern) {
				if (preg_match($pattern, $link, $matches)) {
					$provider = $providerName;
					$id = $matches[1];
					break 2; // 이중 루프 탈출
				}
			}
		}

		$thumbnail = null;
		if ($provider === 'youtube' && $id) {
			$thumbnail = 'https://img.youtube.com/vi/' . $id . '/hqdefault.jpg';
		} elseif ($provider === 'navertv' && $id) {
			// 네이버TV 썸네일 조회 (에러 처리 추가)
			$oembedUrl = 'https://tv.naver.com/oembed?url=https://tv.naver.com/v/' . $id . '&format=json';
			$context = stream_context_create([
				'http' => [
					'timeout' => 5, // 5초 타임아웃
					'user_agent' => 'Mozilla/5.0 (compatible; HKPrice/1.0)'
				]
			]);
			
			$response = @file_get_contents($oembedUrl, false, $context);
			if ($response !== false) {
				$data = json_decode($response, true);
				if (!empty($data['thumbnail_url'])) {
					$thumbnail = $data['thumbnail_url'];
				}
			}
		}

		return [
			'link' => $link,
			'provider' => $provider,
			'id' => $id,
			'thumbnail' => $thumbnail,
		];
	}

	/**
	 * id 타입 체크
	 */
	public function getContentType($id)
	{
		if (strpos($id, ',') !== false) $id = explode(',', $id)[0];
		$contentType = match(true) {
			preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $id) => "daily",
			strpos($id, '!') === 0 => "tag",
			strpos($id, '@') === 0 => "reporterId",
			strpos($id, $this->coId.'_SC') === 0 => "serviceCategoryId",
			strpos($id, $this->coId.'_S') === 0 => "seriesId",
			strpos($id, $this->coId.'_P') === 0 => "programId",
			strlen(str_replace($this->coId, "", $id)) == 9 => "categoryId",
			strlen(str_replace($this->coId, "", $id)) == 12 => "article",
			strlen($id) == 9 => "relation",
			default => "relation"
		};
		return $contentType;
	}

	/**
     * 기자 정보 조회
     */
    public function getReporter($id)
    {
		if (!empty($this->reporters[$id])) {
			return $this->reporters[$id];
		}
        $info = null;
        if (!empty($id)) {
            // info 파일 조회
            $info = $this->json->readJsonFile($this->dataDir.'/reporter', $id);
            // 없으면 DB 조회 후 서비스 파일 생성
            if (empty($info)) {
                // DB 클래스가 있는 경우에만 실행
                if (class_exists('DB') && method_exists('DB', 'item')) {
                    $db = new DB();
                    $info = $db->item('manager', ['id' => $id], ['projection'=>['_id'=>0, 'password'=>0, 'salt'=>0]]);
                    if (!empty($info)) {
                        $this->json->makeJson($this->dataDir.'/reporter', $id, $info);
                    }
                }
            }
        }
		// naverReporter
		if (!empty($info['naverReporter'])) {
			$info['naverReporter'] = array_values($info['naverReporter']);
		}
        // 없으면 ID만 설정
        if (empty($info)) {
            $info = ['id' => $id];
        }
        if ($info['coId'] != $this->coId && !empty($info['allowCompany'])) {
            $temp = $this->searchArray2D($info['allowCompany'], 'coId', $this->coId);
            if (!empty($temp)) {
                if (!empty($temp['departmentId'])) {
                    $info['departmentId'] = $temp['departmentId'][0];
                }
            }
        }
		$info['contentType'] = 'reporter';
        // 불필요 정보 제거
		unset(
			$info['_id'],
			$info['coId'],
			$info['allowCoId'],
			$info['authId'],
			$info['insert'],
			$info['update'],
			$info['delete'],
			$info['latestLogin'],
			$info['favoritesCategory'],
			$info['favoritesSeries'],
			$info['isSuper'],
			$info['allowCompany'],
			$info['favoritesSeries'],
			$info['pagingCnt'],
			$info['isPartner'],
			$info['oldId'],
		);
		$this->reporters[$id] = $info;
        return $info;
    }

	/**
	 * 기사 읽는 시간을 구함 (한글/영문 지원)
	 * 
	 * @param string $content 본문
	 * @param int $wordsPerMinute 읽기 속도 설정 (기본값: 한글 300자/분, 영문 200단어/분)
	 * @param string $language 언어 설정 ('ko' 또는 'en', 기본값: 'ko')
	 * @return int 읽기 시간 (분 단위)
	 */
	public function getReadingTime($content, $wordsPerMinute = null, $language = 'ko')
	{
		if (empty($content)) {
			return 0;
		}

		// 언어별 기본 읽기 속도 설정
		$defaultSpeeds = [
			'ko' => 300, // 한글: 분당 300자 (한국어 평균 읽기 속도)
			'en' => 200  // 영문: 분당 200단어 (영어 평균 읽기 속도)
		];

		// 읽기 속도가 지정되지 않은 경우 언어별 기본값 사용
		if ($wordsPerMinute === null) {
			$wordsPerMinute = $defaultSpeeds[$language] ?? $defaultSpeeds['ko'];
		}

		$readingTimeMinutes = 0;

		if ($language === 'ko') {
			// 한글 기준: 문자 수 계산 (공백 제외)
			$cleanContent = $this->convertTextContent($content);
			$charCount = mb_strlen(preg_replace('/\s+/', '', $cleanContent), 'UTF-8');
			$readingTimeMinutes = ceil($charCount / $wordsPerMinute);
		} else {
			// 영문 기준: 단어 수 계산
			$wordCount = $this->strWordCount($content);
			$readingTimeMinutes = ceil($wordCount / $wordsPerMinute);
		}

		// 최소 1분 보장
		return max(1, $readingTimeMinutes);
	}

	/**
	 * 혼합 언어 콘텐츠의 읽기 시간을 구함 (한글+영문)
	 * 
	 * @param string $content 본문
	 * @param int $koreanCharsPerMinute 한글 읽기 속도 (기본값: 300자/분)
	 * @param int $englishWordsPerMinute 영문 읽기 속도 (기본값: 200단어/분)
	 * @return int 읽기 시간 (분 단위)
	 */
	public function getMixedLanguageReadingTime($content, $koreanCharsPerMinute = 300, $englishWordsPerMinute = 200)
	{
		if (empty($content)) {
			return 0;
		}

		$cleanContent = $this->convertTextContent($content);
		
		// 한글 문자 수 계산
		preg_match_all('/[\p{Hangul}]/u', $cleanContent, $koreanMatches);
		$koreanCharCount = count($koreanMatches[0]);
		
		// 영문 단어 수 계산 (한글 제외)
		$englishContent = preg_replace('/[\p{Hangul}]/u', ' ', $cleanContent);
		preg_match_all('/\b[a-zA-Z]+\b/', $englishContent, $englishMatches);
		$englishWordCount = count($englishMatches[0]);

		// 각 언어별 읽기 시간 계산
		$koreanTime = $koreanCharCount > 0 ? ceil($koreanCharCount / $koreanCharsPerMinute) : 0;
		$englishTime = $englishWordCount > 0 ? ceil($englishWordCount / $englishWordsPerMinute) : 0;

		// 총 읽기 시간 (최소 1분 보장)
		return max(1, $koreanTime + $englishTime);
	}

	/**
	 * 입력값 보안 검증 및 정제
	 * 
	 * @param string $input 입력값
	 * @param int $maxLength 최대 길이 (기본값: 255)
	 * @param bool $allowHtml HTML 허용 여부 (기본값: false)
	 * @return string 정제된 입력값
	 */
	public function sanitizeInput($input, $maxLength = 255, $allowHtml = false)
	{
		if (empty($input)) {
			return '';
		}
		
		// 입력값 타입 검증
		if (!is_string($input)) {
			throw new \Exception('입력값은 문자열이어야 합니다.');
		}
		
		// 길이 제한 (UTF-8 고려)
		if (mb_strlen($input, 'UTF-8') > $maxLength) {
			throw new \Exception("입력값은 {$maxLength}자를 초과할 수 없습니다.");
		}
		
		// 앞뒤 공백 제거
		$input = trim($input);
		
		// HTML 처리
		if (!$allowHtml) {
			// HTML 태그 제거
			$input = strip_tags($input);
			// 특수 문자 이스케이프
			$input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		}
		
		// 보안 패턴 검증 (성능 최적화: 컴파일된 정규식 사용)
		static $dangerousPatterns = null;
		if ($dangerousPatterns === null) {
			$dangerousPatterns = [
				'/\b(union|select|insert|update|delete|drop|create|alter|exec|execute)\b/i',
				'/\b(or|and)\s+\d+\s*=\s*\d+/i',
				'/\b(script|javascript|vbscript|onload|onerror)\b/i',
				'/\b(load_file|into\s+outfile|into\s+dumpfile)\b/i',
				'/\b(concat|char|ascii|substring|mid)\b/i',
				'/\b(iframe|object|embed|form)\b/i',
				'/\b(eval|expression|function)\b/i'
			];
		}
		
		foreach ($dangerousPatterns as $pattern) {
			if (preg_match($pattern, $input)) {
				throw new \Exception('잘못된 입력값이 감지되었습니다.');
			}
		}
		
		return $input;
	}

	/**
	 * 카테고리 ID 처리 및 보안 검증
	 * 
	 * @param string $categoryId 카테고리 ID
	 * @param string $coId 회사 ID (기본값: 현재 coId)
	 * @return string 완전한 카테고리 ID
	 */
	public function processCategoryId($categoryId, $coId = null)
	{
		if (empty($categoryId)) {
			throw new \Exception('카테고리 ID가 필요합니다.');
		}
		
		// coId가 지정되지 않은 경우 현재 coId 사용
		if ($coId === null) {
			$coId = $this->coId;
		}
		
		// 카테고리 ID 형식 검증 (숫자와 영문자만 허용)
		if (!preg_match('/^[a-zA-Z0-9]+$/', $categoryId)) {
			throw new \Exception('카테고리 ID는 영문자와 숫자만 허용됩니다.');
		}
		
		// 길이 검증 (최대 20자)
		if (strlen($categoryId) > 20) {
			throw new \Exception('카테고리 ID는 20자를 초과할 수 없습니다.');
		}
		
		// coId가 포함되어 있지 않은 경우 자동 추가
		if (!str_starts_with($categoryId, $coId)) {
			// 숫자만 있는 경우 (예: 001000000000)
			if (preg_match('/^\d+$/', $categoryId)) {
				$categoryId = $coId . $categoryId;
			} else {
				// 이미 다른 coId가 포함된 경우 검증
				$validCoIds = ['hkp', 'test', 'demo', 'dev']; // 허용된 coId 목록
				$isValidCoId = false;
				
				foreach ($validCoIds as $validCoId) {
					if (str_starts_with($categoryId, $validCoId)) {
						$isValidCoId = true;
						break;
					}
				}
				
				if (!$isValidCoId) {
					throw new \Exception('유효하지 않은 카테고리 ID 형식입니다.');
				}
			}
		}
		
		return $categoryId;
	}

	/**
	 * 숫자 ID 검증 및 정제
	 * 
	 * @param mixed $id ID 값
	 * @param string $fieldName 필드명 (에러 메시지용)
	 * @return int 정제된 숫자 ID
	 */
	public function sanitizeNumericId($id, $fieldName = 'ID')
	{
		if (empty($id)) {
			throw new \Exception("{$fieldName}가 필요합니다.");
		}
		
		// 숫자로 변환
		$numericId = intval($id);
		
		// 유효한 숫자인지 확인
		if ($numericId <= 0) {
			throw new \Exception("유효하지 않은 {$fieldName}입니다.");
		}
		
		return $numericId;
	}

	/**
	 * 날짜 형식 검증
	 * 
	 * @param string $date 날짜 문자열
	 * @param string $format 날짜 형식 (기본값: Y-m-d)
	 * @return bool 유효한 날짜인지 여부
	 */
	public function isValidDateFormat($date, $format = 'Y-m-d')
	{
		if (empty($date)) {
			return false;
		}
		
		$d = \DateTime::createFromFormat($format, $date);
		return $d && $d->format($format) === $date;
	}

	/**
	 * 이메일 형식 검증
	 * 
	 * @param string $email 이메일 주소
	 * @return bool 유효한 이메일인지 여부
	 */
	public function isValidEmail($email)
	{
		if (empty($email)) {
			return false;
		}
		
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	/**
	 * URL 형식 검증
	 * 
	 * @param string $url URL 주소
	 * @return bool 유효한 URL인지 여부
	 */
	public function isValidUrl($url)
	{
		if (empty($url)) {
			return false;
		}
		
		return filter_var($url, FILTER_VALIDATE_URL) !== false;
	}

	/**
	 * 단어 수 구함 (한글 지원)
	 * str_word_count는 한글을 지원하지 않으므로 만듦
	 * 
	 * @param string $string 분석할 문자열
	 * @return int 단어 수
	 */
	public function strWordCount($string)
	{
		// 정규 표현식을 사용하여 한글과 알파벳, 숫자를 단어로 인식
		preg_match_all('/[\p{Hangul}]+|[\p{L}\p{N}]+/u', $this->convertTextContent($string), $matches);
		return count($matches[0]);
	}

	/**
	 * 리스트용 본문 글자 수
	 * 에디터의 본문 수와 동일해야 하므로 에디터의 소스정리 결과와 유사하도록 처리
	 * 
	 * @todo 예외가 발견되면 보완해야 함
	 */
	public function getContentTextLength($content)
	{
		$content = preg_replace('/(&nbsp;)$/m', '', $content);
		$content = preg_replace('/<(p|br)[^<>]*>/im', "\n", $content);
		$content = trim(str_replace(["\r"], [""], $content));
		$content = preg_replace('/<(figcaption)>[^<>]*<\/\1>/im', "", $content);
		$content = preg_replace('/<(iframe)[^<>]*>[^<>]*<\/\1>/im', "", $content);
		$content = preg_replace('/<(img)[^<>]*>/im', "", $content);
		$content = preg_replace('/<(figure)[^<>]*>[^<>]*<\/\1>( )*(\n)?/im', "\n", $content);
		$content = preg_replace('/(\n)( )+([^\n ])/im', '$1$3', $content);
		$content = preg_replace('/(\n)( )+(\n)/im', "$1$3", $content);
		$content = preg_replace('/( )+/im', ' ', $content);
		$content = html_entity_decode(strip_tags($content));
		$content = trim($content, " \r\t\v\x00");
		return mb_strlen($content, 'utf-8');
	}
}