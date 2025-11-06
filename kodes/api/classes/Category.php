<?php 
namespace Kodes\Api;

/**
 * 카테고리 클래스
 * 
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)
 */
class Category
{
    // ===========================================
    // 클래스 속성 및 초기화
    // ===========================================
    
    /** @var DB MongoDB 연결 객체 */
    protected $db;
    
    /** @var Common 공통 유틸리티 객체 */
    protected $common;
    
    /** @var Json JSON 처리 객체 */
    protected $json;

    /** @var ArticlePublish 아티클 발행 관련 객체 */
    protected $articlePublish;
    
    /** @var string 카테고리 컬렉션명 */
    protected $collection = 'category';

    /** @var array 전체 카테고리 배열 */
    protected $allCategorye = [];
    
    /** @var string 회사 ID */
    protected $coId;

    /**
     * 생성자 - 필요한 객체들을 초기화
     */
    public function __construct()
    {
        // 핵심 객체들 초기화
        $this->db = new DB();
        $this->common = new Common();
        $this->json = new Json();
  
        // 회사 ID 설정 (기본값: hkp)
        $this->coId = $this->common->coId ?: 'hkp';
    }

    // ===========================================
    // 공개 API 메서드들 (외부에서 호출)
    // ===========================================

    /**
     * 카테고리 목록 조회 (기본)
     * 
     * @param string $type 출력 타입 ('out'이면 필수 필드만)
     * @return array 카테고리 목록
     */
    public function list($type = '')
    {
        $filter = ['coId' => $this->coId];
        $options = [
            'sort' => ['depth' => 1, 'sort' => 1, 'id' => 1], 
            'projection' => ['_id' => 0]
        ];
        
        // 외부 출력용으로 필수 필드만 선택
        if ($type === 'out') {
            $options['projection'] = ['_id' => 0, 'id' => 1, 'name' => 1, 'depth' => 1, 'sort' => 1, 'parentId' => 1];
        }
        
        $categoryList = $this->db->list($this->collection, $filter, $options);
        
        return ['categoryList' => $categoryList];
    }

    /**
     * 팝업용 카테고리 조회 (CORS 헤더 설정 포함)
     * 
     * @return array 카테고리 목록 및 선택된 카테고리
     */
    public function popup()
    {
        // CORS 및 iframe 허용 헤더 설정
        $this->setCorsHeaders();
        
        $return = $this->list();

        // POST 데이터에서 선택된 카테고리 정보 처리
        if (isset($_POST['price_list'])) {
            $_POST['price_list'] = preg_replace('/(["|\'])([0-9]{12})/m', "$1hkp$2", $_POST['price_list']);
            $_POST['price_list'] = str_replace(['"priceid":', '"pricename":'], ['"id":', '"name":'], $_POST['price_list']);
            $return['selectedCategory'] = $_POST['price_list'];
        }

        return $return;
    }

