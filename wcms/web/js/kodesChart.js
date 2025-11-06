const coId = $('#coId').val();
const domain = $('#domain').val();

let targetNode = ''; // TH, TD
let rowIndex=-1; // 행의 순서
let columnIndex=-1; // 열의 순서
let lastRow=$("#displayData table tbody tr").index();
let lastField=$("#displayData table tbody td:last-child").index();
let defColor=['#4dc9f6','#f67019','#f53794','#537bc4','#acc236','#166a8f','#00a950','#58595b','#8549ba'];
let defColNum = defColor.length;
let contentChange = false;

let modifyFlag = false;

let headerObj = '';
headerObj += '<div class="flex">';
headerObj += '	<div class="full" style="padding-top:3px;"><input type="text" class="coloris" id="colors[]" name="graphColor" title="Choose your color" value="[color]"><label for="graphColor">그래픽 색상</label></div>';
headerObj += '	<select id="graphType[]" name="graphType" class="form-control form-control-sm">';
headerObj += '		<option value="line">선</option>';
headerObj += '		<option value="bar">세로막대</option>';
headerObj += '		<option value="hbar">가로막대</option>';
headerObj += '		<option value="bubble">버블</option>';
headerObj += '		<option value="pie">파이</option>';
headerObj += '		<option value="doughnut">도넛</option>';
//headerObj += '		<option value="half-pie">반형 파이</option>';
//headerObj += '		<option value="half-doughnut">반형 도넛</option>';
headerObj += '		<option value="radar">레이더</option>';
headerObj += '	</select>';
headerObj += '	<input type="text" id="graphTitle[]" placeholder="라벨이름" value="[value]" class="form-control form-control-sm"/>';
headerObj += '</div>';

let dateOptions = {
	"today" : [dateSearch.getToday(), dateSearch.getToday()],
	"yesterday" : [dateSearch.getDiffDay(-1), dateSearch.getDiffDay(-1)],
	"last10Days" : [dateSearch.getDiffDay(-9), dateSearch.getToday()],
	"last30Days" : [dateSearch.getDiffDay(-29), dateSearch.getToday()],
	"last90Days" : [dateSearch.getDiffDay(-89), dateSearch.getToday()],
	"thisMonth" : [dateSearch.getThisMonthStart(), dateSearch.getThisMonthEnd()],
	"agoMonth" : [dateSearch.getDiffMonthStart(-1), dateSearch.getDiffMonthEnd(-1)],
	"last1Years" : [dateSearch.getDiffYear(-1), dateSearch.getToday()],
	"last3Years" : [dateSearch.getDiffYear(-3), dateSearch.getToday()],
}

$(document).on('change',"select[id='graphType[]']",function(){
	/*let idx = $($(this).parent()).index();
	let type = $(this).val();
	let target = $(this).parent().parent().parent().parent().parent().prop("id");

	if(type == 'pie' || type == 'doughnut'){
		for(let i=1;i<$("#"+target+ " tr").length;i++){
			if(idx==1){
				let _obj =$(eval($(eval($("#"+target+ " tr")[i])).find("td")));
				for(let j=1;j<_obj.length;j++){
					let _text = $(eval($(_obj)[j])).text();
					$(eval($(_obj)[j])).html('<input type="color" class="form-control-color" id="mcolor[]" name="mcolor[]" value="'+defColor[(i-1)%defColor.length]+'" title="Choose your color">'+_text);
				}
			}else{
				let text = $(eval($(eval($("#"+target+ " tr")[i])).find("td")[idx])).text();
				$(eval($(eval($("#"+target+ " tr")[i])).find("td")[idx])).html('<input type="color" class="form-control-color" id="mcolor[]" name="mcolor[]" value="#563d7c" title="Choose your color">');
			}
		}
	}else{
		for(let i=1;i<$("#"+target+ " tr").length;i++){
			if(idx==1){
				$(eval($(eval($("#"+target+ " tr")[i])).find("td"))).find('.form-control-color').remove();
			}else{
				$(eval($(eval($("#"+target+ " tr")[i])).find("td")[idx])).find('.form-control-color').remove();
			}
		}
	}

	if(idx==1){
		if(type == 'pie' || type == 'doughnut'){
			for(let i=0;i<$("#"+target+ " tr").length;i++){
				$($("#"+target+ " tr")[i]).find("select[id='graphType[]']").val(type);
			}

			$("#"+target+" tr:first td").find("select[id='graphType[]'] option").prop('disabled',true);
			$("#"+target+" tr:first td").find("select[id='graphType[]'] option[value='"+type+"']").prop('disabled',false);
			$(eval($("#"+target+" tr:first td")[1])).find("select[id='graphType[]'] option").prop('disabled',false);
		}else{
			for(let i=0;i<$("#"+target+ "tr").length;i++){
				if($($("#"+target+ "tr")[i]).find("select[id='graphType[]']").val()=="pie" || $($("#"+target+ " tr")[i]).find("select[id='graphType[]']").val() =="doughnut"){
					$($("#"+target+ " tr")[i]).find("select[id='graphType[]']").val(type);
				}
			}
			for(let i=1;i<$("#"+target+" tr:first td").length;i++){
				let _type = $($("#"+target+" tr:first td")[i]).find("select[id='graphType[]'] option:selected").val();
				if(_type=="pie" || _type =="doughnut"){
					$($("#"+target+" tr:first td")[i]).find("select[id='graphType[]']").val(type);
				}
			}

			$("#"+target+" tr:first td").find("select[id='graphType[]'] option").prop('disabled',false);
			$("#"+target+" tr:first td").find("select[id='graphType[]'] option[value='pie']").prop('disabled',true);
			$("#"+target+" tr:first td").find("select[id='graphType[]'] option[value='doughnut']").prop('disabled',true);
			$(eval($("#"+target+" tr:first td")[1])).find("select[id='graphType[]'] option").prop('disabled',false);
		}
	}*/


	let type = $(this).val();
	if( type == 'hbar' ){
		$("select[id='graphType[]']").val(type)
	}

	for(let i=1;i<$("#dataTable tr").length;i++){
		let obj = $(eval($(eval($("#dataTable tr")[i])).find("td")))[0];
		$(obj).find('input').remove();
	}

	if(type == 'pie' || type == 'doughnut' || type == 'half-pie' || type == 'half-doughnut'){
		for(let i=1;i<$("#dataTable tr").length;i++){
			let obj = $(eval($(eval($("#dataTable tr")[i])).find("td")))[0];
			$(obj).append('<input type="color" class="form-control-color" id="mcolor[]" name="mcolor[]" value="'+defColor[(i-1)%defColor.length]+'" title="Choose your color">');
		}
	}
});

function excelExport(event){
    var input = event.target;
    var reader = new FileReader();
    reader.onload = function(){
        var fileData = reader.result;
        var wb = XLSX.read(fileData, {type : 'binary'});
        var sheetNameList = wb.SheetNames; // 시트 이름 목록 가져오기 
        var firstSheetName = sheetNameList[0]; // 첫번째 시트명
        var firstSheet = wb.Sheets[firstSheetName]; // 첫번째 시트 
		handleExcelDataJson(firstSheet);
    };
    reader.readAsBinaryString(input.files[0]);
}

