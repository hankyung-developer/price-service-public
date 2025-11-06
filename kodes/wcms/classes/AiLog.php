<?php
namespace Kodes\Wcms;

// ini_set('display_errors', 1);

/**
 * Ai Log 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class AiLog
{
    /** const */
	const COLLECTION = 'aiLog';

    /** @var Class */
    protected $db;
    protected $common;
    protected $json;
    protected $log;
    protected $api;
    protected $apiInfo;
    
    /** @var variable */
    protected $coId;
	protected $menu;
    protected $company;
    protected $domain;
    protected $aiPriceInfo;
    protected $siteDocPath;
    protected $aiSetting;

    /**
     * 생성자
     */
    function __construct()
    {
        // class
        $this->db = new DB();
        $this->common = new Common();

        // variable
        $this->coId = $this->common->coId;
        $this->siteDocPath = $this->common->config['path']['data'].'/'.$this->coId;
        $this->aiSetting = new AiSetting();

        $_GET['isUse'] = "all";
        $apiInfo = $this->aiSetting->modelList();
        foreach($apiInfo as $key => $value){
            $this->apiInfo[$value['modelName']] = $value;
        }
    }

    /**
     * 목록
     *
     * @param String [GET] searchText 검색어 (option)
     * @return Array $return[items, page]
     */
    public function list($request=null)
    {
        try {
            $request = empty($request)?$_GET:$request;

            $return = [];
            $filter = [];
            $options = [];

            // filter
            $filter['coId'] = $this->coId;
            $filter['delete.is'] = ['$ne'=>true];
            if (!empty($request['searchText'])) {
                $filter['name'] = new \MongoDB\BSON\Regex($request['searchText'],'i');
            }
            if (!empty($request['mediaType'])) {
                $filter['mediaType'] = $request['mediaType'];
            }

            $sort = empty($request['sort'])?['insert.date'=>-1]:$request['sort'];
            $projection = empty($request['projection'])?['_id'=>0]:$request['projection'];

            //  count 조회
            $return['totalCount'] = $this->db->count(self::COLLECTION, $filter);
            
            // paging
            $noapp = empty($request['noapp'])?20:$request['noapp'];
            $page = empty($request['page'])?1:$request['page'];
            $pageInfo = new Page;
            $return['page'] = $pageInfo->page(20, 5, $return['totalCount'], $page);
            
            // options
            $options = ['skip' => ($page - 1) * $noapp, 'limit'=>$noapp, 'sort'=>$sort, 'projection'=>$projection];
            
            // list 조회
            $return['items'] = $this->db->list(self::COLLECTION, $filter, $options);

            foreach ($return['items'] as $key => $value) {
                $thisAiInfo = $this->apiInfo[trim($value['model'])];

                if(empty($thisAiInfo)){
                    continue;
                }

                $return['items'][$key]['usage']['promptPrice']=$thisAiInfo['inputPrice'] * ( $value['usage']["prompt_tokens"] / $thisAiInfo['tokenUsageScale'] );
                $return['items'][$key]['usage']['completionPrice']=$thisAiInfo['outputPrice'] * ( $value['usage']["completion_tokens"] / $thisAiInfo['tokenUsageScale']);;
                $return['items'][$key]['usage']['totalPrice']=$return['items'][$key]['usage']['promptPrice'] + $return['items'][$key]['usage']['completionPrice'];
            }

        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $return;
    }

    public function put($model, $size, $quality, $price)
    {
        $this->db->insert(self::COLLECTION, [
            'coId' => $this->coId,
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
            'price' => $price
        ]);
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
    public function logGptCall($model, $prompt, $response, $options = [], $usage = [], $cost = 0, $status = 'success', $errorMsg = '')
    {
        try {           
            $logData = [
                'coId' => $this->coId,
                'model' => $model,
                'prompt' => $prompt,
                'response' => $response,
                'options' => $options,
                'usage' => $usage,
                'cost' => $cost,
                'status' => $status,
                'errorMsg' => $errorMsg,
                'insert' => [
                    'managerId' => $_SESSION['managerId'] ?? 'unknown',
                    'managerName' => $_SESSION['managerName'] ?? 'unknown',
                    'date' => date('Y-m-d H:i:s'),
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]
            ];

            $this->db->insert(self::COLLECTION, $logData);
            
        } catch (\Exception $e) {
            error_log("GPT API 로그 기록 실패: " . $e->getMessage());
        }
    }

    /**
     * 모델과 사용량을 기반으로 비용을 계산합니다.
     * 
     * @param string $model 모델명
     * @param array $usage 사용량 정보
     * @return float 계산된 비용
     */
    public function calculateCost($model, $usage)
    {
        if (!isset($this->aiPriceInfo[$model])) {
            error_log("모델 가격 정보 없음: $model");
            return 0;
        }
        
        $priceInfo = $this->aiPriceInfo[$model];
        print_r($priceInfo);
        $inputTokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0;
        
        // 1M tokens 기준으로 계산
        $inputCost = ($inputTokens / 1000000) * $priceInfo['input'];
        $outputCost = ($outputTokens / 1000000) * $priceInfo['output'];
        
        return $inputCost + $outputCost;
    }
}