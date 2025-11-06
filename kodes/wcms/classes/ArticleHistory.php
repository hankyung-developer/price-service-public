<?php
namespace Kodes\Wcms;

// ini_set('display_errors', 1);

/**
 * Article 히스토리 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class ArticleHistory
{
	/** const */
	const COLLECTION = 'articleHistory';

	/** @var Class */
	protected $common;
	protected $db;

	/** @var variable */
	protected $coId;

	/**
	 * 생성자
	 */
	public function __construct()
	{
		// class
		$this->common = new Common();
		$this->db = new DB();

		// variable
		$this->coId = $this->common->coId;
	}

    /**
     * 조회
     *
     * @param string $aid
     * @return array
     */
    public function items($aid)
	{
		$filter = ['aid' => $aid];
        $options = ['sort' => ['historyId' => -1]];
        $result = $this->db->list(self::COLLECTION, $filter, $options);
		foreach ($result as $key => $value) {
			$result[$key]['statusName'] = ($this->common->getStatusName($result[$key]['status'])).(!empty($result[$key]['embargo.is'])?'(예약설정)':'');
		}
        return $result;
    }

    /**
     * 입력
     *
     * @param array $data
     */
    public function insert($data)
	{
		$filter = ["aid" =>$data['aid']];
        $options = ["sort" => ["historyId" => -1], "limit" => 1];
		$cursor = $this->db->item(self::COLLECTION, $filter, $options);
		$latestId = $cursor["historyId"];
        if (empty($latestId)) {
            $latestId = 0;
        }
        $data['historyId'] = $latestId+1;
        
		$this->db->insert(self::COLLECTION, $data);
    }
}