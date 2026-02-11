<?php 
namespace Kodes\Wcms;

/**
 * OpenAI GPT 서비스 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class GPT extends AIInterface
{
    /** @var string OpenAI API 키 */
    protected $apiKey;
    
    /** @var string OpenAI Chat Completions 엔드포인트 */
    protected $apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    
    /** @var string OpenAI Responses 엔드포인트 */
    protected $responsesEndpoint = 'https://api.openai.com/v1/responses';
    
    /** @var string OpenAI Image Generation 엔드포인트 */
    protected $imageEndpoint = 'https://api.openai.com/v1/images/generations';
    
    /** @var array 기본 모델 설정 */
    protected $defaultConfig = [
        'model' => 'gpt-4o-mini',
        'max_tokens' => 1200,
        'temperature' => 0.4,
        'top_p' => 1,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    ];

    /** @var AiLog AI 로그 클래스 */
    protected $aiLog;
    
    /** @var string 회사 ID */
    protected $coId;
    
    /** @var string 사이트 문서 경로 */
    protected $siteDocPath;

    /**
     * GPT 설정 초기화
     */
    protected function initializeConfig()
    {
        $this->coId = $this->common->coId;
        $this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
        $company = $this->json->readJsonFile('/webSiteSource/wcms/config', $this->coId.'_company');
        
        // AiLog 초기화
        if (!$this->aiLog) {
            $this->aiLog = new AiLog();
        }
        
        if (!empty($company['openai']['apiKey'])) {
            $this->apiKey = $company['openai']['apiKey'];
        } else {
            // 기본 API 키 (환경변수 또는 설정 파일에서)
            $this->apiKey = getenv('OPENAI_API_KEY') ?: '';
        }

        if (empty($this->apiKey)) {
            throw new \Exception('OpenAI API 키가 설정되지 않았습니다.');
        }
        
        
        // 기본 헤더 설정
        $this->headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        // 회사별 기본 설정이 있으면 적용
        if (!empty($company['openai']['defaultConfig'])) {
            $this->defaultConfig = array_merge($this->defaultConfig, $company['openai']['defaultConfig']);
        }
        
        // 디버깅: 설정 확인
        error_log("GPT 설정 초기화 완료:");
        error_log("- API 키: " . substr($this->apiKey, 0, 10) . '...');
        error_log("- 기본 설정: " . json_encode($this->defaultConfig));
        error_log("- 헤더: " . json_encode($this->headers));
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
            
            // 메시지 구성 (빈 프롬프트는 전송하지 않음)
            $messages = [];
            if (!empty($options['conversation_history']) && is_array($options['conversation_history'])) {
                $messages = $options['conversation_history'];
            }
            if ($processedPrompt !== '') {
                $messages[] = [
                    'role' => 'user',
                    'content' => $processedPrompt
                ];
            }

            // GPT-5 모델 감지
            $isGpt5Model = (!empty($config['model']) && preg_match('/^gpt-5/i', $config['model']));
            
            // 요청 데이터 구성 (Chat Completions)
            $requestDataChat = [
                'model' => $config['model'],
                'messages' => $messages
            ];
            
            // GPT-5 모델은 제한된 파라미터만 지원
            if ($isGpt5Model) {
                // GPT-5는 reasoning_tokens를 고려하여 더 많은 토큰 할당
                $maxTokens = $config['max_tokens'];
                if ($maxTokens < 16000) {
                    $maxTokens = 16000; // 최소 16000 토큰으로 설정 (reasoning + 매우 긴 응답 고려)
                }
                $requestDataChat['max_completion_tokens'] = $maxTokens;
                // GPT-5는 temperature, top_p 등이 제한되므로 기본값만 사용
            } else {
                // 기존 모델은 모든 파라미터 지원
                $requestDataChat['max_tokens'] = $config['max_tokens'];
                $requestDataChat['temperature'] = $config['temperature'];
                $requestDataChat['top_p'] = $config['top_p'];
                $requestDataChat['frequency_penalty'] = $config['frequency_penalty'];
                $requestDataChat['presence_penalty'] = $config['presence_penalty'];
            }

            // 시스템 메시지: 기본값은 한국어 응답 지시
            $systemMessage = $options['system_message'] ?? '모든 응답은 한국어로 작성해주세요.';
            if (!empty($systemMessage)) {
                array_unshift($requestDataChat['messages'], [
                    'role' => 'system',
                    'content' => $systemMessage
                ]);
            }

            // JSON 강제 응답이 필요한 경우 Chat 경로에서 JSON 포맷을 요청
            $forceJson = !empty($options['return_json']);
            if ($forceJson) {
                $requestDataChat['response_format'] = [ 'type' => 'json_object' ];
            }

            // Responses API용 입력으로 변환
            $transcriptParts = [];
            foreach ($requestDataChat['messages'] as $m) {
                $role = $m['role'];
                $content = is_array($m['content']) ? json_encode($m['content']) : $m['content'];
                $transcriptParts[] = strtoupper($role) . ": " . $content;
            }
            $inputText = implode("\n\n", $transcriptParts);
            // Responses API는 모델별 지원 파라미터가 다릅니다.
            // gpt-5-mini는 'temperature' 등 특정 파라미터가 제한될 수 있어 최소 필드만 전송합니다.
            $requestDataResponses = [
                'model' => $config['model'],
                'input' => $inputText
            ];
            
            // GPT-5 모델은 제한된 파라미터만 지원
            if ($isGpt5Model) {
                // GPT-5는 reasoning_tokens를 고려하여 더 많은 토큰 할당
                $maxTokens = $config['max_tokens'];
                if ($maxTokens < 16000) {
                    $maxTokens = 16000; // 최소 16000 토큰으로 설정 (reasoning + 매우 긴 응답 고려)
                }
                $requestDataResponses['max_completion_tokens'] = $maxTokens;
                // GPT-5는 temperature, top_p 등이 제한되므로 기본값만 사용
            } else {
                $requestDataResponses['max_tokens'] = $config['max_tokens'];
                // 기존 모델은 추가 파라미터 지원 가능
            }

            try {
               $response = $this->sendRequest($this->apiEndpoint, $requestDataChat);
            } catch (\Exception $primaryEx) {
                try {
                    $response = $this->sendRequest($this->responsesEndpoint, $requestDataResponses);
                } catch (\Exception $fallbackEx) {
                    return [
                        'status' => 'error',
                        'msg' => 'GPT API 요청 실패: ' . $primaryEx->getMessage(),
                        'success' => false,
                        'content' => '',
                        'model' => $config['model'] ?? '',
                        'usage' => [],
                        'finish_reason' => '',
                        'raw_response' => null,
                        'error' => $primaryEx->getMessage()
                    ];
                }
            }

            
            // 응답 후처리
            $processedResponse = $this->postprocessResponse($response, $options);

            // 사용량 정보 추가 (양식 호환)
            $processedResponse['usage_info'] = [
                'model' => $config['model'],
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? ($response['usage']['input_tokens'] ?? 0),
                'completion_tokens' => $response['usage']['completion_tokens'] ?? ($response['usage']['output_tokens'] ?? 0),
                'total_tokens' => $response['usage']['total_tokens'] ?? (($response['usage']['input_tokens'] ?? 0) + ($response['usage']['output_tokens'] ?? 0))
            ];


            // AI 로그 기록 (성공)
            $this->logGptCall($config['model'], $inputText, $processedResponse['content'], $options, $processedResponse['usage_info'], 0, 'success');
            
            return $processedResponse;
            
        } catch (\Exception $e) {
            // AI 로그 기록 (실패)
            $this->logGptCall($config['model'] ?? $this->defaultConfig['model'], $inputText, '', $options, [], 0, 'error', $e->getMessage());
            
            return [
                'status' => 'error',
                'msg' => 'GPT API 요청 실패: ' . $e->getMessage(),
                'success' => false,
                'content' => '',
                'model' => $this->defaultConfig['model'] ?? '',
                'usage' => [],
                'finish_reason' => '',
                'raw_response' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * GPT API 호출 로그를 기록합니다.
     * 
     * @param string $model 모델명
     * @param string $prompt 프롬프트 내용
     * @param string $response 응답 내용
     * @param array $options 옵션 (thinking, return_json 등)
     * @param array $usage 사용량 정보 (input_tokens, output_tokens 등)
     * @param float $cost 비용
     * @param string $status 성공/실패 상태
     * @param string $errorMsg 에러 메시지 (실패 시)
     */
    protected function logGptCall($model, $prompt, $response, $options = [], $usage = [], $cost = 0, $status = 'success', $errorMsg = '')
    {
        try {
            // AiLog가 초기화되지 않은 경우 초기화
            if (!$this->aiLog) {
                $this->aiLog = new AiLog();
            }
            
            $this->aiLog->logGptCall($model, $prompt, $response, $options, $usage, $cost, $status, $errorMsg);
            
        } catch (\Exception $e) {
            // 로그 기록 실패 시 무시
        }
    }

    /**
     * GPT 응답 데이터 후처리
     * 
     * @param array $response 원본 응답
     * @return array 처리된 응답
     */
    protected function postprocessResponse($response, $options = [])
    {
        try {
            $content = '';
            $finishReason = 'unknown';
            
            // Chat Completions (표준)
            if (isset($response['choices'][0]['message']['content'])) {
                $content = $response['choices'][0]['message']['content'];
                $finishReason = $response['choices'][0]['finish_reason'] ?? 'unknown';
                
                // GPT-5 모델에서 content가 비어있고 finish_reason이 length인 경우
                if (empty($content) && $finishReason === 'length') {
                    $reasoningTokens = $response['usage']['completion_tokens_details']['reasoning_tokens'] ?? 0;
                    
                    // reasoning_tokens가 있는 경우 이를 content로 사용할 수 있는지 확인
                    if ($reasoningTokens > 0) {
                        $content = "GPT-5 모델이 내부 추론 과정에서 토큰 제한에 도달했습니다. (reasoning_tokens: {$reasoningTokens}) max_completion_tokens를 4000 이상으로 설정해주세요.";
                    } else {
                        $content = "토큰 제한으로 인해 응답이 잘렸습니다. max_completion_tokens를 늘려주세요.";
                    }
                }
            } 
            // Responses API
            elseif (isset($response['output_text'])) {
                $content = $response['output_text'];
                $finishReason = $response['finish_reason'] ?? 'unknown';
            } 
            elseif (isset($response['output'][0]['content'][0]['text'])) {
                $content = $response['output'][0]['content'][0]['text'];
                $finishReason = $response['finish_reason'] ?? 'unknown';
            } 
            elseif (isset($response['output'][0]['text'])) {
                $content = $response['output'][0]['text'];
                $finishReason = $response['finish_reason'] ?? 'unknown';
            }
            // GPT-5 모델의 새로운 응답 형식
            elseif (isset($response['content'])) {
                $content = $response['content'];
                $finishReason = $response['finish_reason'] ?? 'unknown';
            }
            // GPT-5 모델의 choices 구조 (다른 형태)
            elseif (isset($response['choices'][0]['text'])) {
                $content = $response['choices'][0]['text'];
                $finishReason = $response['choices'][0]['finish_reason'] ?? 'unknown';
            }
            // GPT-5 모델의 message 구조 (다른 형태)
            elseif (isset($response['message']['content'])) {
                $content = $response['message']['content'];
                $finishReason = $response['finish_reason'] ?? 'unknown';
            }
            // text 필드 직접 지원
            elseif (isset($response['text'])) {
                $content = $response['text'];
                $finishReason = $response['finish_reason'] ?? 'unknown';
            }
            // result 필드 지원
            elseif (isset($response['result'])) {
                $content = $response['result'];
                $finishReason = $response['finish_reason'] ?? 'unknown';
            }
            // error 필드가 있는 경우
            elseif (isset($response['error'])) {
                $content = 'Error: ' . (is_string($response['error']) ? $response['error'] : json_encode($response['error']));
                $finishReason = 'error';
            }
            else {
                $content = '';
                $finishReason = 'unknown';
            }
            
            $processed = [
                'status' => 'success',
                'msg' => 'GPT API 호출이 성공적으로 완료되었습니다.',
                'success' => true,
                'content' => $content,
                'model' => $response['model'] ?? 'unknown',
                'usage' => $response['usage'] ?? [],
                'finish_reason' => $finishReason,
                'raw_response' => $response
            ];
            
            // JSON 응답이 요청된 경우 파싱 시도
            if (isset($options['return_json']) && $options['return_json']) {
                $jsonContent = $this->extractJsonFromResponse($processed['content']);
                if ($jsonContent !== null) {
                    $processed['json_content'] = $jsonContent;
                }
            }
            
            return $processed;
        } catch (\Exception $e) {
            $this->logError('GPT 응답 후처리 오류: ' . $e->getMessage(), '', []);
            return [
                'status' => 'error',
                'msg' => 'GPT 응답 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
                'success' => false,
                'content' => '',
                'model' => '',
                'usage' => [],
                'finish_reason' => '',
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
    public function extractJsonFromResponse($content)
    {
        error_log("=== extractJsonFromResponse 디버깅 ===");
        error_log("원본 콘텐츠 (처음 500자): " . substr($content, 0, 500));
        
        // 1. JSON 블록 찾기 (```json ... ```)
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonString = trim($matches[1]);
            error_log("JSON 블록 발견 (```json)");
        } 
        // 2. 코드 블록 없이 JSON만 있는 경우 (``` ... ```)
        else if (preg_match('/```\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $jsonString = trim($matches[1]);
            error_log("코드 블록 JSON 발견 (```)");
        }
        // 3. 중괄호로 둘러싸인 JSON 찾기 (가장 큰 JSON 객체)
        else if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches)) {
            $jsonString = $matches[0];
            error_log("중괄호 JSON 발견");
        }
        // 4. 전체 내용이 JSON인 경우
        else {
            $jsonString = trim($content);
            error_log("전체 내용을 JSON으로 시도");
        }
        
        error_log("추출된 JSON 문자열 (처음 300자): " . substr($jsonString, 0, 300));
        
        // JSON 파싱 시도
        $decoded = json_decode($jsonString, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            error_log("JSON 파싱 성공");
            error_log("파싱된 데이터 키들: " . json_encode(array_keys($decoded), JSON_PRETTY_PRINT));
            return $decoded;
        }
        
        // 파싱 실패 시 로그 기록
        error_log("JSON 파싱 실패: " . json_last_error_msg());
        error_log("시도한 JSON 문자열: " . substr($jsonString, 0, 500) . "...");
        error_log("=== extractJsonFromResponse 디버깅 끝 ===");
        
        return null;
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
     * OpenAI 이미지 생성 (GPT Image, DALL-E)
     * 
     * @param string $prompt 이미지 생성 프롬프트
     * @param array $options 추가 옵션
     *   - model: 모델명 (gpt-image-1.5, gpt-image-1, gpt-image-1-mini, dall-e-3, dall-e-2) 기본값: gpt-image-1.5
     *   - n: 생성할 이미지 개수 (dall-e-2: 1-10, 기타: 1) 기본값: 1
     *   - size: 이미지 크기
     *     * GPT Image 모델: 1024x1024, 1024x1536, 1536x1024, auto (기본: 1024x1024)
     *     * DALL-E-3: 1024x1024, 1792x1024, 1024x1792 (기본: 1024x1024)
     *     * DALL-E-2: 256x256, 512x512, 1024x1024 (기본: 1024x1024)
     *   - quality: 품질
     *     * GPT Image 모델: low, medium, high (기본: medium)
     *     * DALL-E-3: standard, hd (기본: standard)
     *   - style: 스타일 (DALL-E-3만 지원: vivid, natural) 기본값: vivid
     * @return array JSON 응답
     */
    public function generateImage($prompt, $options = [])
    {
        try {
            // 기본 설정
            $config = array_merge([
                'model' => 'gpt-image-1.5',
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'medium',
                'style' => 'vivid'  // DALL-E 전용
            ], $options);

            // GPT Image 모델 체크
            $isGptImage = in_array($config['model'], ['gpt-image-1.5', 'gpt-image-1', 'gpt-image-1-mini']);
            
            // DALL-E-3, GPT Image는 1개만 생성 가능
            if (($config['model'] === 'dall-e-3' || $isGptImage) && $config['n'] > 1) {
                $config['n'] = 1;
            }

            // DALL-E-2는 최대 10개
            if ($config['model'] === 'dall-e-2' && $config['n'] > 10) {
                $config['n'] = 10;
            }

            // 요청 데이터 구성
            $requestData = [
                'model' => $config['model'],
                'prompt' => $prompt,
                'n' => $config['n'],
                'size' => $config['size']
            ];

            // 모델별 옵션 추가
            if ($isGptImage) {
                // GPT Image 모델: quality만 지원 (low, medium, high)
                // response_format은 지원하지 않음
                $requestData['quality'] = $config['quality'];
            } elseif ($config['model'] === 'dall-e-3') {
                // DALL-E-3: quality, style, response_format 지원
                $requestData['quality'] = $config['quality'];
                $requestData['style'] = $config['style'];
                $requestData['response_format'] = 'url';  // DALL-E만 지원
            } elseif ($config['model'] === 'dall-e-2') {
                // DALL-E-2: response_format 지원
                $requestData['response_format'] = 'url';
            }

            // 디버그 로그
            error_log('Image Generation Request: ' . json_encode([
                'model' => $config['model'],
                'is_gpt_image' => $isGptImage,
                'prompt_length' => strlen($prompt),
                'request_data' => $requestData
            ], JSON_UNESCAPED_UNICODE));

            // API 요청 전송
            $response = $this->sendRequest($this->imageEndpoint, $requestData);

            // 디버그 로그 - API 응답
            $firstImageKeys = isset($response['data'][0]) ? array_keys($response['data'][0]) : [];
            error_log('Image Generation Response: ' . json_encode([
                'has_data' => isset($response['data']),
                'data_count' => isset($response['data']) ? count($response['data']) : 0,
                'response_keys' => array_keys($response),
                'first_image_keys' => $firstImageKeys,
                'has_url' => isset($response['data'][0]['url']),
                'has_b64' => isset($response['data'][0]['b64_json']),
                'error' => $response['error'] ?? null
            ], JSON_UNESCAPED_UNICODE));

            // 응답 처리
            if (isset($response['data']) && is_array($response['data'])) {
                $images = [];
                foreach ($response['data'] as $imageData) {
                    $imageInfo = [
                        'revised_prompt' => $imageData['revised_prompt'] ?? $prompt
                    ];
                    
                    // URL 형식인 경우
                    if (!empty($imageData['url'])) {
                        $imageInfo['url'] = $imageData['url'];
                        $imageInfo['format'] = 'url';
                    }
                    // Base64 형식인 경우
                    elseif (!empty($imageData['b64_json'])) {
                        $imageInfo['b64_json'] = $imageData['b64_json'];
                        $imageInfo['format'] = 'base64';
                    }
                    
                    $images[] = $imageInfo;
                }

                // 이미지 데이터가 없는 경우
                if (empty($images) || (empty($images[0]['url']) && empty($images[0]['b64_json']))) {
                    throw new \Exception('이미지 데이터가 없습니다. 응답 형식: ' . json_encode($firstImageKeys));
                }

                // AI 로그 기록 (성공)
                $this->logGptCall(
                    $config['model'], 
                    $prompt, 
                    'Generated ' . count($images) . ' image(s)', 
                    $options, 
                    ['generated_images' => count($images)], 
                    0, 
                    'success'
                );

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

            // 응답에 데이터가 없는 경우 상세 정보 포함
            $errorDetail = json_encode($response, JSON_UNESCAPED_UNICODE);
            throw new \Exception('이미지 생성 응답에 데이터가 없습니다. API 응답: ' . $errorDetail);

        } catch (\Exception $e) {
            // 에러 상세 로그
            error_log('Image Generation Error: ' . $e->getMessage());
            
            // AI 로그 기록 (실패)
            $this->logGptCall(
                $config['model'] ?? 'gpt-image-1.5', 
                $prompt, 
                '', 
                $options, 
                [], 
                0, 
                'error', 
                $e->getMessage()
            );

            return [
                'status' => 'error',
                'msg' => '이미지 생성 실패: ' . $e->getMessage(),
                'success' => false,
                'images' => [],
                'model' => $config['model'] ?? 'gpt-image-1.5',
                'count' => 0,
                'error' => $e->getMessage(),
                'debug_info' => [
                    'is_gpt_image' => $isGptImage ?? false,
                    'request_model' => $config['model'] ?? 'unknown',
                    'request_quality' => $requestData['quality'] ?? 'none',
                    'has_style' => isset($requestData['style'])
                ]
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
            error_log("이미지 다운로드 시도: {$url}");
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode === 200 && $imageData !== false) {
                error_log("이미지 다운로드 성공: " . strlen($imageData) . " bytes");
                return $imageData;
            }
            
            error_log("이미지 다운로드 실패: HTTP {$httpCode}, CURL Error: {$curlError}");
            return false;
        } catch (\Exception $e) {
            error_log('이미지 다운로드 예외: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 파일 경로를 웹 URL로 변환
     * 
     * @param string $filePath 파일 절대 경로
     * @return string 웹 URL
     */
    protected function convertToWebUrl($filePath)
    {
        // Windows 경로를 Unix 스타일로 변환
        $filePath = str_replace('\\', '/', $filePath);
        
        // siteDocPath를 기준으로 상대 경로 추출
        $siteDocPath = str_replace('\\', '/', $this->siteDocPath);
        
        if (strpos($filePath, $siteDocPath) === 0) {
            $relativePath = substr($filePath, strlen($siteDocPath));
            $webUrl = '/data/' . $this->coId . $relativePath;
            error_log("URL 변환: {$filePath} -> {$webUrl}");
            return $webUrl;
        }
        
        // 기본적으로 파일 이름만 반환
        error_log("URL 변환 실패 (경로 불일치): {$filePath} (siteDocPath: {$siteDocPath})");
        return basename($filePath);
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
                // 상세한 에러 정보 로그
                error_log('Generate and Save Image Failed: ' . json_encode([
                    'success' => $result['success'] ?? false,
                    'msg' => $result['msg'] ?? 'unknown',
                    'error' => $result['error'] ?? 'unknown',
                    'debug_info' => $result['debug_info'] ?? []
                ], JSON_UNESCAPED_UNICODE));
                
                return [
                    'status' => 'error',
                    'msg' => $result['msg'] ?? '이미지 생성에 실패했습니다.',
                    'success' => false,
                    'saved_files' => [],
                    'error' => $result['error'] ?? 'unknown',
                    'debug_info' => $result['debug_info'] ?? []
                ];
            }

            // 저장 디렉토리 생성
            error_log("저장 경로 확인: {$savePath}");
            error_log("디렉토리 존재 여부: " . (is_dir($savePath) ? 'Y' : 'N'));
            
            if (!is_dir($savePath)) {
                error_log("디렉토리 생성 시도: {$savePath}");
                if (!mkdir($savePath, 0755, true)) {
                    $error = error_get_last();
                    error_log("디렉토리 생성 실패: " . json_encode($error));
                    return [
                        'status' => 'error',
                        'msg' => '저장 디렉토리를 생성할 수 없습니다: ' . $savePath,
                        'success' => false,
                        'saved_files' => []
                    ];
                }
                error_log("디렉토리 생성 성공: {$savePath}");
            } else {
                error_log("디렉토리 이미 존재: {$savePath}");
                error_log("디렉토리 쓰기 가능: " . (is_writable($savePath) ? 'Y' : 'N'));
            }

            // 각 이미지 다운로드 및 저장
            $savedFiles = [];
            $timestamp = date('YmdHis');
            
            error_log("이미지 저장 루프 시작: " . count($result['images']) . "개");
            
            foreach ($result['images'] as $index => $imageInfo) {
                error_log("이미지 #{$index} 처리 중: " . json_encode([
                    'has_url' => !empty($imageInfo['url']),
                    'has_b64' => !empty($imageInfo['b64_json']),
                    'format' => $imageInfo['format'] ?? 'unknown'
                ]));
                
                $imageData = null;
                
                // URL 형식인 경우 다운로드
                if (!empty($imageInfo['url'])) {
                    error_log("URL 다운로드 시도: {$imageInfo['url']}");
                    $imageData = $this->downloadImage($imageInfo['url']);
                    if ($imageData === false) {
                        error_log("이미지 다운로드 실패: {$imageInfo['url']}");
                        continue;
                    }
                    error_log("URL 다운로드 성공: " . strlen($imageData) . " bytes");
                }
                // Base64 형식인 경우 디코딩
                elseif (!empty($imageInfo['b64_json'])) {
                    error_log("Base64 디코딩 시도");
                    $imageData = base64_decode($imageInfo['b64_json']);
                    if ($imageData === false) {
                        error_log("Base64 디코딩 실패");
                        continue;
                    }
                    error_log("Base64 디코딩 성공: " . strlen($imageData) . " bytes");
                }
                else {
                    error_log("이미지 데이터가 없습니다: " . json_encode($imageInfo));
                    continue;
                }

                // 파일명 생성
                $fileName = $filePrefix . '_' . $timestamp . '_' . ($index + 1) . '.png';
                $filePath = rtrim($savePath, '/') . '/' . $fileName;
                
                error_log("파일 저장 시도: {$filePath}");
                error_log("디렉토리 존재: " . (is_dir(dirname($filePath)) ? 'Y' : 'N'));
                error_log("디렉토리 쓰기 가능: " . (is_writable(dirname($filePath)) ? 'Y' : 'N'));
                error_log("이미지 데이터 크기: " . strlen($imageData) . " bytes");
                
                // 파일 저장
                $bytesWritten = @file_put_contents($filePath, $imageData);
                if ($bytesWritten !== false) {
                    // 웹 URL 생성 (data 경로 기준)
                    $webUrl = $this->convertToWebUrl($filePath);
                    
                    error_log("이미지 저장 성공: {$filePath} (size: " . filesize($filePath) . " bytes, written: {$bytesWritten} bytes)");
                    
                    $savedFiles[] = [
                        'filename' => $fileName,  // Article.php와 일관성
                        'path' => $filePath,
                        'url' => $webUrl,
                        'revised_prompt' => $imageInfo['revised_prompt'],
                        'file_size' => filesize($filePath),
                        'format' => $imageInfo['format'] ?? 'unknown'
                    ];
                } else {
                    $error = error_get_last();
                    error_log("파일 저장 실패: {$filePath}");
                    error_log("PHP 에러: " . json_encode($error));
                }
            }

            if (empty($savedFiles)) {
                // 상세한 디버그 정보 수집
                $debugInfo = [
                    'images_count' => count($result['images']),
                    'save_path' => $savePath,
                    'save_path_exists' => is_dir($savePath),
                    'save_path_writable' => is_writable($savePath),
                    'images_info' => []
                ];
                
                foreach ($result['images'] as $idx => $img) {
                    $debugInfo['images_info'][] = [
                        'index' => $idx,
                        'has_url' => !empty($img['url']),
                        'has_b64' => !empty($img['b64_json']),
                        'format' => $img['format'] ?? 'unknown'
                    ];
                }
                
                error_log('이미지 저장 실패 - 모든 이미지 저장 불가: ' . json_encode($debugInfo, JSON_UNESCAPED_UNICODE));
                
                return [
                    'status' => 'error',
                    'msg' => '이미지를 저장할 수 없습니다.',
                    'success' => false,
                    'saved_files' => [],
                    'debug_info' => $debugInfo
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
