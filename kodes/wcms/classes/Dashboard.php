<?php
namespace Kodes\Wcms;

class Dashboard {
    /** @var Class Json Class */
    protected $json;
    /** @var Class Common Class */
    protected $common;

    public function __construct(){
        $this->json = new Json();
        $this->common = new Common();

        // variable
		$this->coId = $this->common->coId;
    }

    public function dashboard()
    {
		try {
            $argdate = date("Y-m-d");
            $gaPath = $this->common->config['path']['data']."/".$this->coId."/analytics/";
            
			$mdata = $this->json->readJsonFile($gaPath,'m_dashboard');
				
            if( !empty($mdata) ){
                $data['datetime_dash'] = $mdata['dashboard'];
                $data['update'] = $mdata['date'] ;
            }else{
                $data['datetime_dash'] = [];
                $data['update'] = '';
            }

            $data['source'] = $this->json->readJsonFile($gaPath,'m_referrers');

            $data['realtime'] = $this->json->readJsonFile($gaPath,'m_realtime');
        }catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
		
        return $data;
    }
}