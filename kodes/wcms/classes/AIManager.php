<?php 
namespace Kodes\Wcms;

/**
 * AI 모델명을 확인하여 처리하는 공통 AI 클래스
 * 
 * 사용법:
 * $aiManager = new AIManager();
 * $result = $aiManager->sendPrompt('프롬프트 내용', ['model' => 'gpt-4']);
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 2.0
 */
class AIManager
{
    /** @var Common 공통 클래스 */
    protected $common;
    
    /** @var string 회사 ID */
    protected $coId;
    
    /** @var string 사이트 문서 경로 */
    protected $siteDocPath;
    
    /** @var array AI 서비스 인스턴스들 (지연 로딩) */
    protected $services = [];
    
    /** @var array 모델별 서비스 매핑 */
    protected $modelServiceMap = [];
    
    /** @var AiSetting AI 설정 클래스 */
    protected $aiSetting;
    
    /** @var array 사용 가능한 모델 목록 */
    protected $availableModels = [];

    /**
     * AI 매니저 초기화
     */
    public function __construct()
    {
        $this->common = new Common();
        $this->aiSetting = new AiSetting();
        $this->coId = $this->common->coId;
        $this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
        
        // 사용 가능한 모델 로드
        $this->loadAvailableModels();
    }


    /**
     * 사용 가능한 모델 로드
     */
    protected function loadAvailableModels()
    {
        try {
            $models = $this->aiSetting->modelList();
            
            if (is_array($models) && !empty($models)) {
                foreach ($models as $model) {
                    if (!empty($model['isUse']) && $model['isUse'] === 'Y') {
                        $serviceName = $this->detectService($model['company']);
                        
                        $this->availableModels[$model['modelName']] = [
                            'idx' => $model['idx'],
                            'name' => $model['name'],
                            'modelName' => $model['modelName'],
                            'service' => $serviceName,
                            'modelType' => $model['modelType'] ?? 'text'
                        ];
                        
                        $this->modelServiceMap[$model['modelName']] = $serviceName;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("AIManager: 모델 로드 실패 - " . $e->getMessage());
        }
    }

    /**
     * 회사명/모델명으로 AI 서비스 감지
     * 
     * @param string $identifier 회사명 또는 모델명
     * @return string 서비스명 (gpt|claude|gemini)
     */
    protected function detectService($identifier)
    {
        $lower = strtolower($identifier);
        
        if (strpos($lower, 'openai') !== false || strpos($lower, 'gpt') !== false || strpos($lower, 'dall-e') !== false) {
            return 'gpt';
        }
        
        if (strpos($lower, 'anthropic') !== false || strpos($lower, 'claude') !== false) {
            return 'claude';
        }
        
        if (strpos($lower, 'google') !== false || strpos($lower, 'gemini') !== false || strpos($lower, 'imagen') !== false) {
            return 'gemini';
        }
        
        return 'gpt'; // 기본값
    }

    /**
     * AI 서비스 인스턴스 가져오기 (지연 로딩)
     * 
     * @param string $serviceName 서비스명 (gpt|claude|gemini)
     * @return object AI 서비스 인스턴스
     * @throws \Exception 지원되지 않는 서비스
     */
    protected function getService($serviceName)
    {
        if (isset($this->services[$serviceName])) {
            return $this->services[$serviceName];
        }
        
        switch ($serviceName) {
            case 'gpt':
                $this->services[$serviceName] = new GPT();
                break;
            case 'claude':
                $this->services[$serviceName] = new Claude();
                break;
            case 'gemini':
                $this->services[$serviceName] = new Gemini();
                break;
            default:
                throw new \Exception("지원되지 않는 AI 서비스: {$serviceName}");
        }
        
        return $this->services[$serviceName];
    }

    /**
     * 프롬프트 전송 (모델명 자동 감지)
     * 
     * @param string $prompt 프롬프트 내용
     * @param array $options 옵션
     *   - model: AI 모델명 (예: gpt-4, claude-3-opus, gemini-pro)
     *   - temperature: 창의성 (0.0~2.0, 기본: 0.7)
     *   - max_tokens: 최대 토큰 수 (기본: 4096)
     *   - return_json: JSON 응답 여부 (기본: false)
     *   - web_format: 웹 친화적 포맷으로 응답 (기본: false)
     * @return array 응답 배열
     *   - status: 'success' 또는 'error'
     *   - content: AI 응답 내용
     *   - model: 사용된 모델명
     *   - service: 사용된 서비스명 (gpt|claude|gemini)
     */
    public function sendPrompt($prompt, $options = [])
    {
        try {
            // 모델명 결정
            $modelName = $options['model'] ?? $this->getDefaultModel();
            
            // 서비스 감지
            $serviceName = isset($this->modelServiceMap[$modelName]) 
                ? $this->modelServiceMap[$modelName] 
                : $this->detectService($modelName);
            
            // AI 서비스 호출
            $service = $this->getService($serviceName);
            $response = $service->sendPrompt($prompt, $options);
            
            // 응답에 메타 정보 추가
            $response['service'] = $serviceName;
            $response['model'] = $modelName;
            
            // 웹 친화적 포맷으로 변환 (옵션)
            return $this->formatWebResponse($response);            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'msg' => 'AI 요청 실패: ' . $e->getMessage(),
                'success' => false,
                'content' => '',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 웹 친화적 응답 포맷으로 변환
     * 
     * @param array $response AI 서비스 원본 응답
     * @return array 정리된 응답
     */
    public function formatWebResponse($response)
    {
        // 에러 응답 처리
        if ($response['status'] !== 'success') {
            return [
                'success' => false,
                'error' => $response['msg'] ?? '알 수 없는 오류',
                'data' => null,
                'meta' => [
                    'model' => $response['model'] ?? 'unknown',
                    'service' => $response['service'] ?? 'unknown'
                ]
            ];
        }

        // content에서 JSON 추출 시도
        $data = $this->extractJson($response['content']);
        
        // JSON 추출 실패 시 원본 content 사용
        if (!$data) {
            $data = [
                'content' => $response['content']
            ];
        }

        // 토큰 사용량 정보 추출
        $tokens = $this->extractTokenUsage($response);

        // 깔끔한 응답 구조
        return [
            'success' => true,
            'data' => $data,
            'meta' => [
                'model' => $response['model'] ?? 'unknown',
                'service' => $response['service'] ?? 'unknown',
                'tokens' => $tokens,
                'finish_reason' => $response['finish_reason'] ?? null
            ]
        ];
    }

    /**
     * 토큰 사용량 정보 추출
     * 
     * @param array $response AI 서비스 응답
     * @return array 토큰 사용량 정보
     */
    protected function extractTokenUsage($response)
    {
        // GPT 형식 (usage 또는 usage_info)
        if (isset($response['usage'])) {
            $usage = $response['usage'];
            return [
                'prompt' => $usage['prompt_tokens'] ?? 0,
                'completion' => $usage['completion_tokens'] ?? 0,
                'total' => $usage['total_tokens'] ?? 0
            ];
        }
        
        if (isset($response['usage_info'])) {
            $usage = $response['usage_info'];
            return [
                'prompt' => $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0,
                'completion' => $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0,
                'total' => $usage['total_tokens'] ?? 0
            ];
        }

        // Claude 형식 (input_tokens, output_tokens)
        if (isset($response['input_tokens']) || isset($response['output_tokens'])) {
            return [
                'prompt' => $response['input_tokens'] ?? 0,
                'completion' => $response['output_tokens'] ?? 0,
                'total' => ($response['input_tokens'] ?? 0) + ($response['output_tokens'] ?? 0)
            ];
        }

        return [
            'prompt' => 0,
            'completion' => 0,
            'total' => 0
        ];
    }

    /**
     * 기본 모델 가져오기
     * 
     * @return string 기본 모델명
     */
    protected function getDefaultModel()
    {
        if (!empty($this->availableModels)) {
            $firstModel = reset($this->availableModels);
            return $firstModel['modelName'];
        }
        return 'gpt-4o'; // 폴백 기본값
    }

    /**
     * 사용 가능한 모델 목록 조회
     * 
     * @param string $modelType 모델 타입 필터 (text|image)
     * @return array 모델 목록
     */
    public function getAvailableModels($modelType = null)
    {
        $models = $this->availableModels;
        
        // 모델 타입 필터링
        if ($modelType) {
            $models = array_filter($models, function($model) use ($modelType) {
                return $model['modelType'] === $modelType;
            });
        }
        
        return array_values($models);
    }

    /**
     * API 분석을 위한 AI 호출
     * 
     * @param string $prompt 분석 프롬프트
     * @param string $modelName AI 모델명 (선택사항)
     * @return array 분석 결과
     */
    public function analyzeApi($prompt, $modelName = null)
    {
        try {
            if (empty($prompt)) {
                return [
                    'success' => false,
                    'msg' => '프롬프트가 제공되지 않았습니다.'
                ];
            }

            // AI 호출
            $response = $this->sendPrompt($prompt, [
                'model' => $modelName ?: $this->getDefaultModel(),
                'return_json' => true,
                'max_tokens' => 8192
            ]);

            // 응답 처리
            if ($response['status'] !== 'success') {
                return [
                    'success' => false,
                    'msg' => 'AI 분석 실패: ' . ($response['msg'] ?? '알 수 없는 오류')
                ];
            }

            // JSON 추출
            $data = $this->extractJson($response['content']);
            
            if (!$data) {
                return [
                    'success' => false,
                    'msg' => 'JSON 응답을 파싱할 수 없습니다.',
                    'raw_content' => $response['content']
                ];
            }

            return [
                'success' => true,
                'msg' => 'AI 분석이 완료되었습니다.',
                'data' => $data,
                'model_info' => [
                    'model' => $response['model'],
                    'service' => $response['service']
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'msg' => 'API 분석 오류: ' . $e->getMessage()
            ];
        }
    }

    /**
     * JSON 추출 헬퍼
     * 
     * @param mixed $content 응답 콘텐츠
     * @return array|null 파싱된 JSON 또는 null
     */
    protected function extractJson($content)
    {
        if (is_array($content)) {
            return $content;
        }
        
        if (is_string($content)) {
            // 전체를 JSON으로 파싱 시도
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // ```json ... ``` 형태에서 추출
            if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
                $decoded = json_decode(trim($matches[1]), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }
        
        return null;
    }

    /**
     * 이미지 생성
     * 
     * @param string $prompt 이미지 생성 프롬프트
     * @param array $options 옵션
     *   - model: 모델명 (기본: dall-e-3)
     *   - size: 크기 (예: 1024x1024)
     *   - n: 개수 (dall-e-2만 지원)
     * @return array 응답
     */
    public function generateImage($prompt, $options = [])
    {
        try {
            $modelName = $options['model'] ?? 'dall-e-3';
            $serviceName = $this->detectService($modelName);
            $service = $this->getService($serviceName);
            
            if (!method_exists($service, 'generateImage')) {
                return [
                    'status' => 'error',
                    'msg' => "{$serviceName} 서비스는 이미지 생성을 지원하지 않습니다.",
                    'success' => false
                ];
            }
            
            $response = $service->generateImage($prompt, $options);
            $response['service'] = $serviceName;
            $response['model'] = $modelName;
            
            return $response;
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'msg' => '이미지 생성 실패: ' . $e->getMessage(),
                'success' => false
            ];
        }
    }

    /**
     * 이미지 생성 및 저장
     * 
     * @param string $prompt 이미지 생성 프롬프트
     * @param string $savePath 저장 경로 (상대 경로 또는 절대 경로)
     * @param string $filePrefix 파일명 접두사 (기본: 'ai_image')
     * @param array $options 옵션
     * @return array 저장된 파일 정보
     */
    public function generateAndSaveImage($prompt, $savePath, $filePrefix = 'ai_image', $options = [])
    {
        try {
            // 상대 경로 처리
            if (!preg_match('/^[a-z]:/i', $savePath) && substr($savePath, 0, 1) !== '/') {
                $savePath = $this->siteDocPath . '/' . ltrim($savePath, '/');
            }
            
            $modelName = $options['model'] ?? 'dall-e-3';
            $serviceName = $this->detectService($modelName);
            $service = $this->getService($serviceName);
            
            if (!method_exists($service, 'generateAndSaveImage')) {
                return [
                    'status' => 'error',
                    'msg' => "{$serviceName} 서비스는 이미지 저장을 지원하지 않습니다.",
                    'success' => false
                ];
            }
            
            $response = $service->generateAndSaveImage($prompt, $savePath, $filePrefix, $options);
            $response['service'] = $serviceName;
            $response['model'] = $modelName;
            
            return $response;
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'msg' => '이미지 생성 및 저장 실패: ' . $e->getMessage(),
                'success' => false
            ];
        }
    }
}