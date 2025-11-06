// skin preview
let setPreview = (modal)=>{
	var modal = $(modal);

	// skin preview
	if(modal.find('#pcSkin').val()){
		modal.find("#pcSkinPreview").attr('src', modal.find('#pcSkin option:selected').data('json').thumbnail.pc.path);
	}else{
		modal.find("#pcSkinPreview").attr('src', '/img/skin/show-hidden-icons.png');
	}
	if(modal.find('#mobileSkin').val()){
		modal.find("#mobileSkinPreview").attr('src', !!modal.find('#mobileSkin option:selected').data('json').thumbnail?.mobile?.path ? modal.find('#mobileSkin option:selected').data('json').thumbnail.mobile.path : modal.find('#mobileSkin option:selected').data('json').thumbnail.pc.path);
	}else{
		modal.find("#mobileSkinPreview").attr('src', '/img/skin/show-hidden-icons.png');
	}
};

// 면편집 좌측영역 nav tab active
$(document).on('click','#objBoxList nav[role="tab"] a',function(){
	var tabList = $(this).data('list');
	$('#objBoxList nav[role="tab"] a').removeClass('active');
	$('#objBoxList [role="tablist"]').removeClass('active');
	$(this).addClass('active');
	$(tabList).addClass('active');
});

// 면편집 좌측영역 fold
$(document).on('click','#btn_objBoxList',function(){
	$('#objBoxList').toggleClass('fold');
	if($('#objBoxList').hasClass('fold')){
		$('#layout').addClass('wide');
	}else{
		$('#layout').removeClass('wide');
	}
	$(this).children('i').toggleClass('rotate-180');
	$(this).attr('data-tooltip', $('#objBoxList').hasClass('fold') ? '펼쳐보기' : '접어두기');
});

// 화면 width 감지 : resize
$(window).resize(function(){
	var deviceWidth = getDeviceWidth();
	if(deviceWidth >= 992){
		if($('#layout').hasClass('wide')){
			$('#objBoxList').addClass('fold');
			$('#btn_objBoxList').children('i').addClass('rotate-180');
			$('#btn_objBoxList').attr('data-tooltip', '펼쳐보기');
		}
	}
});

// 박스설정 목록합치기 off일 경우에만 목록정렬 사용가능
$(document).on('click','#listMerge',function(e){
	let modal = $(this).closest('article');
	if ($(this).prop('checked')) {
		modal.find('#listSort').prop('disabled', true);
	}else{
		modal.find('#listSort').prop('disabled', false);
	}
});

// skin select 로딩
let setSelectSkin = (selector, dinnum, device, boxType)=>{
	$.ajax({
		url: '/box/templateList/ajax?dinnum='+dinnum+'&device='+device+'&boxType='+boxType,
		type: 'GET',
		dataType: "json",
		traditional : true,
		async : false,
		success: (data)=>{
			let result = data.result;
			let html ='';
			$(selector).find('option').remove();
			$(selector).append('<option value="" data-image="/img/skin/show-hidden-icons.png">'+ ( boxType=='banner' ? '기본':'안보이기')+'</option>');
			$(result).each((i, data)=>{
				html = '<option value="'+data.id+'" data-image="'+data.thumbnail[device].path+'">'+data.title+'</option>';
				$(selector).append(html);
				$(selector).find('option[value="'+data.id+'"]').data('json',data);
			});
		}
	});
};

// BoxId select option html 생성
let getSelectBoxId = function(layoutId, boxType, boxId=null){
	let items = getLayoutBoxList(layoutId, boxType);
	let html = '<option value="">선택 없음</option>';
	$(items).each(function(i,item){
		if(!!boxId && boxId == item.objId) {
			// 제외 : 현재 박스
		} else if (item.listType == 'A' && item.autoGroupType == 'syncBox') {
			// 제외 : 박스연동
		} else {
			html += '<option value="'+item.objId+'">'+(!!item?.title ? item.title : '이름 없음')+'</option>';
		}
	});
	return html;
};

// 레이아웃 박스 리스트 조회
let getLayoutBoxList = function(layoutId, boxType){
	let items = [];
	$.ajax({
        url: '/layout/getLayout/ajax',
        method : 'GET',
		dataType: 'json',
		async: false,
        data : {
			id: layoutId
		}
    }).done((data)=> {
		if (!!data?.result?.item?.pageInfo) {
			$(data.result.item.pageInfo).each(function(i,item){
				if (item.objType == boxType) {
					items.push(item);
				}
			});
		}
    }).fail((jqXHR, textStatus, errorThrown)=>{
		if(!!jqXHR?.responseJSON?.result?.msg) {
			alert(jqXHR.responseJSON.result.msg);
		} else {
			alert(textStatus+' : '+errorThrown);
		}
	});
	return items;
};

// input[role="switch"] 관련
$(document).on('change','input[role="switch"]',function(){
	var checked = $(this).prop('checked');
	var row = $(this).closest('.row');
	var on = $(this).data('on');
	var off = $(this).data('off');
	if(checked){
		row.find('.switch-label').empty().append(on);
	}else{
		row.find('.switch-label').empty().append(off);
	}
});

// customBoxId 중복 체크
$(document).on('change','#customBoxId',function(){
	checkCustomboxId(this);
});
let checkCustomboxId = function (obj) {
	let $obj = $(obj);
	let customBoxId = $.trim($obj.val());
	let objId = $obj.closest('.layoutInput').find('#objId').val();
	if (!!customBoxId) {
		$('.layoutBase [data-customboxid="'+customBoxId+'"]').each(function(i, item){
			if (objId != $(item).data('objid')) {
				alert('중복된 custom boxId가 있습니다. : '+customBoxId);
				$obj.val('');
				$obj.focus();
			}
		});	
	}
};


let formatState = (state)=>{
	let imageSrc = $(state.element).data('image');
	let $state = $(
		'<span><p>' + state.text + '</p><img src="' + imageSrc + '" class="img-flag" /> </span>'
	);
	return $state;
};