function handleExcelDataJson(sheet){
	var data = XLSX.utils.sheet_to_json (sheet);
	var maxFieldNum= Object.keys(data[0]).length;
	var colorNum = defColor.length;

	//$("#displayData").empty();
	//$("#displayData").append("<table id='dataTable'></table>");
	//$("#displayData table").addClass('table');
	//$("#displayData table").addClass('table-striped');
	//$("#displayData table").addClass('table-bordered');

	// 해더처리
	$("#displayData thead").empty();
	let theadHtml = '<tr><th><span onclick="javascript:rowFieldChange()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="기준축 변경"><img src="/img/row_field_change.svg" style="width:22px;height:22px;margin:2px;"></span></th>';
	for(i = 0; i < maxFieldNum-1; i++){
		colNum = i % defColNum;
		theadHtml += '<th contenteditable="true">'+headerObj.replace('[color]',defColor[colNum]).replace('[value]','')+'</th>';
	}
	$("#displayData thead").append(theadHtml);

	$("#displayData tbody").empty();
	$.each(data, function (i, row) {
		tmp = '<tr>';
		$.each(row, function (j, field) {
			tmp+="<td contenteditable='true'>"+field+"</td>";
		});
		tmp+="</tr>";

		$("#displayData tbody").append(tmp);
	});
	$("#chartTitle").val($('#excelFile')[0].files[0].name.replace(/[.]?[a-z]+$/gm, ''));

	colorSetting();

	$('#data').click();
}

/**
 * Table 정보를 읽어서 그래픽를 그린다.
 **/
let script='';
let tableData;
function tableDraw(table){
    var data = [];
	data['axisX'] = [];
	data['axisY'] = [];

	axisY = 0;

	title = [];
	$("#"+table+" [id='graphTitle[]']").each(function(){
		title.push($(this).val());
	});

	type = [];
	$("#"+table+" [id='graphType[]']").each(function(){
		type.push($(this).val());
	});
	color = [];
	$("input[id='colors[]']").each(function(){
		color.push($(this).val());
	});

	flag = true;
	if(color){
		for(let j=0;j<$("#displayData table tbody td:last-child").index();j++){
			data['axisX'][j] = [];
		}

		$('#'+table+" table tr").each(function(i,n){
			if(i!=0){
				var _row = $(n);
				data['axisY'].push(_row.children()[axisY].innerText);
				
				for(var j=1;j<$(_row).find('td').length;j++){
					data['axisX'][j-1].push(_row.children()[j].innerText);
				}
			}
		});

		var dataset = {
			labels:  data['axisY'],
			datasets: [
			]
		};

		for(let j=0;j<$("#displayData table tbody td:last-child").index();j++){
		
			let colors = color[j];
			if(type[j] == 'pie' || type[j] == 'doughnut' ||  type[j]=='half-doughnut' || type[j]=='half-pie' ){
				mcolor = [];
				colors = '';
				//$("#"+table+" tr td:nth-child("+(j+2)+") input[id='mcolor[]']").each(function(i){
					$("#"+table+" tr td:nth-child(1) input[id='mcolor[]']").each(function(i){
					mcolor.push($(this).val());
				});
				colors=mcolor;
			}

			if( type[j]=='half-doughnut' || type[j]=='half-pie' ){
				flag = false;
				type[j] = type[j].replace('half-','');
			}

			let datasetItem = {	
							label:  title[j],
							fill:false,
							backgroundColor:colors,
							borderColor:colors,
							data: data['axisX'][j],
							type:type[j]
						};

			if(type[j] == 'hbar')
			{
				datasetItem.indexAxis = 'y';
				datasetItem.type = 'bar';
			}

			dataset.datasets.push(datasetItem);
		}
		drawChart(dataset, type, flag);
	}

	tableData = data;
}

// 그래픽를 그리기 위한 Canvas Tag 생성
function makeCanvas(id) {
	var container = document.getElementById(id);
	var canvas = document.createElement('canvas');
	var ctx = canvas.getContext('2d');
	ctx.fillStyle = "white";
	ctx.fillRect(0, 0, canvas.width, canvas.height);

	container.innerHTML = '';
	canvas.width = container.offsetWidth;
	canvas.height = 400; //container.offsetHeight;
	container.appendChild(canvas);

	return ctx;
}

// 차트를 Canvas tag에 출력
function drawChart(dataset, type, flag){

	var paddingBottom = 18;
	var source = $("#source").val().trim();
	if(source == ""){
		paddingBottom = 0;
	}

	options = {
		layout: {
			padding: {
				bottom: paddingBottom
			}
		},
		plugins :{
			title: {
				display: true,
				//align:'start',
				align:$('#chartTitleAlign').val(),
				font:{weight:'bold',size: 16},
				text: $('#chartTitle').val()
			},
			subtitle: {
				display: true,
				align:'start',
				font:{weight:'normal',size: 13},
				text: $('#unitTitle').val()
			},
			customCanvasBackgroundColor: {
				color: $('#graphBackColor').val(),
			}
		}
	};

	if( !flag){
		options['rotation'] = -90;
		options['circumference'] = 180;
	}

	new Chart(makeCanvas('chart1'),{
		type: type,
		data: dataset,
		options: options,
		plugins: [chartText,pluginBackgroundColor]
	});

	setTimeout( ()=>{
		new Chart(makeCanvas('chart2'),{
			type: type,
			data: dataset,
			options: options,
			plugins: [chartText,pluginBackgroundColor]
		});
	}, 80);
}

// 하단 출처를 그래픽에 생성
const chartText =  {
	id : "chartText",
	beforeDraw : function(chart){
		var source = $("#source").val().trim();
		if(source != ""){
			chart.ctx.textAlign = "bottom";
			chart.ctx.font = "normal 13px silver";
			chart.ctx.fillText("출처 : "+source, 10, (chart.height - 10));
		}
	}
}

// 차트의 백배경 색상을 지정한다.
const pluginBackgroundColor = {
  id: 'customCanvasBackgroundColor',
  beforeDraw: (chart, args, options) => {
    chart.ctx.save();
    chart.ctx.globalCompositeOperation = 'destination-over';
    chart.ctx.fillStyle = options.color || '#99ffff';
    chart.ctx.fillRect(0, 0, chart.width, chart.height);
    chart.ctx.restore();
  }
};


// 데이터 table 앞쪽에 줄 추가
let tableRowPreAdd = ()=>{
	let tmpRow = $("#displayData table tbody tr:last-child").clone();
	tmpRow.find('td').text('');
	tmpRow.find('td:first-child').append('<div class="full"><input type="text" class="coloris" id="colors[]" name="graphColor" title="Choose your color" value="#4dc9f6"><label for="graphColor">그래픽 색상</label></div>');
	$("#displayData table tbody tr:nth-child("+((columnIndex==-1?0:columnIndex)+1)+")").before('<tr>'+tmpRow.html()+'</tr>');
	Coloris({
		el: '.coloris'
	});
}

// 데이터 table 다음에 줄 추가
let tableRowAdd = ()=>{
	let tmpRow = $("#displayData table tbody tr:last-child").clone();
	tmpRow.find('td').text('');
	tmpRow.find('td:first-child').append('<div class="full"><input type="text" class="coloris" id="colors[]" name="graphColor" title="Choose your color" value="#4dc9f6"><label for="graphColor">그래픽 색상</label></div>');
	$("#displayData table tbody tr:nth-child("+((columnIndex==-1?0:columnIndex)+1)+")").after('<tr>'+tmpRow.html()+'</tr>');
	//$("#displayData table tbody tr:nth-child("+((columnIndex==-1?0:columnIndex)+2)+")").find('td').text('');
	Coloris({
		el: '.coloris'
	});
}

