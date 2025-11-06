<?php 
namespace Kodes\Wcms;

/**
 * AI 서비스 공통 인터페이스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
abstract class AIInterface
{
    /** @var Class 공통 */
    protected $common;
    protected $json;
    protected $db;
    
    /** @var string API 키 */
    protected $apiKey;
    
    /** @var string API 엔드포인트 */
    protected $apiEndpoint;
    
    /** @var array 기본 설정 */
    protected $defaultConfig;
    
    /** @var array 응답 헤더 */
    protected $headers;

    public function __construct()
    {
        // class
        $this->common = new Common();
        $this->json = new Json();
        $this->db = new DB();
        
        // 기본 설정 초기화
        $this->initializeConfig();
    }

    /**
     * AI 서비스별 설정 초기화
     */
    abstract protected function initializeConfig();

    /**
     * 프롬프트 전송 및 JSON 응답 받기
     * 
     * @param string $prompt 프롬프트
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    abstract public function sendPrompt($prompt, $options = []);

    /**
     * 멀티모달 프롬프트 전송 (이미지 포함) - 선택적 구현
     * 
     * @param string $prompt 프롬프트
     * @param array $images 이미지 데이터 배열
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function sendMultimodalPrompt($prompt, $images, $options = [])
    {
        throw new \Exception('멀티모달 프롬프트는 이 AI 서비스에서 지원하지 않습니다.');
    }

    /**
     * HTTP 요청 전송
     * 
     * @param string $url 요청 URL
     * @param array $data POST 데이터
     * @param array $headers 요청 헤더
     * @return array 응답 데이터
     */
    protected function sendRequest($url, $data = [], $headers = [])
    {
        try {
            // cURL이 사용 가능한 경우 cURL 사용
            if (function_exists('curl_init')) {
                return $this->sendRequestWithCurl($url, $data, $headers);
            } else {
                // cURL이 없는 경우 file_get_contents 사용
                return $this->sendRequestWithFileGetContents($url, $data, $headers);
            }
        } catch (\Exception $e) {
            // 로그 기록
            $this->logError($e->getMessage(), $url, $data);
            
            // AJAX 호환 응답 반환
            return [
                'status' => 'error',
                'msg' => 'API 요청 중 오류가 발생했습니다: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'http_code' => ($e->getCode() ?: 500),
                'raw_response' => null
            ];
        }
    }
    
    /**
     * cURL을 사용한 HTTP 요청 전송
     */
    private function sendRequestWithCurl($url, $data = [], $headers = [])
    {
        $maxAttempts = 3;            // 재시도 횟수
        $baseBackoffSec = 0.5;       // 초기 대기 시간 (지수 백오프)

        $lastErrorMessage = '';
        $lastHttpCode = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init();

            // 기본 CURL 옵션 설정 (보수적 타임아웃)
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2분으로 증가
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'hkprice-wcms/1.0 (+curl)');
            // 네트워크 안정성 옵션
            if (defined('CURL_IPRESOLVE_V4')) {
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            }
            // 일부 프록시/보안장비 호환을 위해 HTTP/1.1 사용
            if (defined('CURL_HTTP_VERSION_1_1')) {
                curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            }
            if (defined('CURLOPT_TCP_KEEPALIVE')) {
                curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
            }
            // 압축 응답 허용
            if (defined('CURLOPT_ENCODING')) {
                curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
            }

            // 헤더 설정
            $requestHeaders = array_merge($this->headers, $headers);
            // 기본 Accept 헤더 보강 및 100-continue 비활성화
            $hasAccept = false;
            $hasExpect = false;
            foreach ($requestHeaders as $h) {
                if (stripos($h, 'Accept:') === 0) $hasAccept = true;
                if (stripos($h, 'Expect:') === 0) $hasExpect = true;
            }
            if (!$hasAccept) $requestHeaders[] = 'Accept: application/json';
            // 일부 환경에서 100-continue 대기 문제 방지
            if (!$hasExpect) $requestHeaders[] = 'Expect:';
            if (!empty($requestHeaders)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
            }

            // POST 데이터 설정
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            $response = curl_exec($ch);
            $curlErrNo = curl_errno($ch);
            $curlErrStr = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $nameLookupTime = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
            $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
            $appConnectTime = curl_getinfo($ch, CURLINFO_APPCONNECT_TIME);
            $startTransferTime = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
            $primaryIp = curl_getinfo($ch, CURLINFO_PRIMARY_IP);

            curl_close($ch);

            // 성공 (2xx)
            if ($curlErrNo === 0 && $httpCode >= 200 && $httpCode < 300) {
                $decodedResponse = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("JSON Decode Error: " . json_last_error_msg(), $httpCode ?: 0);
                }
                return $decodedResponse;
            }

            // 실패 처리: 재시도 가능한 오류인지 판단
            $retryableCurlErrors = [
                CURLE_OPERATION_TIMEDOUT,
                CURLE_COULDNT_CONNECT,
                CURLE_COULDNT_RESOLVE_HOST,
                CURLE_COULDNT_RESOLVE_PROXY,
                CURLE_RECV_ERROR,
                CURLE_SEND_ERROR
            ];
            $retryableHttpCodes = [408, 409, 425, 429]; // 혼잡/대기 관련

            $isRetryable = in_array($curlErrNo, $retryableCurlErrors, true)
                || in_array($httpCode, $retryableHttpCodes, true)
                || ($httpCode >= 500 && $httpCode <= 599);

            // 에러 메시지 구성 (로그 및 최종 예외 메시지)
            $diagnostics = [
                'attempt' => $attempt,
                'http_code' => $httpCode,
                'curl_errno' => $curlErrNo,
                'curl_error' => $curlErrStr,
                'timings' => [
                    'total' => $totalTime,
                    'namelookup' => $nameLookupTime,
                    'connect' => $connectTime,
                    'appconnect' => $appConnectTime,
                    'starttransfer' => $startTransferTime
                ],
                'primary_ip' => $primaryIp
            ];

            // HTTP 400 오류의 경우 응답 본문에서 에러 메시지 추출
            $errorMessage = 'HTTP 요청 실패: ' . json_encode($diagnostics, JSON_UNESCAPED_UNICODE);
            if ($httpCode === 400 && is_string($response) && $response !== '') {
                $errorResponse = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($errorResponse['error'])) {
                    $apiError = $errorResponse['error'];
                    $errorMessage = 'API 오류: ' . ($apiError['message'] ?? '알 수 없는 오류');
                    
                    // 특정 오류 타입에 대한 사용자 친화적 메시지
                    if (isset($apiError['type'])) {
                        if ($apiError['type'] === 'invalid_request_error' && strpos($apiError['message'], 'credit balance') !== false) {
                            $errorMessage = 'API 크레딧이 부족합니다. 계정을 업그레이드하거나 크레딧을 구매해주세요.';
                        } elseif ($apiError['type'] === 'authentication_error') {
                            $errorMessage = 'API 인증에 실패했습니다. API 키를 확인해주세요.';
                        } elseif ($apiError['type'] === 'rate_limit_error') {
                            $errorMessage = 'API 요청 한도를 초과했습니다. 잠시 후 다시 시도해주세요.';
                        }
                    }
                }
            }

            $lastErrorMessage = $errorMessage;
            $lastHttpCode = $httpCode ?: 0;

            // 재시도
            if ($attempt < $maxAttempts && $isRetryable) {
                $sleepSec = $baseBackoffSec * pow(2, $attempt - 1); // 0.5, 1.0, 2.0 ...
                usleep((int)($sleepSec * 1_000_000));
                continue;
            }

            // 재시도 불가 또는 마지막 시도 실패 → 예외 발생
            // 응답 본문 일부를 포함 (너무 길면 잘라서)
            $snippet = '';
            if (is_string($response) && $response !== '') {
                $snippet = substr($response, 0, 1000);
            }
            $exceptionMessage = $lastErrorMessage . ($snippet !== '' ? ' | body_snippet=' . $snippet : '');
            throw new \Exception($exceptionMessage, $lastHttpCode ?: 0);
        }

        // 이 지점에 도달하지 않음
        throw new \Exception('알 수 없는 네트워크 오류가 발생했습니다.');
    }
    
    /**
     * file_get_contents를 사용한 HTTP 요청 전송 (cURL 대안)
     */
    private function sendRequestWithFileGetContents($url, $data = [], $headers = [])
    {
        // 기본 컨텍스트 설정
        $contextOptions = [
            'http' => [
                'method' => !empty($data) ? 'POST' : 'GET',
                'header' => '',
                'content' => !empty($data) ? json_encode($data) : '',
                'timeout' => 60,
                'ignore_errors' => true
            ]
        ];
        
        // 헤더 설정
        $requestHeaders = array_merge($this->headers, $headers);
        $headerLines = [];
        $hasContentType = false;
        foreach ($requestHeaders as $header) {
            if (strpos($header, ':') !== false) {
                $headerLines[] = $header;
                if (stripos($header, 'Content-Type:') === 0) {
                    $hasContentType = true;
                }
            }
        }
        if (!empty($headerLines)) {
            $contextOptions['http']['header'] = implode("\r\n", $headerLines) . "\r\n";
        }
        
        // Content-Type 헤더가 없는 경우에만 추가
        if (!empty($data) && !$hasContentType) {
            $contextOptions['http']['header'] .= "Content-Type: application/json\r\n";
        }
        
        $context = stream_context_create($contextOptions);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new \Exception("file_get_contents failed to retrieve content from URL");
        }
        
        // HTTP 응답 코드 확인
        $httpResponseHeader = $http_response_header ?? [];
        $statusLine = $httpResponseHeader[0] ?? '';
        if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $statusLine, $matches)) {
            $httpCode = (int)$matches[1];
            if ($httpCode !== 200) {
                // HTTP 400 오류의 경우 응답 본문에서 에러 메시지 추출
                $errorMessage = "HTTP Error: " . $httpCode . " - " . $response;
                if ($httpCode === 400 && is_string($response) && $response !== '') {
                    $errorResponse = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($errorResponse['error'])) {
                        $apiError = $errorResponse['error'];
                        $errorMessage = 'API 오류: ' . ($apiError['message'] ?? '알 수 없는 오류');
                        
                        // 특정 오류 타입에 대한 사용자 친화적 메시지
                        if (isset($apiError['type'])) {
                            if ($apiError['type'] === 'invalid_request_error' && strpos($apiError['message'], 'credit balance') !== false) {
                                $errorMessage = 'API 크레딧이 부족합니다. 계정을 업그레이드하거나 크레딧을 구매해주세요.';
                            } elseif ($apiError['type'] === 'authentication_error') {
                                $errorMessage = 'API 인증에 실패했습니다. API 키를 확인해주세요.';
                            } elseif ($apiError['type'] === 'rate_limit_error') {
                                $errorMessage = 'API 요청 한도를 초과했습니다. 잠시 후 다시 시도해주세요.';
                            }
                        }
                    }
                }
                throw new \Exception($errorMessage);
            }
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Decode Error: " . json_last_error_msg());
        }
        
        return $decodedResponse;
    }

    /**
     * 에러 로그 기록
     * 
     * @param string $message 에러 메시지
     * @param string $url 요청 URL
     * @param array $data 요청 데이터
     */
    protected function logError($message, $url, $data)
    {
        /*$logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'service' => get_class($this),
            'message' => $message,
            'url' => $url,
            'data' => $data
        ];
        
        // 로그 파일에 기록
        $logPath = $this->common->config['path']['data'] . '/' . $this->common->coId . '/logs';
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
        
        $logFile = $logPath . '/ai_error.log';
        file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);*/
    }

    /**
     * 응답 데이터 검증
     * 
     * @param array $response 응답 데이터
     * @return bool 검증 결과
     */
    protected function validateResponse($response)
    {
        if (!is_array($response)) {
            return false;
        }
        
        // AI 서비스별 응답 구조 검증은 하위 클래스에서 구현
        return true;
    }

    /**
     * 프롬프트 전처리
     * 
     * @param string $prompt 원본 프롬프트
     * @return string 처리된 프롬프트
     */
    protected function preprocessPrompt($prompt)
    {
        // 기본 전처리: 공백 정리, 특수문자 처리 등
        $prompt = trim($prompt);
        $prompt = str_replace(["\r\n", "\r", "\n"], " ", $prompt);
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        
        return $prompt;
    }

    /**
     * 응답 데이터 후처리
     * 
     * @param array $response 원본 응답
     * @return array 처리된 응답
     */
    protected function postprocessResponse($response)
    {
        // 기본 후처리: 불필요한 데이터 제거, 형식 통일 등
        if (isset($response['choices']) && is_array($response['choices'])) {
            // OpenAI/GPT 형식 응답 처리
            $response['processed'] = true;
        } elseif (isset($response['candidates']) && is_array($response['candidates'])) {
            // Google AI 형식 응답 처리
            $response['processed'] = true;
        } elseif (isset($response['content']) && is_array($response['content'])) {
            // Claude 형식 응답 처리
            $response['processed'] = true;
        }
        
        return $response;
    }
}
