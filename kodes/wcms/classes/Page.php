<?php 
namespace Kodes\Wcms;

class Page
{
	public function page($pageLimit, $pageNavCnt, $maxNum, $page = 1)
	{
		$filePage = intval(100/$pageLimit);

		// 기본 페이지와 최대 페이지 계산
		$maxPage = ceil($maxNum/$pageLimit);
		if($page < $maxPage) {
			$page = $page;
		}else{
			$page = $maxPage;
		}

		// 실제 가져와야 할 갯수 계산
		$startArticle = ($page * $pageLimit) - $pageLimit;

		// 네비게이션 시작과 끝을 계산
		$startPage = (floor(($page-1) / $pageNavCnt) * $pageNavCnt) + 1;
		$endPage = $startPage + $pageNavCnt - 1;
		if ($endPage > $maxPage) $endPage = $maxPage;

		// 페이징 계산
		if ($startPage > 0) {
			for ($i=$startPage; $i<=$endPage; $i++) {
				$pageInfo['navibar'][] = [
					'page' => $i,
					'class' => ($i == $page)? 'active' : '',
				];
			}
		} else {
			$pageInfo['navibar'][] = [
				'page' => 1,
				'class' => 'active'
			];
		}

		// 이전/다음 페이지 계산
		$prevPage = $startPage - 1;
		if ($prevPage > 1) {
			$pageInfo['prevPage'] = $prevPage;
		} else {
			$pageInfo['prevPage'] = '';
		}
		
		$nextPage = $endPage + 1;
		if ($nextPage <= $maxPage) {
			$pageInfo['nextPage'] = $nextPage;
		} else {
			$pageInfo['nextPage'] = '';
		}

		return $pageInfo;
	}
}