// 데이터 table 줄 삭제
function tableRowDel(){
	$("#displayData table tbody tr:nth-child("+(columnIndex+1)+")").remove();
}

// 데이터 table 컬럼 추가
function tableColumnLeftAdd(idx){
	if(!idx) idx=rowIndex!=-1?rowIndex+1:$('#displayData table thead th').length;

	$("#displayData table thead th:nth-child("+(idx)+")").before('<th contenteditable="true">'+headerObj.replace('[color]',defColor[colNum]).replace('[value]','')+'</th>');
	$("#displayData table tbody td:nth-child("+(idx)+")").before('<td contenteditable="true"></td>');
	colorSetting();
}


// 데이터 table 컬럼 추가
function tableColumnAdd(idx){
	if(!idx) idx=rowIndex!=-1?rowIndex+1:$('#displayData table thead th').length;

	$("#displayData table thead th:nth-child("+(idx)+")").after('<th contenteditable="true">'+headerObj.replace('[color]',defColor[colNum]).replace('[value]','')+'</th>');
	$("#displayData table tbody td:nth-child("+(idx)+")").after('<td contenteditable="true"></td>');
	colorSetting();
}

// 데이터 table 컬럼 제거
function tableColumnDel(){
	let idx = rowIndex!=-1?rowIndex+1:$('#displayData table thead th').length-1;
	$('#displayData table thead tr th:nth-child('+idx+')').remove()
	$('#displayData table tbody tr td:nth-child('+idx+')').remove()
}

// 그래픽 이미지 URL 처리
function canvasToURL(){
	let canvas = document.getElementById('chart1').getElementsByTagName('canvas')[0];
	let dataURL = canvas.toDataURL();
	let imageTag = "<img src='"+dataURL+"'/>";
	$('#canvasImage').html(imageTag);
	$('#graphImage').attr('src',dataURL);
}

// 그래픽 이미지 저장
function canvasToImage(){
	changeCavasWidth('1200px');
	setTimeout(saveImage,80);
}

function changeCavasWidth(width){
	$('#chart1').css('width',width);
}

function saveImage(){
	let canvas = $('#chart2>canvas')[0];
	let data={};
	data['idx'] = $("#gIdx").val();
	data['makeDate'] = $("#makeDate").val();
	data['photo'] = canvas.toDataURL();
	
	$.ajax({
		method: "POST",
		url:'/chart/toImage/ajax',
		data : data,
	}).done(function(response){
		canvasToFile(response);
	});
}

// 그래픽 스크립트 코드 화면 처리
function canvasToScript(){
	$('#canvasScript').val(script);
}

// 그래픽 스크립트 저장
function canvasToFile(){
	let data={};
	data['idx'] = $("#gIdx").val();
	data['makeDate'] = $("#makeDate").val();
	data['html'] = script;

	$.ajax({
		method: "POST",
		url:'/chart/toFile/ajax',
		data : data,
	}).done(function(data){
		var idx = $("#gIdx").val();
		var makeDate = $("#makeDate").val();
		// iframe 주소 변경
		if( window.opener.tinymce ){
			window.opener.tinymce.execCommand('mceInsertContent', true, "<iframe frameborder='0' style='width:730px;height:400px;' src='/data/"+coId+"/chart/"+makeDate+"/"+idx+".html' data-img='/data/"+coId+"/chart/"+makeDate+"/"+idx+".png' data-thumb='/data/"+coId+"/chart/"+makeDate+"/"+idx+"_thumb.png'></iframe><br>");
			window.close();
		}
	});
}

let makeScript = (data)=>{
	// script 태그 생성
	script ="<script>\nvar chartText =  {\n"
	+"	id : 'chartText',\n"
	+"	beforeDraw : function(chart){\n"
	+"		var source = '"+$("#source").val().trim()+"';\n"
	+"		if(source != ''){\n"
	+"			chart.ctx.textAlign = 'bottom';\n"
	+"			chart.ctx.font = 'normal 13px silver';\n"
	+"			chart.ctx.fillText('출처 : '+source, 10, (chart.height - 10));\n"
	+"		}\n"
	+"	}\n"
	+"};";
	script +="var chart = new Chart(document.getElementById('chart'),{\n"
				+"type: '"+type[0]+"',\n"
				+"options: {\n"
				+"layout: {padding: {bottom: 18}},"
				+"plugins :{\n"
				+"title: {align:'"+$('#chartTitleAlign').val()+"',display: true,text:'"+$('#chartTitle').val()+"',font: {weight:'bold',size: 16}},\n"
				+"subtitle: {align:'start',display: true,text:'"+$('#unitTitle').val()+"',font: {weight:'normal',size: 13}}\n"
				+"},\n"
				+"indexAxis: 'x',\n"
				+"elements: {\n"
				+"bar: {\n"
				+"borderWidth: 2,\n"
				+"}\n"
				+"}\n"
				+"},\n";
	script += "plugins: [chartText]";
	script += "});\n";
	if(!isNumber($('#dataKind').find(":selected").val()) && $('#field1').find(":selected").val()==undefined && $('#field2').find(":selected").val()==undefined && $('#endDate').find(":selected").text()!="최신자"){
		script += "chart.data={";
		script += "labels : ";
		
		for(let i=0;i<tableData.axisY.length;i++){
			script += (i==0?"[":"")+"'"+data.axisY[i]+"'"+(i==data.axisY.length-1?'], \n':',');
		}
		
		script += "		datasets:[";
		for(let j=0;j<title.length;j++){
			
			let colors = "'"+color[j]+"'";
			if(type == 'pie' || type == 'doughnut' ||  type[j]=='half-doughnut' || type[j]=='half-pie'){
				mcolor = [];
				colors = '';
				$("#"+table+" tr td:nth-child("+(j+2)+") input[id='mcolor[]']").each(function(i){
					mcolor.push($(this).val());
				});
				for(let i=0;i<mcolor.length;i++){
					colors += (i==0?"[":"'")+"'"+mcolor[i]+"'"+(i==mcolor.length-1?']':',');
				}
				colors=mcolor;
			}

			if( type[j]=='half-doughnut' || type[j]=='half-pie' ){
				flag = false;
				type[j] = type[j].replace('half-','');
			}

			//if(type[j] == 'hbar')
			//{
			//	type[j] = 'bar';
			//}

			if(j!=0){
				script += ",";
			}
			script += "			{";
			script += "				label: '" + title[j] + "',";
			script += "				fill:false,";
			script += "				backgroundColor: " + colors + ",";
			script += "				borderColor: " + colors + ",";
			script += "				type: '" + type[j] + "',";
			script += "				data:";

			for(let i=0;i<tableData.axisX[j].length;i++){
			script +=  (i==0?"[":"")+"'"+tableData.axisX[j][i]+"'"+(i==tableData.axisX[j].length-1?']\n':',');
			}

			//if(type[j] == 'hbar')
			//{
			//	script += "				datasetItem.indexAxis = 'y'";
			//}

			script += "}";
		}
		script += "]";
		script += "};";
		script += 'chart.update();';
	}else{
		/** To Do : script 확인 */
		script +='$.ajax({\n'
					+'url: "/chartService/get?id='+$("#gIdx").val()+'&coId='+coId+'&date='+$('#makeDate').val()+'",\n'
					+'method : "GET",\n'
					+'dataType : "json"\n'
					+'}).done(function(data){\n'
					+'let labelData = new Array();\n'
					+'let filedData = new Array();\n'
					+'\n'
					+'let dataset = {\n'
					+'	labels:  data["axisY"],\n'
					+'	datasets: []\n'
					+'}\n'
					+'\n'
					+'let axisX = data["axisX"];\n'
					+'let colors = data["color"];\n'
					+'let type = data["type"];\n'
					+'let title = data["fieldTitle"];\n'
					+'\n'
					+'for(let i =0; i < axisX.length; i++){\n'		
					+'	let datasetItem = {	\n'
					+'		label:  title[i],\n'
					+'		fill:false,\n'
					+'		backgroundColor:colors[i],\n'
					+'		borderColor:colors[i],\n'
					+'		data: data["axisX"][i],\n'
					+'		type:type[i]\n'
					+'	};\n'
					+'\n'
					+'	if(type[i] == "hbar")\n'
					+'	{\n'
					+'		datasetItem.indexAxis = "y";\n'
					+'		datasetItem.type = "bar";\n'
					+'	}\n'
					+'\n'	
					+'	dataset.datasets.push(datasetItem);\n'
					+'}\n'
					+'\n'
					+'chart.data = dataset;\n'

					+'chart.update();\n'
				+'});\n';
	}
	script += '</script>';
}

