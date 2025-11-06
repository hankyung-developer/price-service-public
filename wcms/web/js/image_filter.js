var imageFilter = fabric.Image.filters;

$(function(){
	$("#filterRangebox1").slider({
		range: "max",
		min: 0,
		max: 1,
		value: 1,
		slide: function(event, ui){
			const sel = canvas.getActiveObject();
			if(checkObject()){
				const cnt = ui.value;
				
				if(cnt == 0){
					applyFilter(new imageFilter.Grayscale(),"Grayscale");
				}else if(cnt == 1){
					removeFilter("Grayscale");
				}

				$("#filterAmount1").val(cnt);
			}else{
				alert('이미지를 선택해주세요');
				$('#chk7b_1').prop('checked',false);
			}
		}
	});

	$(document).on('click','#chk7b_1',function(){
		const sel = canvas.getActiveObject();
		if(checkObject()){
			if($('#chk7b_1').prop('checked')){
				applyFilter(new imageFilter.Grayscale(),"Grayscale");
			}else{
				removeFilter("Grayscale");
			}
		}else{
			alert('이미지를 선택해주세요');
			$('#chk7b_1').prop('checked',false);
		}
	});

	$("#filterRangebox2").slider({
		range: "max",
		min: 0,
		max: 100,
		value: 100,
		slide: function (event, ui) {
			if(checkObject()){
				const cnt = ui.value;
				$("#filterAmount2").val(cnt);
				
				object.set({ opacity: parseFloat(cnt/100)});
				canvas.renderAll();
			}else{
				alert('이미지를 선택해주세요');
				$("#filterRangebox2").slider({value:100});
				$("#filterAmount2").val(Number(100));
			}
		}
	});

	$(document).on('change','#filterAmount2',function(){
		if(checkObject()){
			const cnt = $(this).val();

			if(isNumber(cnt)){
				if(cnt >= -100 && cnt <= 100){
					$("#filterRangebox2").slider({value:cnt});
					object.set({ opacity: parseFloat(cnt/100)});
					canvas.renderAll();
				}else{
					alert('0과 100사이의 숫자를 입력하세요');
				}
			}else{
				alert('0과 100사이의 숫자를 입력하세요');
			}
		}else{
			alert('이미지를 선택해주세요');
			$("#filterRangebox2").slider({value:100});
			$("#filterAmount2").val(Number(100));
		}
	});

	$(document).on('keyup','#filterAmount2',function(){
		if(checkObject()){
			if(!isNumber($(this).val())){
				alert('-, 숫자만 입력 가능합니다.');
				 $(this).val($(this).val().replace(/[^-\][^0-9]/g,""));
			}
		}else{
			alert('이미지를 선택해주세요');
			$("#filterRangebox2").slider({value:100});
			$("#filterAmount2").val(Number(100));
		}
	});

	$("#filterRangeboxBrightness").slider({
		range: "max",
		min: -100,
		max: 100,
		value: 0,
		slide: function (event, ui) {
			if(checkObject()){
				const cnt = ui.value;
				$("#filterAmountBrightness").val(cnt);
				applyFilter(new imageFilter.Brightness({brightness:parseFloat(cnt/100)}),"Brightness",true);
			}else{
				alert('이미지를 선택해주세요');
				$("#filterRangeboxBrightness").slider({value:0});
				$("#filterAmountBrightness").val(Number(0));
			}
		}
	});
	
	$(document).on('change','#filterAmountBrightness',function(){
		if(checkObject()){
			const cnt = $(this).val();

			if(isNumber(cnt)){
				if(cnt >= -100 && cnt <= 100){
					const cnt = $(this).val();
					$("#filterRangeboxBrightness").slider({value:cnt});
					applyFilter(new imageFilter.Brightness({brightness:parseFloat(cnt/10)}),"Brightness",true);
				}else{
					alert('-100과 100사이의 숫자를 입력하세요');
				}
			}else{
				alert('-100과 100사이의 숫자를 입력하세요');
			}
		}else{
			alert('이미지를 선택해주세요');
			$("#filterRangeboxBrightness").slider({value:0});
			$("#filterAmountBrightness").val(Number(0));
		}
	});

	$("#filterRangeboxContrast").slider({
		range: "max",
		min: -100,
		max: 100,
		value: 0,
		slide: function (event, ui) {
			if(checkObject()){
				const cnt = ui.value;
				$("#filterAmountContrast").val(cnt);
				applyFilter(new imageFilter.Contrast({contrast:parseFloat(cnt/100)}),"Contrast",true);
			}else{
				alert('이미지를 선택해주세요');
				$("#filterRangeboxContrast").slider({value:0});
				$("#filterAmountContrast").val(Number(0));
			}
		}
	});

	$(document).on('change','#filterAmountContrast',function(){
		if(checkObject()){
			const cnt = $(this).val();

			if(isNumber(cnt)){
				if(cnt >= -100 && cnt <= 100){
					const cnt = $(this).val();
					$("#filterRangeboxContrast").slider({value:cnt});
					applyFilter(new imageFilter.Contrast({contrast:parseFloat(cnt/100)}),"Contrast",true);
				}else{
					alert('-100과 100사이의 숫자를 입력하세요');
				}
			}else{
				alert('-100과 100사이의 숫자를 입력하세요');
			}
		}else{
			alert('이미지를 선택해주세요');
			$("#filterRangeboxContrast").slider({value:0});
			$("#filterAmountContrast").val(Number(0));
		}
	});

	$("#filterRangeboxSaturation").slider({
		range: "max",
		min: -100,
		max: 100,
		value: 0,
		slide: function (event, ui) {
			if(checkObject()){
				const cnt = ui.value;
				$("#filterAmountSaturation").val(cnt);
				applyFilter(new imageFilter.Saturation({saturation:parseFloat(cnt/100)}),"Saturation",true);
			}else{
				alert('이미지를 선택해주세요');
				$("#filterRangeboxSaturation").slider({value:0});
				$("#filterAmountSaturation").val(Number(0));
			}
		}
	});

	$(document).on('change','#filterAmountSaturation',function(){
		if(checkObject()){
			const cnt = $(this).val();

			if(isNumber(cnt)){
				if(cnt >= -100 && cnt <= 100){
					const cnt = $(this).val();
					$("#filterRangeboxSaturation").slider({value:cnt});
					applyFilter(new imageFilter.Saturation({saturation:parseFloat(cnt/100)}),"Saturation",true);
				}else{
					alert('-100과 100사이의 숫자를 입력하세요');
				}
			}else{
				alert('-100과 100사이의 숫자를 입력하세요');
			}
		}else{
			alert('이미지를 선택해주세요');
			$("#filterRangeboxSaturation").slider({value:0});
			$("#filterAmountSaturation").val(Number(0));
		}
	});
})

