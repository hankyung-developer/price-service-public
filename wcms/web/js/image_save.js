var frmData = new Array();
var fileData = new Array();
var jsonData = new Object();
async function save(){
	if(confirm("저장하시겠습니까?")){
		tcnt=0;
		canvas.getObjects().forEach(function(e){
			if(e.type=="image"){
				if(we){
					if( we.getSrc()!=e.getSrc() ){
						tcnt=tcnt+1;
					}
				}else{
					tcnt=tcnt+1;
				}
			}
		});

		if(tcnt==0){
			alert('이미지를 등록해주세요.');
		}else{

			if(object2 || object3){
				if( confirm("완료하지 작업이 있습니다. \n완료하지 않은 작업을 취소하고 등록하시겠습니까?")){
					clearObject();
					if($("#canvasXY").val()=="0"){ //사용자정의
						await getData();
						await sendData();
					}else{ //자동
						await drawObjects();
						await getData();
						await sendData();
					}
				}
			}else{
				clearObject();
				if($("#canvasXY").val()=="0"){ //사용자정의
					await getData();
					await sendData();
				}else{ //자동
					await drawObjects();
					await getData();
					await sendData();
				}
			}
		}
	}
}

var convertCanvas;
var drawObjects = function(){
	var canvasWidth = canvas.width;
	var canvasHeight = canvas.height;
	var position = $('input[name="r6"]:checked').val();

	convertCanvas = new Array(cnt);
	for(var i=0;i<cnt;i++){
		const iw = imgInfos[i].width;
		const ih = imgInfos[i].height;
		
		convertCanvas[i] = new fabric.Canvas('pImage'+i,{crossOrigin:'anonymous'});
		convertCanvas[i].setBackgroundColor('rgba(255, 255, 255, 1.0)', convertCanvas[i].renderAll.bind(convertCanvas[i]));
	
		canvas.getObjects().forEach(function(e){
			if(we){
				//if(e.type=="image"){
					if(we.getSrc()==e.getSrc()){
						var imgSrc = imgInfos[i]["logo"]["src"];
						if(imgSrc){		
							let imgEle2 = document.querySelectorAll(".wimage")[i];
							imgEle2.crossorigin = "anonymous";
							switch(position){
								case "w1":
									wx=10;
									wy=10;
									break;
								case "w2":
									wx=iw*1/2-imgEle2.width/2;
									wy=10;
									break;
								case "w3":
									wx=iw-10-imgEle2.width;
									wy=10;
									break;
								case "w4":
									wx=10;
									wy=ih*1/3+10;
									break;
								case "w5":
									wx=iw*1/2-imgEle2.width/2;
									wy=ih*1/3+10;
									break;
								case "w6":
									wx=iw-10-imgEle2.width;
									wy=ih*1/3+10;
									break;
								case "w7":
									wx=10;
									wy=ih-10-imgEle2.height;
									break;
								case "w8":
									wx=iw*1/2-imgEle2.width/2;
									wy=ih-10-imgEle2.height;
									break;
								case "w9":
									wx=iw-10-imgEle2.width;
									wy=ih-10-imgEle2.height;
									break;
								default:
							}
							
							object2 = fabric.util.object.clone(e);
							object2.src = imgSrc;
							object2.left = wx;
							object2.top = wy;
							object2.bringForward();

							convertCanvas[i].add(object2);
						}
					//}
				}else{
					object2 = fabric.util.object.clone(e);
					object2.scaleX = (iw*e.scaleX)/parseInt(canvasWidth);
					object2.scaleY = (ih*e.scaleY)/parseInt(canvasHeight);
					object2.left = (iw*e.left)/parseInt(canvasWidth);
					object2.top = (ih*e.top)/parseInt(canvasHeight);
					convertCanvas[i].add(object2);
				}
			}else{
				object2 = fabric.util.object.clone(e);
				object2.scaleX = (iw*e.scaleX)/parseInt(canvasWidth);
				object2.scaleY = (ih*e.scaleY)/parseInt(canvasHeight);
				object2.left = (iw*e.left)/parseInt(canvasWidth);
				object2.top = (ih*e.top)/parseInt(canvasHeight);
				convertCanvas[i].add(object2);
			}
		});		
	}
}