function graphSetting(){
	let color = $('#graphColor').val();
	let type = $('#graphType').val();

	let target = '';
	if(targetNode=='TH'){
		target = $('#displayData table').find(targetNode.toLowerCase()).eq(rowIndex);
	}else if(targetNode=='TD'){
		target = $('#displayData table').find('tr').eq(columnIndex+1).find('td').eq(rowIndex);
	}

	if(type == 'pie' || type == 'doughnut'){
		$(target).css('backgroundColor','white');
		$(target).css('color','black');

		for(var i=0;i<defColor.length;i++){
			$('#displayData table tbody tr:nth-child('+defColor.length+'n+'+(i+1)+') td:nth-child('+(rowIndex+1)+')').css('background-color',defColor[i]).append('<input type="hidden" id="mcolor[]" name="mcolor[]" value="'+defColor[i]+'"/>');
		}
	}else if(targetNode=='TH'){
		$(target).css('backgroundColor',color);
		$(target).css('color',getComplementaryColor(color));
		if( !(type == 'pie' || type == 'doughnut')){
			$('#displayData table tbody td').css('backgroundColor','white');
			$('#displayData table tbody td').css('color','black');
			$('input[id^=mcolor]').remove();
		}
	}else{
		$(target).css('backgroundColor',color);
		$(target).css('color',getComplementaryColor(color));
	}

	$(target).find('img').remove();
	$(target).find('input').remove();

	if(targetNode=='TH'){
		$(target).append('<img src="/svg/'+type+'.svg" class="graphKind" >');
		$(target).append('<input type="hidden" id="color[]" name="color[]" value="'+color+'"/>');
		$(target).append('<input type="hidden" id="graphType[]" name="graphType[]" value="'+type+'"/>');
	}else if(targetNode=='TD'){
		$(target).append('<input type="hidden" id="mcolor[]" name="mcolor[]" value="'+color+'"/>');
	}

	$(".contextmenu").hide();
}

// 컬러의 보색의 값을 리턴
function getComplementaryColor(color){
	return '#'+(0Xffffff - parseInt('0X'+color.substr(1,6))).toString(16).padStart(6,"0");
}

// 새로운 테이블을 세팅
function createTable(){
	let row = $('#row').val();
	let column = $('#column').val();
	
	$("#displayData").empty().append("<table id='dataTable'></table>");
	$("#displayData table").addClass('table');
	$("#displayData table").addClass('table-striped');
	$("#displayData table").addClass('table-bordered');

	let tag = "";
	for(let j = 0; j < row; j++){
		if(j==0){
			tag += '<thead><tr>';
		}else if(j==1){
			tag += "<tbody><tr>";
		}else{
			tag += "<tr>";
		}

		for (let i = 0; i < column; i++) {
			if(j==0){
				tag += "<th contenteditable='true'></th>";
			}else{
				tag += "<td contenteditable='true'></td>";
			}
		}

		if(j==0){
			tag += "</tr></thead>";
		}else if(j==row-1){
			tag += "</tr></tbody>";
		}else{
			tag += "</tr>";
		}
	}

	$("#displayData table").append(tag);
	$('#popover-content').hide();
}

// 
function moveToHead(){
	let type = 'line';

	$("#displayData table tbody tr:nth-child("+(columnIndex+1)+") td").each(function(i,v){
		$("#displayData table thead th:nth-child("+($(this).index()+1)+")").text($(this).text());
		if(i!=0){
		$("#displayData table thead th:nth-child("+($(this).index()+1)+")").append('<img src="/svg/'+type+'.svg" class="graphKind" >');
		$("#displayData table thead th:nth-child("+($(this).index()+1)+")").append('<input type="hidden" id="color[]" name="color[]" value="'+defColor[(i-1)]+'"/>');
		$("#displayData table thead th:nth-child("+($(this).index()+1)+")").append('<input type="hidden" id="graphType[]" name="graphType[]" value="'+type+'"/>');
		}
	});
	$("#displayData table tbody tr:nth-child("+(columnIndex+1)+")").remove();
	$('.contextmenu').hide();
}

$(".tableButton svg").on("click",function(){
	var action = $(this).attr("class");
	switch(action){
		case "prependRow":
			tableRowPreAdd();
		break;
		case "addRow":
			tableRowAdd();
		break;
		case "delRow":
			tableRowDel();
		break;
		case "prependColumn":
			tableColumnAdd(rowIndex);
		break;
		case "addColumn":
			tableColumnAdd(rowIndex+1);
		break;
		case "delColumn":
			tableColumnDel();
		break;
		default:
	}
});

$("#displayData table tbody").on("click",'td',function(){
	columnIndex = $(this).closest("tr").index(); 
	rowIndex = $(this).index();
	lastRow=$("#displayData table tbody tr:last-child").index();
	lastField=$("#displayData table tbody td:last-child").index();
});

$("#dataKind").change(function(){
	let dataKind = $(this).val();
	/*if(dataKind=="input"){
		$("#excelFile").show();
		$("#apiTools").hide();
		$("#makedList").hide("slow");
	}else if(dataKind=="maked"){
		$("#makedList").show("slow");
		$("#apiTools").hide();
		$(".chartArea .card-body").css("height","200px;");
		getMakedList(0);
	}else{
		$("#excelFile").hide();
		$("#makedList").hide("slow");
		$("#apiTools").show();
		$("#chartTitle").val($(this).find(":selected").text());
		$("#source").val($(this).find(":selected").attr('data-provider'));
		$("#unitTitle").val($(this).find(":selected").attr('data-unit'));

		$.ajax({
			method: "GET",
			url:'/chart/Field?apiIdx='+dataKind,
			dataType:'json'
		}).done(function(data){
			$("#field1").empty();
			$("#field2").empty();

			$("#field1").append("<option>X축 선택</option>");
			$("#field2").append("<option>Y축 선택</option>");
			data.forEach(function(item,index){
				$("#field1").append("<option value='"+item['field']+"'>"+item['remark']+"</option>");
				$("#field2").append("<option value='"+item['field']+"'>"+item['remark']+"</option>");
			});
		});
	}*/
	if( dataKind != ""){
		$("#chartTitle").val($(this).find(":selected").text());
		$("#source").val($(this).find(":selected").attr('data-provider'));
		$("#unitTitle").val($(this).find(":selected").attr('data-unit'));

		$.ajax({
			method: "GET",
			url:'/chart/field/ajax',
			data:{'apiIdx':dataKind},
			dataType:'json'
		}).done(function(data){
			$('#field1, #field2, #field3').empty()

			$("#field1").append("<option>X축 선택</option>");
			$("#field2").append("<option>Y축 선택</option>");
			$("#field3").append("<option>조건 선택</option>");
			data.result.forEach(function(item,index){
				$("#field1").append("<option value='"+item['field']+"'>"+item['remark']+"</option>");
				$("#field2").append("<option value='"+item['field']+"'>"+item['remark']+"</option>");
				$("#field3").append("<option value='"+item['field']+"'>"+item['remark']+"</option>");
			});
			$('#filed3List').empty();
		});

	}
});

