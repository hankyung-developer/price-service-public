<?php
/**
 * ================================================================================
 * 한경 WCMS 자동 기사 전송 스크립트 (sendToHkWcms.php)
 * ================================================================================
 * 
 * 목적:
 *   AI 생성 기사를 한경 WCMS 시스템에 자동으로 전송하는 배치 스크립트
 *   - 이미지 업로드 후 PID 확보
 *   - 기사 본문과 메타데이터를 API로 전송
 * 
 * 실행 방법:
 *   php sendToHkWcms.php
 * 
 * 환경:
 *   - PHP 7.4+ (curl 확장 필수)
 *   - composer 의존성 없음
 * 
 * crontab 설정 예시:
 *   # 매 1분마다 실행
 *   * * * * * /usr/bin/php /path/to/wcms/cron/sendToHkWcms.php >> /path/to/wcms/cron/logs/sendToHkWcms.log 2>&1
 * ================================================================================
 */

// ================================================================================
// 설정 영역
// ================================================================================

// API 엔드포인트 설정
// define('API_BASE_URL', 'https://testcms.hankyung.com'); // 기사 테스트 환경
// define('IMAGE_API_BASE_URL', 'https://testcms.hankyung.com'); // 이미지 테스트 환경
define('API_BASE_URL', 'https://hkicms.hankyung.com'); // 기사 전송 운영 환경
define('IMAGE_API_BASE_URL', '211.115.69.123'); // 이미지 전송 운영 환경

// 인증 토큰 (Python 코드와 동일)
define('API_TOKEN', '8DAA7145-4E02-4A66-896E-159AA9F9C111');

// 매체 아이디
define('MEDIA_ID', 'AN'); // 예: AN, 0D 등

// 전송 주체
define('MEDIA', 'HKAI');

// 부서 아이디
define('DEPT_ID', '39');

// 기사 저장 디렉터리
define('ARTICLE_DIR', __DIR__ . '/../sendArticle');

// 로그 디렉터리
define('LOG_DIR', __DIR__ . '/logs');

// HTTP 타임아웃 (초)
define('HTTP_TIMEOUT', 30);

// Autoload 및 클래스 로드
require_once '/webSiteSource/wcms/classes/autoload.php';

// ================================================================================
// 메인 실행 로직
// ================================================================================

class HkWcmsSender
{
    private $baseUrl;
    private $imageBaseUrl;
    private $token;
    private $mediaId;
    private $media;
    private $deptId;
    private $db;
    
    /**
     * 생성자
     */
    public function __construct()
    {
        $this->baseUrl = API_BASE_URL;
        $this->imageBaseUrl = IMAGE_API_BASE_URL;
        $this->token = API_TOKEN;
        $this->mediaId = MEDIA_ID;
        $this->media = MEDIA;
        $this->deptId = DEPT_ID;
        
        // DB 인스턴스 생성
        $this->db = new \Kodes\Wcms\DB();
        
        // 로그 디렉터리 생성
        if (!is_dir(LOG_DIR)) {
            mkdir(LOG_DIR, 0755, true);
        }
    }
    
    /**
     * 로그 기록 함수
     * 
     * @param string $message 로그 메시지
     * @param string $level 로그 레벨 (INFO, ERROR, WARNING)
     */
    private function log($message, $level = 'INFO')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        // 콘솔 출력
        echo $logMessage;
        
