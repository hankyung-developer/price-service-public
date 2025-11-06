<?php 
namespace Kodes\Api;

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
/**
 * 업로드 처리 설정
 * 
 * 업로드 파일크기 관련하여 php.ini 에서 아래 값을 확인할 것
    upload_max_filesize = 250M
    post_max_size = 200M
    max_execution_time = 600
    max_input_time = 600
 * nginx wcms.conf
   client_max_body_size 200M;
 */
ini_set('memory_limit','2048M');

/**
 * 파일 클래스
 * 
 * @file
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 * https://www.kodes.co.kr
 */
class File
{
	/** const */
	const DS = DIRECTORY_SEPARATOR;
	const MAX_WIDTH = 1200;	// 이미지 가로 사이즈 제한
	const MAX_IMAGE_SIZE = 1 * 1048576;	// 이미지 크기 제한
	const IMAGE_QUALITY = 90;	// 이미지 퀄리티 설정
	const FILE_COLLECTION = 'file';
	
    /** @var Class DB Class */
    protected $db;
    /** @var Class Common Class */
    protected $common;
	protected $log;
	protected $iptc;

    public function __construct()
	{
        $this->db = new DB();
        $this->common = new Common();
		$this->log = new Log();
		$this->iptc = new Iptc();

		$this->coId = $this->common->coId;

		$this->imageExt = ['jpg','jpeg','png','gif','bmp','ico'];
    }