$("#field1").change(function(){
	let dataKind = $("#dataKind").val();
	let field = $(this).val();
	$.ajax({
		method: "GET",
		url:'/chart/dates/ajax',
		data:{'apiIdx':dataKind,'field':field},
		dataType:'json'
	}).done(function(data){
		$("#startDate").empty();
		$("#endDate").empty();
		$("#startDate").append("<option>날짜선택</option>");
		$("#endDate").append("<option>최신자</option>");
		var maxNum = data.result.length;
		data.result.forEach(function(item,index){
			$("#startDate").append("<option value='"+item[field]+"'>"+item[field+"Dp"]+"</option>");
			$("#endDate").append("<option value='"+data.result[(maxNum-1-index)][field]+"'>"+data.result[(maxNum-1-index)][field+"Dp"]+"</option>");
		});
	});
});

$("#field3").change(function(){
	let dataKind = $("#dataKind").val();
	let field = $(this).val();

	if( field == ""){
		$('#filed3List').hide();
	}else{
		$('#filed3List').show();
		$.ajax({
			method: "GET",
			url:'/chart/condition/ajax',
			data:{'apiIdx':dataKind,'field':field},
			dataType:'json'
		}).done(function(data){
			$('#filed3List').empty();
			data.result.forEach(function(item,index){
				if( item._id ){
					$('#filed3List').append('<li><input type="checkbox" id="condition[]" name="condition[]" value="'+item._id+'">'+item._id+'</li>');
				}
			})
		});
	}
})

let conditionChecked= (flag) =>{
	$("#filed3List li").find('input[type=checkbox]').prop('checked', flag);
}

$("#selectData").click(function(){
	makeApiToTable();
});


function makeApiToTable(){
	let dataKind = $("#dataKind").val();
	let field1 = $("#field1").val();
	let field2 = $("#field2").val();
	let field3 = $("#field3").val();
	let condition = [];
	$('[id="condition[]"]:checked').each((idx, e)=>{ condition.push($(e).val());});
	let startDate = $("#startDate").val();
	let endDate = $("#endDate").val();

	$.ajax({
		method: "GET",
		url:'/chart/data/ajax',
		data:{'apiIdx':dataKind,'field1':field1,'field2':field2,'field3':field3,'condition':condition.join(),'startDate':startDate,'endDate':endDate},
		dataType:'json'
	}).done(function(data){
		$("#displayData").empty().append("<table id='dataTable'><thead></thead><tbody></tbody></table>");
		$("#displayData table").addClass('table table-striped table-bordered');

		let contionCnt = $('[id="condition[]"]:checked').length;
		let tag = '';
		if( contionCnt == 0){

			tag = '<tr><th><span onclick="javascript:rowFieldChange()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="기준축 변경"><img src="/img/row_field_change.svg" style="width:22px;height:22px;margin:2px;"></span></th>';
			tag += '<th contenteditable="true">'+headerObj.replace('[color]',defColor[0])+'</th>';
			$("#displayData table thead").append(tag);
			$("#displayData table thead").find('[id="graphTitle[]"]').val($("#field2 option:selected").text());

			data.result.forEach(function(item,index){
				tag = "<tr><td contenteditable='true'>"+item[field1]+"</td><td contenteditable='true'>"+item[field2]+"</td></tr>";
				$("#displayData table tbody").append(tag);
			});
		}else{

			tag = '<tr><th><span onclick="javascript:rowFieldChange()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="기준축 변경"><img src="/img/row_field_change.svg" style="width:22px;height:22px;margin:2px;"></span></th>';
			for(let i=0; i< contionCnt; i++){
				tag += '<th contenteditable="true">'+headerObj.replace('[color]',defColor[i]).replace('[value]',condition[i])+'</th>';
			}
			$("#displayData table thead").append(tag);
			
			let items = new Array();
			let cnt = 0;
			data.result.forEach(function(item,index){
				
				let flag = true;
				let tcnt = 0;
				for( let k=0; k< items.length; k++){
					if(items[k][field1] == item[field1]){
						flag = false;
						tcnt = k;
					}
				}

				if(flag){
				  items[cnt] = new Array();
					items[cnt][field1] = item[field1];
					for(let i=0; i< contionCnt; i++){
						items[cnt][condition[i]] = '';
						if( condition[i] == item[field3]){
							items[cnt][condition[i]] = item[field2]
						}
					}
					cnt++;
				}else{
					for(let i=0; i< contionCnt; i++){
						if( condition[i] == item[field3]){
							items[tcnt][condition[i]] = item[field2]
						}
					}

				}
			});

			for(let i=0; i< items.length; i++){
				let temp = items[i];

				tag = "<tr><td contenteditable='true'>"+temp[field1]+"</td>";

				for(let j =0; j < condition.length; j++){
					tag += "<td contenteditable='true'>"+temp[condition[j]]+"</td>";
				}
				tag += '</tr>';
				$("#displayData table tbody").append(tag);
			}
		}

		colorSetting();

		tableDraw('displayData');
	});
}

$("#makedList .btn-close").click(function(){
	$("#makedList").hide("slow");
	$("#dataKind").val('input').trigger('change');
});

