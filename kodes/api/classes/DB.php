<?php 
/**
 * DB 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 * 
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
namespace Kodes\Api;

class DB
{
	/** @var DB Info */
    protected $dbInfo;

	/** @var DB Connection mongodb Connection */
    protected $_mongo;

    /** @var db mongodb write pipline */
    protected $_writeConcen;

	/** @var db name */
	protected $dbName;

	/**
     * 생성자
     */
    public function __construct($dbName=null)
	{
		$this->dbName = $dbName;
		// $this->getConnection();
	}

	/**
	 * database change
	 * @param string $databaseName 데이타 베이스 이름
	 */
	public function changeDatabase($dbName)
	{
		$this->dbName = $dbName;
		$this->_mongo = null;
		$this->getConnection();
	}

	/**
	 * db 연결
	 */
	protected function getConnection()
	{
		if (empty($this->_mongo)) {
			$maxRetries = 3;
			$retryCount = 0;
			
			while ($retryCount < $maxRetries) {
				try {
					$configDB = json_decode(file_get_contents("/webSiteSource/kodes/api/config/db.json"), true);
					$this->dbInfo = !empty($configDB[$this->dbName])?$configDB[$this->dbName]:$configDB['wcmsDB'];
					
					// MongoDB 연결 옵션 개선
					$options = [
						'connectTimeoutMS' => 5000,  // 연결 타임아웃 5초
						'socketTimeoutMS' => 30000,  // 소켓 타임아웃 30초
						'serverSelectionTimeoutMS' => 10000,  // 서버 선택 타임아웃 10초
						'retryWrites' => true,  // 쓰기 재시도 활성화
						'retryReads' => true,   // 읽기 재시도 활성화
						'w' => 1,  // Write Concern 레벨 (Primary가 죽어도 Secondary에 즉시 쓰기)
						'wtimeout' => 10000  // Write Concern 타임아웃
					];
					
					$this->_mongo = new \MongoDB\Driver\Manager($this->dbInfo['server'], $options);
					
					// 연결 테스트
					$command = new \MongoDB\Driver\Command(['ping' => 1]);
					$this->_mongo->executeCommand($this->dbInfo['db'], $command);
					
					// WriteConcern 설정 최적화
					// WriteConcern(1, 10000): Primary가 죽어도 Secondary에 즉시 쓰기 가능
					// - 첫 번째 파라미터(1): 최소 1개 노드에 쓰기 완료 시 성공
					// - 두 번째 파라미터(10000): 10초 타임아웃
					// - WriteConcern::MAJORITY보다 빠르고 고가용성 보장
					$this->_writeConcen = new \MongoDB\Driver\WriteConcern(1, 10000);
					
					// 연결 성공 시 루프 종료
					break;
					
				} catch (\Exception $e) {
					$retryCount++;
					error_log("MongoDB 연결 시도 {$retryCount}/{$maxRetries} 실패: " . $e->getMessage());
					
					if ($retryCount >= $maxRetries) {
						throw new \Exception("데이터베이스 연결에 실패했습니다 (시도: {$maxRetries}회): " . $e->getMessage());
					}
					
					// 재시도 전 잠시 대기
					sleep(1);
				}
			}
		}
	}


	/**
     * DB Collection의 검색조건에 맞는 Row의 갯수를 반환
	 * 
     * @param String $collection [필수] Mongodb collection 명
     * @param Array  $filter     [필수] Mongodb 검색조건
     * @param String $hint       [선택] hint index 명
     * @return int Row의 갯수
     */
	public function count($collection, $filter, $hint=null)
	{
		try {
			$this->getConnection();

			$req = ["count" => $collection, "query" => (Object)$filter];
			if (!empty($hint)) $req['hint'] = $hint;
			$command = new \MongoDB\Driver\Command($req);
			$result = $this->_mongo->executeCommand($this->dbInfo['db'], $command);
			$res = current($result->toArray());
			return intval($res->n);
		} catch (\Exception $e) {
			throw $e;
		}
	}

    /**
     * DB Collection 의 리스트를 다차원 배열로 반환
	 * 
     * @param string $collection Mongodb collection 명
     * @param Array $filter Mongodb 검색조건
     * @param Array $options Mongodb 검색 옵션 sort등
     * @return Array 검색 조건에 맞는 리스트 배열
     */
	public function list($collection, $filter, $options)
	{
		try {
			$this->getConnection();

			$return = [];
			$query = new \MongoDB\Driver\Query((Object)$filter, $options);
			$cursor = $this->_mongo->executeQuery($this->dbInfo['db'].'.'.$collection, $query);
			if (!empty($cursor)) {
				foreach ($cursor as $document) {
					$return[] = json_decode(json_encode($document), true);
				}
			}
			return $return;
		} catch (\Exception $e) {
			throw $e;
		}
	}

    /**
     * DB Collection의 검색조건에 맞는 정보를 반환
	 * 
     * @param string $collection Mongodb collection 명
     * @param Array $filter Mongodb 검색조건
     * @return Array 검색 단일 문서
     */
	public function item($collection, $filter, $options)
	{
		try {
			$this->getConnection();

			$return = [];
			$options['limit'] = 1;
			$query = new \MongoDB\Driver\Query($filter, $options);
			$cursor = $this->_mongo->executeQuery($this->dbInfo['db'].'.'.$collection, $query);
			if (!empty($cursor)) {
				foreach ($cursor as $document) {
					$return = json_decode(json_encode($document), true);
				}
			}
			return $return;
		} catch (\Exception $e) {
			throw $e;
		}
    }

	/**
     * DB Collection의 검색조건에 맞는 Row의 정보를 입력
	 * 
     * @param string $collection Mongodb collection 명
	 * @param Array $object Mongodb 변경내용
     * @return int Row의 갯수
     */
	public function insert($collection, $object)
	{
		try {
			$this->getConnection();

			$bulk = new \MongoDB\Driver\BulkWrite;
			$bulk->insert($object);
			// WriteConcern 객체를 배열로 감싸서 전달해야 MongoDB 드라이버가 올바르게 인식합니다.
			$result = $this->_mongo->executeBulkWrite(
				$this->dbInfo['db'].'.'.$collection,
				$bulk,
				['writeConcern' => $this->_writeConcen]
			);
			return $result;
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
     * DB Collection의 검색조건에 맞는 Row의 정보를 수정
	 * 
     * @param String $collection Mongodb collection 명
     * @param Array $filter Mongodb 검색조건
     * @param Array $object Mongodb 변경내용
	 * @param Bool $multi Mongodb 다중문서 수정 여부
	 * @param Bool $arrayFilters Mongodb 문서 내 배열 검색조건
     * @return int Row의 갯수
     */
	public function update($collection, $filter, $object, $multi=false, $arrayFilters=null)
	{
		try {
			$this->getConnection();

			$bulk = new \MongoDB\Driver\BulkWrite;
			$options = ['multi'=>$multi];
			if (!empty($arrayFilters)) {
				$options['arrayFilters'] = $arrayFilters;
			}

			$bulk->update($filter, $object, $options);

			// WriteConcern 객체를 배열로 감싸서 전달해야 MongoDB 드라이버가 올바르게 인식합니다.
			$result = $this->_mongo->executeBulkWrite(
				$this->dbInfo['db'].'.'.$collection,
				$bulk,
				['writeConcern' => $this->_writeConcen]
			);
			return $result;
		} catch (\Exception $e) {
			error_log("DB update error: " . $e->getMessage());
			throw $e;
		}
    }
    
    /**
     * DB Collection의 검색조건에 맞는 Row의 정보를 입력 또는 수정
	 * 
     * @param string $collection Mongodb collection 명
	 * @param Array $filter Mongodb 검색조건
     * @param Array $object Mongodb 변경내용
     * @return int Row의 갯수
     */
	public function upsert($collection, $filter, $object)
	{
		try {
			$this->getConnection();

			$bulk = new \MongoDB\Driver\BulkWrite;
			$bulk->update($filter, $object, ['upsert'=>true, 'multi'=>false]);
			// WriteConcern 객체를 배열로 감싸서 전달해야 MongoDB 드라이버가 올바르게 인식합니다.
			$result = $this->_mongo->executeBulkWrite(
				$this->dbInfo['db'].'.'.$collection,
				$bulk,
				['writeConcern' => $this->_writeConcen]
			);
			return $result;
		} catch (\Exception $e) {
			throw $e;
		}
	}

    /**
     * DB Collection의 검색조건에 맞는 Row의 정보를 삭제
	 * 
     * @param string $collection Mongodb collection 명
     * @param Array $filter Mongodb 검색조건
	 * @param Bool $limit true(매칭된 첫번째 문서 삭제), false(매칭된 모든 문서 삭제)
     * @return int Row의 갯수
     */
	public function delete($collection, $filter, $limit=true)
	{
		try {
			$this->getConnection();

			$bulk = new \MongoDB\Driver\BulkWrite;
			$bulk->delete($filter, ['limit' => $limit]);
			// WriteConcern 객체를 배열로 감싸서 전달해야 MongoDB 드라이버가 올바르게 인식합니다.
			$result = $this->_mongo->executeBulkWrite(
				$this->dbInfo['db'].'.'.$collection,
				$bulk,
				['writeConcern' => $this->_writeConcen]
			);
			return $result;
		} catch (\Exception $e) {
			throw $e;
		}
    }

   	/**
     * Mongo Command 함수 실행
     *
     * @param array $aggregate 검색 조건
     * @return array 검색 조건에 맞는 검색 결과
     */
	public function command($aggregate)
	{
		try {
			$this->getConnection();

			// aggregate 명령에 cursor 옵션 추가 (MongoDB 3.6+ 호환)
			if (isset($aggregate['aggregate']) && !isset($aggregate['cursor'])) {
				$aggregate['cursor'] = (object)[];
			}

			$query = new \MongoDB\Driver\Command($aggregate);
			$cursor = $this->_mongo->executeCommand($this->dbInfo['db'], $query);
			$result = [];
			$i=0;
			foreach ($cursor as $document) {
				$result[$i++] = json_decode(json_encode($document),true);;
			}
			return $result;
		} catch (\Exception $e) {
			throw $e;
		}
	}
}
