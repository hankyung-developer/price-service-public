<?php
ini_set('memory_limit','2048M');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
header("Image-Process: preImage");

$docRoot = $_SERVER["DOCUMENT_ROOT"];
$filePath = $docRoot.$_SERVER["REQUEST_URI"];
$cacheFilePath = str_replace("/image/","/cache/",$filePath);

// 외부수신 이미지가 없으면 스토리지에서 다운로드
if (str_starts_with($_GET["fileName"], '/data/external/')) {
    $tempPath = str_replace('/data/', '/webData/', $_GET["fileName"]);
    // 리사이즈 파일명이면 원본 파일로 변환
    preg_match("/\/webData\/([a-z]+)(([\/][^\/]+)+)[\/]([A-Za-z0-9_\-]+)[.]([^.]+)[.]([^.]+)[.]([^.]+)$/i", $tempPath, $matches);
    if (!empty($matches[1])) {
        $matchSize = count($matches);
        $tempPath = str_replace(['.'.$matches[($matchSize-3)],'.'.$matches[($matchSize-2)]], '', $tempPath);
    }
    if (!is_file($tempPath)) {
        include_once("/webSiteSource/kodes/wcms/vendor/autoload.php");
        $result = (new \Kodes\Wcms\Storage())->download($tempPath, $tempPath);
        if (!$result['success']) {
            responseNotFound();
        }
    }
}