$("#btnGraphComplate").click(function(){
    let sendData = {};
	sendData['title'] = $("#chartTitle").val();
	sendData['unitTitle'] = $("#unitTitle").val();
	sendData['category'] = $("#category").val();
	sendData['source'] = $("#source").val();
	sendData['caption'] = $("#caption").val();
	sendData['axisXName'] = $("#field1 option:selected").text();
	sendData['axisYName'] = $("#field2 option:selected").text();

	sendData['condition'] = [];
	$('[id="condition[]"]:checked').each((idx, e)=>{ sendData['condition'].push($(e).val());});
	
	sendData['startDate'] = $("#startDate").val();
	sendData['endDate'] = $("#endDate").val();
	sendData['makeDate'] = $("#makeDate").val();

	sendData['axisX'] = [];
	sendData['axisY'] = [];

	axisY = 0;

	sendData['fieldTitle'] = [];
	$("#displayData [id='graphTitle[]']").each(function(){
		if( $(this).val() != "" ){
			sendData['fieldTitle'].push($(this).val());
		}
	});
	
	sendData['type'] = [];
	$("#displayData table [id='graphType[]']").each(function(){
		sendData['type'].push($(this).val());
	});

	sendData['color'] = [];
	$("#displayData table input[id='colors[]']").each(function(){
		sendData['color'].push($(this).val());
	});
	sendData['mcolor'] = [];
	$("#displayData table input[id='mcolor[]']").each(function(){
		sendData['mcolor'].push($(this).val());
	});

	for(let j=0;j<$("#displayData table tbody td:last-child").index();j++){
		sendData['axisX'][j] = [];
	}
	//sendData['color'] = sendData['color'].slice(0, sendData['fieldTitle'].length);
	let dateLen = sendData['fieldTitle'].length > 0 ? sendData['fieldTitle'].length : sendData['axisX'].length;

	sendData['color'] = sendData['color'].slice(0, dateLen);
	if( dateLen != sendData['fieldTitle'].length ){
		for(let i = 0; i < dateLen; i++){
			if( !!!sendData['fieldTitle'][i] ){
				sendData['fieldTitle'][i] = " ";
			}
		}
	}

	$('#displayData table tr').each(function(i,n){
		if(i!=0){
			var _row = $(n);

			if( _row.children()[axisY].innerText != "" ){
				sendData['axisY'].push(_row.children()[axisY].innerText);
			}
			
			for(var j=1;j<$(_row).find('td').length;j++){
				if(_row.children()[j].innerText != "" ){
					sendData['axisX'][j-1].push(_row.children()[j].innerText);
				}
			}
		}
	});
	$('#chart1').css('width','600px');

	// 옵션 설정 값
	sendData['chartTitleAlign'] = $('#chartTitleAlign').val();
	sendData['graphBackColor'] = $('#graphBackColor').val();
	sendData['graphBackColor'] = $('#graphBackColor').val();

	if(modifyFlag){
		sendData['idx'] = $("#gIdx").val();
	}

	sendData['inputType']=type;

	if( $('#chartTitle').val() == ''){
		alert('차트 제목을 입력하세요.');
		return false;
	}

	let canvas = $('#chart2>canvas')[0];
	if( !canvas ){
		alert('그래픽 데이터를 생성해주세요');
		return false;
	}
	if( canvas.toDataURL() == ''){
		alert('그래픽 데이터를 생성해주세요');
		return false;
	}

	if(contentChange){
		$('#workingpopup').show();
		$.ajax({
				method: "POST",
				url:'/chart/recode/ajax',
				data : sendData,
				async: false,
				dataType:'json'
		}).done(function(data){
			$("#gIdx").val(data['result']['idx']);
			$("#chart1>canvas").style;
			canvasToImage();

			makeScript(data.result);

			$('#workingpopup').hide();
			alert('저장되었습니다.');
		});
	}else{
		canvasToImage();
	}
});

// 과거 그래픽 선택시 처리
$("#makedListTable tbody").on("click","tr",function(){
	var idx = $(this).find("td:first").data("idx");

	$.ajax({
		method: "GET",
		url:'/chart/makedview/ajax',
		data:{idx:idx},
		dataType:'json'
	}).done(function(response){
		let result = response.result;
		$("#chartTitle").val(result['title']);
		$("#unitTitle").val(result['unitTitle']);
		$("#source").val(result['source']);
		$("#makeDate").val(result['insert']['date'].replace(/([0-9]+)[-]([0-9]+)[-]([0-9]+)[ ]([0-9]+)[:]([0-9]+)[:]([0-9]+).*/, `$1$2`));
		$('#field1').val(undefined);
		$('#field2').val(undefined);
		$('#endDate').val(undefined);

/*		result['axisX']=result['axisX'];
		result['axisY']=result['axisY'];
		result['color']=result['color'];
		result['fieldTitle']=result['fieldTitle'];
		result['type']=result['type'];		*/

		fieldSize = result['axisX'].length+1;
		rowSize = result['axisX'][0].length;
		$("#gIdx").val(result['idx']);

		$("#displayData").empty();
		$("#displayData").append('<table id="dataTable" class="table table-striped table-bordered"><thead><tr></tr></thead><tbody></tbody></table>');
		// header 세팅
		var tmp = '';
		for(var i = 0 ; i < fieldSize ; i++  ){
			if(i == 0 ){
				tmp='<th><span onclick="javascript:rowFieldChange()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="기준축 변경"><img src="/img/row_field_change.svg" style="width:22px;height:22px;margin:2px;"></span></th>';
			}else{
				colNum = (i-1) % defColNum;
				tmp+='<th>'+headerObj.replace('[color]',result['color'][(i-1)]).replace('[value]', result['fieldTitle'][(i-1)]) +'</th>';
			}
		}
		$("#displayData thead tr").append(tmp);

		/* Type 설정 */
		let type = result['type'];
		$('select[name=graphType').each((idx, e)=>{
			$(e).val(type[idx]);
		});
		
		for(var i = 0 ; i < rowSize ; i++  ){
			tmp = "<tr>";
			for(var j = 0 ; j < fieldSize ; j++  ){
				if(j == 0 ){
					tmp+='<td contenteditable="true">'+result['axisY'][i]+'</td>';
				}else{
					tmp+='<td contenteditable="true">'+result['axisX'][j-1][i]+'</td>';
				}
			}
			tmp += "</tr>";
			$("#displayData tbody").append(tmp);
		}
		tableDraw('displayData');
		
		$('#workingList').hide();
		$('#chartResult').show();


		$('#data').click();

		contentChange = false;

		colorSetting();
	});
});

//
function graphKindOption(state) {
	if (!state.id) {
		return state.text;
	}
	
	var baseUrl = "/svg/";
	var $state = $('<span><img src="' + baseUrl + '/' + state.element.value.toLowerCase() + '.svg" class="graphImage" /> ' + state.text + '</span>');
	
	$state.find("span").text(state.text);
	$state.find("img").attr("src", baseUrl + "/" + state.element.value.toLowerCase() + ".svg");
	
	return $state;
};

$("#searchKeyword").keyup(function(){
	if (window.event.keyCode == 13) {
		getMakedList();
    }
});

$("#btnSearch").click(function(){
	//makeApiToTable();
	getMakedList();
});

function getMakedList(page){
	var keyword = $("#searchKeyword").val();
	var page = (!page?1:page);
	let noapp = 15;

	$('#makedListTable tbody').empty();

	$.ajax({
		method: "GET",
		url:'/chart/makedList/ajax',
		data:{'keyword':keyword,'page':page,'noapp':noapp},
		dataType:'json'
	}).done(function(data){
		data.result.list.forEach(function(item,index){
			$('#makedListTable tbody').append('<tr><td data-idx="'+item['idx']+'">'+item['title']+'</td><td>'+item['insert']['managerName']+'</td><td>'+item['insert']['date']+'</td></tr>');


		});

		let paging ='';
		paging +='<div class="ht-60 d-flex align-items-center justify-content-end">';
		paging +=' <nav aria-label="Page navigation">';
		paging +='    <ul class="pagination pagination-basic mg-b-0">';
		if(data.result.page.prevPage > 1){
		paging +='       <li class="page-item">';
		paging +='         <a class="page-link" href=\'javascript:getMakedList('+data.result.page.prevPage+');\' aria-label="Previous">';
		paging +='          <i class="fa fa-angle-left"></i>';
		paging +='        </a>';
		paging +='      </li>';
		}
		$(data.result.page.navibar).each(function() {
			paging +='      <li class="page-item '+(page==this.page?'active':'')+'"><a class="page-link" href=\'javascript:getMakedList('+this.page+');\'>'+this.page+'</a></li>';
		});
		if(data.result.page.nextPage > 1){
		paging +='      <li class="page-item">';
		paging +='        <a class="page-link" href=\'javascript:getMakedList('+data.result.page.nextPage+');\' aria-label="Next">';
		paging +='          <i class="fa fa-angle-right"></i>';
		paging +='        </a>';
		paging +='      </li>';
		}
		paging +='    </ul>';
		paging +='  </nav>';
		paging +='</div>';

		$("#makedList .pagination").empty().append(paging);
	});
}