	/**
	 * 파일을 업로드한다.
	 */
    public function upload()
	{
		try {
			if (!empty($_POST["coId"])) $this->coId = $_POST["coId"];

			$ds          = self::DS;
			$storeFolder = '/webData/'.$this->coId.'/image';
			$targetFolder = '/data/'.$this->coId.'/image';
			$targetFileName = '';
			$fileInfo = [];

			if (!empty($_FILES)) {
				// File Upload
				$tempFile = $_FILES['file']['tmp_name'];
				$storeFullPath = $storeFolder.$ds.date('Y').$ds.date('m').$ds.date('d');
				$targetFullPath = $targetFolder.$ds.date('Y').$ds.date('m').$ds.date('d');
				if (!is_dir($storeFullPath)) {mkdir($storeFullPath,0755,true);}

				$ext = substr(strrchr($_FILES['file']['name'],"."),1);
				$ext = strtolower($ext);
				$misec = explode(" ", microtime());

				// jfif : 확장자만 변경
				if ($ext == 'jfif') {
					$ext = 'jpg';
				}

				$mediaId=$this->getFileId($this->coId);
				$targetFileName=$mediaId.'.'.$ext;
				$targetFilePath = $targetFullPath.$ds.$targetFileName;
				$storeFilePath = $storeFullPath.$ds.$targetFileName;
				$originFilePath = $storeFullPath.$ds.$mediaId.'_o.'.$ext;
				move_uploaded_file($tempFile, $storeFilePath);
				//copy($storeFullPath.$ds.$targetFileName, $originFilePath);

				// webp : jpg로 변환
				if ($ext == 'webp') {
					$im = imagecreatefromwebp($storeFilePath);
					$ext = 'jpg';
					$targetFileName = $mediaId.'.'.$ext;
					$targetFilePath = $targetFullPath.$ds.$targetFileName;
					$storeFilePath = $storeFullPath.$ds.$targetFileName;
					imagejpeg($im, $storeFilePath, 90);
					imagedestroy($im);
				}

				$size = getimagesize($storeFilePath);
				
				//File Information DB Insert
				//Media Id, File Name, File Path, Create Date
				$fileInfo["coId"]=$this->coId;
				$fileInfo["id"]=$mediaId;
				$fileInfo["name"]=$targetFileName;
				$fileInfo["orgName"]= $_FILES['file']['name'];
				$fileInfo["ext"]= $ext;
				$fileInfo["path"]=$targetFilePath;
				$fileInfo["type"]=in_array($ext, $this->imageExt)?"image":"doc";
				$fileInfo["mimeType"]=$_FILES['file']['type'];
				$fileInfo['title']="";
				$fileInfo['caption']=$_FILES['file']['caption'];
				$fileInfo['description']=$_FILES['file']['memo'];
				$fileInfo['watermarkPlace']=0;
				$fileInfo["size"]=$_FILES['file']['size'];
				$fileInfo["width"]=$size[0];
				$fileInfo["height"]=$size[1];
				$fileInfo['copyright']="";
				$fileInfo['categoryId']="";
				$fileInfo['paper']['date']="";
				$fileInfo['paper']['edit']="";
				$fileInfo['paper']['page']="";
				$fileInfo['isDisplay']=$_POST["isDisplay"]=="N"?(Bool)false:(Bool)true;
				$fileInfo['isCopy']=$_POST["isCopy"]=="Y"?(Bool)true:(Bool)false;
				$fileInfo['copyFileId']=$_POST["copyFileId"]!=""?$_POST["copyFileId"]:"";
				$fileInfo["insert"]["managerId"]=$_SESSION["managerId"];
				$fileInfo["insert"]["managerName"]=$_SESSION["managerName"];
				$fileInfo["insert"]["date"]=date('Y-m-d H:i:s');
				$fileInfo["filming"] = ['date' => '','location' => ''];

				// 업로드 구분
				if( !empty($_POST["uploadFlag"])) $fileInfo['uploadFlag'] = $_POST["uploadFlag"];

				// iptc 조회하여 없는 정보를 설정
				$iptc = $this->iptc->getIptc($storeFilePath);
				if (empty($fileInfo['title']) && !empty($iptc['title'])) $fileInfo['title'] = $iptc['title'];
				if (empty($fileInfo['caption']) && !empty($iptc['caption'])) $fileInfo['caption'] = $iptc['caption'];
				// if (empty($fileInfo['description']) && !empty($iptc['description'])) $fileInfo['description'] = $iptc['description'];
				if (empty($fileInfo['keyword']) && !empty($iptc['keyword'])) $fileInfo['keyword'] = $iptc['keyword'];
				if (empty($fileInfo['credit']) && !empty($iptc['credit'])) $fileInfo['credit'] = $iptc['credit'];
				if (empty($fileInfo['source']) && !empty($iptc['source'])) $fileInfo['source'] = $iptc['source'];
				if (empty($fileInfo['copyright']) && !empty($iptc['copyright'])) $fileInfo['copyright'] = $iptc['copyright'];
				if (empty($fileInfo["filming"]['date']) && !empty($iptc['creationDate'])) $fileInfo["filming"]['date'] = $iptc['creationDate'];
				if (empty($fileInfo["filming"]['location'])) {
					if (!empty($iptc['country'])) $fileInfo["filming"]['location'] .= $iptc['country'].' ';
					if (!empty($iptc['state'])) $fileInfo["filming"]['location'] .= $iptc['state'].' ';
					if (!empty($iptc['city'])) $fileInfo["filming"]['location'] .= $iptc['city'].' ';
					if (!empty($iptc['addr'])) $fileInfo["filming"]['location'] .= $iptc['addr'];
					$fileInfo["filming"]['location'] = trim($fileInfo["filming"]['location']);
				}

				// 이미지 최적화
				if (in_array($ext, ['jpg','jpeg','png','bmp'])) {
					$resizeInfo = $this->optimizeImage($storeFilePath, self::MAX_WIDTH, self::MAX_IMAGE_SIZE, self::IMAGE_QUALITY);
					if ($resizeInfo) {
						$fileInfo["size"] = $resizeInfo["size"];
						$fileInfo["width"] = $resizeInfo["width"];
						$fileInfo["height"] = $resizeInfo["height"];
					}
				}

				// getFileId 로직 변경으로 upsert로 변경
				$this->db->upsert(self::FILE_COLLECTION, ['id'=>$fileInfo["id"]], $fileInfo);
				//$this->db->insert($this->collection, $fileInfo);
				
				$msg = 'File '.$mediaId.'('.$targetFileName.') '.$_SESSION['managerId'].' '."Upload";
				$this->log->writeLog($this->coId, $msg, 'File_upload');

				$data = $fileInfo;
			}
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

	/**
	 * PDF 파일을 업로드한다.
	 */
    public function uploadPdf()
	{
		try {
			if (!empty($_POST["coId"])) $this->coId = $_POST["coId"];

			$ds          = self::DS;
			$storeFolder = '/webData/'.$this->coId.'/image/newspaper';
			$targetFolder = '/data/'.$this->coId.'/image/newspaper';
			$targetFileName = '';
			$fileInfo = [];

			if (!empty($_FILES)) {
				// File Upload
				$tempFile = $_FILES['file']['tmp_name'];
				$storeFullPath = $storeFolder.$ds.date('Y').$ds.date('m').$ds.date('d');
				$targetFullPath = $targetFolder.$ds.date('Y').$ds.date('m').$ds.date('d');
				if (!is_dir($storeFullPath)) {mkdir($storeFullPath,0755,true);}

				$ext = substr(strrchr($_FILES['file']['name'],"."),1);
				$ext = strtolower($ext);
				$misec = explode(" ", microtime());
				$mediaId=$this->getFileId($this->coId);
				$targetFileName=$mediaId.'.'.$ext;
				$targetFilePath = $targetFullPath.$ds.$targetFileName;
				$storeFilePath = $storeFullPath.$ds.$targetFileName;
				$originFilePath = $storeFullPath.$ds.$mediaId.'_o.'.$ext;
				move_uploaded_file($tempFile, $storeFilePath);
				$size = getimagesize($storeFilePath);

				$pdfsource = $storeFilePath;
				$pdftarget = $storeFullPath.$ds.str_replace('.pdf','.jpg', $targetFileName);
				$pdf = new \Spatie\PdfToImage\Pdf($storeFilePath);
					
				$this->fileInfo["thumbnail"]=[];
				foreach (range(1, $pdf->pageCount()) as $pageNumber) {
					$pdf->quality(100);
					$pdf->selectPage($pageNumber)->format(\Spatie\PdfToImage\Enums\OutputFormat::Jpg)->save($storeFullPath.$ds.str_replace('.pdf','', $targetFileName).'_'.$pageNumber.'.jpg');
					$fileInfo["thumbnail"][$pageNumber]=str_replace('/webData/','/data/',$storeFullPath.$ds.str_replace('.pdf','', $targetFileName).'_'.$pageNumber.'.jpg');
				}
				
				//File Information DB Insert
				//Media Id, File Name, File Path, Create Date
				$fileInfo["coId"]=$this->coId;
				$fileInfo["id"]=$mediaId;
				$fileInfo["name"]=$targetFileName;
				$fileInfo["orgName"]= $_FILES['file']['name'];
				$fileInfo["ext"]= $ext;
				$fileInfo["path"]=$targetFilePath;
				$fileInfo["type"]="doc";
				$fileInfo["mimeType"]=$_FILES['file']['type'];
				$fileInfo['title']="";
				$fileInfo['caption']=$_FILES['file']['caption'];
				$fileInfo['description']=$_FILES['file']['memo'];
				$fileInfo['watermarkPlace']=0;
				$fileInfo["size"]=$_FILES['file']['size'];
				$fileInfo["width"]=$size[0];
				$fileInfo["height"]=$size[1];
				$fileInfo['copyright']="";
				$fileInfo['categoryId']="";
				$fileInfo['isDisplay']=(Bool)false;
				$fileInfo['isCopy']=(Bool)false;
				$fileInfo['copyFileId']=$_POST["copyFileId"]!=""?$_POST["copyFileId"]:"";
				$fileInfo["insert"]["managerId"]=$_SESSION["managerId"];
				$fileInfo["insert"]["managerName"]=$_SESSION["managerName"];
				$fileInfo["insert"]["date"]=date('Y-m-d H:i:s');
				$fileInfo["filming"] = ['date' => '','location' => ''];
				
				$this->db->upsert(self::FILE_COLLECTION, ['id'=>$fileInfo["id"]], $fileInfo);
				
				$msg = 'File '.$mediaId.'('.$targetFileName.') '.$_SESSION['managerId'].' '."Upload";
				$this->log->writeLog($this->coId, $msg, 'Pdf_upload');

				$data = $fileInfo;
			}
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

	/**
	 * 이미지 파일을 지정 경로로 업로드, DB저장 안함
	 */
	public function save()
	{
		try {
			if (!empty($_POST["coId"])) $this->coId = $_POST["coId"];

			$ds = self::DS;
			$global = $_POST['global'];
			// 상위경로 접근 방지를 위해 '.' 제거
			$path = (empty($_POST['path'])?'':$ds.str_replace('.','',$_POST['path']));
			if ($global!='1') {
				$path = $ds.$this->coId.$ds.'upload'.$ds.'save'.$path;
			}
			if (!empty($path)) {
				$storeFolder = '/webData'.$path;
				$targetFolder = '/data'.$path;
				$targetFileName = '';
	
				if (!empty($_FILES)) {
					// File Upload
					$tempFile = $_FILES['file']['tmp_name'];
					if (!is_dir($storeFolder)) {mkdir($storeFolder,0755,true);}
	
					$ext = substr(strrchr($_FILES['file']['name'],"."),1);
					$ext = strtolower($ext);

					// jfif : 확장자만 변경
					if ($ext == 'jfif') {
						$ext = 'jpg';
					}

					$fileId = str_replace('.','',microtime(true));
					$targetFileName = $this->coId.$fileId.'.'.$ext;
					$storeFilePath = $storeFolder.$ds.$targetFileName;
					$targetFilePath = $targetFolder.$ds.$targetFileName;
					move_uploaded_file($tempFile,$storeFilePath);

					// webp : jpg로 변환
					if ($ext == 'webp') {
						$im = imagecreatefromwebp($storeFilePath);
						$ext = 'jpg';
						$targetFileName = $mediaId.'.'.$ext;
						$targetFilePath = $targetFullPath.$ds.$targetFileName;
						$storeFilePath = $storeFullPath.$ds.$targetFileName;
						imagejpeg($im, $storeFilePath, 90);
						imagedestroy($im);
					}

					$size = getimagesize($storeFilePath);

					$fileInfo["orgName"] = $_FILES['file']['name'];
					$fileInfo["size"] = $_FILES['file']['size'];
					$fileInfo["width"] = $size[0];
					$fileInfo["height"] = $size[1];
					$fileInfo["mimeType"] = $_FILES['file']['type'];
	
					// 이미지 최적화
					if (in_array($ext, ['jpg','jpeg','png','bmp'])) {
						$resizeInfo = $this->optimizeImage($storeFilePath, self::MAX_WIDTH, self::MAX_IMAGE_SIZE, self::IMAGE_QUALITY);
						if ($resizeInfo) {
							$fileInfo["size"] = $resizeInfo["size"];
							$fileInfo["width"] = $resizeInfo["width"];
							$fileInfo["height"] = $resizeInfo["height"];
						}
					}
	
					$data = [
						'path'=>$targetFilePath,
						'name'=>$targetFileName,
						'orgName'=>$fileInfo["orgName"],
						'width'=>$fileInfo["width"],
						'height'=>$fileInfo["height"],
						'size'=>$fileInfo["size"],
						'ext'=>$ext,
						'mimeType'=>$fileInfo["mimeType"],
						'type'=>in_array($ext, $this->imageExt)?"image":"doc",
					];
				}
			}
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

	/**
	 * 파일을 지정 경로로 업로드, DB저장 안함
	 */
	public function attach()
	{
		try {
			if (!empty($_POST["coId"])) $this->coId = $_POST["coId"];
			
			$ds = self::DS;
			// 상위경로 접근 방지를 위해 '.' 제거
			$path = (empty($_POST['path'])?'':$ds.str_replace('.','',$_POST['path']));
			if (!empty($path)) {

				$storeFolder = '/webData/'.$this->coId.$path;
				$targetFolder = '/data/'.$this->coId.$path;
				$targetFileName = '';

				if (!empty($_FILES)) {
					// File Upload
					$tempFile = $_FILES['file']['tmp_name'];
					if (!is_dir($storeFolder)) {mkdir($storeFolder,0755,true);}

					if (empty($_POST['useOrgName'])) {
						$ext = substr(strrchr($_FILES['file']['name'],"."),1);
						$ext = strtolower($ext);
						$fileId = str_replace('.','',microtime(true));
						$targetFileName = $this->coId.$fileId.'.'.$ext;
						$storeFilePath = $storeFolder.$ds.$targetFileName;
						$targetFilePath = $targetFolder.$ds.$targetFileName;
					} else {
						$targetFileName = $_FILES['file']['name'];
						$storeFilePath = $storeFolder.$ds.$targetFileName;
						$targetFilePath = $targetFolder.$ds.$targetFileName;
					}

					move_uploaded_file($tempFile,$storeFilePath);

					$size = getimagesize($storeFilePath);

					$fileInfo["orgName"] = $_FILES['file']['name'];
					$fileInfo["size"] = $_FILES['file']['size'];
					$fileInfo["width"] = $size[0];
					$fileInfo["height"] = $size[1];
					$fileInfo["mimeType"] = $_FILES['file']['type'];
	
					$data = [
						'path'=>$targetFilePath,
						'name'=>$targetFileName,
						'orgName'=>$fileInfo["orgName"],
						'width'=>$fileInfo["width"],
						'height'=>$fileInfo["height"],
						'size'=>$fileInfo["size"],
						'ext'=>$ext,
						'mimeType'=>$fileInfo["mimeType"],
						'type'=>in_array($ext, $this->imageExt)?"image":"doc",
					];
				}
			}
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

	/**
	 * 파일을 카피해서 새로운 이미지로 등록한다.
	 */
	public function copy()
	{
		try {
			$fileName = $_POST['fileName'];
			$filePath = $_POST['filePath'];
			$orginalFile = ".".$_POST['filePath'];
			
			$ds = DIRECTORY_SEPARATOR;
			$ext = substr(strrchr($fileName,"."),1);
			$ext = strtolower($ext);
			$fileId=$this->getFileId($this->coId);
			$targetFileName=$fileId.'.'.$ext;
			$targetFolder = '/data/'.$this->coId.'/image'.$ds.date('Y').$ds.date('m').$ds.date('d');
			$storeFolder = '/webData/'.$this->coId.'/image';
			$targetFullPath = $storeFolder.$ds.date('Y').$ds.date('m').$ds.date('d');
			if (!is_dir($targetFullPath)) {mkdir($targetFullPath,0755,true);}
			$targetFile = $targetFullPath.$ds.$targetFileName;
			
			copy($orginalFile, $targetFile);

			$imageInfo = getimagesize($targetFullPath.$ds.$targetFileName);

			$fileInfo["coId"]=$this->coId;
			$fileInfo["id"]=$fileId;
			$fileInfo["name"]=$targetFileName;
			$fileInfo["orgName"]=$fileName;
			$fileInfo["ext"]= $ext;
			$fileInfo["path"]=$targetFolder.$ds.$targetFileName;
			$fileInfo["type"]="image";
			$fileInfo["mimeType"]=$imageInfo['mime'];
			$fileInfo['title']="";
			$fileInfo['caption']="";
			$fileInfo['description']="";
			$fileInfo['watermarkPlace']=0;
			$fileInfo["size"]="";
			$fileInfo["width"]=$imageInfo[0];
			$fileInfo["height"]=$imageInfo[1];
			$fileInfo['copyright']="";
			$fileInfo['categoryId']="";
			$fileInfo['paper']['date']="";
			$fileInfo['paper']['edit']="";
			$fileInfo['paper']['page']="";
			$fileInfo['isDisplay']=(Bool)false;
			$fileInfo['isCopy']=(Bool)true;
			$fileInfo['copyFileId']=explode(".", $fileName)[0];
			$fileInfo["insert"]["managerId"]=$_SESSION["managerId"];
			$fileInfo["insert"]["managerName"]=$_SESSION["managerName"];
			$fileInfo["insert"]["date"]=date('Y-m-d H:i:s');
			$fileInfo["filming"] = ['date' => '','location' => ''];

			// iptc 조회하여 없는 정보를 설정
			$iptc = $this->iptc->getIptc($targetFile);
			if (empty($fileInfo['title']) && !empty($iptc['title'])) $fileInfo['title'] = $iptc['title'];
			if (empty($fileInfo['caption']) && !empty($iptc['caption'])) $fileInfo['caption'] = $iptc['caption'];
			// if (empty($fileInfo['description']) && !empty($iptc['description'])) $fileInfo['description'] = $iptc['description'];
			if (empty($fileInfo['keyword']) && !empty($iptc['keyword'])) $fileInfo['keyword'] = $iptc['keyword'];
			if (empty($fileInfo['credit']) && !empty($iptc['credit'])) $fileInfo['credit'] = $iptc['credit'];
			if (empty($fileInfo['source']) && !empty($iptc['source'])) $fileInfo['source'] = $iptc['source'];
			if (empty($fileInfo['copyright']) && !empty($iptc['copyright'])) $fileInfo['copyright'] = $iptc['copyright'];
			if (empty($fileInfo["filming"]['date']) && !empty($iptc['creationDate'])) $fileInfo["filming"]['date'] = $iptc['creationDate'];
			if (empty($fileInfo["filming"]['location'])) {
				if (!empty($imgInfo['country'])) $fileInfo["filming"]['location'] .= $imgInfo['country'].' ';
				if (!empty($imgInfo['state'])) $fileInfo["filming"]['location'] .= $imgInfo['state'].' ';
				if (!empty($imgInfo['city'])) $fileInfo["filming"]['location'] .= $imgInfo['city'].' ';
				if (!empty($imgInfo['addr'])) $fileInfo["filming"]['location'] .= $imgInfo['addr'];
				$fileInfo["filming"]['location'] = trim($fileInfo["filming"]['location']);
			}
			
			// getFileId 로직 변경으로 upsert로 변경
			$this->db->upsert(self::FILE_COLLECTION, ['id'=>$fileInfo["id"]], $fileInfo);
			//$this->db->insert($this->collection, $fileInfo);
			
			$msg = 'File '.$fileId.'('.$targetFileName.') '.$_SESSION['managerId'].' '."File Copy";
			$this->log->writeLog($this->coId, $msg, 'File_copy');

			$fileInfo['sfilePath'] = preg_replace('/([.][a-z]+)$/','.120x120.0$1',$fileInfo["path"]);

			$data = $fileInfo;
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

    public function getFileId($coId, $date="")
	{
        $fileDate = ($date==""?date("Ymd"):date("Ymd",strtotime($date)));
        $fileId = "";
        $regex = new \MongoDB\BSON\Regex($coId.$fileDate,'');
        $filter = ["id"=>$regex];
        $options = ["sort"=>["id"=>-1],"limit"=>1];
		$result = $this->db->item(self::FILE_COLLECTION, $filter, $options);
        if (array_key_exists('id',$result)) {
			$fileId = $result["id"];
            $fileId++;
        } else {
            $fileId = $coId. $fileDate."000001";
        }
		$result = $this->db->insert(self::FILE_COLLECTION, ['id'=>$fileId]);
		if ($result->getInsertedCount() == 0) {
			// 재귀호출
			return $this->getFileId($coId, $date);
		}
        return $fileId;
    }

	public function externalImg()
	{
		try {
			$data = [];

			$storeFolder = '/webData/'.$this->coId.'/image/'.date("Y/m/d/");
			if (!is_dir($storeFolder)) {mkdir($storeFolder,0755,true);}
			$targetFolder = str_replace('/webData','/data',$storeFolder);
			$mediaId=$this->getFileId($this->coId);
			$orgFile = $_POST['url'];
			
			preg_match('/[.]([a-z0-9]+)$/i',$orgFile,$tmp);
			$ext = $tmp[1];
			$ext = strtolower($ext);
			$misec = explode(" ", microtime());
			$mediaId=$this->getFileId($this->coId);
			$targetFileName=$mediaId.'.'.$ext;
			$storeFilePath = $storeFolder.$targetFileName;

			if (copy($orgFile,$storeFilePath)) {

				$imgSize = getimagesize($storeFilePath);
				$size = filesize($storeFilePath);

				$fileInfo["coId"]=$this->coId;
				$fileInfo["id"]=$mediaId;
				$fileInfo["name"]=$targetFileName;
				$fileInfo["orgName"]= $orgFile;
				$fileInfo["ext"]= $ext;
				$fileInfo["path"]=$targetFolder.$targetFileName;
				$fileInfo["type"]="image";
				$fileInfo["mimeType"]=$imgSize['mime'];
				$fileInfo['title']=$_POST['caption'];
				$fileInfo['caption']=$_POST['caption'];
				$fileInfo['description']=$_POST['description'];
				$fileInfo['watermarkPlace']=0;
				$fileInfo["size"]=$size;
				$fileInfo["width"]=$imgSize[0];
				$fileInfo["height"]=$imgSize[1];
				$fileInfo['copyright']="";
				$fileInfo['categoryId']="";
				$fileInfo['paper']['date']="";
				$fileInfo['paper']['edit']="";
				$fileInfo['paper']['page']="";
				$fileInfo['isDisplay']=(Bool)true;
				$fileInfo['isCopy']=(Bool)true;
				$fileInfo['copyFileId']=$_POST["copyFileId"]!=""?$_POST["copyFileId"]:"";
				$fileInfo["insert"]["managerId"]=$_SESSION["managerId"];
				$fileInfo["insert"]["managerName"]=$_SESSION["managerName"];
				$fileInfo["insert"]["date"]=date('Y-m-d H:i:s');
				$fileInfo["filming"] = ['date' => '','location' => ''];

				// iptc 조회하여 없는 정보를 설정
				$iptc = $this->iptc->getIptc($storeFilePath);
				if (empty($fileInfo['title']) && !empty($iptc['title'])) $fileInfo['title'] = $iptc['title'];
				if (empty($fileInfo['caption']) && !empty($iptc['caption'])) $fileInfo['caption'] = $iptc['caption'];
				// if (empty($fileInfo['description']) && !empty($iptc['description'])) $fileInfo['description'] = $iptc['description'];
				if (empty($fileInfo['keyword']) && !empty($iptc['keyword'])) $fileInfo['keyword'] = $iptc['keyword'];
				if (empty($fileInfo['credit']) && !empty($iptc['credit'])) $fileInfo['credit'] = $iptc['credit'];
				if (empty($fileInfo['source']) && !empty($iptc['source'])) $fileInfo['source'] = $iptc['source'];
				if (empty($fileInfo['copyright']) && !empty($iptc['copyright'])) $fileInfo['copyright'] = $iptc['copyright'];
				if (empty($fileInfo["filming"]['date']) && !empty($iptc['creationDate'])) $fileInfo["filming"]['date'] = $iptc['creationDate'];
				if (empty($fileInfo["filming"]['location'])) {
					if (!empty($imgInfo['country'])) $fileInfo["filming"]['location'] .= $imgInfo['country'].' ';
					if (!empty($imgInfo['state'])) $fileInfo["filming"]['location'] .= $imgInfo['state'].' ';
					if (!empty($imgInfo['city'])) $fileInfo["filming"]['location'] .= $imgInfo['city'].' ';
					if (!empty($imgInfo['addr'])) $fileInfo["filming"]['location'] .= $imgInfo['addr'];
					$fileInfo["filming"]['location'] = trim($fileInfo["filming"]['location']);
				}

				// 이미지 최적화
				if (in_array($ext, ['jpg','jpeg','png','bmp'])) {
					$resizeInfo = $this->optimizeImage($storeFilePath, self::MAX_WIDTH, self::MAX_IMAGE_SIZE, self::IMAGE_QUALITY);
					if ($resizeInfo) {
						$fileInfo["size"] = $resizeInfo["size"];
						$fileInfo["width"] = $resizeInfo["width"];
						$fileInfo["height"] = $resizeInfo["height"];
					}
				}
			
				// getFileId 로직 변경으로 upsert로 변경
				$this->db->upsert(self::FILE_COLLECTION, ['id'=>$fileInfo["id"]], $fileInfo);
				//$this->db->insert($this->collection, $fileInfo);
				
				$msg = 'File '.$mediaId.'('.$targetFileName.') '.$_SESSION['managerId'].' '."Upload";
				$this->log->writeLog($this->coId, $msg, 'File_externalImg');

				$data = $fileInfo;
			}
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
		return $data;
	}

	/**
	 * base64 string을 content로 전송하면 이미지 파일을 만들고 저장한다
	 * param $_POST['base64']
	 */
	public function base64Img()
	{
		try {
			$data = [];

			// base64 문자열에서 "data:image/png;base64," 부분을 제거
			$base64_string = preg_replace('#^data:image/[^;]+;base64,#', '', $_POST['base64']);
			// base64 디코딩
			$decoded_image = base64_decode($base64_string);

			$storeFolder = '/webData/'.$this->coId.'/image/'.date("Y/m/d/");
			if (!is_dir($storeFolder)) {mkdir($storeFolder,0755,true);}
			$targetFolder = str_replace('/webData','/data',$storeFolder);
			$mediaId=$this->getFileId($this->coId);

			
			//preg_match('/[.]([a-z0-9]+)$/i',$orgFile,$tmp);
			//$ext = $tmp[1];
			$ext = "png";
			$misec = explode(" ", microtime());
			$mediaId=$this->getFileId($this->coId);
			$targetFileName=$mediaId.'.'.$ext;
			$storeFilePath = $storeFolder.$targetFileName;
			file_put_contents($storeFilePath,$decoded_image);

			if (is_file($storeFilePath)) {
				$imgSize = getimagesize($storeFilePath);
				$size = filesize($storeFilePath);

				$fileInfo["coId"]=$this->coId;
				$fileInfo["id"]=$mediaId;
				$fileInfo["name"]=$targetFileName;
				$fileInfo["orgName"]= $orgFile;
				$fileInfo["ext"]= $ext;
				$fileInfo["path"]=$targetFolder.$targetFileName;
				$fileInfo["type"]="image";
				$fileInfo["mimeType"]=$imgSize['mime'];
				$fileInfo['title']=$_POST['caption'];
				$fileInfo['caption']=$_POST['caption'];
				$fileInfo['description']=$_POST['description'];
				$fileInfo['watermarkPlace']=0;
				$fileInfo["size"]=$size;
				$fileInfo["width"]=$imgSize[0];
				$fileInfo["height"]=$imgSize[1];
				$fileInfo['copyright']="";
				$fileInfo['categoryId']="";
				$fileInfo['paper']['date']="";
				$fileInfo['paper']['edit']="";
				$fileInfo['paper']['page']="";
				$fileInfo['isDisplay']=(Bool)true;
				$fileInfo['isCopy']=(Bool)true;
				$fileInfo['copyFileId']=$_POST["copyFileId"]!=""?$_POST["copyFileId"]:"";
				$fileInfo["insert"]["managerId"]=$_SESSION["managerId"];
				$fileInfo["insert"]["managerName"]=$_SESSION["managerName"];
				$fileInfo["insert"]["date"]=date('Y-m-d H:i:s');

				// 이미지 최적화
				if (in_array($ext, ['jpg','jpeg','png','bmp'])) {
					$resizeInfo = $this->optimizeImage($storeFilePath, self::MAX_WIDTH, self::MAX_IMAGE_SIZE, self::IMAGE_QUALITY);
					if ($resizeInfo) {
						$fileInfo["size"] = $resizeInfo["size"];
						$fileInfo["width"] = $resizeInfo["width"];
						$fileInfo["height"] = $resizeInfo["height"];
					}
				}
			
				// getFileId 로직 변경으로 upsert로 변경
				$this->db->upsert(self::FILE_COLLECTION, ['id'=>$fileInfo["id"]], $fileInfo);
				//$this->db->insert($this->collection, $fileInfo);
				
				$msg = 'File '.$mediaId.'('.$targetFileName.') '.$_SESSION['managerId'].' '."Upload";
				$this->log->writeLog($this->coId, $msg, 'File_externalImg');

				$data = $fileInfo;
			}
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
		return $data;
	}

	/**
	 * url string을 전송하면 이미지 파일을 만들고 저장한다
	 * param $_POST['url']
	 */
	public function url()
	{
		try {
			$imgUrl = $_POST['url'];
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $imgUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			curl_setopt($ch, CURLOPT_HEADER, 0);

			$imageData = curl_exec($ch);

			curl_close($ch);
			
			$storeFolder = '/webData/'.$this->coId.'/image/'.date("Y/m/d/");
			if (!is_dir($storeFolder)) {mkdir($storeFolder,0755,true);}
			$targetFolder = str_replace('/webData','/data',$storeFolder);
			$mediaId=$this->getFileId($this->coId);
			
			//preg_match('/[.]([a-z0-9]+)$/i',$orgFile,$tmp);
			//$ext = $tmp[1];
			$ext = "jpg";
			$misec = explode(" ", microtime());
			$mediaId=$this->getFileId($this->coId);
			$targetFileName=$mediaId.'.'.$ext;
			$storeFilePath = $storeFolder.$targetFileName;
			file_put_contents($storeFilePath, $imageData);

			$data = [];

			if (is_file($storeFilePath)) {
				$imgSize = getimagesize($storeFilePath);
				$size = filesize($storeFilePath);

				$fileInfo["coId"]=$this->coId;
				$fileInfo["id"]=$mediaId;
				$fileInfo["name"]=$targetFileName;
				$fileInfo["orgName"]= $orgFile;
				$fileInfo["ext"]= $ext;
				$fileInfo["path"]=$targetFolder.$targetFileName;
				$fileInfo["type"]="image";
				$fileInfo["mimeType"]=$imgSize['mime'];
				$fileInfo['title']=$_POST['caption'];
				$fileInfo['caption']=$_POST['caption'];
				$fileInfo['description']=$_POST['description'];
				$fileInfo['watermarkPlace']=0;
				$fileInfo["size"]=$size;
				$fileInfo["width"]=$imgSize[0];
				$fileInfo["height"]=$imgSize[1];
				$fileInfo['copyright']="";
				$fileInfo['categoryId']="";
				$fileInfo['paper']['date']="";
				$fileInfo['paper']['edit']="";
				$fileInfo['paper']['page']="";
				$fileInfo['isDisplay']=(Bool)true;
				$fileInfo['isCopy']=(Bool)true;
				$fileInfo['copyFileId']=$_POST["copyFileId"]!=""?$_POST["copyFileId"]:"";
				$fileInfo["insert"]["managerId"]=$_SESSION["managerId"];
				$fileInfo["insert"]["managerName"]=$_SESSION["managerName"];
				$fileInfo["insert"]["date"]=date('Y-m-d H:i:s');

				// 이미지 최적화
				if (in_array($ext, ['jpg','jpeg','png','bmp'])) {
					$resizeInfo = $this->optimizeImage($storeFilePath, self::MAX_WIDTH, self::MAX_IMAGE_SIZE, self::IMAGE_QUALITY);
					if ($resizeInfo) {
						$fileInfo["size"] = $resizeInfo["size"];
						$fileInfo["width"] = $resizeInfo["width"];
						$fileInfo["height"] = $resizeInfo["height"];
					}
				}
			
				// getFileId 로직 변경으로 upsert로 변경
				$this->db->upsert(self::FILE_COLLECTION, ['id'=>$fileInfo["id"]], $fileInfo);
				//$this->db->insert($this->collection, $fileInfo);
				
				$msg = 'File '.$mediaId.'('.$targetFileName.') '.$_SESSION['managerId'].' '."Upload";
				$this->log->writeLog($this->coId, $msg, 'File_externalImg');

				$data = $fileInfo;
				$data['url'] = $_POST['url'];
			}
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
		return $data;
	}

	/**
	 * 파일 다운로드
	 * 
	 * @param path 파일경로
	 * @param orgName 원본파일명
	 */
	public function download()
	{
		$path = trim($_GET['path'], '.');
		$orgName = $_GET['orgName'];

		// path가 없으면 실행하지 않음
		if (empty($path)) exit;
		// orgName이 없으면 path의 파일명으로 다운로드
		if (empty($orgName)) {
			$orgName = basename($path);
		}
		$file_name = str_replace("/data","/webData",$path);

		// make sure it's a file before doing anything!
		if (!is_file($file_name)) exit;

		// required for IE
		if (ini_get('zlib.output_compression')) 
			ini_set('zlib.output_compression', 'Off');	

		$mime = mime_content_type('$file_name');

		header("Pragma: public");
		header("Expires: 0");
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Last-Modified: '.gmdate ('D, d M Y H:i:s', filemtime($file_name)).' GMT');
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=".$orgName);
		header("Content-Transfer-Encoding: binary");
		header("Content-Length: ".filesize($file_name));

		ob_clean();
		flush();
		readfile($file_name);
		exit;
	}

	/**
	 * 파일 정보 조회
	 */
	public function info()
	{
		try {
			$path = !empty($_POST['path'])?trim($_POST['path'], '.'):null;
			if (empty($path)) {
				throw new \Exception("path는 필수값입니다.", 400);
			}

			$storeFilePath = str_replace('/data/', '/webData/', $path);
			$imagesize = getimagesize($storeFilePath);
			$size = filesize($storeFilePath);
			$name = basename($path);
			$ext = strtolower(substr(strrchr($name, "."), 1));
			$data = [
				'path' => $path,
				'name' => $name,
				'ext' => $ext,
				'size' => $size,
				'width' => $imagesize[0],
				'height' => $imagesize[1],
				'mimeType'=>$imagesize['mime'],
				'type'=>in_array($ext, $this->imageExt)?"image":"doc",
			];
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

    /**
     * 이미지 최적화
     * 길이, 용량을 제한하여 최적화
     * 
     * @param string $path 이미지 파일 경로
     * @param int $maxLength 최대 길이(가로 or 세로)
     * @param int $maxSize 최대 용량
     * @param int $quality 퀄리티
     */
    public function optimizeImage($path, $maxLength = 1200, $maxSize = 1048576 * 1, $quality = 90)
    {
        try {
            // 처리 가능한 파일 체크
            if (!preg_match('/jpg|jpeg|png|gif|webp$/i', $path)) {
                return false;
            }
            if (empty($path) || !is_file($path)) {
                throw new \Exception("이미지 파일이 존재하지 않습니다. {$path}");
            }

            $imagesize = getimagesize($path);
            $size = filesize($path);

            if (empty($imagesize) || empty($size)) {
                throw new \Exception("이미지 정보가 없습니다. {$path}");
            }
            if (!str_starts_with($imagesize['mime'], 'image/')) {
                throw new \Exception("이미지 파일이 아닙니다. {$path}");
            }

            $resizeInfo = [];
            if ($imagesize[0] > $maxLength || $imagesize[1] > $maxLength) {
                // 리사이즈
                $resizeInfo = $this->resizeMaxImage($path, $path, $maxLength, $imagesize[0], $imagesize[1], $quality);
            } elseif ($size > $maxSize) {
                // 퀄리티 조정
                $resizeInfo = $this->optimizeImageQuality($path, $path, $quality);
            }
            return $resizeInfo;

        } catch (\Exception $e) {
			$this->log->writeLog($this->coId, "optimizeImage failed: {$path} - {$e->getMessage()}", 'error_image_optimize');
            return false;
        }
    }

	/**
     * 이미지 제한 크기로 리사이즈
     * 이미지 비율은 유지
     * 
     * @param sourceFileStore 원본 파일 (절대경로 권장)
     * @param targetFileStore 생성 파일 (절대경로 권장)
     * @param maxImageLength 이미지 길이 제한
     * @param quality 품질 (0-100)
	 * @param force 강제실행
     */
    protected function resizeMaxImage($sourceFileStore, $targetFileStore, $maxImageLength=1920, $width=null, $height=null, $quality=90, $force=false)
	{
        if (empty($sourceFileStore) || empty($targetFileStore)) {
            return false;
        }
        if (!is_file($sourceFileStore)) {
            return false;
        }

        if (empty($width) || empty($height)) {
            list($width, $height) = getimagesize($sourceFileStore);
        }

		$resizeWidth = $resizeHeight = null;

		// 이미지의 긴 방향이 길이 제한보다 큰지 판단
        if ($width >= $height && $width > $maxImageLength) {
            $resizeWidth = $maxImageLength;
        } else if ($width < $height && $height > $maxImageLength) {
            $resizeHeight = $maxImageLength;
        } elseif ($force) {
			// 강제 실행
			if ($width >= $height) {
				$resizeWidth = $maxImageLength;
			} else {
				$resizeHeight = $maxImageLength;
			}
		}

        if ( !empty($resizeWidth) || !empty($resizeHeight) ) {
            // 사이즈 계산
            $src_x = 0;
            $src_y = 0;
            if ( $resizeWidth && $resizeHeight ) {
                $wRate = $resizeWidth / $width;
                $hRate = $resizeHeight / $height;
                // 이미지 비율 유지
                if ($wRate > $hRate) {
                    $rate =  $wRate;
                    $src_y = ( ( $height * $rate ) - $resizeHeight ) / 2;
                } else {
                    $rate =  $hRate;
                    $src_x = ( ( $width * $rate ) - $resizeWidth ) / 2;
                }
            } elseif ($resizeWidth) {
                $rate = $resizeWidth / $width;
                $resizeHeight = $height * $rate;
            } elseif ($resizeHeight) {
                $rate = $resizeHeight / $height;
                $resizeWidth = $width * $rate;
            }

            // 이미지 리사이즈
            $this->resizeImage($sourceFileStore, $targetFileStore, $src_x, $src_y, ($width*$rate), ($height*$rate), $width, $height, $quality);

            // 리사이즈 이후 값 설정
            $fileInfo["size"] = filesize($targetFileStore);
            $fileInfo["width"] = $resizeWidth;
            $fileInfo["height"] = $resizeHeight;
            return $fileInfo;
        }
        return false;
    }

    /**
     * 이미지 리사이즈
     * 
     * @param string $file 원본 파일 경로
     * @param string $newfile 저장할 파일 경로
     * @param int $src_x 원본 이미지 시작 위치 X
     * @param int $src_y 원본 이미지 시작 위치 Y
     * @param int $dst_w 리사이즈 너비
     * @param int $dst_h 리사이즈 높이
     * @param int $width 이미지 너비
     * @param int $height 이미지 높이
     * @param int $quality 품질 (0-100)
     */
    protected function resizeImage($file, $newfile, $src_x, $src_y, $dst_w, $dst_h, $width='', $height='', $quality=90)
    {
        try {
            if (empty($width) || empty($height)) {
                list($width, $height) = getimagesize($file);
            }

            $imagick = new \Imagick($file);

            // 이미지 포맷에 따른 처리
            $format = $imagick->getImageFormat();
            if ($format === 'JPEG' || $format === 'JPG') {
                $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                $imagick->setImageCompressionQuality($quality);
            } elseif ($format === 'PNG') {
                $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                $imagick->setOption('png:compression-level', 9); // 압축률 (0-9, 최대 압축:9)
            } elseif ($format === 'WEBP') {
                // WebP는 투명도 지원 여부에 따라 다른 설정 적용
                if ($imagick->getImageAlphaChannel()) {
                    $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                    $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                }
                $imagick->setImageCompression(\Imagick::COMPRESSION_WEBP);
                $imagick->setImageCompressionQuality($quality);
            }

            // 리사이즈
            $imagick->cropImage($width, $height, $src_x, $src_y);
            $imagick->resizeImage($dst_w, $dst_h, \Imagick::FILTER_LANCZOS, 1);

            // 저장
            $imagick->writeImage($newfile);
            $imagick->clear();
            $imagick->destroy();

            return true;
        } catch (\Exception $e) {
			$this->log->writeLog($this->coId, "resizeImage failed: {$file} - {$e->getMessage()}", 'error_image_optimize');
            return false;
        }
    }

    /**
     * 이미지 품질 조정
     * 
     * @param string $file 원본 파일 경로
     * @param string $newfile 저장할 파일 경로
     * @param int $quality 품질 (0-100)
     * @return bool 성공 여부
     */
    public function optimizeImageQuality($file, $newfile, $quality=90)
    {
        try {
            if (!is_file($file)) {
                throw new \Exception("No valid image provided with {$file}.");
            }

            $imagick = new \Imagick($file);
            $format = $imagick->getImageFormat();

            // 포맷별 품질 설정
            switch ($format) {
                case 'JPEG':
                case 'JPG':
                    $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                    $imagick->setImageCompressionQuality($quality);
                    break;
                case 'PNG':
                    $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                    $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                    $imagick->setOption('png:compression-level', 9); // 압축률 (0-9, 최대 압축:9)
                    break;
                case 'WEBP':
                    if ($imagick->getImageAlphaChannel()) {
                        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_ACTIVATE);
                        $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                    }
                    $imagick->setImageCompression(\Imagick::COMPRESSION_WEBP);
                    $imagick->setImageCompressionQuality($quality);
                    break;
                case 'GIF':
                    // GIF는 품질 조정이 의미가 없으므로 그대로 저장
                    break;
                default:
                    throw new \Exception("Unsupported image format: {$format}");
            }

            // 저장
            $imagick->writeImage($newfile);
            $imagick->clear();
            $imagick->destroy();

            // 리턴 값 설정
            $imagesize = getimagesize($newfile);
            $fileInfo["size"] = filesize($newfile);
            $fileInfo["width"] = $imagesize[0];
            $fileInfo["height"] = $imagesize[1];
            return $fileInfo;

        } catch (\Exception $e) {
			$this->log->writeLog($this->coId, "optimizeImageQuality failed: {$file} - {$e->getMessage()}", 'error_image_optimize');
            return false;
        }
    }
	
}