        // 파일 기록
        $logFile = LOG_DIR . '/sendToHkWcms_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * 이미지 업로드 API 호출
     * 
     * @param string $imagePath 이미지 파일 경로
     * @return array ['success' => bool, 'pid' => string, 'message' => string]
     */
    private function uploadImage($imagePath)
    {
        $this->log("이미지 업로드 시작: {$imagePath}");
        
        // 파일 존재 확인
        if (!file_exists($imagePath)) {
            $this->log("이미지 파일이 존재하지 않음: {$imagePath}", 'ERROR');
            return ['success' => false, 'pid' => '', 'message' => '파일이 존재하지 않습니다.'];
        }
        
        // API URL
        $url = $this->imageBaseUrl . '/api/imgRecv';
        
        // multipart/form-data 준비 (Python 코드와 동일: img[] 형식)
        $curlFile = new CURLFile($imagePath, mime_content_type($imagePath), basename($imagePath));
        
        $postData = [
            'runtype' => '1',
            'mediaid' => $this->mediaId,
            'token' => $this->token,
            'img[]' => $curlFile // Python 코드처럼 img[] 형식 사용
        ];
        
        $this->log("이미지 파일 포스트 데이터: " . json_encode($postData, JSON_UNESCAPED_UNICODE), 'INFO');

        // cURL 초기화
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => HTTP_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'HK-WCMS-Auto-Sender/1.0'
        ]);
        
        // API 호출
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // HTTP 오류 체크
        if ($curlError) {
            $this->log("cURL 오류: {$curlError}", 'ERROR');
            return ['success' => false, 'pid' => '', 'message' => $curlError];
        }
        
        if ($httpCode !== 200) {
            $this->log("HTTP 오류 코드: {$httpCode}", 'ERROR');
            return ['success' => false, 'pid' => '', 'message' => "HTTP {$httpCode} 오류"];
        }
        
        // JSON 파싱
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON 파싱 오류: " . json_last_error_msg(), 'ERROR');
            $this->log("응답 내용: {$response}", 'ERROR');
            return ['success' => false, 'pid' => '', 'message' => 'JSON 파싱 실패'];
        }
        
        // API 응답 로그
        $this->log("이미지 업로드 응답: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        
        // 결과 확인
        if (isset($result['result']) && $result['result'] === 'success') {
            $pid = $result['photo_list'] ?? '';
            $this->log("이미지 업로드 성공 - PID: {$pid}");
            return ['success' => true, 'pid' => $pid, 'message' => $result['message'] ?? ''];
        } else {
            $message = $result['message'] ?? '알 수 없는 오류';
            $this->log("이미지 업로드 실패: {$message}", 'ERROR');
            return ['success' => false, 'pid' => '', 'message' => $message];
        }
    }
    
    /**
     * PHOTO_LIST 문자열 생성
     * Python 코드와 동일: "파일명^PID|파일명2^PID2" (캡션 제외)
     * 
     * @param array $images [['filename' => '...', 'caption' => '...', 'pid' => '...'], ...]
     * @return string
     */
    private function buildPhotoList($images)
    {
        $photoListParts = [];

        foreach ($images as $image) {
            $filename = $image['filename'] ?? '';
            $pid = $image['pid'] ?? '';
            
            // Python 코드처럼 "파일명^PID" 형태 (캡션 없음)
            $photoListParts[] = "{$filename}^{$pid}";
        }
        
        // "|"로 결합
        return implode('|', $photoListParts);
    }
    
    /**
     * 기사 저장 API 호출
     * 
     * @param array $articleData 기사 데이터 (JSON에서 로드)
     * @param string $photoList PHOTO_LIST 문자열
     * @return array ['success' => bool, 'message' => string]
     */
    private function saveArticle($articleData, $photoList)
    {
        $action = "send";  // "test" 또는 "send"
        
        $this->log("기사 저장 시작: " . ($articleData['ORGARTICLEID'] ?? 'UNKNOWN') . " (모드: {$action})");
        
        // 테스트 모드일 경우 API 전송 없이 저장만 수행
        if ($action === "test") {
            $this->log("테스트 모드: API 전송 없이 저장만 수행");
            
            // 테스트 결과 구성 (API 응답 시뮬레이션)
            $result = [
                'errorCode' => 'test_success',
                'errorMessage' => '테스트 모드 - 실제 전송 없이 저장만 완료'
            ];
            
            // success 폴더에 저장 (테스트이므로 항상 성공으로 처리)
            $this->saveArticleToFolder('success', $articleData, $photoList, $result);
            
            return ['success' => true, 'message' => '테스트 모드 - 저장 완료 (전송 안함)'];
        }
        
        // 실제 전송 모드 (action !== "test")
        // API URL
        $url = $this->baseUrl . '/api/save';
        
        // POST 데이터 준비 (Python 코드와 동일한 필드 구성)
        $postData = [
            'ORGARTICLEID' => $articleData['ORGARTICLEID'] ?? '',
            'ISRECV' => 'Y', // 무조건 Y
            'media' => $this->media,
            'MEDIAID' => $this->mediaId,
            'TITLE' => $articleData['TITLE'] ?? '',
            'SUBTITLE' => $articleData['SUBTITLE'] ?? '',
            'TEXTCONTENT' => str_replace(["\n","\r"],"",$articleData['TEXTCONTENT'] ?? ''),
            'CONTENTS_CODE' => $articleData['CONTENTS_CODE'] ?? '0100',
            'token' => $this->token,
            'PHOTO_LIST' => $photoList,
            'ISEMBARGO' => $articleData['ISEMBARGO'] ?? 'N',
            'EMBARGODATE' => $articleData['EMBARGODATE'] ?? '',
            'DEPTID' => $this->deptId,
            'PHOTOKIND' => '4', // 일반
            'ISMATCHING_PHOTO' => $articleData['ISMATCHING_PHOTO'] ?? 'Y',
            'SEND_WEB' => $articleData['SEND_WEB'] ?? 'N',
            'SEND_PORTAL' => $articleData['SEND_PORTAL'] ?? 'N',
            'SEND_HOST' => $articleData['SEND_HOST'] ?? 'N',
            'SEND_NAVER' => $articleData['SEND_NAVER'] ?? 'N',
            'SEND_DAUM' => $articleData['SEND_DAUM'] ?? 'N',
            'SEND_NATE' => $articleData['SEND_NATE'] ?? 'N',
            'SEND_ZOOM' => $articleData['SEND_ZOOM'] ?? 'N',
            'HASHTAG' => $articleData['HASHTAG'] ?? '',
        ];
        
        // PRICE_LIST 처리 (id|name 형식의 문자열 배열로 변환)
        if (isset($articleData['PRICE_LIST']) && is_array($articleData['PRICE_LIST'])) {
            $priceListArray = [];
            foreach ($articleData['PRICE_LIST'] as $priceItem) {
                if (isset($priceItem['id']) && isset($priceItem['name'])) {
                    $priceListArray[] = $priceItem['id'] . '|' . $priceItem['name'];
                }
            }
            $postData['PRICE_LIST'] = $priceListArray;
        } else {
            $postData['PRICE_LIST'] = '[]'; // 기본값
        }

        // cURL 초기화
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => HTTP_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'HK-WCMS-Auto-Sender/1.0',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        // API 호출
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // HTTP 오류 체크
        if ($curlError) {
            $this->log("cURL 오류: {$curlError}", 'ERROR');
            
            // 실패 시 error/ 폴더에 저장
            $errorResult = ['errorCode' => 'curl_error', 'errorMessage' => $curlError];
            $this->saveArticleToFolder('error', $articleData, $photoList, $errorResult);
            
            return ['success' => false, 'message' => $curlError];
        }
        
        if ($httpCode !== 200) {
            $this->log("HTTP 오류 코드: {$httpCode}", 'ERROR');
            
            // 실패 시 error/ 폴더에 저장
            $errorResult = ['errorCode' => 'http_error', 'errorMessage' => "HTTP {$httpCode} 오류"];
            $this->saveArticleToFolder('error', $articleData, $photoList, $errorResult);
            
            return ['success' => false, 'message' => "HTTP {$httpCode} 오류"];
        }
        
        // JSON 파싱
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON 파싱 오류: " . json_last_error_msg(), 'ERROR');
            $this->log("응답 내용: {$response}", 'ERROR');
            
            // 실패 시 error/ 폴더에 저장
            $errorResult = ['errorCode' => 'json_error', 'errorMessage' => 'JSON 파싱 실패', 'raw_response' => $response];
            $this->saveArticleToFolder('error', $articleData, $photoList, $errorResult);
            
            return ['success' => false, 'message' => 'JSON 파싱 실패'];
        }
        
        // API 응답 로그
        $this->log("기사 저장 응답: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        
        // 결과 확인
        if (isset($result['errorCode']) && $result['errorCode'] === 'success') {
            $this->log("기사 저장 성공");
            
            // 성공 시 success/ 폴더에 저장
            $this->saveArticleToFolder('success', $articleData, $photoList, $result);
            
            return ['success' => true, 'message' => $result['errorMessage'] ?? ''];
        } else {
            $message = $result['errorMessage'] ?? '알 수 없는 오류';
            $this->log("기사 저장 실패: {$message}", 'ERROR');
            
            // 실패 시 error/ 폴더에 저장
            $this->saveArticleToFolder('error', $articleData, $photoList, $result);
            
            return ['success' => false, 'message' => $message];
        }
    }
    
    /**
     * 기사 및 이미지를 폴더에 저장
     * 
     * @param string $folderType 'success' 또는 'error'
     * @param array $articleData 기사 데이터
     * @param array $photoList 사진 목록
     * @param array $apiResult API 응답 결과
     */
    private function saveArticleToFolder($folderType, $articleData, $photoList, $apiResult)
    {
        try {
            // 기본 디렉토리 경로
            $baseDir = "/webSiteSource/wcms/sendArticle";
            $targetDir = $baseDir . '/' . $folderType;
            
            // 디렉토리 생성 (없으면)
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            $orgArticleId = $articleData['ORGARTICLEID'] ?? 'UNKNOWN';
            
            // 1. JSON 파일 저장 (기사 데이터 + API 응답)
            $jsonData = [
                'saved_at' => date('Y-m-d H:i:s'),
                'folder_type' => $folderType,
                'article_data' => $articleData,
                'photo_list' => $photoList,
                'api_response' => $apiResult
            ];
            
            $jsonFilePath = $targetDir . '/' . $orgArticleId . '.json';
            file_put_contents($jsonFilePath, json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $this->log("JSON 파일 저장 완료: {$jsonFilePath}");
            
            // 2. 이미지 파일 복사 (원본 파일이 있는 경우)
            if (!empty($articleData['images']) && is_array($articleData['images'])) {
                $imageDir = $targetDir . '/images';
                if (!is_dir($imageDir)) {
                    mkdir($imageDir, 0755, true);
                }
                
                foreach ($articleData['images'] as $index => $image) {
                    if (isset($image['path'])) {
                        // 원본 이미지 경로 (sendArticle 디렉토리 기준)
                        $sourcePath = '/webSiteSource/wcms/sendArticle/' . $image['path'];
                        
                        if (file_exists($sourcePath)) {
                            $filename = basename($image['path']);
                            $targetPath = $imageDir . '/' . $orgArticleId . '_' . $index . '_' . $filename;
                            
                            if (copy($sourcePath, $targetPath)) {
                                $this->log("이미지 복사 완료: {$filename}");
                            } else {
                                $this->log("이미지 복사 실패: {$sourcePath}", 'WARNING');
                            }
                        } else {
                            $this->log("이미지 파일 없음: {$sourcePath}", 'WARNING');
                        }
                    }
                }
            }
            
            $this->log("{$folderType} 폴더에 기사 저장 완료: {$orgArticleId}");
            
        } catch (\Exception $e) {
            $this->log("폴더 저장 중 오류: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * 전송 완료된 파일 삭제
     * 
     * @param string $jsonFilePath JSON 파일 경로
     * @param array $articleData 기사 데이터
     * @return array ['success' => bool, 'deleted_images' => int, 'message' => string]
     */
    private function deleteTransferredFiles($jsonFilePath, $articleData)
    {
        $deletedImages = 0;
        $failedDeletes = [];
        
        try {
            // 1. 이미지 파일 삭제
            if (isset($articleData['images']) && is_array($articleData['images'])) {
                foreach ($articleData['images'] as $imageInfo) {
                    $imagePath = $imageInfo['path'] ?? '';
                    
                    if (empty($imagePath)) {
                        continue;
                    }
                    
                    // 상대 경로를 절대 경로로 변환
                    $fullImagePath = $imagePath;
                    if (!file_exists($fullImagePath)) {
                        $fullImagePath = '/webSiteSource/wcms/sendArticle/images/' . basename($imagePath);
                    }
                    
                    // 이미지 파일 삭제
                    if (file_exists($fullImagePath)) {
                        if (unlink($fullImagePath)) {
                            $deletedImages++;
                            $this->log("이미지 삭제: " . basename($fullImagePath));
                        } else {
                            $failedDeletes[] = basename($fullImagePath);
                            $this->log("이미지 삭제 실패: " . basename($fullImagePath), 'WARNING');
                        }
                    }
                }
            }
            
            // 2. JSON 파일 삭제
            if (file_exists($jsonFilePath)) {
                if (unlink($jsonFilePath)) {
                    $this->log("JSON 파일 삭제: " . basename($jsonFilePath));
                } else {
                    $failedDeletes[] = basename($jsonFilePath);
                    $this->log("JSON 파일 삭제 실패: " . basename($jsonFilePath), 'WARNING');
                }
            }
            
            // 3. MongoDB article collection 업데이트 (send.date 기록)
            $aid = $articleData['ORGARTICLEID'] ?? '';
            if (!empty($aid)) {
                try {
                    $updateData = [
                        'send' => [
                            'date' => date('Y-m-d H:i:s')
                        ]
                    ];
                    
                    $updateResult = $this->db->update(
                        'article',
                        ['aid' => $aid],
                        ['$set' => $updateData]
                    );
                    
                    $this->log("MongoDB 업데이트 완료: aid={$aid}, send.date=" . date('Y-m-d H:i:s'));
                } catch (\Exception $e) {
                    $this->log("MongoDB 업데이트 실패: " . $e->getMessage(), 'WARNING');
                    // MongoDB 업데이트 실패는 치명적이지 않으므로 계속 진행
                }
            } else {
                $this->log("기사 ID(aid)가 없어 MongoDB 업데이트 생략", 'WARNING');
            }
            
            // 결과 반환
            if (empty($failedDeletes)) {
                return [
                    'success' => true,
                    'deleted_images' => $deletedImages,
                    'message' => '모든 파일 삭제 완료'
                ];
            } else {
                return [
                    'success' => false,
                    'deleted_images' => $deletedImages,
                    'message' => '일부 파일 삭제 실패: ' . implode(', ', $failedDeletes)
                ];
            }
            
        } catch (Exception $e) {
            $this->log("파일 삭제 중 예외 발생: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'deleted_images' => $deletedImages,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 단일 기사 처리 (이미지 업로드 + 기사 전송)
     * 
     * @param string $jsonFilePath 기사 JSON 파일 경로
     * @return bool 성공 여부
     */
    public function processArticle($jsonFilePath)
    {
        $this->log("========================================");
        $this->log("기사 처리 시작: {$jsonFilePath}");
        
        // JSON 파일 읽기
        if (!file_exists($jsonFilePath)) {
            $this->log("JSON 파일이 존재하지 않음: {$jsonFilePath}", 'ERROR');
            return false;
        }
        
        $jsonContent = file_get_contents($jsonFilePath);
        $articleData = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("JSON 파일 파싱 오류: " . json_last_error_msg(), 'ERROR');
            return false;
        }

        // 이미지 업로드 처리
        $uploadedImages = [];
        
        if (isset($articleData['images']) && is_array($articleData['images'])) {
            foreach ($articleData['images'] as $imageInfo) {
                $imagePath = $imageInfo['path'] ?? '';
                $caption = $imageInfo['caption'] ?? '';
                
                // 상대 경로를 절대 경로로 변환
                if (!file_exists($imagePath)) {
                    $imagePath = '/webSiteSource/wcms/sendArticle/images/' . basename($imagePath);
                }
                
                // 이미지 업로드
                $uploadResult = $this->uploadImage($imagePath);
                
                if ($uploadResult['success']) {
                    $uploadedImages[] = [
                        'filename' => basename($imagePath),
                        'caption' => $caption,
                        'pid' => $uploadResult['pid']
                    ];

                    $re = '/["].+'.basename($imagePath).'/';
                    $subst = '/photo/load/?pid='.$uploadResult['pid'].'&size=1&mediaid=AN';
                    $articleData['TEXTCONTENT'] = preg_replace($re, $subst, $articleData['TEXTCONTENT']);
                } else {
                    $this->log("이미지 업로드 실패로 기사 전송 중단: {$imagePath}", 'ERROR');
                    return false;
                }
            }
        }

        // PHOTO_LIST 생성
        $photoList = $this->buildPhotoList($uploadedImages);
        $this->log("PHOTO_LIST: {$photoList}");
        

        // 기사 저장
        $saveResult = $this->saveArticle($articleData, $photoList);
        
        if ($saveResult['success']) {
            $this->log("기사 처리 완료: {$jsonFilePath}");
            
            // 전송 성공 시 파일 삭제
            $deleteResult = $this->deleteTransferredFiles($jsonFilePath, $articleData);
            if ($deleteResult['success']) {
                $this->log("전송 파일 삭제 완료 - JSON: 1개, 이미지: {$deleteResult['deleted_images']}개");
            } else {
                $this->log("전송 파일 삭제 중 일부 실패: {$deleteResult['message']}", 'WARNING');
            }
            
            

            return true;
        } else {
            $this->log("기사 처리 실패: {$jsonFilePath}", 'ERROR');
            return false;
        }
    }
    
    /**
     * 디렉터리 내 모든 기사 처리
     * 
     * @return array ['total' => int, 'success' => int, 'fail' => int]
     */
    public function processAllArticles()
    {
        $this->log("========================================");
        $this->log("전체 기사 배치 처리 시작");
        $this->log("========================================");
        
        // 기사 디렉터리 확인
        if (!is_dir(ARTICLE_DIR)) {
            $this->log("기사 디렉터리가 존재하지 않음: " . ARTICLE_DIR, 'ERROR');
            return ['total' => 0, 'success' => 0, 'fail' => 0];
        }
        
        // JSON 파일 목록 가져오기
        $jsonFiles = glob(ARTICLE_DIR . '/*.json');
        
        if (empty($jsonFiles)) {
            $this->log("처리할 기사가 없습니다.", 'WARNING');
            return ['total' => 0, 'success' => 0, 'fail' => 0];
        }
        
        $total = count($jsonFiles);
        $success = 0;
        $fail = 0;
        
        // 각 기사 처리
        foreach ($jsonFiles as $jsonFile) {
            if ($this->processArticle($jsonFile)) {
                $success++;
            } else {
                $fail++;
            }
        }
        
        $this->log("========================================");
        $this->log("배치 처리 완료 - 전체: {$total}, 성공: {$success}, 실패: {$fail}");
        $this->log("========================================");
        
        return ['total' => $total, 'success' => $success, 'fail' => $fail];
    }
}

// ================================================================================
// 실행
// ================================================================================

try {
    $sender = new HkWcmsSender();
    $result = $sender->processAllArticles();
    
    // 종료 코드 (실패가 있으면 1, 없으면 0)
    exit($result['fail'] > 0 ? 1 : 0);
    
} catch (Exception $e) {
    echo "[FATAL ERROR] " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

