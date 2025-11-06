<?php 
namespace Kodes\Wcms;

/**
 * Google Gemini AI 서비스 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Gemini extends AIInterface
{
    /** @var string Google AI API 키 */
    protected $apiKey;
    
    /** @var string Google AI API 엔드포인트 */
    protected $apiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models';
    
    /** @var array 기본 모델 설정 (텍스트) */
    protected $defaultConfig = [
        'model' => 'gemini-2.5-flash',
        'max_output_tokens' => 8192,
        'temperature' => 0.7,
        'top_p' => 1,
        'top_k' => 40
    ];
    
    /** @var array 기본 이미지 생성 설정 */
    protected $defaultImageConfig = [
        'model' => 'gemini-2.5-flash-image',
        'aspect_ratio' => '1:1'  // 1:1, 2:3, 3:2, 3:4, 4:3, 4:5, 5:4, 9:16, 16:9, 21:9
    ];
    
    /** @var string 회사 ID */
    protected $coId;
    
    /** @var string 사이트 문서 경로 */
    protected $siteDocPath;

    /**
     * Gemini 설정 초기화
     */
    protected function initializeConfig()
    {
        // 회사 설정에서 API 키 가져오기
        $this->coId = $this->common->coId;
        $this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
        $company = $this->json->readJsonFile($this->siteDocPath.'/config', $this->coId.'_company');
        
        if (!empty($company['googleai']['apiKey'])) {
            $this->apiKey = $company['googleai']['apiKey'];
        } else {
            // 기본 API 키 (환경변수 또는 설정 파일에서)
            $this->apiKey = getenv('GOOGLE_AI_API_KEY') ?: '';
        }
        
        // 임시 API 키 (개발/테스트용 - 실제 운영에서는 제거해야 함)
        if (empty($this->apiKey)) {
            $this->apiKey = "AIzaSyB2GkFuElWiWGt_sirhIk0PXV1LXDH-J2c";
        }

        if (empty($this->apiKey)) {
            throw new \Exception('Google AI API 키가 설정되지 않았습니다.');
        }
        
        // 기본 헤더 설정
        $this->headers = [
            'Content-Type: application/json'
        ];
        
        // 회사별 기본 설정이 있으면 적용
        if (!empty($company['googleai']['defaultConfig'])) {
            $this->defaultConfig = array_merge($this->defaultConfig, $company['googleai']['defaultConfig']);
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
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $processedPrompt
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'maxOutputTokens' => $config['max_output_tokens'],
                    'temperature' => $config['temperature'],
                    'topP' => $config['top_p'],
                    'topK' => $config['top_k']
                ]
            ];
            
            // 시스템 인스트럭션이 있으면 추가
            if (!empty($options['system_instruction'])) {
                $requestData['systemInstruction'] = [
                    'parts' => [
                        [
                            'text' => $options['system_instruction']
                        ]
                    ]
                ];
            }
            
            // 안전 설정이 있으면 추가
            if (!empty($options['safety_settings'])) {
                $requestData['safetySettings'] = $options['safety_settings'];
            }
            
            // API 요청 전송
            $url = $this->apiEndpoint . '/' . $config['model'] . ':generateContent?key=' . $this->apiKey;
            $response = $this->sendRequest($url, $requestData);
            
            // 모델을 찾을 수 없는 경우 다른 모델로 재시도
            if (isset($response['error']['code']) && $response['error']['code'] === 404) {
                $fallbackModels = [
                    'gemini-2.5-flash',
                    'gemini-2.5-pro',
                    'gemini-1.5-flash', 
                    'gemini-1.5-pro',
                    'gemini-pro'
                ];
                $originalModel = $config['model'];
                
                foreach ($fallbackModels as $fallbackModel) {
                    if ($fallbackModel !== $originalModel) {
                        $config['model'] = $fallbackModel;
                        $url = $this->apiEndpoint . '/' . $config['model'] . ':generateContent?key=' . $this->apiKey;
                        $response = $this->sendRequest($url, $requestData);
                        
                        // 성공하면 중단
                        if (!isset($response['error']['code']) || $response['error']['code'] !== 404) {
                            break;
                        }
                    }
                }
            }
            
            // 응답 검증 (더 유연하게 처리)
            if (!$this->validateResponse($response)) {
                // 검증 실패해도 후처리 시도 (에러 정보 포함)
            }
            
            // 응답 후처리 (검증 실패해도 시도)
            $processedResponse = $this->postprocessResponse($response, $options);
            
            // 사용량 정보 추가
            $processedResponse['usage_info'] = [
                'model' => $config['model'],
                'prompt_token_count' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'candidates_token_count' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_token_count' => $response['usageMetadata']['totalTokenCount'] ?? 0
            ];
            
            return $processedResponse;
            
        } catch (\Exception $e) {
            throw new \Exception('Gemini API 요청 실패: ' . $e->getMessage());
        }
    }

    /**
     * Gemini 응답 데이터 검증
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
        if (!isset($response['candidates']) || !is_array($response['candidates'])) {
            return false;
        }
        
        if (empty($response['candidates'])) {
            return false;
        }
        
        // 첫 번째 후보 확인
        $firstCandidate = $response['candidates'][0];
        if (!isset($firstCandidate['content']) || !isset($firstCandidate['content']['parts'])) {
            return false;
        }
        
        if (empty($firstCandidate['content']['parts']) || !isset($firstCandidate['content']['parts'][0]['text'])) {
            return false;
        }
        
        return true;
    }

    /**
     * Gemini 응답 데이터 후처리
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
            $finishReason = 'unknown';
            $safetyRatings = [];
            
            // 에러 응답 처리
            if (isset($response['error'])) {
                return [
                    'status' => 'error',
                    'msg' => 'Gemini API 에러: ' . ($response['error']['message'] ?? '알 수 없는 오류'),
                    'success' => false,
                    'content' => '',
                    'model' => $model,
                    'finish_reason' => $finishReason,
                    'safety_ratings' => $safetyRatings,
                    'raw_response' => $response,
                    'error' => $response['error']
                ];
            }
            
            // 정상 응답 처리
            if (isset($response['candidates']) && is_array($response['candidates']) && !empty($response['candidates'])) {
                $firstCandidate = $response['candidates'][0];
                
                // 콘텐츠 추출
                if (isset($firstCandidate['content']['parts'][0]['text'])) {
                    $content = $firstCandidate['content']['parts'][0]['text'];
                }
                
                // 메타데이터 추출
                $finishReason = $firstCandidate['finishReason'] ?? 'unknown';
                $safetyRatings = $firstCandidate['safetyRatings'] ?? [];
                
                // MAX_TOKENS 처리
                if ($finishReason === 'MAX_TOKENS' && empty($content)) {
                    $content = "응답이 토큰 제한으로 인해 잘렸습니다. 더 짧은 프롬프트를 사용하거나 max_output_tokens를 늘려주세요.";
                }
            }
            
            // 모델 정보 추출
            $model = $response['model'] ?? 'unknown';
            
            $processed = [
                'status' => 'success',
                'msg' => 'Gemini API 호출이 성공적으로 완료되었습니다.',
                'success' => true,
                'content' => $content,
                'model' => $model,
                'finish_reason' => $finishReason,
                'safety_ratings' => $safetyRatings,
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
            error_log('Gemini 응답 후처리 오류: ' . $e->getMessage());
            $this->logError('Gemini 응답 후처리 오류: ' . $e->getMessage(), '', []);
            return [
                'status' => 'error',
                'msg' => 'Gemini 응답 처리 중 오류가 발생했습니다: ' . $e->getMessage(),
                'success' => false,
                'content' => '',
                'model' => '',
                'finish_reason' => '',
                'safety_ratings' => [],
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
     * 멀티모달 프롬프트 전송 (이미지 포함)
     * 
     * @param string $prompt 프롬프트
     * @param array $images 이미지 데이터 배열
     * @param array $options 추가 옵션
     * @return array JSON 응답
     */
    public function sendMultimodalPrompt($prompt, $images, $options = [])
    {
        $options['model'] = 'gemini-2.5-flash';
        
        // 이미지 데이터를 parts에 추가
        $imageParts = [];
        foreach ($images as $image) {
            if (isset($image['mime_type']) && isset($image['data'])) {
                $imageParts[] = [
                    'inlineData' => [
                        'mimeType' => $image['mime_type'],
                        'data' => $image['data']
                    ]
                ];
            }
        }
        
        // 요청 데이터 재구성
        $requestData = [
            'contents' => [
                [
                    'parts' => array_merge(
                        [['text' => $prompt]],
                        $imageParts
                    )
                ]
            ]
        ];
        
        // 옵션 병합
        $config = array_merge($this->defaultConfig, $options);
        
        // API 요청 전송
        $url = $this->apiEndpoint . '/' . $config['model'] . ':generateContent?key=' . $this->apiKey;
        $response = $this->sendRequest($url, $requestData);
        
        return $this->postprocessResponse($response, $options);
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
        $systemInstruction = "응답은 반드시 유효한 JSON 형식으로 제공해주세요.";
        
        if ($jsonSchema) {
            $systemInstruction .= "\n\n다음 JSON 스키마를 따라주세요:\n" . json_encode($jsonSchema, JSON_PRETTY_PRINT);
        }
        
        $options['system_instruction'] = $systemInstruction;
        $options['return_json'] = true;
        
        return $this->sendPrompt($prompt, $options);
    }

    /**
     * 사용 가능한 모델 목록 조회
     * 
     * @return array 모델 목록
     */
    public function getAvailableModels()
    {
        try {
            $url = $this->apiEndpoint . '?key=' . $this->apiKey;
            $response = $this->sendRequest($url, []);
            
            if (isset($response['models'])) {
                return [
                    'success' => true,
                    'models' => $response['models'],
                    'raw_response' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'msg' => '모델 목록을 가져올 수 없습니다.',
                    'raw_response' => $response
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'msg' => '모델 목록 조회 실패: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 이미지 생성 (Gemini Native Image Generation)
     * 
     * Gemini 2.5 Flash는 네이티브 이미지 생성을 지원합니다.
     * 참고: https://ai.google.dev/gemini-api/docs/image-generation
     * 
     * @param string $prompt 이미지 생성 프롬프트
     * @param array $options 추가 옵션
     *   - model: 모델명 (gemini-2.5-flash-image) 기본값
     *   - aspect_ratio: 종횡비 (1:1, 2:3, 3:2, 3:4, 4:3, 4:5, 5:4, 9:16, 16:9, 21:9)
     *   - n: 생성할 이미지 개수 (현재는 1개만 지원)
     * @return array JSON 응답
     */
    public function generateImage($prompt, $options = [])
    {
        try {
            // 기본 설정
            $config = array_merge($this->defaultImageConfig, $options);
            
            // Gemini 이미지 생성은 현재 1개만 지원
            $config['n'] = 1;
            
            // 프롬프트 전처리
            $processedPrompt = $this->preprocessPrompt($prompt);
            
            // 요청 데이터 구성
            $requestData = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $processedPrompt
                            ]
                        ]
                    ]
                ]
            ];
            
            // aspectRatio 설정 추가
            if (!empty($config['aspect_ratio'])) {
                $requestData['generationConfig'] = [
                    'imageConfig' => [
                        'aspectRatio' => $config['aspect_ratio']
                    ]
                ];
            }
            
            // API 요청 전송
            $url = $this->apiEndpoint . '/' . $config['model'] . ':generateContent?key=' . $this->apiKey;
            $response = $this->sendRequest($url, $requestData);
            
            // 응답 처리
            if (isset($response['candidates']) && is_array($response['candidates']) && !empty($response['candidates'])) {
                $images = [];
                
                foreach ($response['candidates'] as $candidate) {
                    if (isset($candidate['content']['parts'])) {
                        foreach ($candidate['content']['parts'] as $part) {
                            // 인라인 이미지 데이터 추출
                            if (isset($part['inlineData']) && isset($part['inlineData']['data'])) {
                                $images[] = [
                                    'data' => $part['inlineData']['data'],  // Base64 인코딩된 이미지
                                    'mime_type' => $part['inlineData']['mimeType'] ?? 'image/png',
                                    'revised_prompt' => $prompt  // Gemini는 프롬프트를 수정하지 않음
                                ];
                            }
                        }
                    }
                }
                
                if (!empty($images)) {
                    return [
                        'status' => 'success',
                        'msg' => count($images) . '개의 이미지가 성공적으로 생성되었습니다.',
                        'success' => true,
                        'images' => $images,
                        'model' => $config['model'],
                        'count' => count($images),
                        'aspect_ratio' => $config['aspect_ratio'],
                        'raw_response' => $response
                    ];
                }
            }
            
            // 에러 응답 처리
            if (isset($response['error'])) {
                return [
                    'status' => 'error',
                    'msg' => 'Gemini 이미지 생성 에러: ' . ($response['error']['message'] ?? '알 수 없는 오류'),
                    'success' => false,
                    'images' => [],
                    'model' => $config['model'],
                    'count' => 0,
                    'error' => $response['error']
                ];
            }
            
            throw new \Exception('이미지 생성 응답에 데이터가 없습니다.');
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'msg' => 'Gemini 이미지 생성 실패: ' . $e->getMessage(),
                'success' => false,
                'images' => [],
                'model' => $config['model'] ?? 'gemini-2.5-flash-image',
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
                    'msg' => $result['msg'] ?? 'Gemini 이미지 생성에 실패했습니다.',
                    'success' => false,
                    'saved_files' => [],
                    'error' => $result['error'] ?? 'generation_failed'
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

            // 각 이미지 디코딩 및 저장
            $savedFiles = [];
            $timestamp = date('YmdHis');
            
            foreach ($result['images'] as $index => $imageInfo) {
                // Base64 디코딩
                $imageData = base64_decode($imageInfo['data']);
                
                if ($imageData === false) {
                    error_log("이미지 디코딩 실패");
                    continue;
                }

                // 파일 확장자 결정
                $extension = 'png';
                if (isset($imageInfo['mime_type'])) {
                    if (strpos($imageInfo['mime_type'], 'jpeg') !== false) {
                        $extension = 'jpg';
                    } elseif (strpos($imageInfo['mime_type'], 'webp') !== false) {
                        $extension = 'webp';
                    }
                }

                // 파일명 생성
                $fileName = $filePrefix . '_' . $timestamp . '_' . ($index + 1) . '.' . $extension;
                $filePath = rtrim($savePath, '/') . '/' . $fileName;
                
                // 파일 저장
                if (file_put_contents($filePath, $imageData) !== false) {
                    $savedFiles[] = [
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'mime_type' => $imageInfo['mime_type'],
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
                'aspect_ratio' => $result['aspect_ratio'] ?? '1:1',
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