    /**
     * 키워드로 카테고리 검색 (API용)
     * 
     * @param string $keyword 검색 키워드
     * @return array 검색 결과
     */
    public function searchByKeyword($keyword = '')
    {
        try {
            // 키워드 파라미터 검증
            $keyword = $keyword ?: ($_GET['keyword'] ?? '');
            if (empty($keyword)) {
                throw new \Exception('키워드 파라미터가 필요합니다.', 400);
            }

            // 전체 카테고리 조회 및 검색 수행
            $allCategories = $this->getAllCategoriesForSearch();
            $matchingCategories = $this->searchInArray($allCategories, $keyword);
            $result = $this->organizeSearchResultsFromArray($matchingCategories, $allCategories, $keyword);

            return [
                'success' => true,
                'data' => $result,
                'meta' => [
                    'keyword' => $keyword,
                    'totalResults' => count($result),
                    'description' => '검색어를 포함한 카테고리, 품종에 대한 리스트 정보'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * 카테고리 계층 구조 조회 (API용)
     * 
     * @param string $categoryId 카테고리 ID (없으면 전체 정보)
     * @return array 계층 구조 정보
     */
    public function getHierarchy($categoryId = '')
    {
        try {
            // 카테고리 ID 파라미터 검증
            $categoryId = $categoryId ?: ($_GET['categoryId'] ?? '');

            // 전체 카테고리 조회
            $allCategories = $this->getAllCategoriesForSearch();
            
            // 요청에 따른 결과 생성
            if (empty($categoryId)) {
                $result = $this->getAllHierarchyFromArray($allCategories);
            } else {
                $result = $this->getCategoryHierarchyFromArray($categoryId, $allCategories);
            }

            return [
                'success' => true,
                'data' => $result,
                'meta' => [
                    'categoryId' => $categoryId,
                    'description' => empty($categoryId) ? '전체 카테고리 계층 구조' : '특정 카테고리의 하위 분류 정보'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * 카테고리 단일 조회
     * 
     * @return array 카테고리 정보
     */
    public function getCategory()
    {  
        $this->common->checkRequestMethod('POST');
        
        $data = $_POST;
        if (empty($data['id'])) {
            throw new \Exception("유효하지 않은 접근입니다.", 400);
        }

        $filter = ['coId' => $this->coId, 'id' => $data['id']];
        $options = ['projection' => ['_id' => 0]];
        
        return $this->db->item('category', $filter, $options);
    }

    /**
     * 1depth 카테고리만 조회
     * 
     * @return array 1depth 카테고리 목록
     */
    public function getFirstDepth()
    {
        try {
            // 전체 카테고리 조회
            $allCategories = $this->getAllCategoriesForSearch();
            
            // 1depth 카테고리들만 필터링
            $depth1Categories = $this->getCategoriesByDepth($allCategories, 1);
            $this->sortCategoriesBySort($depth1Categories);

            // 결과 구성 (children 없이)
            $result = [];
            foreach ($depth1Categories as $category) {
                $result[] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'depth' => $category['depth'],
                    'parentId' => $category['parentId'],
                    'sort' => $category['sort']
                ];
            }

            return [
                'success' => true,
                'data' => $result,
                'meta' => [
                    'description' => '1depth 카테고리 목록'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * 카테고리 트리 생성 (JSON 파일용)
     * 
     * @return array 트리 구조 카테고리
     */
    public function getCategoryTree()
    {
        $cursor = $this->db->list('category', ['coId' => $this->coId], ['sort' => ['sort' => 1, 'id' => 1], 'projection' => ['_id' => 0]]);
        $flatList = iterator_to_array($cursor, false);
        
        $lookup = [];
        $tree = [];

        // 1단계: lookup 테이블 구성
        foreach ($flatList as &$item) {
            $item['child'] = [];
            $lookup[$item['id']] = &$item;
        }

        // 2단계: 트리 구성
        foreach ($flatList as &$item) {
            if ($item['parentId'] === '0') {
                $tree[] = &$item; // 루트 노드
            } else {
                if (isset($lookup[$item['parentId']])) {
                    $lookup[$item['parentId']]['child'][] = &$item;
                }
            }
        }
        unset($item); // 참조 해제

        return $tree;
    }

    // ===========================================
    // 카테고리 관리 메서드들 (CRUD)
    // ===========================================

    /**
     * 카테고리 저장 처리
     * 
     * @return array 처리 결과
     */
    public function saveProc()
    {
        try {
            $this->common->checkRequestMethod('POST');
            
            $data = $_POST;
            unset($_POST);
            
            // 데이터 검증 및 정리
            $this->validateCategoryData($data);
            
            // 저장 처리
            $result = $this->processCategorySave($data);
            
            // JSON 파일 재생성 및 API 발행
            $this->makeJson();
            $this->updateApiPublish($data);
            
            return ["status" => "success", "msg" => "저장되었습니다.", "data" => $result];
            
        } catch (\Exception $e) {
            return ["status" => "error", "msg" => $this->common->getExceptionMessage($e)];
        }
    }

    /**
     * 카테고리 삭제 처리
     * 
     * @return array 처리 결과
     */
    public function deleteProc()
    {
        try {
            $this->common->checkRequestMethod('POST');
            
            $id = $_POST['id'];
            unset($_POST);
            
            if (empty($id)) {
                throw new \Exception("id가 없습니다.");
            }

            // 하위 카테고리 존재 여부 확인
            $this->checkSubCategories($id);
            
            // 카테고리 삭제
            $this->db->delete($this->collection, ["id" => $id]);
            
            // JSON 파일 재생성
            $this->coId = substr($id, 0, -9);
            $this->makeJson();
            
            return ["status" => "success", "msg" => "카테고리가 삭제되었습니다."];
            
        } catch (\Exception $e) {
            return ["status" => "error", "msg" => $this->common->getExceptionMessage($e)];
        }
    }

    /**
     * 카테고리 순서 변경 처리
     * 
     * @return array 처리 결과
     */
    public function changeSort()
    {
        try {
            $this->common->checkRequestMethod('POST');
            
            $data = $_POST;
            unset($_POST);
            
            if (empty($data['sort'])) {
                throw new \Exception("필수 데이터가 누락되었습니다.", 400);
            }

            // 순서 업데이트
            $this->updateCategorySort($data);
            
            // JSON 파일 재생성
            $this->makeJson();
            
            return ["status" => "success", "msg" => "카테고리 순서가 변경되었습니다."];
            
        } catch (\Exception $e) {
            return ["status" => "error", "msg" => $this->common->getExceptionMessage($e)];
        }
    }

    // ===========================================
    // 핵심 비즈니스 로직 메서드들
    // ===========================================

    /**
     * 전체 카테고리 조회 (검색용)
     * 
     * @return array 전체 카테고리 배열
     */
    private function getAllCategoriesForSearch()
    {
        if (!empty($this->allCategorye)) {
            return $this->allCategorye;
        }

        $filter = ['coId' => $this->coId, 'isUse' => true];
        $options = [
            'sort' => ['depth' => 1, 'sort' => 1, 'id' => 1],
            'projection' => ['_id' => 0]
        ];
        
        $categories = $this->db->list($this->collection, $filter, $options);
        $this->allCategorye = iterator_to_array($categories, false);
        return $this->allCategorye;
    }

    /**
     * PHP 배열에서 키워드 검색 수행
     * 
     * @param array $allCategories 전체 카테고리 배열
     * @param string $keyword 검색 키워드
     * @return array 매칭된 카테고리들
     */
    private function searchInArray($allCategories, $keyword)
    {
        $matchingCategories = [];
        
        foreach ($allCategories as $category) {
            if (stripos($category['name'], $keyword) !== false) {
                $matchingCategories[] = $category;
            }
        }
        
        return $matchingCategories;
    }

    /**
     * 검색 결과를 계층적으로 정리
     * 
     * @param array $matchingCategories 매칭된 카테고리들
     * @param array $allCategories 전체 카테고리 배열
     * @param string $keyword 검색 키워드
     * @return array 정리된 검색 결과
     */
    private function organizeSearchResultsFromArray($matchingCategories, $allCategories, $keyword)
    {
        $result = [];
        $processedParents = [];
        
        // 카테고리 맵 생성 (빠른 조회를 위해)
        $categoryMap = $this->createCategoryMap($allCategories);

        foreach ($matchingCategories as $category) {
            // 부모 카테고리들 추가
            $this->addParentCategories($category, $categoryMap, $result, $processedParents);
            
            // 현재 카테고리 추가 (중복 방지)
            $this->addCurrentCategory($category, $categoryMap, $result, $processedParents);
            
            // 하위 카테고리들 추가
            $this->addChildrenToResult($category['id'], $categoryMap, $result, $processedParents);
        }

        // 계층적 순서로 정렬
        return $this->sortHierarchically($result);
    }

    /**
     * 전체 계층 구조 조회 (1depth 카테고리와 하위 분류들)
     * 
     * @param array $allCategories 전체 카테고리 배열
     * @return array 전체 계층 구조
     */
    private function getAllHierarchyFromArray($allCategories)
    {
        $categoryMap = $this->createCategoryMap($allCategories);
        
        // 1depth 카테고리들을 sort 순서로 정렬
        $depth1Categories = $this->getCategoriesByDepth($allCategories, 1);
        $this->sortCategoriesBySort($depth1Categories);

        // 계층 구조 구성
        $result = [];
        foreach ($depth1Categories as $category) {
            $result[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'depth' => $category['depth'],
                'parentId' => $category['parentId'],
                'sort' => $category['sort'],
                'children' => $this->getChildrenRecursiveFromArray($category['id'], $categoryMap)
            ];
        }

        return $result;
    }

    /**
     * 특정 카테고리의 바로 하위 depth만 조회
     * 
     * @param string $categoryId 카테고리 ID
     * @param array $allCategories 전체 카테고리 배열
     * @return array 바로 하위 depth 카테고리들
     */
    private function getCategoryHierarchyFromArray($categoryId, $allCategories)
    {
        $categoryMap = $this->createCategoryMap($allCategories);
        
        if (!isset($categoryMap[$categoryId])) {
            throw new \Exception('카테고리를 찾을 수 없습니다.', 404);
        }
        
        // 바로 하위 depth 카테고리들만 조회
        $children = [];
        foreach ($categoryMap as $category) {
            if ($category['parentId'] === $categoryId) {
                $children[] = $category;
            }
        }
        
        // sort 오름차순으로 정렬
        $this->sortCategoriesBySort($children);
        
        return $children;
    }

    /**
     * 재귀적으로 자식 카테고리들 조회
     * 
     * @param string $parentId 부모 카테고리 ID
     * @param array $categoryMap 카테고리 맵
     * @return array 자식 카테고리들
     */
    private function getChildrenRecursiveFromArray($parentId, $categoryMap)
    {
        $children = $this->getDirectChildren($parentId, $categoryMap);
        $this->sortCategoriesBySort($children);
        
        $result = [];
        foreach ($children as $child) {
            $result[] = [
                'id' => $child['id'],
                'name' => $child['name'],
                'depth' => $child['depth'],
                'parentId' => $child['parentId'],
                'sort' => $child['sort'],
                'children' => $this->getChildrenRecursiveFromArray($child['id'], $categoryMap)
            ];
        }
        
        return $result;
    }

    // ===========================================
    // 정렬 및 유틸리티 메서드들
    // ===========================================

    /**
     * 계층적 순서로 정렬
     * 
     * @param array $result 정렬할 결과 배열
     * @return array 정렬된 결과 배열
     */
    private function sortHierarchically($result)
    {
        $categoryMap = $this->createCategoryMap($result);
        $sorted = [];
        $processed = [];
        
        // 1depth 카테고리들을 sort 순서로 정렬
        $depth1Categories = $this->getCategoriesByDepth($result, 1);
        $this->sortCategoriesBySort($depth1Categories);
        
        // 각 1depth 카테고리와 그 하위 카테고리들을 재귀적으로 처리
        foreach ($depth1Categories as $category) {
            $this->addCategoryAndChildren($category, $categoryMap, $sorted, $processed);
        }
        
        return $sorted;
    }

    /**
     * 카테고리와 그 하위 카테고리들을 정렬된 순서로 추가
     * 
     * @param array $category 현재 카테고리
     * @param array $categoryMap 카테고리 맵
     * @param array &$sorted 정렬된 결과 배열 (참조)
     * @param array &$processed 처리된 카테고리 추적 (참조)
     */
    private function addCategoryAndChildren($category, $categoryMap, &$sorted, &$processed)
    {
        if (isset($processed[$category['id']])) {
            return;
        }
        
        $sorted[] = $category;
        $processed[$category['id']] = true;
        
        $children = $this->getDirectChildren($category['id'], $categoryMap);
        $this->sortCategoriesBySort($children);
        
        foreach ($children as $child) {
            $this->addCategoryAndChildren($child, $categoryMap, $sorted, $processed);
        }
    }

    /**
     * 카테고리들을 sort 필드로 오름차순 정렬
     * 
     * @param array &$categories 정렬할 카테고리 배열 (참조)
     */
    private function sortCategoriesBySort(&$categories)
    {
        usort($categories, function($a, $b) {
            $sortA = isset($a['sort']) ? (int)$a['sort'] : 0;
            $sortB = isset($b['sort']) ? (int)$b['sort'] : 0;
            return $sortA - $sortB;
        });
    }

    /**
     * 카테고리 맵 생성 (빠른 조회를 위해)
     * 
     * @param array $categories 카테고리 배열
     * @return array 카테고리 맵
     */
    private function createCategoryMap($categories)
    {
        $map = [];
        foreach ($categories as $category) {
            $map[$category['id']] = $category;
        }
        return $map;
    }

    /**
     * 특정 depth의 카테고리들만 필터링
     * 
     * @param array $categories 카테고리 배열
     * @param int $depth 원하는 depth
     * @return array 해당 depth의 카테고리들
     */
    private function getCategoriesByDepth($categories, $depth)
    {
        $result = [];
        foreach ($categories as $category) {
            if ($category['depth'] == $depth) {
                $result[] = $category;
            }
        }
        return $result;
    }

    /**
     * 직접 자식 카테고리들 조회
     * 
     * @param string $parentId 부모 카테고리 ID
     * @param array $categoryMap 카테고리 맵
     * @return array 직접 자식 카테고리들
     */
    private function getDirectChildren($parentId, $categoryMap)
    {
        $children = [];
        foreach ($categoryMap as $category) {
            if ($category['parentId'] === $parentId) {
                $children[] = $category;
            }
        }
        return $children;
    }

    // ===========================================
    // 검색 결과 구성 메서드들
    // ===========================================

    /**
     * 부모 카테고리들을 결과에 추가
     * 
     * @param array $category 현재 카테고리
     * @param array $categoryMap 카테고리 맵
     * @param array &$result 결과 배열 (참조)
     * @param array &$processedParents 처리된 부모 추적 (참조)
     */
    private function addParentCategories($category, $categoryMap, &$result, &$processedParents)
    {
        $parentPath = $this->getParentPathFromArray($category['id'], $categoryMap);
        
        foreach ($parentPath as $parent) {
            $parentKey = $parent['id'];
            if (!isset($processedParents[$parentKey])) {
                $parentPathSlice = array_slice($parentPath, 0, array_search($parent, $parentPath) + 1);
                $displayText = $this->buildPathString($parentPathSlice);
                
                $result[] = [
                    'id' => $parent['id'],
                    'name' => $parent['name'],
                    'depth' => $parent['depth'],
                    'parentId' => $parent['parentId'],
                    'path' => $displayText,
                    'isParent' => true,
                    'displayText' => $displayText
                ];
                $processedParents[$parentKey] = true;
            }
        }
    }

    /**
     * 현재 카테고리를 결과에 추가 (중복 방지)
     * 
     * @param array $category 현재 카테고리
     * @param array $categoryMap 카테고리 맵
     * @param array &$result 결과 배열 (참조)
     * @param array &$processedParents 처리된 부모 추적 (참조)
     */
    private function addCurrentCategory($category, $categoryMap, &$result, &$processedParents)
    {
        $categoryKey = $category['id'];
        if (!isset($processedParents[$categoryKey])) {
            $parentPath = $this->getParentPathFromArray($category['id'], $categoryMap);
            $fullPath = $this->buildPathString($parentPath);
            
            $result[] = [
                'id' => $category['id'],
                'name' => $category['name'],
                'depth' => $category['depth'],
                'parentId' => $category['parentId'],
                'path' => $fullPath,
                'isParent' => false,
                'isMatching' => true,
                'displayText' => $fullPath
            ];
            $processedParents[$categoryKey] = true;
        }
    }

    /**
     * 특정 카테고리의 모든 하위 카테고리를 결과에 추가
     * 
     * @param string $parentId 부모 카테고리 ID
     * @param array $categoryMap 카테고리 맵
     * @param array &$result 결과 배열 (참조)
     * @param array &$processedParents 처리된 부모 추적 (참조)
     */
    private function addChildrenToResult($parentId, $categoryMap, &$result, &$processedParents)
    {
        foreach ($categoryMap as $category) {
            if ($category['parentId'] === $parentId) {
                $childKey = $category['id'];
                if (!isset($processedParents[$childKey])) {
                    $parentPath = $this->getParentPathFromArray($category['id'], $categoryMap);
                    $fullPath = $this->buildPathString($parentPath);
                    
                    $result[] = [
                        'id' => $category['id'],
                        'name' => $category['name'],
                        'depth' => $category['depth'],
                        'parentId' => $category['parentId'],
                        'path' => $fullPath,
                        'isParent' => false,
                        'isMatching' => false,
                        'displayText' => $fullPath
                    ];
                    $processedParents[$childKey] = true;
                    
                    // 재귀적으로 더 깊은 하위 카테고리들도 추가
                    $this->addChildrenToResult($category['id'], $categoryMap, $result, $processedParents);
                }
            }
        }
    }

    // ===========================================
    // 경로 및 문자열 처리 메서드들
    // ===========================================

    /**
     * 배열에서 특정 카테고리의 부모 경로 조회
     * 
     * @param string $categoryId 카테고리 ID
     * @param array $categoryMap 카테고리 맵
     * @return array 부모 카테고리들의 배열
     */
    private function getParentPathFromArray($categoryId, $categoryMap)
    {
        $path = [];
        $currentId = $categoryId;
        
        while ($currentId && $currentId !== '0') {
            if (isset($categoryMap[$currentId])) {
                $category = $categoryMap[$currentId];
                array_unshift($path, $category);
                $currentId = $category['parentId'];
            } else {
                break;
            }
        }
        
        return $path;
    }

    /**
     * 카테고리 경로를 문자열로 변환
     * 
     * @param array $path 카테고리 경로 배열
     * @return string 경로 문자열 ("> "로 구분)
     */
    private function buildPathString($path)
    {
        $names = array_column($path, 'name');
        return implode(' > ', $names);
    }

    // ===========================================
    // 카테고리 관리 헬퍼 메서드들
    // ===========================================

    /**
     * 카테고리 데이터 검증
     * 
     * @param array &$data 카테고리 데이터 (참조)
     * @throws \Exception 검증 실패 시
     */
    private function validateCategoryData(&$data)
    {
        $data['name'] = trim($data['name']);
        
        if (empty($data['coId'])) {
            throw new \Exception("회사코드가 없습니다.", 400);
        }
        if (empty($data['name'])) {
            throw new \Exception("카테고리 이름을 입력하세요.", 400);
        }
    }

    /**
     * 카테고리 저장 처리
     * 
     * @param array $data 카테고리 데이터
     * @return array 처리된 데이터
     */
    private function processCategorySave($data)
    {
        $action = empty($data['id']) ? 'insert' : 'update';
        
        if ($action === 'insert') {
            $data['depth'] = $this->calculateDepth($data['parentId'], $data['coId']);
            $data['id'] = $this->generateId($data['parentId'], $data['coId'], $data['depth']);
        }
        
        // 데이터 타입 변환
        $data['isUse'] = (bool)$data['isUse'];
        $data['sort'] = $this->calculateSort($data);
        
        // DB 저장
        $filter = ['id' => $data['id']];
        $this->db->upsert($this->collection, $filter, ['$set' => $data]);
        
        $this->coId = $data['coId'];
        
        return $data;
    }

    /**
     * 카테고리 depth 계산
     * 
     * @param string $parentId 부모 카테고리 ID
     * @param string $coId 회사 ID
     * @return int 계산된 depth
     */
    private function calculateDepth($parentId, $coId)
    {
        if ($parentId == '0') {
            return 1;
        }
        
        $parentFilter = ['id' => $parentId, 'coId' => $coId];
        $parentCategory = $this->db->item($this->collection, $parentFilter, ['projection' => ['depth' => 1]]);
        
        return $parentCategory ? $parentCategory['depth'] + 1 : 1;
    }

    /**
     * 카테고리 sort 값 계산
     * 
     * @param array $data 카테고리 데이터
     * @return int 계산된 sort 값
     */
    private function calculateSort($data)
    {
        if (!empty($data['sort'])) {
            return (int)$data['sort'];
        }
        
        if (!empty($data['maxCount'])) {
            return (int)$data['maxCount'] + 1;
        }
        
        return 100;
    }

    /**
     * 하위 카테고리 존재 여부 확인
     * 
     * @param string $id 카테고리 ID
     * @throws \Exception 하위 카테고리가 존재할 경우
     */
    private function checkSubCategories($id)
    {
        $filter = ['parentId' => $id];
        $subCount = (int)$this->db->count($this->collection, $filter);
        
        if ($subCount > 0) {
            throw new \Exception("해당 카테고리의 하위 카테고리가 존재하므로 삭제할 수 없습니다.");
        }
    }

    /**
     * 카테고리 순서 업데이트
     * 
     * @param array $data 순서 데이터
     */
    private function updateCategorySort($data)
    {
        $filter = ['coId' => $this->coId, 'parentId' => $data['parentId']];
        
        foreach ($data['sort'] as $value) {
            $filter['id'] = $value['id'];
            $this->db->update($this->collection, $filter, ['$set' => ['sort' => (int)$value['sort']]]);
        }
    }

    /**
     * API 발행 업데이트
     * 
     * @param array $data 카테고리 데이터
     */
    private function updateApiPublish($data)
    {
        if ($this->articlePublish) {
            $param = [
                'coId' => $this->coId,
                'category' => [$data]
            ];
            $this->articlePublish->setApiPublish($param, 0);
        }
    }

    /**
     * CORS 헤더 설정
     */
    private function setCorsHeaders()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('X-Frame-Options: ALLOWALL');
        header("Content-Security-Policy: frame-ancestors *");
    }

    // ===========================================
    // 카테고리 ID 생성 및 JSON 파일 관리
    // ===========================================

    /**
     * 카테고리 ID 생성
     * 생성규칙: coId(회사코드)+1depth(숫자3자리)+2depth(숫자3자리)+3depth(숫자3자리)
     * 
     * @param string $parentId 부모 카테고리 ID
     * @param string $coId 회사 코드
     * @param int $depth 카테고리 depth
     * @return string 생성된 카테고리 ID
     */
    protected function generateId($parentId, $coId, $depth)
    {
        $regex = $this->getRegexForDepth($parentId, $coId, $depth);
        $filter = ["id" => $regex];
        $options = ["sort" => ["id" => -1], "limit" => 1];
        
        $result = $this->db->item($this->collection, $filter, $options);
        $tempId = $result['id'] ?? '';
        
        return $this->buildNewId($tempId, $coId, $depth);
    }

    /**
     * depth에 따른 정규식 패턴 생성
     * 
     * @param string $parentId 부모 카테고리 ID
     * @param string $coId 회사 코드
     * @param int $depth 카테고리 depth
     * @return \MongoDB\BSON\Regex 정규식 객체
     */
    private function getRegexForDepth($parentId, $coId, $depth)
    {
        switch ($depth) {
            case 1:
                return new \MongoDB\BSON\Regex($coId, 'i');
            case 2:
                return new \MongoDB\BSON\Regex(substr($parentId, 0, -9), 'i');
            case 3:
                return new \MongoDB\BSON\Regex(substr($parentId, 0, -6), 'i');
            case 4:
                return new \MongoDB\BSON\Regex(substr($parentId, 0, -3), 'i');
            default:
                return new \MongoDB\BSON\Regex($coId, 'i');
        }
    }

    /**
     * 새로운 ID 생성
     * 
     * @param string $tempId 기존 ID
     * @param string $coId 회사 코드
     * @param int $depth 카테고리 depth
     * @return string 새로운 ID
     */
    private function buildNewId($tempId, $coId, $depth)
    {
        if (empty($tempId)) {
            return $coId . '001000000000';
        }
        
        switch ($depth) {
            case 1:
                $tempInt = (int)substr($tempId, -12, 3);
                return substr($tempId, 0, -12) . sprintf('%03d', $tempInt + 1) . '000000000';
            case 2:
                $tempInt = (int)substr($tempId, -9, 3);
                return substr($tempId, 0, -9) . sprintf('%03d', $tempInt + 1) . '000000';
            case 3:
                $tempInt = (int)substr($tempId, -6, 3);
                return substr($tempId, 0, -6) . sprintf('%03d', $tempInt + 1) . '000';
            case 4:
                $tempInt = (int)substr($tempId, 3, 3);
                return substr($tempId, 0, -3) . sprintf('%03d', $tempInt + 1);
            default:
                return $coId . '001000000000';
        }
    }

    /**
     * 카테고리 정보 변경 시 JSON 파일 재생성
     */
    protected function makeJson()
    {
        $filter = ['coId' => $this->coId];
        $options = ['sort' => ['sort' => 1, 'id' => 1], 'projection' => ['_id' => 0]];
        
        // depth별 조회 및 계층 구조 생성
        $category = $this->buildHierarchicalCategory($filter, $options);
        
        // JSON 파일 생성
        $dataPath = $this->common->config['path']['data'] . '/' . $this->coId;
        $this->json->makeJson($dataPath, $this->coId . '_category', $category);
        $this->json->makeJson($dataPath, $this->coId . '_categoryTree', $this->getCategoryTree());
        
        unset($category);
    }

    /**
     * 계층적 카테고리 구조 생성
     * 
     * @param array $filter 필터 조건
     * @param array $options 옵션
     * @return array 계층적 카테고리 배열
     */
    private function buildHierarchicalCategory($filter, $options)
    {
        $category = [];
        
        // depth별 조회
        $depths = [1, 2, 3];
        $depthData = [];
        
        foreach ($depths as $depth) {
            $filter['depth'] = $depth;
            $depthData[$depth] = iterator_to_array($this->db->list($this->collection, $filter, $options), false);
        }
        
        // 계층 구조 구성
        foreach ($depthData[1] as $value1) {
            $category[] = $value1;
            foreach ($depthData[2] as $value2) {
                if ($value1['id'] == $value2['parentId']) {
                    $category[] = $value2;
                    foreach ($depthData[3] as $value3) {
                        if ($value2['id'] == $value3['parentId']) {
                            $category[] = $value3;
                        }
                    }
                }
            }
        }
        
        return $category;
    }

    /**
     * 구글 시트를 읽어서 카테고리 데이터로 변환
     * 
     * @param string $sheetIdentifier 시트 gid 값 (GET 파라미터로 받음)
     * @return array 카테고리 데이터
     */
    public function googleSheetToCategory($sheetIdentifier = '')
    {
        try {
            // 시트 gid 조회
            if(!empty($_GET['gid'])){
                $sheetIdentifier = $_GET['gid'];
            }else{
                throw new \Exception("시트 gid가 없습니다.", 400);
            }

            // 구글 시트에서 CSV 데이터 가져오기
            $csvData = $this->readGoogleSheet($sheetIdentifier);
            // CSV 데이터 파싱
            $parsedData = $this->parseCsvData($csvData);
            
            // 카테고리 계층 구조 처리 및 DB 저장
            $categoryResults = $this->processCategoryHierarchy($parsedData['data']);
            
            // 결과 구성
            $result = [
                'success' => true,
                'data' => $parsedData['data'],
                'categoryResults' => $categoryResults,
                'meta' => [
                    'sheetIdentifier' => $sheetIdentifier ?: 'default',
                    'sheetType' => is_numeric($sheetIdentifier) ? 'index' : 'name',
                    'totalRows' => count($parsedData['data']),
                    'headers' => $parsedData['headers'],
                    'processedCategories' => count($categoryResults),
                    'description' => '구글 시트에서 읽어온 카테고리 데이터 및 DB 저장 결과'
                ]
            ];
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
                'meta' => [
                    'description' => '구글 시트 데이터 읽기 실패'
                ]
            ];
        }
    }





    /**
     * 구글 시트에서 CSV 데이터 읽기
     * 
     * @param string $sheetIdentifier 시트 gid 값
     * @return string CSV 데이터
     * @throws \Exception
     */
    private function readGoogleSheet($sheetIdentifier = '')
    {
        // 구글 시트 URL
        $spreadsheetId = '16MRE-x0qyGlD-_9HNZrzd7IHr5XLjXja3TgiYl0A0k4';
        
        // gid를 직접 사용
        $gid = $sheetIdentifier;
        
        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&gid={$gid}";
        
        // 디버깅을 위한 로그
        error_log("구글 시트 접근 - GID: {$gid}, URL: {$url}");

        // cURL로 구글 시트 데이터 가져오기
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'HKPrice-GoogleSheet-Reader/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/csv, */*',
            'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8',
            'Cache-Control: no-cache'
        ]);
        
        $csvData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($csvData === false) {
            throw new \Exception("구글 시트 데이터 가져오기 실패: " . $curlError);
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \Exception("HTTP 오류 코드: " . $httpCode);
        }
        
        return $csvData;
    }

    /**
     * CSV 데이터 파싱
     * 
     * @param string $csvData CSV 데이터
     * @return array 파싱된 데이터 (headers, data)
     */
    private function parseCsvData($csvData)
    {
        $lines = explode("\n", $csvData);
        $headers = [];
        $data = [];
        
        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // CSV 파싱 (쉼표로 구분, 따옴표 처리)
            $row = $this->parseCsvLine($line);
            
            if ($index === 0) {
                // 첫 번째 줄은 헤더
                $headers = $row;
            } else {
                // 데이터 줄
                if (count($row) >= count($headers)) {
                    $data[] = array_combine($headers, array_slice($row, 0, count($headers)));
                }
            }
        }
        
        return [
            'headers' => $headers,
            'data' => $data
        ];
    }
    
    /**
     * CSV 라인 파싱 (쉼표로 구분, 따옴표 처리)
     * 
     * @param string $line CSV 라인
     * @return array 파싱된 데이터 배열
     */
    private function parseCsvLine($line)
    {
        $result = [];
        $current = '';
        $inQuotes = false;
        $i = 0;
        
        while ($i < strlen($line)) {
            $char = $line[$i];
            
            if ($char === '"') {
                if ($inQuotes && $i + 1 < strlen($line) && $line[$i + 1] === '"') {
                    // 연속된 따옴표는 하나의 따옴표로 처리
                    $current .= '"';
                    $i += 2;
                } else {
                    // 따옴표 시작/끝
                    $inQuotes = !$inQuotes;
                    $i++;
                }
            } elseif ($char === ',' && !$inQuotes) {
                // 쉼표로 구분
                $result[] = trim($current);
                $current = '';
                $i++;
            } else {
                $current .= $char;
                $i++;
            }
        }
        
        // 마지막 필드 추가
        $result[] = trim($current);
        
        return $result;
    }

    /**
     * 카테고리 계층 구조 처리 및 DB 저장
     * 
     * @param array $data 구글 시트 데이터
     * @return array 처리 결과
     */
    private function processCategoryHierarchy($data)
    {
        $results = [];
        $processedCategories = []; // 중복 처리 방지
        
        // 각 행을 완전한 카테고리 경로로 처리
        foreach ($data as $row) {
            $categoryChain = [];
            
            // 1depth부터 4depth까지 순차 처리 (각 행의 완전한 경로)
            for ($depth = 1; $depth <= 4; $depth++) {
                $categoryKey = (string)$depth;
                if (!isset($row[$categoryKey]) || empty(trim($row[$categoryKey]))) {
                    break; // 해당 depth가 없으면 중단
                }
                
                $categoryName = trim($row[$categoryKey]);
                $categoryChain[] = $categoryName;
                
                // 중복 처리 방지 키 생성 (전체 경로로 구분)
                $processKey = implode('|', $categoryChain);
                if (in_array($processKey, $processedCategories)) {
                    continue; // 이미 처리된 카테고리 경로
                }
                
                // 카테고리 존재 여부 확인 및 생성
                $categoryResult = $this->createCategoryIfNotExists($categoryName, $depth, $categoryChain);
                $results[] = $categoryResult;
                $processedCategories[] = $processKey;
            }
        }
        
        return $results;
    }

    /**
     * 카테고리가 존재하지 않으면 생성
     * 
     * @param string $categoryName 카테고리 이름
     * @param int $depth 카테고리 depth
     * @param array $categoryChain 카테고리 체인 (부모 경로)
     * @return array 처리 결과
     */
    private function createCategoryIfNotExists($categoryName, $depth, $categoryChain)
    {
        try {
            // 부모 카테고리 ID 찾기
            $parentId = '0';
            if ($depth > 1) {
                // 구글 시트 구조에 따라 부모는 바로 이전 depth의 카테고리
                $parentName = $categoryChain[$depth - 2]; // 이전 depth의 카테고리
                
                // 같은 경로 내에서 부모 카테고리를 찾기
                // 전체 경로를 기억하여 정확한 부모 찾기
                $parentPath = array_slice($categoryChain, 0, $depth - 1);
                $parentCategory = $this->findCategoryByPath($parentName, $depth - 1, $parentPath);
                $parentId = $parentCategory ? $parentCategory['id'] : '0';
                
                // 부모를 찾지 못한 경우 오류 처리
                if ($parentId === '0' && $depth > 1) {
                    error_log("ERROR: 부모 카테고리를 찾을 수 없습니다. Depth: {$depth}, ParentName: {$parentName}, CategoryChain: " . implode(' > ', $categoryChain));
                    return [
                        'action' => 'error',
                        'categoryName' => $categoryName,
                        'depth' => $depth,
                        'error' => "부모 카테고리 '{$parentName}' (depth: " . ($depth - 1) . ")를 찾을 수 없습니다.",
                        'message' => "카테고리 '{$categoryName}' (depth: {$depth}) 처리 중 오류: 부모 카테고리를 찾을 수 없습니다."
                    ];
                }
                
                // 디버깅을 위한 로그
                error_log("Depth {$depth} - ParentName: {$parentName}, FoundParentId: {$parentId}");
            }
            
            // 현재 카테고리가 이미 존재하는지 확인 (부모 ID 포함하여 정확히 찾기)
            $existingCategory = $this->findCategoryByName($categoryName, $depth, $parentId);
            
            if ($existingCategory) {
                return [
                    'action' => 'exists',
                    'categoryName' => $categoryName,
                    'depth' => $depth,
                    'categoryId' => $existingCategory['id'],
                    'parentId' => $parentId,
                    'message' => "카테고리 '{$categoryName}' (depth: {$depth})가 이미 존재합니다."
                ];
            }
            
            // 새 카테고리 생성
            $newCategoryId = $this->generateNewCategoryId($parentId, $depth);
            
            $categoryData = [
                'id' => $newCategoryId,
                'name' => $categoryName,
                'depth' => $depth,
                'parentId' => $parentId,
                'coId' => $this->coId,
                'isUse' => true,
                'sort' => $this->getNextSortValue($parentId),
                'insert' => [
                    'date' => date('Y-m-d H:i:s'),
                    'managerId' => 'system',
                    'managerName' => 'GoogleSheet Importer',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                ]
            ];
            
            // print_r("categoryData: " . json_encode($categoryData));
            // echo "\n\n<br>";
            
            // DB에 저장
            $this->db->insert($this->collection, $categoryData);
            
            return [
                'action' => 'created',
                'categoryName' => $categoryName,
                'depth' => $depth,
                'categoryId' => $newCategoryId,
                'parentId' => $parentId,
                'message' => "카테고리 '{$categoryName}' (depth: {$depth})를 생성했습니다."
            ];
            
        } catch (\Exception $e) {
            return [
                'action' => 'error',
                'categoryName' => $categoryName,
                'depth' => $depth,
                'error' => $e->getMessage(),
                'message' => "카테고리 '{$categoryName}' (depth: {$depth}) 처리 중 오류: " . $e->getMessage()
            ];
        }
    }

    /**
     * 이름으로 카테고리 찾기
     * 
     * @param string $name 카테고리 이름
     * @param int $depth 카테고리 depth
     * @param string $parentId 부모 카테고리 ID (선택사항)
     * @return array|null 카테고리 정보 또는 null
     */
    private function findCategoryByName($name, $depth, $parentId = null)
    {
        $filter = [
            'coId' => $this->coId,
            'name' => $name,
            'depth' => $depth
        ];
        
        // 부모 ID가 명시적으로 지정된 경우에만 필터에 포함
        // 구글 시트 구조에서는 같은 이름이어도 depth가 다르면 다른 카테고리
        if ($parentId !== null && $parentId !== '') {
            $filter['parentId'] = $parentId;
        }
        
        $options = ['projection' => ['_id' => 0]];
        
        return $this->db->item($this->collection, $filter, $options);
    }

    /**
     * 경로를 기반으로 카테고리 찾기
     * 
     * @param string $name 카테고리 이름
     * @param int $depth 카테고리 depth
     * @param array $path 카테고리 경로
     * @return array|null 카테고리 정보 또는 null
     */
    private function findCategoryByPath($name, $depth, $path)
    {
        // 같은 이름과 depth의 모든 카테고리 조회
        $filter = [
            'coId' => $this->coId,
            'name' => $name,
            'depth' => $depth
        ];
        
        $options = ['projection' => ['_id' => 0]];
        $categories = $this->db->list($this->collection, $filter, $options);
        
        if (empty($categories)) {
            return null;
        }
        
        // 경로가 일치하는 카테고리 찾기
        foreach ($categories as $category) {
            if ($this->isPathMatch($category, $path)) {
                return $category;
            }
        }
        
        return null;
    }

    /**
     * 카테고리의 경로가 주어진 경로와 일치하는지 확인
     * 
     * @param array $category 카테고리 정보
     * @param array $path 확인할 경로
     * @return bool 일치 여부
     */
    private function isPathMatch($category, $path)
    {
        $currentId = $category['id'];
        $currentPath = [];
        
        // 현재 카테고리부터 루트까지 경로 추적
        while ($currentId && $currentId !== '0') {
            $parentCategory = $this->db->item($this->collection, [
                'coId' => $this->coId,
                'id' => $currentId
            ], ['projection' => ['_id' => 0]]);
            
            if (!$parentCategory) {
                break;
            }
            
            array_unshift($currentPath, $parentCategory['name']);
            $currentId = $parentCategory['parentId'];
        }
        
        // 경로 비교 (길이가 같고 모든 요소가 일치해야 함)
        return count($currentPath) === count($path) && $currentPath === $path;
    }

    /**
     * 새 카테고리 ID 생성
     * 
     * @param string $parentId 부모 카테고리 ID
     * @param int $depth 카테고리 depth
     * @return string 새 카테고리 ID
     */
    private function generateNewCategoryId($parentId, $depth)
    {
        // ID 형식: hkp + 001 + 001 + 001 + 001 (3자리씩 끊어서 각 depth별 연번)
        
        if ($parentId === '0' && $depth === 1) {
            // 1depth 카테고리: hkp001000000000
            $filter = ['coId' => $this->coId, 'depth' => 1];
            $options = ['sort' => ['id' => -1], 'limit' => 1];
            $lastCategory = $this->db->item($this->collection, $filter, $options);
            
            if ($lastCategory) {
                $lastId = $lastCategory['id'];
                $lastNumber = (int)substr($lastId, 3, 3); // hkp 다음 3자리
                $newNumber = $lastNumber + 1;
                return $this->coId . sprintf('%03d', $newNumber) . '000000000';
            } else {
                return $this->coId . '001000000000';
            }
        } else {
            // 하위 depth 카테고리
            $filter = ['coId' => $this->coId, 'parentId' => $parentId];
            $options = ['sort' => ['id' => -1], 'limit' => 1];
            $lastCategory = $this->db->item($this->collection, $filter, $options);
            
            if ($lastCategory) {
                $lastId = $lastCategory['id'];
                
                if ($depth === 2) {
                    // 2depth: hkp001002000000
                    $parentPrefix = substr($parentId, 0, 6); // hkp001
                    $lastNumber = (int)substr($lastId, 6, 3); // 002 부분
                    $newNumber = $lastNumber + 1;
                    return $parentPrefix . sprintf('%03d', $newNumber) . '000000';
                } elseif ($depth === 3) {
                    // 3depth: hkp001002001000
                    $parentPrefix = substr($parentId, 0, 9); // hkp001002
                    $lastNumber = (int)substr($lastId, 9, 3); // 001 부분
                    $newNumber = $lastNumber + 1;
                    return $parentPrefix . sprintf('%03d', $newNumber) . '000';
                } elseif ($depth === 4) {
                    // 4depth: hkp001002001001
                    $parentPrefix = substr($parentId, 0, 12); // hkp001002001
                    $lastNumber = (int)substr($lastId, 12, 3); // 001 부분
                    $newNumber = $lastNumber + 1;
                    return $parentPrefix . sprintf('%03d', $newNumber);
                }
            } else {
                // 첫 번째 하위 카테고리
                if ($depth === 2) {
                    // 2depth: hkp001002000000
                    $parentPrefix = substr($parentId, 0, 6); // hkp001
                    return $parentPrefix . '001000000';
                } elseif ($depth === 3) {
                    // 3depth: hkp001002001000
                    $parentPrefix = substr($parentId, 0, 9); // hkp001002
                    return $parentPrefix . '001000';
                } elseif ($depth === 4) {
                    // 4depth: hkp001002001001
                    $parentPrefix = substr($parentId, 0, 12); // hkp001002001
                    return $parentPrefix . '001';
                }
            }
        }
        
        // 기본값 반환 (오류 방지)
        return $this->coId . '001000000000';
    }

    /**
     * 다음 sort 값 가져오기
     * 
     * @param string $parentId 부모 카테고리 ID
     * @return int 다음 sort 값
     */
    private function getNextSortValue($parentId)
    {
        $filter = ['coId' => $this->coId, 'parentId' => $parentId];
        $options = ['sort' => ['sort' => -1], 'limit' => 1];
        $lastCategory = $this->db->item($this->collection, $filter, $options);
        return $lastCategory ? $lastCategory['sort'] + 1 : 1;
    }

    /**
     * 카테고리 ID를 입력받아 계층구조 문자열을 반환하는 함수
     * 예) hkp001002016002 -> "농수축산물 > 채소류 > 당근 > 무세척"
     * 
     * @param string $categoryId 카테고리 ID
     * @return string 계층구조 문자열 ("> "로 구분)
     */
    public function getCategoryHierarchyString($categoryId)
    {
        try {
            // 카테고리 ID 검증
            if (empty($categoryId)) {
                throw new \Exception('카테고리 ID가 필요합니다.', 400);
            }
            
            // 전체 카테고리 조회
            $allCategories = $this->getAllCategoriesForSearch();
            $categoryMap = $this->createCategoryMap($allCategories);
            
            // 카테고리 존재 여부 확인
            if (!isset($categoryMap[$categoryId])) {
                throw new \Exception("카테고리 ID '{$categoryId}'를 찾을 수 없습니다.", 404);
            }
            
            // 부모 경로 조회
            $parentPath = $this->getParentPathFromArray($categoryId, $categoryMap);
            
            // 계층구조 문자열 생성
            $hierarchyString = $this->buildPathString($parentPath);
            
            return $hierarchyString;
            
        } catch (\Exception $e) {
            // 오류 발생 시 빈 문자열 반환 또는 예외 재발생
            throw $e;
        }
    }

    /**
     * 카테고리 ID를 입력받아 계층구조 정보를 반환하는 함수 (상세 정보 포함)
     * 
     * @param string $categoryId 카테고리 ID
     * @return array 계층구조 정보
     */
    public function getCategoryHierarchyInfo($categoryId)
    {
        try {
            // 카테고리 ID 검증
            if (empty($categoryId)) {
                throw new \Exception('카테고리 ID가 필요합니다.', 400);
            }
            
            // 전체 카테고리 조회
            $allCategories = $this->getAllCategoriesForSearch();
            $categoryMap = $this->createCategoryMap($allCategories);
            
            // 카테고리 존재 여부 확인
            if (!isset($categoryMap[$categoryId])) {
                throw new \Exception("카테고리 ID '{$categoryId}'를 찾을 수 없습니다.", 404);
            }
            
            // 부모 경로 조회
            $parentPath = $this->getParentPathFromArray($categoryId, $categoryMap);
            
            // 계층구조 문자열 생성
            $hierarchyString = $this->buildPathString($parentPath);
            
            // 결과 구성
            return [
                'success' => true,
                'categoryId' => $categoryId,
                'hierarchyString' => $hierarchyString,
                'path' => $parentPath,
                'depth' => count($parentPath),
                'meta' => [
                    'description' => '카테고리 계층구조 정보',
                    'format' => '부모 > 자식 형태의 문자열'
                ]
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'categoryId' => $categoryId,
                'hierarchyString' => '',
                'path' => [],
                'depth' => 0
            ];
        }
    }

    /**
     * 구글 시트 gid 테스트 함수
     * 
     * @return array 테스트 결과
     */
    public function testGid()
    {
        $gid = $_GET['gid'] ?? '0';
        $spreadsheetId = '16MRE-x0qyGlD-_9HNZrzd7IHr5XLjXja3TgiYl0A0k4';
        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/gviz/tq?tqx=out:csv&gid={$gid}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Cache-Control: no-cache'
        ]);
        
        $csvData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($csvData !== false && $httpCode === 200) {
            $lines = explode("\n", $csvData);
            $firstLine = trim($lines[0] ?? '');
            $secondLine = trim($lines[1] ?? '');
            
            return [
                'success' => true,
                'gid' => $gid,
                'url' => $url,
                'firstLine' => $firstLine,
                'secondLine' => $secondLine,
                'totalLines' => count($lines),
                'httpCode' => $httpCode
            ];
        } else {
            return [
                'success' => false,
                'gid' => $gid,
                'url' => $url,
                'httpCode' => $httpCode,
                'error' => '데이터를 가져올 수 없습니다.'
            ];
        }
    }
}