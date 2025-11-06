<?php 
namespace Kodes\Wcms;

/**
 * 이미지 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Image
{
    /** @var String Collection Name */
    const COLLECTION = "file";
    
    /** @var Class DB Class */
    protected $db;
    /** @var Class Json Class */
    protected $json;
    /** @var Class Common Class */
    protected $common;
    /** @var Class Log Class */
    protected $log;
    /** @var Class Iptc Class */
    protected $iptc;

    /** @var variable */
    protected $coId;

    /**
     * Image 생성자
     */
    public function __construct()
    {
        $this->db = new DB("wcmsDB");
        $this->json = new Json();
        $this->common = new Common();
        $this->log = new Log();

        $this->coId = $this->common->coId;
	}

    /**
     * Image 리스트를 조회한다.
     */
	public function list()
    {
        $data = [];

		try {
            // 기본값 : 기간
            if (empty($_GET['startDate'])) {
                $_GET['startDate'] = date('Y-m-d', strtotime('-10 year'));
            }
            if (empty($_GET['endDate'])) {
                $_GET['endDate'] = date('Y-m-d');
            }

            $filter = [];
            $filter['coId'] = $_SESSION['coId'];
            $filter['isDisplay'] = ['$ne' => false];

            $startDate = empty($_GET['startDate'])?"":$_GET['startDate'];
			$endDate = empty($_GET['endDate'])?"":$_GET['endDate'];
            if($startDate!='' && $endDate=='') {
                $filter['insert.date'] = ['$gte'=>$startDate.' 00:00:00'];
            } elseif ($startDate!='' && $endDate!='') {
                $filter['insert.date'] = ['$gte'=>$startDate.' 00:00:00','$lte'=>$endDate.' 23:59:59'];
            } elseif ($startDate==''&& $endDate!='') {
                $filter['insert.date'] = ['$lte'=>$endDate.' 23:59:59'];
            }

            if (!empty($_GET['searchItem']) && !empty($_GET['searchText'])) {
                $filter[$_GET['searchItem']] = new \MongoDB\BSON\Regex($_GET['searchText']);
            }

            //  전체 게시물 숫자
            $data["totalCount"] = $this->db->count(self::COLLECTION, $filter);

            $noapp = empty($_GET['noapp'])?25:$_GET['noapp'];
            $page = empty($_GET["page"])?1:$_GET["page"];
            $pageInfo = new Page;
            $data['page'] = $pageInfo->page($noapp, 10, $data["totalCount"], $page);

            $options = ["skip" => ($page - 1) * $noapp, "limit" => $noapp, 'sort' => ['id' => -1]];

            $result = $this->db->list(self::COLLECTION, $filter, $options);
            foreach ($result as $i => $tempImg) {
                if ($tempImg['ext']=="gif") {
                    $fileListPath = $tempImg['path'];
                } else {
                    $fileListPath = preg_replace("/([.][a-z]+)$/",".250x.0$1",$tempImg['path']);
                }
                $result[$i]['path'] = str_replace("/webData/","/data/",$tempImg['path']);
                $result[$i]['listPath'] = str_replace("/webData/","/data/",$fileListPath);
            }

            $data['items'] = $result;
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}
    
    /**
     * 이미지 캡션 및 메모 업데이트
     *  - 이미지 등록시 일관 수정
     *
     * @return void
     */
    public function bulkupdate()
    {
		try {
            $fileId = $_POST['file_id'];
            $filter = [
                'id' => [
                    '$in' => $fileId
                ]
            ];

            $options = [
                '$set' =>[
                    'caption'=>$_POST['caption'],
                    'description'=>$_POST['description']
                ]
            ];

            $result = $this->db->update(self::COLLECTION, $filter, $options, true);
            $msg = 'Image '.$fileId.' '.$_SESSION['managerId'].' '."Caption Update";
            $this->log->writeLog($this->coId, $msg, "image");
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
    }

    /**
     * 이미지 등록 화면
     *
     * @return data 이미지 정보
     */
	public function editor()
    {
		try {
            $this->common->checkRequestMethod('GET');

            $imageInfo =  [];
            $fileId = $_GET['id'];
            $filter = ['id' => $fileId];
            
            $options = [];
            $imageInfo = $this->db->item(self::COLLECTION, $filter, $options);

            $imageInfo['path'] = str_replace("/webData/","/data/",$imageInfo['path']);

            $data['image'] = $imageInfo;
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

    /**
     * 이미지 및 크기, 사이즈 수정
     *
     * @return void
     */
    public function change()
    {
		try {
            $upload_file = ".".$_POST['path'];

            if($_POST['imgData']!=""){
                $img = $_POST['imgData'];
                
                $cnt = strpos($img,';base64,');
                $img = substr($img, $cnt+8);

                $data = base64_decode($img);
                // echo $data;
                $success = file_put_contents($upload_file, $data);

                $size = getimagesize($upload_file);
                $filesize = filesize($upload_file);

                $imageInfo = [
                    'id'=>$_POST['id'],
                    'width'=>$size[0],
                    'height'=>$size[1],
                    'size'=>$filesize
                ];

                $result = $this->db->update(self::COLLECTION, ['id'=>$imageInfo['id']], ['$set'=>$imageInfo]);

                $msg = 'Image '.$_POST["id"].' '.$_SESSION['managerId'].' '."Update";
                $this->log->writeLog($this->coId, $msg, "image");

                $this->fileDelete($_POST["id"], str_replace('/image/','/cache/', $upload_file));
            }

            $data = $upload_file;
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
    }
    
	/**
     * 이미지 정보 수정
     *
     * @return void
     */
	public function update()
    {
        $imageInfo = [
            'id'=>$_POST['id'],
            'caption'=>$_POST['caption'],
            'description'=>$_POST['description'],
            'watermarkPlace'=>$_POST['watermarkPlace']
        ];

        $result = $this->db->update(self::COLLECTION, ['id'=>$imageInfo['id']], ['$set'=>$imageInfo]);

		$msg = 'Image '.$this->imageInfo["id"].' '.$_SESSION['managerId'].' '."Update";
		$this->log->writeLog($this->coId, $msg, "image");

        return $_POST;
    }
    
	/**
     * 이미지 정보 수정
     *
     * @return void
     */
	public function detectionUpdate()
    {
        $detection = json_decode($_POST['detection']);
        $imageInfo = [
            'detection'=> $detection
        ];

        $result = $this->db->update(self::COLLECTION, ['id'=>$_POST['id']], ['$set'=>$imageInfo]);

		$msg = 'Image '.$this->imageInfo["id"].' '.$_SESSION['managerId'].' '."Update";
		$this->log->writeLog($this->coId, $msg, "image_detection");

        // IPTC에 detection 정보 기록
        $filePath = \str_replace("/data/","/webData/",$_POST['filePath']);
        $iptc['2#998'] = $_POST['detection'];

        // Convert the IPTC tags into binary code
		$tagData = '';
		foreach($iptc as $tag => $string) {
			$rec = substr($tag, 0, 1);
			$tag = substr($tag, 2);
			if (is_array($string) && count($string) > 0) {
				foreach ($string as $key => $value) {
					$tagData .= $this->iptc->iptcMakeTag($rec, $tag, $value);
				}
			} else {
				$tagData .= $this->iptc->iptcMakeTag($rec, $tag, $string);
			}
		}

		// Embed the IPTC data
		$content = iptcembed($tagData, $filePath);

		// Write the new image data out to the file.
		$fp = fopen($filePath, "wb");
		fwrite($fp, $content);
		fclose($fp);

        return $_POST;
    }

	/**
     * 이미지 삭제
     *
     * @return void
     */
    public function delete()
    {
		try {
            $fileId = $_POST['id'];
            $upload_file = str_replace('/data/','/webData/',$_POST['path']);

            $result = $this->db->delete(self::COLLECTION,["id"=> $fileId]);

            $msg = 'Image '.$fileId.'('.$upload_file.') '.$_SESSION['userId'].' '."Delete";
            $this->log->writeLog($this->coId, $msg, "image");

            $this->fileDelete($fileId, $upload_file);
            $this->fileDelete($fileId, str_replace('/image/','/cache/',$upload_file));
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }
        
        return $result;
    }

   	/**
     * 이미지 파일을 삭제한다.
     *
     * @return void
     */ 
    public function fileDelete($fileId, $imageName)
    {
        $imagePath = preg_replace("/[^\/]+$/i","",$imageName);
        $searchDir = scandir($imagePath);

        foreach($searchDir as $key=>$value){
            if(strpos($value,$fileId)!==FALSE){
                unlink($imagePath.'/'.$value);
            }
        }
    }

    /**
     * 이미지 정보를 구해온다.
     */
	public function item()
    {
		try {
            $this->common->checkRequestMethod('GET');

            $imageInfo =  [];
            $filter = ['path' => $_GET['path']];
            
            $options = [];
            $imageInfo = $this->db->item(self::COLLECTION, $filter, $options);
            if($imageInfo['mimeType']==""){
                $imageInfo['mimeType'] = mime_content_type($imageInfo['path']);
            }
            $data['image'] = $imageInfo;
        } catch(\Exception $e) {
            $data['msg'] = $this->common->getExceptionMessage($e);
        }

        return $data;
	}

    /**
     * 이미지 정보를 DB에 저장
     * AI 기사에서 생성한 이미지를 file 컬렉션에 저장
     * 
     * @param array $imageData 이미지 정보
     * [
     *   'path' => '/data/coId/image/2025/11/02/filename.png',
     *   'filename' => 'filename.png',
     *   'caption' => '이미지 캡션',
     *   'description' => '이미지 설명',
     *   'aid' => '기사 ID'
     * ]
     * @return array 저장된 이미지 정보 (id 포함)
     */
    public function saveImageInfo($imageData)
    {
        try {
            // 실제 파일 경로 (웹 경로를 시스템 경로로 변환)
            $webPath = str_replace('/data/','/webData/',$imageData['path']) ?? '';
            
            // 파일 확장자
            $extension = pathinfo($imageData['filename'], PATHINFO_EXTENSION);
            
            // ID 생성 (타임스탬프 + 랜덤)
            $fileId = date('YmdHis') . substr(md5(uniqid()), 0, 8);

            // DB에 저장할 데이터 구조
            $fileData = [
                'id' => $fileId,
                'coId' => $_SESSION['coId'] ?? $this->coId,
                'path' => $webPath,
                'filename' => $imageData['filename'] ?? '',
                'originalName' => $imageData['filename'] ?? '',
                'ext' => strtolower($extension),
                'mimeType' => $mimeType,
                'width' => $fileInfo[0] ?? 0,
                'height' => $fileInfo[1] ?? 0,
                'size' => $fileSize,
                'caption' => $imageData['caption'] ?? '',
                'description' => $imageData['description'] ?? '',
                'aid' => $imageData['aid'] ?? '',  // 연결된 기사 ID
                'isDisplay' => true,
                'insert' => [
                    'date' => date('Y-m-d H:i:s'),
                    'managerId' => $_SESSION['managerId'] ?? 'AI',
                    'managerName' => $_SESSION['managerName'] ?? 'AI System'
                ]
            ];
            
            // MongoDB에 저장
            $result = $this->db->insert(self::COLLECTION, $fileData);

            // 로그 기록
            $msg = 'AI Image ' . $fileId . ' (' . $imageData['filename'] . ') saved by ' . ($_SESSION['managerId'] ?? 'AI');
            $this->log->writeLog($this->coId, $msg, "ai_image");
            
            return [
                'success' => true,
                'id' => $fileId,
                'data' => $fileData
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'msg' => $this->common->getExceptionMessage($e)
            ];
        }
    }

    function resizeImage($imagePath, $newWidth=1200)
    {
        // 파일 확장자 확인
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        
        // 확장자에 따라 이미지 생성
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($imagePath);
                break;
            case 'png':
                $image = imagecreatefrompng($imagePath);
                break;
            case 'gif':
                $image = imagecreatefromgif($imagePath);
                break;
            case 'webp':
                $image = imagecreatefromwebp($imagePath);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        // 원본 크기
        $width = imagesx($image);
        $height = imagesy($image);
                
        // 원본이 1200px보다 작으면 원본 크기 유지
        if ($width <= $newWidth) {
            imagedestroy($image);
            return true; // 리사이즈 불필요
        }
        
        // 비율에 맞춰 높이 계산
        $newHeight = intval(($height / $width) * $newWidth);
        
        // 리사이즈된 이미지 생성
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // PNG 투명도 체크 (파일 크기 최적화를 위해)
        $hasTransparency = false;
        if ($extension === 'png') {
            // 투명 픽셀이 있는지 확인
            for ($x = 0; $x < $width; $x += max(1, intval($width / 100))) {
                for ($y = 0; $y < $height; $y += max(1, intval($height / 100))) {
                    $rgba = imagecolorat($image, $x, $y);
                    $alpha = ($rgba & 0x7F000000) >> 24;
                    if ($alpha > 0) {
                        $hasTransparency = true;
                        break 2;
                    }
                }
            }
        }
        
        // PNG와 GIF의 투명도 보존 (투명도가 있는 경우만)
        if (($extension === 'png' && $hasTransparency) || $extension === 'gif') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // 고품질 리샘플링 (imagecopyresized 대신 imagecopyresampled 사용)
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // 확장자에 따라 저장 (PNG 최적화)
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($resizedImage, $imagePath, 100); // 품질 90
                break;
            case 'png':
                // 투명도가 없는 PNG는 JPEG로 변환하여 파일 크기 대폭 감소
                if (!$hasTransparency) {
                    // 흰색 배경 추가
                    $white = imagecolorallocate($resizedImage, 255, 255, 255);
                    $finalImage = imagecreatetruecolor($newWidth, $newHeight);
                    imagefill($finalImage, 0, 0, $white);
                    imagecopy($finalImage, $resizedImage, 0, 0, 0, 0, $newWidth, $newHeight);
                    
                    // JPEG로 저장 (파일명은 .jpg로 변경)
                    $jpegPath = preg_replace('/\.png$/i', '.jpg', $imagePath);
                    imagejpeg($finalImage, $jpegPath, 100); // 품질 100으로 파일 크기 최적화
                    imagedestroy($finalImage);
                    $imagePath = $jpegPath;
                } else {
                    // 투명도가 있는 경우 PNG로 저장 (최대 압축)
                    imagepng($resizedImage, $imagePath, 9);
                }
                break;
            case 'gif':
                imagegif($resizedImage, $imagePath);
                break;
            case 'webp':
                imagewebp($resizedImage, $imagePath, 85); // 품질 85로 파일 크기 최적화
                break;
        }
        
        // 메모리 해제
        imagedestroy($image);
        imagedestroy($resizedImage);
        
        return $imagePath;
    }
}