$(document).on('mousedown', 'td', function(e){
	rowIndex = $(e.target).index();
	columnIndex = $(e.target).parent().index();
})

$(document).ready(function(){
	Coloris({
		el: '.bcoloris'
	});

	cellAppend();
	rowAppend();
	colorSetting();

	var $targ = $('.toolButton');
	$targ.popoverButton({target: '#popover-content', placement: 'bottom'});
	$targ.on('click', function() {});

	// UI 관련 이벤트 //
	$('#dataKind').select2({width:'100%'});
	$('#field1').select2({width:'100%', minimumResultsForSearch: Infinity});
	$('#field2').select2({width:'100%', minimumResultsForSearch: Infinity});
	$('#field3').select2({width:'100%', minimumResultsForSearch: Infinity});
	$('#startDate').select2({width:'100%', minimumResultsForSearch: Infinity});
	$('#endDate').select2({width:'100%', minimumResultsForSearch: Infinity});

	//$("select[id^='graphType[]']").select2({width:'100%',templateResult: graphKindOption,minimumResultsForSearch: Infinity});

	$("#displayData td").attr('contenteditable',true);
	$("#displayData th").attr('contenteditable',true);
	// UI 관련 이벤트 //

	$("#apiTools").on('change', "select", ()=>{
		apiCheck();
	});

	$("#apiTools").on('click', "input", ()=>{
		apiCheck();
	});

	let apiCheck = ()=>{
		var field1 = $("#apiTools #field1").val();
		var field2 = $("#apiTools #field2").val();
		var startDate = $("#apiTools #startDate").val();
		var endDate = $("#apiTools #endDate").val();
		startDate = startDate ? startDate:"";
		endDate = endDate ? endDate:"";

		if(field1!= null && field2 != null && startDate!= "" && endDate!= ""){
			makeApiToTable();
		}
	}

	$(document).contextmenu(function(e){
		if(e.target.nodeName == 'TD' ){ //|| e.target.nodeName == 'TH'){

			showContextMenu(e);
			return false;
		}else{
			$(".contextmenu").hide();
			return false;
		}
	});
	// 마우스 우측 버튼 이벤트 //

	$(document).on('click','body',function(e){
		$(".contextmenu").hide();
	});
	
	$(document).on('click','.graphKind',function(e){
		showContextMenu(e);
	});

	function showContextMenu(e){
		targetNode = e.target.nodeName;

		if(targetNode=="IMG"){
			targetObj=e.target.parentNode;
			targetNode = targetObj.nodeName;
		}else{
			targetObj=e.target;
		}
		rowIndex = $(targetObj).index();
		columnIndex = $(targetObj).parent().index();

		//let thisColor = $(targetObj).find('input[id="color[]"]').val()?$(targetObj).find('input[id="color[]"]').val():'#4dc9f6';
		//let thisType = $(targetObj).find('input[id="graphType[]"]').val()?$(targetObj).find('input[id="graphType[]"]').val():'line';
		//$('#graphColor').val(thisColor);
		//$('.clr-field button').css('color',thisColor)
		//$('#graphType').val(thisType);
		//$("#graphType").trigger("change");
		
		if(rowIndex == 0 || targetObj.nodeName == 'TD'){
			//$('#graphColor').prop('disabled','disabled');
			//$('#graphType').prop('disabled','disabled')
		}else{
			//$('#graphColor').prop('disabled','');
			//$('#graphType').prop('disabled','')
		}

		if(targetObj.nodeName ==' TH' ){
			$('#toHead').prop('disabled','disabled');
		}else{				
			$('#toHead').prop('disabled','');
		}

		//let thType = $("#displayData table thead th:eq("+(rowIndex)+") input[id='graphType[]']").val();

		//if( thType == 'pie' ||  thType == 'doughnut'){
		//	$('#graphColor').prop('disabled','');
		//}

		/*let firstGraphtype = $("#displayData table thead th:eq(1) input[id='graphType[]']").val();
		if(rowIndex > 1 && (firstGraphtype=='pie' || firstGraphtype=='doughnut')){
			$("#graphType option").prop('disabled',true);
			$("#graphType option[value='"+firstGraphtype+"']").prop('disabled',false);
			$("#graphType").val(firstGraphtype);
			$("#graphType").trigger("change");
		}else{
			$("#graphType option").prop('disabled',false);
			$("#graphType").trigger("change");
		}*/

		//Get window size:
		var winWidth = $(document).width();
		var winHeight = $(document).height();
		
		//Get pointer position:
		var posX = e.pageX;
		var posY = e.pageY;
		
		//Get contextmenu size:
		var menuWidth = $(".contextmenu").width();
		var menuHeight = $(".contextmenu").height();
		
		//Security margin:
		var secMargin = 10;
		//Prevent page overflow:
		if(winHeight >= posY + menuHeight){
			posTop = posY + secMargin + "px";
		}else{
			posTop = winHeight - menuHeight - secMargin;
		}

		if(winWidth >= (posX + menuWidth)){
			posLeft = posX - secMargin + "px";
		}else{
			posLeft = menuWidth - menuWidth + "px";
		}

		//Display contextmenu:
		$(".contextmenu").css({
			"left": posLeft,
			"top": posTop
		}).show();
		//Prevent browser default contextmenu.
	}

	$(document).on("change","#graphColor",function(){
		$(this).closest('.clr-field').find('button').css('color',$(this).val());
	});

	const target = document.querySelector("#displayData");
	target.addEventListener("paste", (event) => {

		if( event.target.type != 'text'){
			event.preventDefault();
			$("#displayData tbody").empty();
			let paste = (event.clipboardData || window.clipboardData).getData("text");
			let rows = paste.split("\n");
			for(var v in rows){
				let cells = rows[v].split("\t");
	
				if( cells[0] != ""){
					tmp = '<tr>';
					for(var v2 in cells){
						tmp+='<td contenteditable="true">'+cells[v2]+'</td>';
					}
					tmp += "</tr>";
					$("#displayData tbody").append(tmp);
				}
			}
	
			let thCnt = $("#displayData tbody tr:first td").length;
			$("#displayData thead").empty();
			let theadHtml = '<tr><th><span onclick="javascript:rowFieldChange()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="기준축 변경"><img src="/img/row_field_change.svg" style="width:22px;height:22px;margin:2px;"></span></th>';
			for(i = 1; i < thCnt; i++){
				colNum = i % defColNum;
				theadHtml += '<th contenteditable="true">'+headerObj.replace('[color]',defColor[colNum]).replace('[value]','')+'</th>';
			}
			$("#displayData thead").append(theadHtml);
	
			colorSetting();
		}
	})

	$('#listLoading').on('click',()=>{
		$('#workingList').show();
		$('#apiList').hide();
		$('#chartResult').hide();
		getMakedList();
	});
	$('#excelLoading').on('click',()=>{
		$('#excelFile').click();

		$('#workingList').hide();
		$('#apiList').hide();
	});
	$('#apiLoading').on('click',()=>{
		$('#workingList').hide();
		$('#apiList').show();
		$('#chartResult').show();
	});
});