var getData = function(){
	jsonData = {};
	jsonData["from"] = "imageeditor";
	jsonData["to"] = "news";

	var sourceInfo = new Object();
	var imageInfo = new Array();

	var i=0;

	$('#dropzone li').each(function(){
		var tempObject = new Object();


		console.log($(this).find('.tempId').val());
		var tempFlag = false;
		canvas.getObjects().forEach(function(e){
			if(e.title==$(this).find('.tempId').val()){
				tempObject = {
					"url":$(this).find('.mediaPath').val(),
					"type": "image",
					"size": {
						"width": e.width+"px",
						"height": e.height+"px"
					},
					"position": {
						"top": e.top,
						"left": e.left
					},
					"scale": {
						"x": e.scaleX,
						"y": e.scaleY
					},
					"crop": {
						"x": e.cropX,
						"y": e.cropY
					}
				}
				imageInfo.push(tempObject);
				tempFlag=true;
			}
		});

		if(!tempFlag){
			tempObject = {
				"url":$(this).find('.mediaPath').val(),
				"type": e.type,
				"size": {
					"width": "",
					"height": ""
				},
				"position": {
					"top": "",
					"left": ""
				},
				"scale": {
					"x": "",
					"y": ""
				},
				"crop": {
					"x": "",
					"y": ""
				}
			}
			imageInfo.push(tempObject);
		}
	});

	sourceInfo["images"] = imageInfo;
	jsonData["source"] = sourceInfo;
	jsonData["source"]["enlargement"] = $('.top_remote .btn4').hasClass('sel');
	jsonData["source"]["logo"] = Number(wp?wp.replace('w',''):"0");
	jsonData["source"]["caption"] = $('#caption').val();
	jsonData["source"]['canvasX'] = canvas.width;
	jsonData["source"]['canvasY'] = canvas.height;


	
	if($("#canvasXY").val()=="0"){
		jsonData["source"]['canvasTitle'] = "사용자정의";
		jsonData["source"]['canvasId'] = "0";

		const imgData =  canvas.toDataURL({format: 'jpeg'});

		fileData.push({
			"type": 0,
			"data": imgData
		});
	}else{
		for(var i=0;i<config.length;i++){
			if(config[i]["title"]==$("#canvasXY option:selected").text()){
				jsonData["source"]['canvasTitle'] = config[i]["title"];
				jsonData["source"]['canvasId'] = config[i]["id"];
			}
		}

		for(var i=0;i<cnt;i++){
			/*dataURI=document.querySelector('#pImage'+i).toDataURL({format: 'jpeg'},0.70);
			var byteString = atob(dataURI.split(',')[1]);
			var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
			var ab = new ArrayBuffer(byteString.length);
			var ia = new Uint8Array(ab);
			for (var i = 0; i < byteString.length; i++) {
				ia[i] = byteString.charCodeAt(i);
			}&/
			/*
			var tmpThumbFile = new Blob([ab], {type: mimeString});
			console.log(tmpThumbFile);
			fileData.push({
				"type": parseInt(frmData['type'][i]),
				"data": tmpThumbFile
			});*/

			imgData = convertCanvas[i].toDataURL({format: 'jpeg'});
			
			fileData.push({
				"type": parseInt(imgInfos[i].type),
				"data": imgData
			});
		}
	}
}

var resizeFiledata = function(){
	for(var i=0;i < fileData.lenth; i++){
		fileData[i]['data'] = resizeImageData(fileData[i]['data']);
		console.log(fileData[i]['data']);
	}
}

var resizeImageData = function(dataURI){
	var byteString = atob(dataURI.split(',')[1]);
	var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
	var ab = new ArrayBuffer(byteString.length);
	var ia = new Uint8Array(ab);
	for (var i = 0; i < byteString.length; i++) {
		ia[i] = byteString.charCodeAt(i);
	}
	
	//리사이징된 file 객체
	var tmpThumbFile = new Blob([ab], {type: mimeString});
	console.log(tmpThumbFile);
	blobToDataURL(tmpThumbFile,function(dataurl){
		console.log(dataurl);
		return dataurl;
	});
}

function blobToDataURL(blob, callback) {
    var a = new FileReader();
    a.onload = function(e) {callback(e.target.result);}
    a.readAsDataURL(blob);
}


var sendData = function(){
	$.ajax({
        "type":"POST",
        "data": JSON.stringify(fileData),
		"dataType": "json",
		"contentType": "application/json; charset=utf-8",
		"url": uploadApiUrl,
		"beforeSend" : function(){
			$('.ldio').show();
		},
        "success":function(data){
			var resultInfo = new Object();
			var imageInfo = new Array();
			data.forEach(function(e){
				var tempInfo = new Object();
				tempInfo = {
					"type": Number(e.type),
					"url": e.image.fileUrl,
					"mimeType": e.image.mimeType,
					"fileSize": e.image.fileSize,
					"width": e.image.width,
					"height": e.image.height
					};
				imageInfo.push(tempInfo);

			});
			resultInfo["images"] = data;
			resultInfo["caption"] = $('#caption').val();
			jsonData["result"] = resultInfo;
			console.log(jsonData);
			window.parent.postMessage(jsonData,'*');
		},
		error: function(xhr){console.log(xhr)},
		complete: function(){
			$('.ldio').hide();
		}
	});
}