if (is_file($filePath)) {
    printImage($filePath);
} elseif (is_file($cacheFilePath)) {
    printImage($cacheFilePath);
} else {
    $fileName = $_GET["fileName"];
    preg_match("/\/data\/([a-z]+)(([\/][^\/]+)+)[\/]([A-Za-z0-9_\-]+)[.]([^.]+)[.]([^.]+)[.]([^.]+)$/i", $fileName, $matches);
    if (empty($matches[1])) {
        // 리사이즈 파일명이 아니고, 파일이 없음
        responseNotFound();
    }

    $coId = $matches[1];
    $matchSize = count($matches);
    $fileExt = $matches[($matchSize-1)];
    $watermarkPlace = intval($matches[($matchSize-2)]);
    $imgSize = explode("x",$matches[($matchSize-3)]);

    $orgFile = $docRoot.str_replace(['.'.$matches[($matchSize-3)],'.'.$matches[($matchSize-2)]], '', $fileName);

    if (!is_file($orgFile)) {
        responseNotFound();
    }

    $imageType = exif_imagetype($orgFile);
    header("Content-type: " . image_type_to_mime_type($imageType));

    // Get new sizes
    list($width, $height) = getimagesize($orgFile);

    if ($width == 0 || $height == 0) {
    responseNotFound();
    }
    if (empty($imgSize[0]) && empty($imgSize[1])) {
    responseNotFound();
    }

    $thumb_width = $imgSize[0];
    $thumb_height = $imgSize[1];

    $top = 0;
    $left = 0;
    if (!empty($thumb_width) && !empty($thumb_height)) {
        $wRate = $thumb_width / $width;
        $hRate = $thumb_height / $height;
        if ($wRate > $hRate) {
            $rate = $wRate;
            // $top = ($height - ($thumb_height * ($width / $thumb_width))) / 2; // 가운데
            $top = 0; // 상단 고정
        } else {
            $rate = $hRate;
            $left = ($width - ($thumb_width * ($height / $thumb_height))) / 2; // 가운데
            // 리사이즈가 원본보다 크면 위치 비율 재조정
            if ($left > $width) {
                $left = $left / $rate;
            }
        }
    } elseif (!empty($thumb_width)) {
        $rate = $thumb_width / $width;
        $thumb_height = $height * $rate;
    } elseif (!empty($thumb_height)) {
        $rate = $thumb_height / $height;
        $thumb_width = $width * $rate;
    }

    // Load
    $thumb = imagecreatetruecolor($thumb_width, $thumb_height);

    switch ($imageType) {
        case 1:
            $source = imagecreatefromgif($orgFile);
            break;
        case 2:
            $source = imagecreatefromjpeg($orgFile);
            break;
        case 3:
            // png는 배경 투명하게 처리
            @imagealphablending($thumb, false);
            @imagesavealpha($thumb, true);
            $source = imagecreatefrompng($orgFile);
    }

    $dst_width = $width * $rate;
    $dst_height = $height * $rate;
    imagecopyresampled($thumb, $source, 0, 0, $left, $top, $dst_width, $dst_height, $width, $height);

    // 워터마크 합성
    if ($watermarkPlace > 0) {
        $companyInfo = readJsonFile("/webData/".$coId."/config", $coId."_company");
        $watermarkImg = !empty($companyInfo['image']['watermark'])?$docRoot.$companyInfo['image']['watermark']:null;
        // 워터마크 파일 체크 (png만 가능)
        if (!empty($watermarkImg) && is_file($watermarkImg) && str_ends_with(strtolower($watermarkImg), '.png')) {
            $defaultMargin = 30;

            $wmSource = imagecreatefrompng($watermarkImg);
            list($wmWidth, $wmHeight) = getimagesize($watermarkImg);

            // 워터마크 이미지가 원본 이미지보다 크면 리사이즈
            if ($wmWidth > ($dst_width / 2) || $wmHeight > ($dst_height / 2)) {
                $newWidth = min(($dst_width / 2), $wmWidth);
                $newHeight = ($newWidth / $wmWidth) * $wmHeight;
                $resizedWatermark = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($resizedWatermark, false);
                imagesavealpha($resizedWatermark, true);
                // $transparentBackground = imagecolorallocatealpha($resizedWatermark, 0, 0, 0, 127);
                // imagefill($resizedWatermark, 0, 0, $transparentBackground);
                imagecopyresampled($resizedWatermark, $wmSource, 0, 0, 0, 0, $newWidth, $newHeight, $wmWidth, $wmHeight);
                $wmSource = $resizedWatermark;
                $wmWidth = $newWidth;
                $wmHeight = $newHeight;
                imagedestroy($resizedWatermark);
            }

            // 가로 정렬
            switch ((int)(($watermarkPlace-1) / 3)) {
                case 1: // 좌측 정렬
                    $top = $thumb_height/2 - $wmHeight/2;
                    break;
                case 2: // 가운데 정렬
                    $top = $thumb_height - $wmHeight - $defaultMargin;
                    break;
                case 0:  // 우측 정렬
                    $top = $defaultMargin;
                    break;
            }
            // 세로 정렬
            switch ($watermarkPlace % 3) {
                case 1: // 상단 정렬
                    $left = $defaultMargin;
                    break;
                case 2: // 중앙 정렬
                    $left = $thumb_width/2 - $wmWidth/2;
                    break;
                case 0:  // 하단 정렬
                    $left = $thumb_width - $wmWidth - $defaultMargin;
                    break;
            }
            if ($imageType == 3) {
                imagealphablending($thumb, true);
                imagesavealpha($thumb, true);
            }
            imagecopy($thumb, $wmSource, $left, $top, 0, 0, $wmWidth, $wmHeight);
            imagedestroy($wmSource);
        }
    }

    // cache dir가 없는 경우 만들기
    preg_match("/(^[\/]([^\/]+[\/])+)/i",$cacheFilePath,$tmp);
    if (!is_dir($tmp[0])) {
        mkdir($tmp[0],0777,true);
    }

    switch ($imageType) {
        case 1:
            imagegif($thumb);
            imagegif($thumb,$cacheFilePath);
            break;
        case 2:
            imagejpeg($thumb,NULL,100);
            imagejpeg($thumb,$cacheFilePath,100);
            break;
        case 3:
            imagepng($thumb);
            imagepng($thumb,$cacheFilePath);
            break;
    }
    imagedestroy($source);
    imagedestroy($thumb);
}

// 이미지 출력
function printImage($filePath) {
    if (is_file($filePath)) {
        header("Content-type: " . image_type_to_mime_type( exif_imagetype($filePath) ) );
        checkHttpCache($filePath);
        readfile($filePath);
    } else {
        responseNotFound();
    }
    exit;
}

// 브라우저 캐시 사용
function checkHttpCache($filePath)
{
    if (is_file($filePath)) {
        $response['Last-Modified'] = gmdate('D, d M Y H:i:s', filemtime($filePath)).' GMT';
        $response['ETag'] = md5($response['Last-Modified']);
        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $response['Last-Modified'] : false;
        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] == $response['ETag'] : false;
        header('Last-Modified: '.$response['Last-Modified']);
        header('ETag: '.$response['ETag']);
        if ($ifModifiedSince !== false || $ifNoneMatch !== false) {
            http_response_code(304);    // Not Modified
            exit;
        }
    }
}

// json 파일 읽기
function readJsonFile($path, $fileName)
{
    $jsonFile = $path.'/'.$fileName.'.json';
    $jsonData = '';
    if (is_file($jsonFile)) {
        $str = file_get_contents($jsonFile);
        $jsonData = json_decode($str, true);
    }
    return $jsonData;
}

// Not Found
function responseNotFound()
{
    http_response_code(404);    // Not found
    exit;
}