let rowFieldChange = ()=>{
	let tableArray= Array($('#displayData tr th').length).fill(null).map(()=>Array());
	$('#displayData tr').each(function(i,row){
		$(row).children().each(function(j, field){
			if(i ==0  ){
				tableArray[j][i]=$(field).find('input[id="graphTitle[]"]').val();	
			}else{
				tableArray[j][i]=$(field).text();	
			}
		});
	});

	$('#dataTable').empty();
	$('#dataTable').append('<thead></thead>');
	$('#dataTable').append('<tbody></tbody>');

	let thHtml = '';
	let rowHtml = '';

	thHtml = '<tr>';
	tableArray.forEach(function(row, i){
		if(i!=0){
			rowHtml = '<tr>';
		}
		row.forEach(function(field, j){
			if(i==0){
				if(j == 0 ){
					thHtml+='<th><span onclick="javascript:rowFieldChange()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="기준축 변경"><img src="/img/row_field_change.svg" style="width:22px;height:22px;margin:2px;"></span></th>';
				}else{
					colNum = (j-1) % defColNum;
					thHtml+='<th>'+headerObj.replace('[color]',defColor[colNum]).replace('[value]', field) +'</th>';
				}
			}else{
				if(j == 0){
					rowHtml += "<td contenteditable='true'>"+field+"</td>";
				}else{
					rowHtml += "<td contenteditable='true'>"+field+"</td>";
				}
			}
		});
		if(i!=0){
			rowHtml += "</tr>";
			$('#displayData tbody').append(rowHtml);
		}
	});
	thHtml += "</tr>";
	$("#displayData thead").append(thHtml);

	colorSetting();
};

// 숫자인지 확인
function isNumber(s) {
  s += '';
  s = s.replace(/^\s*|\s*$/g, '');
  if (s == '' || isNaN(s)) return false;
  return true;
}


// table 데이터 변경시 
$('#displayData').on("DOMSubtreeModified","table", function(){
	contentChange = true;
});

let colorSetting = ()=>{
	Coloris({
		el: '.coloris',
		theme: 'pill',
		themeMode: 'dark',
		swatchesOnly: false,
		formatToggle: false,
		swatches: [          
			'#C35079','#DBA5B3','#00BBB4','#D0EBF2','#EFEFEF','#9FA1A0', '#ffffff'
		],
		onChange: (color, input) =>tableDraw('displayData')
	});
}

$('#graphTypeAll').on('change', (e)=>{
	let graphType = $(e.target).val();
	if( graphType != ''){
		$('select[name=graphType]').val(graphType);
		tableDraw('displayData');
	}
})

$('#dateRange').on('change', (e)=>{
	let range = $(e.target).val();

	if( range != "" )
	{
		let periodDate = dateOptions[range];
	
		let dateValue = $('#startDate  option:eq(1)').val();
		if( dateValue.indexOf('-') == -1)
		{
			startDate = periodDate[0].replaceAll('-','');
			endDate = periodDate[1].replaceAll('-','');
		}
		else
		{
			startDate = periodDate[0];
			endDate = periodDate[1];
		}
	
		$('#startDate').val(startDate).select2();
		$('#endDate').val(endDate).select2();
	}
})

const cellInit = 25;
const rowInit = 50;
let cellAppend = ()=>{
	let fieldSize = $('#displayData thead th').length;
	for(i = 0; i < cellInit-fieldSize; i++){
		colNum = (i+fieldSize-1) % defColNum;
		$("#displayData thead tr").append('<th contenteditable="true">'+headerObj.replace('[color]',defColor[colNum]).replace('[value]','')+'</th>');
	}
}

let rowAppend = ()=>{
	let thCnt = cellInit - $('#dataTable tbody tr:eq(1) td').length;
	$('#dataTable tbody tr').each(function(i, row){
		for(j = 0; j < thCnt; j++){
			$('#dataTable tbody tr:eq('+i+')').append('<td contenteditable="true"></td>');
		}
	})

	$('#displayData table tr:empty').remove();
	
	let tmpCnt = rowInit - $('#dataTable tbody tr').length;
	let maxFieldNum = $('#dataTable thead tr th').length;
	
	for(i = 0; i < tmpCnt; i++){
		tmp = '<tr>';
		for(j = 0; j < maxFieldNum; j++){
			tmp+="<td contenteditable='true'>"+''+"</td>";
		}
		tmp+="</tr>";

		$("#displayData tbody").append(tmp);
	}
}

let apiListHide = ()=>{
	$('#apiList').hide();
}


let modifyMakedData = (idx) =>{
	modifyFlag = true;
	$('#gIdx').val(idx);
	getMakedData(idx);
}

let getMakedData = (idx) =>{
	$.ajax({
		method: "GET",
		url:'/chart/makedview/ajax',
		data:{idx:idx},
		dataType:'json'
	}).done(function(response){
		let result = response.result;
		$("#chartTitle").val(result['title']);
		$("#unitTitle").val(result['unitTitle']);
		$("#source").val(result['source']);
		$("#caption").val(result['caption']);
		$("#makeDate").val(result['insert']['date'].replace(/([0-9]+)[-]([0-9]+)[-]([0-9]+)[ ]([0-9]+)[:]([0-9]+)[:]([0-9]+).*/, `$1$2`));
		$('#field1').val(undefined);
		$('#field2').val(undefined);
		$('#endDate').val(undefined);

		fieldSize = result['axisX'].length+1;
		rowSize = result['axisX'][0].length;
		$("#gIdx").val(result['idx']);
		$("#category").val(result['category']);

		$("#displayData").empty();
		$("#displayData").append('<table id="dataTable" class="table table-striped table-bordered"><thead><tr></tr></thead><tbody></tbody></table>');
		// header 세팅
		var tmp = '';
		for(var i = 0 ; i < fieldSize ; i++  ){
			if(i == 0 ){
				tmp='<th><span onclick="javascript:rowFieldChange()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="기준축 변경"><img src="/img/row_field_change.svg" style="width:22px;height:22px;margin:2px;"></span></th>';
			}else{
				colNum = (j-1) % defColNum;
				tmp+='<th>'+headerObj.replace('[color]',result['color'][(i-1)]).replace('[value]', result['fieldTitle'][(i-1)]) +'</th>';
			}
		}
		$("#displayData thead tr").append(tmp);

		/* Type 설정 */
		let type = result['type'];
		$('select[name=graphType').each((idx, e)=>{
			$(e).val(type[idx]);
		});
		
		for(var i = 0 ; i < rowSize ; i++  ){
			tmp = "<tr>";
			for(var j = 0 ; j < fieldSize ; j++  ){
				if(j == 0 ){
					tmp+='<td contenteditable="true">'+result['axisY'][i]+'</td>';
				}else{
					tmp+='<td contenteditable="true">'+result['axisX'][j-1][i]+'</td>';
				}
			}
			tmp += "</tr>";
			$("#displayData tbody").append(tmp);
		}

		if(type == 'pie' || type == 'doughnut' || type == 'half-pie' || type == 'half-doughnut'){
			let mcolor = result['mcolor'];
			for(let i=1;i<$("#dataTable tr").length;i++){
				let obj = $(eval($(eval($("#dataTable tr")[i])).find("td")))[0];
				$(obj).append('<input type="color" class="form-control-color" id="mcolor[]" name="mcolor[]" value="'+mcolor[(i-1)%defColor.length]+'" title="Choose your color">');
			}
		}

		tableDraw('displayData');

		contentChange = false;

		cellAppend();
		rowAppend();
		colorSetting();
	});
}