var checkObject = function(){
	var flag = false;
	if(canvas.getActiveObject()){
		const sel = canvas.getActiveObject();
		if(sel.type=="image"){
			flag = true;
		}
	}
	return flag;
}

var applyFilter = function(filter, type,flg){
	var sel = canvas.getActiveObject();
	var tempFilter = new Array();
	var filterFlag = false;
	canvas.getObjects().forEach(function(e){
		if(e.title == sel.title ){
			e.filters.forEach(function(fe){
				if(fe.type!=type){
					tempFilter.push(fe);
				}else{
					filterFlag = true;
				}
			});
		}
	});

	if(flg){
		tempFilter.push(filter);
	}

	if(filterFlag){
		fabric.textureSize = 50000
		sel.filters = tempFilter;
		sel.applyFilters();
		sel.applyResizeFilters();
		canvas.renderAll();
	}else{
		fabric.textureSize = 50000;
		sel.filters.push(filter);
		sel.applyFilters();
		sel.applyResizeFilters();
		canvas.renderAll();
	}
	
	historyPush();

	$('#radio2_1').prop('checked',false);
}

var removeFilter = function(filter){
	var sel = canvas.getActiveObject();
	var tempFilter = sel.filters;
	
	tempFilter.forEach(function(fe){
		if(fe.type == filter){
			tempFilter.pop(fe);
		}
	});

	sel.filters = tempFilter;
	sel.applyFilters();
	sel.applyResizeFilters();
	canvas.renderAll();

	historyPush();
}

var initFilters = function(){
	//$("#filterRangebox1").slider({value:1});
	//$("#filterAmount1").val(Number(1));
	$('#chk7b_1').prop('checked',false);
	$("#filterRangebox2").slider({value:100});
	$("#filterAmount2").val(Number(100));
	$("#filterRangeboxBrightness").slider({value:0});
	$("#filterAmountBrightness").val(Number(0));
	$("#filterRangeboxContrast").slider({value:0});
	$("#filterAmountContrast").val(Number(0));
	$("#filterRangeboxSaturation").slider({value:0});
	$("#filterAmountSaturation").val(Number(0));
}

var filterInfo = new Array();
var settingFilters = function(){
	var sel = canvas.getActiveObject();
	if(sel){
		var tempFilter = sel.filters;

		$("#filterRangebox2").slider({value:sel.opacity*100});
		$("#filterAmount2").val(Number(sel.opacity*100));

		filterInfo['Grayscale'] = false;
		filterInfo['Brightness'] = false;
		filterInfo['Contrast'] = false;
		filterInfo['Saturation'] = false;

		$('#chk7b_1').prop('checked',false);
		tempFilter.forEach(function(fe){
			if(fe.type=="Grayscale"){
				//$("#filterRangebox1").slider({value:0});
				//$("#filterAmount1").val(Number(0));
				$('#chk7b_1').prop('checked',true);
				filterInfo['Grayscale'] = true;
			}else if(fe.type=="Brightness"){
				$("#filterRangeboxBrightness").slider({value:fe.brightness*100});
				$("#filterAmountBrightness").val(Number(fe.brightness*100));
				filterInfo['Brightness'] = true;
			}else if(fe.type=="Contrast"){
				$("#filterRangeboxContrast").slider({value:fe.contrast*100});
				$("#filterAmountContrast").val(Number(fe.contrast*100));
				filterInfo['Contrast'] = true;
			}else if(fe.type=="Saturation"){
				$("#filterRangeboxSaturation").slider({value:fe.saturation*100});
				$("#filterAmountSaturation").val(Number(fe.saturation*100));
				filterInfo['Saturation'] = true;
			}
		});

		for(var key in filterInfo){
			if(!filterInfo[key]){
				$("#filterRangebox"+key).slider({value:0});
				$("#filterAmount"+key).val(Number(0));
			}
		}
	}
}

var isNumber = function(s){
	s += '';
	s = s.replace(/^\[-\]?\\d\*$/g, '');
	if(s==''|isNaN(s)){
		return false;
	}
	return true;
}