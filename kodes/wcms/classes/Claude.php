<?php 
namespace Kodes\Wcms;

/**
 * Anthropic Claude AI 서비스 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Claude extends AIInterface
{
    /** @var string Anthropic API 키 */
    protected $apiKey;
    
    /** @var string Anthropic API 엔드포인트 */
    protected $apiEndpoint = 'https://api.anthropic.com/v1/messages';
    
    /** @var array 기본 모델 설정 */
    protected $defaultConfig = [
        'model' => 'claude-3-sonnet-20240229',
        'max_tokens' => 8192,
        'temperature' => 0.7,
        'top_p' => 1,
        'top_k' => 40
    ];
    
    /** @var string 회사 ID */
    protected $coId;
    
    /** @var string 사이트 문서 경로 */
    protected $siteDocPath;

    /**
     * Claude 설정 초기화
     */
    protected function initializeConfig()
    {
        // 회사 설정에서 API 키 가져오기
        $this->coId = $this->common->coId;
        $this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
        $company = $this->json->readJsonFile($this->siteDocPath.'/config', $this->coId.'_company');
        
        if (!empty($company['anthropic']['apiKey'])) {
            $this->apiKey = $company['anthropic']['apiKey'];
        } else {
            // 기본 API 키 (환경변수 또는 설정 파일에서)
            $this->apiKey = getenv('ANTHROPIC_API_KEY') ?: '';
        }
        $this->apiKey = "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";

        if (empty($this->apiKey)) {
            throw new \Exception('Anthropic API 키가 설정되지 않았습니다.');
        }
        
        // 기본 헤더 설정
        $this->headers = [
            'x-api-key: ' . $this->apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ];
        
        // 회사별 기본 설정이 있으면 적용
        if (!empty($company['anthropic']['defaultConfig'])) {
            $this->defaultConfig = array_merge($this->defaultConfig, $company['anthropic']['defaultConfig']);
        }
    }

    /**
     * 프롬프트 전송 및 JSON 응답 받기
     * 
     * @param string $prompt 프롬프트
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function sendPrompt($prompt, $options = [])
    {
        try {
            // 프롬프트 전처리
            $processedPrompt = $this->preprocessPrompt($prompt);
            
            // 옵션 병합
            $config = array_merge($this->defaultConfig, $options);
            
            // 요청 데이터 구성
            $requestData = [
                'model' => $config['model'],
                'max_tokens' => $config['max_tokens'],
                'temperature' => $config['temperature'],
                'top_p' => $config['top_p'],
                'top_k' => $config['top_k'],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $processedPrompt
                    ]
                ]
            ];
            
            // 시스템 메시지가 있으면 추가
            if (!empty($options['system_message'])) {
                $requestData['system'] = $options['system_message'];
            }
            
            // 대화 히스토리가 있으면 추가
            if (!empty($options['conversation_history'])) {
                $requestData['messages'] = array_merge($options['conversation_history'], $requestData['messages']);
            }
            
            // 스트리밍 옵션이 있으면 추가
            if (!empty($options['stream'])) {
                $requestData['stream'] = $options['stream'];
            }
            
            // API 요청 전송
            $response = $this->sendRequest($this->apiEndpoint, $requestData);
            
            // 응답 검증 (더 유연하게 처리)
            if (!$this->validateResponse($response)) {
                // 검증 실패해도 후처리 시도 (에러 정보 포함)
            }
            
            // 응답 후처리
            $processedResponse = $this->postprocessResponse($response, $options);
            
            // 사용량 정보 추가
            $processedResponse['usage_info'] = [
                'model' => $config['model'],
                'input_tokens' => $response['usage']['input_tokens'] ?? 0,
                'output_tokens' => $response['usage']['output_tokens'] ?? 0
            ];
            
            return $processedResponse;
            
        } catch (\Exception $e) {
            // API 오류 메시지가 이미 사용자 친화적으로 처리된 경우 그대로 전달
            if (strpos($e->getMessage(), 'API 오류:') === 0 || 
                strpos($e->getMessage(), 'API 크레딧이 부족합니다') !== false ||
                strpos($e->getMessage(), 'API 인증에 실패했습니다') !== false ||
                strpos($e->getMessage(), 'API 요청 한도를 초과했습니다') !== false) {
                throw new \Exception($e->getMessage());
            }
            throw new \Exception('Claude API 요청 실패: ' . $e->getMessage());
        }
    }

    /**
     * Claude 응답 데이터 검증
     * 
     * @param array $response 응답 데이터
     * @return bool 검증 결과
     */
    protected function validateResponse($response)
    {
        if (!is_array($response)) {
            return false;
        }
        
        // 에러 응답 확인
        if (isset($response['error'])) {
            return false;
        }
        
        // 필수 필드 확인
        if (!isset($response['content']) || !is_array($response['content'])) {
            return false;
        }
        
        if (empty($response['content'])) {
            return false;
        }
        
        if (!isset($response['content'][0]['text'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Claude 응답 데이터 후처리
     * 
     * @param array $response 원본 응답
     * @return array 처리된 응답
     */
    protected function postprocessResponse($response, $options = [])
    {
        try {
            // 안전한 데이터 추출
            $content = '';
            $model = 'unknown';
            $stopReason = 'unknown';
            $stopSequence = null;
            
            // 에러 응답 처리
            if (isset($response['error'])) {
                $errorMessage = $response['error']['message'] ?? '알 수 없는 오류';
                $errorType = $response['error']['type'] ?? 'unknown';
                
                // 특정 오류 타입에 대한 사용자 친화적 메시지
                if ($errorType === 'invalid_request_error' && strpos($errorMessage, 'credit balance') !== false) {
                    $errorMessage = 'Claude API 크레딧이 부족합니다. 계정을 업그레이드하거나 크레딧을 구매해주세요.';
                } elseif ($errorType === 'authentication_error') {
                    $errorMessage = 'Claude API 인증에 실패했습니다. API 키를 확인해주세요.';
                } elseif ($errorType === 'rate_limit_error') {
                    $errorMessage = 'Claude API 요청 한도를 초과했습니다. 잠시 후 다시 시도해주세요.';
                }
                
                return [
                    'status' => 'error',
                    'msg' => 'Claude API 에러: ' . $errorMessage,
                    'success' => false,
                    'content' => '',
                    'model' => $model,
                    'stop_reason' => $stopReason,
                    'stop_sequence' => $stopSequence,
                    'raw_response' => $response,
                    'error' => $response['error']
                ];
            }
            
            // 정상 응답 처리
            if (isset($response['content']) && is_array($response['content']) && !empty($response['content'])) {
                if (isset($response['content'][0]['text'])) {
                    $content = $response['content'][0]['text'];
                }
            }
            
            // 메타데이터 추출
            $model = $response['model'] ?? 'unknown';
            $stopReason = $response['stop_reason'] ?? 'unknown';
            $stopSequence = $response['stop_sequence'] ?? null;
            
            $processed = [
                'status' => 'success',
                'msg' => 'Claude API 호출이 성공적으로 완료되었습니다.',
                'success' => true,
                'content' => $content,
                'model' => $model,
                'stop_reason' => $stopReason,
                'stop_sequence' => $stopSequence,
                'raw_response' => $response
            ];
            
            // JSON 응답이 요청된 경우 파싱 시도
            if (isset($options['return_json']) && $options['return_json'] && !empty($content)) {
                $jsonContent = $this->extractJsonFromResponse($content);
                if ($jsonContent !== null) {
                    $processed['json_content'] = $jsonContent;
                }
            }
            
            return $processed;
        } catch (\Exception $e) {
            $this->logError('Claude 응답 후처리 오류: ' . $e->getMessage(), '', []);
            return [
                'status' => 'error',
                'msg' => 'Claude 응답 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
                'success' => false,
                'content' => '',
                'model' => '',
                'stop_reason' => '',
                'stop_sequence' => null,
                'raw_response' => $response,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 응답에서 JSON 추출
     * 
     * @param string $content 응답 내용
     * @return array|null JSON 데이터 또는 null
     */
    private function extractJsonFromResponse($content)
    {
        // JSON 블록 찾기 (```json ... ```)
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonString = trim($matches[1]);
        } else {
            // 중괄호로 둘러싸인 JSON 찾기
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $jsonString = $matches[0];
            } else {
                return null;
            }
        }
        
        $decoded = json_decode($jsonString, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    }

    /**
     * 특정 모델로 프롬프트 전송
     * 
     * @param string $prompt 프롬프트
     * @param string $model 모델명
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function sendPromptWithModel($prompt, $model, $options = [])
    {
        $options['model'] = $model;
        return $this->sendPrompt($prompt, $options);
    }

    /**
     * 대화형 프롬프트 전송
     * 
     * @param array $messages 대화 메시지 배열
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function sendConversation($messages, $options = [])
    {
        $options['conversation_history'] = $messages;
        return $this->sendPrompt('', $options);
    }

    /**
     * 스트리밍 응답 받기
     * 
     * @param string $prompt 프롬프트
     * @param array $options 추가 옵션
     * @return array 스트리밍 응답
     */
    public function sendPromptStream($prompt, $options = [])
    {
        $options['stream'] = true;
        return $this->sendPrompt($prompt, $options);
    }

    /**
     * JSON 형식으로 응답 요청
     * 
     * @param string $prompt 프롬프트
     * @param array $jsonSchema JSON 스키마 (선택사항)
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function sendPromptForJson($prompt, $jsonSchema = null, $options = [])
    {
        $systemMessage = "응답은 반드시 유효한 JSON 형식으로 제공해주세요.";
        
        if ($jsonSchema) {
            $systemMessage .= "\n\n다음 JSON 스키마를 따라주세요:\n" . json_encode($jsonSchema, JSON_PRETTY_PRINT);
        }
        
        $options['system_message'] = $systemMessage;
        $options['return_json'] = true;
        
        return $this->sendPrompt($prompt, $options);
    }

    /**
     * 도구 사용 프롬프트 전송
     * 
     * @param string $prompt 프롬프트
     * @param array $tools 도구 정의 배열
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function sendPromptWithTools($prompt, $tools, $options = [])
    {
        $options['tools'] = $tools;
        return $this->sendPrompt($prompt, $options);
    }

    /**
     * 이미지 생성
     * 
     * 주의: Claude는 현재 이미지 생성 기능을 제공하지 않습니다.
     * Claude는 텍스트 생성 및 이미지 분석(Vision)만 지원합니다.
     * 이 메서드는 향후 기능 추가를 대비한 구조입니다.
     * 
     * @param string $prompt 이미지 생성 프롬프트
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function generateImage($prompt, $options = [])
    {
        // Claude는 현재 이미지 생성을 지원하지 않음
        // Anthropic이 향후 이미지 생성 기능을 추가할 경우를 대비한 구조
        
        try {
            // 기본 설정
            $config = array_merge([
                'model' => 'claude-3-opus',
                'n' => 1
            ], $options);

            // 현재는 지원하지 않음을 알림
            return [
                'status' => 'error',
                'msg' => 'Claude는 현재 이미지 생성 기능을 제공하지 않습니다.',
                'success' => false,
                'images' => [],
                'model' => $config['model'],
                'count' => 0,
                'note' => 'Claude는 텍스트 생성 및 이미지 분석(Vision) 기능을 제공합니다. 이미지 생성은 지원하지 않습니다.',
                'alternative' => 'DALL-E (OpenAI) 또는 Imagen (Google)을 사용하시기 바랍니다.',
                'supported_features' => [
                    '텍스트 생성',
                    '이미지 분석 (Vision)',
                    '대화형 AI',
                    '코드 생성'
                ]
            ];

            // 향후 Claude가 이미지 생성을 지원하면 아래 코드 활성화
            /*
            $requestData = [
                'model' => $config['model'],
                'prompt' => $prompt,
                'num_images' => $config['n']
            ];

            $response = $this->sendRequest($this->apiEndpoint . '/images', $requestData);

            if (isset($response['images']) && is_array($response['images'])) {
                $images = [];
                foreach ($response['images'] as $imageData) {
                    $images[] = [
                        'url' => $imageData['url'] ?? '',
                        'revised_prompt' => $imageData['prompt'] ?? $prompt
                    ];
                }

                return [
                    'status' => 'success',
                    'msg' => count($images) . '개의 이미지가 성공적으로 생성되었습니다.',
                    'success' => true,
                    'images' => $images,
                    'model' => $config['model'],
                    'count' => count($images),
                    'raw_response' => $response
                ];
            }

            throw new \Exception('이미지 생성 응답에 데이터가 없습니다.');
            */

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'msg' => 'Claude 이미지 생성 실패: ' . $e->getMessage(),
                'success' => false,
                'images' => [],
                'model' => $config['model'] ?? 'claude-3-opus',
                'count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * URL에서 이미지 다운로드
     * 
     * @param string $url 이미지 URL
     * @return string|false 이미지 데이터 또는 false
     */
    protected function downloadImage($url)
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200 && $imageData !== false) {
                return $imageData;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log('이미지 다운로드 실패: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 이미지 생성 및 저장
     * 
     * @param string $prompt 이미지 생성 프롬프트
     * @param string $savePath 저장 경로 (디렉토리)
     * @param string $filePrefix 파일명 접두사 (기본값: 'ai_image')
     * @param array $options 추가 옵션
     * @return array 저장된 파일 정보
     */
    public function generateAndSaveImage($prompt, $savePath, $filePrefix = 'ai_image', $options = [])
    {
        try {
            // 이미지 생성
            $result = $this->generateImage($prompt, $options);
            
            if (!$result['success'] || empty($result['images'])) {
                return [
                    'status' => 'error',
                    'msg' => $result['msg'] ?? 'Claude는 이미지 생성을 지원하지 않습니다.',
                    'success' => false,
                    'saved_files' => [],
                    'error' => $result['error'] ?? 'not_supported',
                    'note' => $result['note'] ?? '',
                    'alternative' => $result['alternative'] ?? '',
                    'supported_features' => $result['supported_features'] ?? []
                ];
            }

            // 저장 디렉토리 생성
            if (!is_dir($savePath)) {
                if (!mkdir($savePath, 0755, true)) {
                    return [
                        'status' => 'error',
                        'msg' => '저장 디렉토리를 생성할 수 없습니다: ' . $savePath,
                        'success' => false,
                        'saved_files' => []
                    ];
                }
            }

            // 각 이미지 다운로드 및 저장
            $savedFiles = [];
            $timestamp = date('YmdHis');
            
            foreach ($result['images'] as $index => $imageInfo) {
                $imageUrl = $imageInfo['url'];
                $imageData = $this->downloadImage($imageUrl);
                
                if ($imageData === false) {
                    error_log("이미지 다운로드 실패: {$imageUrl}");
                    continue;
                }

                // 파일명 생성
                $fileName = $filePrefix . '_' . $timestamp . '_' . ($index + 1) . '.png';
                $filePath = rtrim($savePath, '/') . '/' . $fileName;
                
                // 파일 저장
                if (file_put_contents($filePath, $imageData) !== false) {
                    $savedFiles[] = [
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'url' => $imageUrl,
                        'revised_prompt' => $imageInfo['revised_prompt'],
                        'file_size' => filesize($filePath)
                    ];
                } else {
                    error_log("파일 저장 실패: {$filePath}");
                }
            }

            if (empty($savedFiles)) {
                return [
                    'status' => 'error',
                    'msg' => '이미지를 저장할 수 없습니다.',
                    'success' => false,
                    'saved_files' => []
                ];
            }

            return [
                'status' => 'success',
                'msg' => count($savedFiles) . '개의 이미지가 성공적으로 저장되었습니다.',
                'success' => true,
                'saved_files' => $savedFiles,
                'model' => $result['model'],
                'original_prompt' => $prompt
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'msg' => '이미지 생성 및 저장 실패: ' . $e->getMessage(),
                'success' => false,
                'saved_files' => [],
                'error' => $e->getMessage()
            ];
        }
    }
}
