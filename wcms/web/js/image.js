$(document).ready(()=>{
	$(document).on('click','#closeBtn',()=>{
		self.close();
	});
	
	$('#imgFront').click(()=>{
		let sel = canvas.getActiveObject();
		canvas.bringToFront(sel);
		canvas.discardActiveObject();
		if(we){
			we.bringForward();
		}
	});
	
	$('#imgBack').click(()=>{
		let sel = canvas.getActiveObject();
		canvas.sendToBack(sel);
		canvas.discardActiveObject();
		if(we){
			we.bringForward();
		}
	});

	$('.imageMenu .menuIcon, .imageMenu .menuText').click((e)=>{
		if($(e.target).parent().hasClass("sel")){
			$(e.target).parent().removeClass("sel");
		}else{
			$(e.target).parent().addClass("sel");
		}

		let n = $(e.target).closest('li').index();
		$('.functions .fun').hide();
		$('.functions .fun:nth-child('+(n+1)+')').show();

		if(clearObject){
			clearObject();
		}

		if($(e.target).closest('li').hasClass("m6")){
			$('input[id="radio5b_1"]').prop('checked',true);
			drawMode.setFreeDrawingMode(true);

			deselectImage();

			addBackgroundElement();

			drawSelected=true;

			canvas.discardActiveObject();
			canvas.renderAll();

			$('.imagePlus').show();
			$('.imageZone').show();
		}else if($(e.target).closest('li').hasClass("m2") || $(e.target).closest('li').hasClass("m4")){
			groupAction();

			removeCanvasEvents();
			drawMode.setFreeDrawingMode(false);
			drawSelected=false;

			$('.imagePlus').show();
			$('.imageZone').show();
		}else if($(e.target).closest('li').hasClass("m7")){
			removeCanvasEvents();
			drawMode.setFreeDrawingMode(false);
			drawSelected=false;
			
			deselectImage();

			addBackgroundElement();

			$('.imagePlus').hide();
			$('.imageZone').hide();
		}else{
			removeCanvasEvents();
			drawMode.setFreeDrawingMode(false);

			canvas.getObjects().forEach(function(e){
				if(e.type){
					if(e.type == "image"){
						e.selectable = true;
					}
				}
			});
			drawSelected=false;

			$('.imagePlus').show();
			$('.imageZone').show();
		}
	})

	$("#mosaicRangebox").slider({
		range: "max",
		min: 1,
		max: 10,
		value: 1,
		slide: function (event, ui) {
			$("#mosaicAmount").val(ui.value);
			if(canvas.getActiveObject()){
				$("#mosaicAmount").val(ui.value);
				doMosaic(false);
			}
		}
	});

	
	fabric.textureSize = 8192;
	let _fabricConfig = {
		crossOrigin:'anonymous'
    };
	canvas = new fabric.Canvas('imgCanvas',_fabricConfig);
	canvas.setBackgroundColor('rgba(255, 255, 255, 1.0)', canvas.renderAll.bind(canvas));
	canvas.setWidth(680);
	canvas.setHeight(600);
	canvas.renderAll();

	reset();

	$(document).on('click','.mosaicZone',(e)=>{
		if($(e.target).closest('.mosaicZone').hasClass('selected')){
			canvas.remove(el);
			canvas.remove(bel);
			canvas.remove(object2);
			canvas.setActiveObject(object);
			$(e.target).closest('.mosaicZone').removeClass('selected');
		}else{
			$('.mosaicZone').removeClass('selected');
			$(e.target).closest('.mosaicZone').addClass('selected');
			$('input[name="mosaicr"]').prop('checked',false);
			$(e.target).closest('.mosaicZone').find('input[name="mosaicr"]').prop('checked',true);
			clearObject();
			setRect('Mosaic');
		}
	});
	

	$(document).on('click','.rataion1',()=>{
		if(canvas.getActiveObject()) {
			let sel = canvas.getActiveObject();
			sel.flipY=!sel.flipY;
			canvas.renderAll();
			canvasChange = true; //이미지 변경
		}else{
			object.flipY=!object.flipY;
			canvas.renderAll();
			//alert('이미지를 선택해주세요.');
		}
	});

	$(document).on('click','.rataion2',()=>{
		if(canvas.getActiveObject()) {
			let sel = canvas.getActiveObject();
			sel.flipX=!sel.flipX;
			canvas.renderAll();
			canvasChange = true; //이미지 변경
		}else{
			object.flipX=!object.flipX;
			canvas.renderAll();
			 
			//alert('이미지를 선택해주세요.');
		}
	});

	
	$(document).on('click','.rataion3',()=>{
			object.rotate(object.angle+90);
			canvas.renderAll();
			canvasChange = true; //이미지 변경
	});
	$(document).on('click','.rataion4',()=>{
			object.rotate(object.angle-90);
			canvas.renderAll();
			canvasChange = true; //이미지 변경
	});

	
	$(document).on('click','.cropBox label',(e)=>{
		if($(e.target).hasClass('selected')){
			canvas.remove(el);
			canvas.remove(bel);
			canvas.remove(object2);
			canvas.setActiveObject(object);
			$(e.target).removeClass('selected');
		}else{
			$('.cropBox label').removeClass('selected');
			$(e.target).closest('span').find('label').addClass('selected');
			$('input[name="cropr"]').prop('checked',false);
			$(e.target).parent().find('input[name="cropr"]').prop('checked',true);
			clearObject();
			setRect('Crop',$(e.target).parent().find('input[name="cropr"]').val());
		}
	});

	$("#canvasX, #canvasY").on('keydown',(e)=>{
		if (e.keyCode == 13) {
			canvasResize($(e.target).attr('id'));
		}
	});

	$("#imageX").on('keydown',(e)=>{
		if (e.keyCode == 13) {
			const sel = canvas.getActiveObject();
			if(sel && sel.type){
				if(sel.type=="image"){
					imageResizeX();

					scalingCanvas(sel);
				}
			}
		}
	});

	$("#imageX").on('focusout',(e)=>{
		const sel = canvas.getActiveObject();
		if(sel && sel.type){
			if(sel.type=="image"){
				imageResizeX();

				scalingCanvas(sel);
			}
		}
	});

	$("#imageY").on('keydown',(e)=>{
		if (e.keyCode == 13) {
			const sel = canvas.getActiveObject();
			if(sel && sel.type){
				if(sel.type=="image"){
					imageResizeY();

					scalingCanvas(sel);
				}
			}
		}
	});

	$("#imageY").on('focusout',(e)=>{
		const sel = canvas.getActiveObject();
		if(sel && sel.type){
			if(sel.type=="image"){
				imageResizeY();

				scalingCanvas(sel);
			}
		}
	});

	
	// 가로 맞춤
	$(document).on('click','#halign',()=>{
		const sel = canvas.getActiveObject();
		if(sel.type=="image"){
			horizontalAlign(sel);
			//$('#radio1_1').prop('checked',false);
		}
	});
	
	// 세로 맞춤
	$(document).on('click','#valign',()=>{
		const sel = canvas.getActiveObject();
		if(sel.type=="image"){
			verticalAlign(sel);
			//$('#radio1_2').prop('checked',false);
		}
	});

	// 원본 크기
	$(document).on('click','#oalign',()=>{
		const sel = canvas.getActiveObject();
		if(sel.type=="image"){
			sel.scale(1);
			sel.left=0;
			sel.top=0;
			canvas.renderAll();

			//$('#radio1_3').prop('checked',false);
		}
	});

	$(document).on('click','#canvasLock',()=>{
		$("#canvasLock").toggleClass("lock unlock");
	});

	$(document).on('click','#imageLock',()=>{
		$("#imageLock").toggleClass("lock unlock");
	});

	document.addEventListener('keydown', keyEvent);

	canvas.on('object:scaling', function(e) {
        const obj = e.target;
		if( obj.type == "image" && getImageCount() == 1){

			scalingCanvas(obj); //obj.width * obj.scaleX, obj.height * obj.scaleY);

		}
    });
});

let imagePlus = (url, w, h, /*option*/title, /*option*/pt,/*option*/pl,/*option*/sx,/*option*/sy, reflag=false, waterflag=false)=>{

	new fabric.Image.fromURL(url, function(i){
		i.scaleX = scaleX;
		i.scaleY = scaleY;
		i.title = title;

		if(pt)i.top=Number(pt);
		if(pl)i.left=Number(pl);

		var sflag=true;
		if(sx){
			if(parseFloat(w.replace("px",""))!=i.width){
				const ratio = parseInt(w) / parseInt(i.width);
				i.scale(ratio);
				sflag=false;
			}else{
				i.scaleX=Number(sx);
			}
		}
		if(sy && sflag){
			i.scaleY=Number(sy);
		}

		canvas.add(i);
		i.setControlsVisibility({'mtr':false});
		object=i;

		if(we){
			we.bringForward();
		}

		var nw = parseFloat(String(w).replace("px",""));
		var nh = parseFloat(String(h).replace("px",""));
		/*if(!reflag){
			const resizex = 710;
			const ratio = parseInt(resizex) / parseInt(i.width);
			i.scale(ratio);
			i.left=0;
			i.top=0;

			$('#canvasXY').val("0");
			$('#canvasX').val(710);
			$('#canvasY').val(Number(i.height*i.scaleY).toFixed(0));
			$('.canvers_b').width(710);
			$('.canvers_b').height(Number(i.height*i.scaleY).toFixed(0)>500?500:Number(i.height*i.scaleY).toFixed(0));
			canvas.setWidth(710);
			canvas.setHeight(Number(i.height*i.scaleY));
			$('.caption').width(710);

			if(nw >= nh){
				$('.canvas-container').attr("style","width:"+(710)+"px;height:"+Number(i.height*i.scaleY).toFixed(0)+"px;max-height:500px;overflow-y:auto;-webkit-overflow-scrolling: touch;position: relative;");
				$('.canvers_b-container').attr("style","width:"+(710)+"px;height:500px;");
			}else{
				$('.canvas-container').attr("style","width:"+(710+17)+"px;height:"+Number(i.height*i.scaleY).toFixed(0)+"px;max-height:500px;overflow-y:auto;-webkit-overflow-scrolling: touch;position: relative;");
				$('.canvers_b-container').attr("style","width:"+(710+17)+"px;height:500px;");
			}
		}*/

		$('#originXY').empty().append(w+' * '+h);
		//$('#imageX').val(Number(object.width*object.scaleX/scaleX).toFixed(0));
		//$('#imageY').val(Number(object.height*object.scaleY/scaleY).toFixed(0));
		$('#imageX').val(Number(object.width*object.scaleX).toFixed(0));
		$('#imageY').val(Number(object.height*object.scaleY).toFixed(0));

		i.on('mousedown', function(){
			object = this;
			selectedObject(object);
		});
		i.on('mousedblclick', function(){
			object = this;
			selectedObject(object);
		});

		initFilters();

		if(waterflag != 0){
			$('#radio6_'+data.logo).trigger('click');
			drawWatermark(true);
		}

		canvas.renderAll();
		historyPush();

		selectedObject(object);
	},null,{ crossOrigin: 'anonymous'});
}

var canvasResize = (flag)=>{
	const x = $('#canvasX').val();
	const y = $('#canvasY').val();

	tempX = canvas.getWidth();
	tempY = canvas.getHeight();

	rateX = x/tempX;
	rateY = y/tempY;
	
	/*if(flag=="canvasX"){
		canvas.setWidth(x);
		canvas.setHeight(y*rateX);
		$('.canvas-container').width(x);
		$('#canvasY').val(Number(y*rateX).toFixed(0));
		
		canvas.getObjects().forEach(function(obj){
			obj.scaleX = obj.scaleX;//*rateX;
			obj.scaleY = obj.scaleY;//*rateX;
		});
	}else if(flag=="canvasY"){
		canvas.setWidth(x*rateY);
		canvas.setHeight(y);
		$('.canvas-container').width(x*rateY);
		$('#canvasX').val(Number(x*rateY).toFixed(0));
		
		canvas.getObjects().forEach(function(obj){
			obj.scaleX = obj.scaleX;//*rateY;
			obj.scaleY = obj.scaleY;//*rateY;
		});
	}*/
	
	if($('#canvasLock').hasClass('lock')){
		tempX = canvas.getWidth();
		tempY = canvas.getHeight();

		rateX = x/tempX;
		rateY = y/tempY;
		
		if(flag=="canvasX"){
			canvas.setWidth(x);
			canvas.setHeight(y*rateX);
			$('.canvas-container').width(x);
			$('#canvasY').val(Number(y*rateX).toFixed(0));

			$('.canvers_b').width(x);
			$('.canvers_b').height(y*rateX>500?500:y*rateX);

			//$('.caption').width(x);
			
			canvas.getObjects().forEach(function(obj){
				obj.scaleX = obj.scaleX;//*rateX;
				obj.scaleY = obj.scaleY;//*rateX;
			});
		}else if(flag=="canvasY"){
			canvas.setWidth(x*rateY);
			canvas.setHeight(y);
			$('.canvas-container').width(x*rateY);
			$('#canvasX').val(Number(x*rateY).toFixed(0));

			$('.canvers_b').width(x*rateY);
			$('.canvers_b').height(y>500?500:y);

			//$('.caption').width(x*rateY);
			
			canvas.getObjects().forEach(function(obj){
				obj.scaleX = obj.scaleX;//*rateY;
				obj.scaleY = obj.scaleY;//*rateY;
			});
		}
	}else{
		if(flag=="canvasX"){
			canvas.setWidth(x);
			$('.canvers_b').width(x);
		}else if(flag=="canvasY"){
			canvas.setHeight(y);
			$('.canvers_b').height(y>600?600:y);
		}
		$('.canvas-container').width(x);
	}

	if(cHistoryArray[cStep].canvasX == $('#canvasX').val() && cHistoryArray[cStep].canvasY == $('#canvasY').val()){
	}else{
		historyPush();
	}
}

let imageResizeX = ()=>{
	let sel = canvas.getActiveObject();
	let w = $('#imageX').val();
	let h = sel.height*sel.scaleY;

	const originX = sel.width;
	const originY = sel.height;
	let rationXY = sel.width/sel.height;

	if($('#imageLock').hasClass('lock')){
		const ratioX = parseInt(w) / parseInt(sel.width);
		const ratioY = (parseFloat(sel.height*sel.scaleY)*w / parseFloat(sel.width*sel.scaleX)) / parseInt(sel.height);
		sel.scaleX = ratioX;
		sel.scaleY = ratioY;
		h = sel.height*ratioY;
	}else{
		const ratioX = parseInt(w) / parseInt(sel.width);
		sel.scaleX = ratioX;
		if($('#imageY').val()){
			var ratioY = parseInt(h) / parseInt(sel.height);
			sel.scaleY = ratioY;
		}
	}
	canvas.renderAll();
	historyPush();

	$('#imageX').val(Number(w).toFixed(0));
	$('#imageY').val(Number(h).toFixed(0));
}

let imageResizeY = ()=>{
	let sel = canvas.getActiveObject();
	let w = sel.width*sel.scaleX;
	let h = $('#imageY').val();

	const originX=sel.width;
	const originY=sel.height;
	let rationXY=sel.width/sel.height;

	if($('#imageLock').hasClass('lock')){
		const ratioY = parseInt(h) / parseInt(sel.height);
		const ratioX = (parseFloat(sel.width*sel.scaleX)*h / parseFloat(sel.height*sel.scaleY)) / parseInt(sel.width);
		w = sel.width*ratioX;
		sel.scaleX = ratioX;
		sel.scaleY = ratioY;
	}else{
		const ratioY = parseInt(h) / parseInt(sel.height);
		sel.scaleY = ratioY;
		if($('#imageX').val()){
			const ratioX = parseInt(w) / parseInt(sel.width);
			sel.scaleX = ratioX;
		}
	}
	canvas.renderAll();
	historyPush();

	$('#imageX').val(Number(w).toFixed(0));
	$('#imageY').val(Number(h).toFixed(0));
}


let horizontalAlign = (sel, width)=>{ //가로 맞춤
	const w = width?width:canvas.width;
	const ratio = parseInt(w) / parseInt(sel.width);
	sel.scale(ratio);
	sel.left=0;
	sel.top=0;
	canvas.renderAll();
	historyPush();
}

let verticalAlign = (sel)=>{ //세로 맞춤
	const h =canvas.height;
	const ratio = parseInt(h) / parseInt(sel.height);
	sel.scale(ratio);
	sel.left=0;
	sel.top=0;
	canvas.renderAll();
	historyPush();
}

let reset = ()=>{
	canvas.setWidth($('#originImg').width());
	canvas.setHeight($('#originImg').height());

	new fabric.Image.fromURL($('#originImg').attr('src'), (i)=>{
		canvas.add(i);
		i.setControlsVisibility({'mtr':false});
		i.selectable = false;
		
		const orgX = $('#originImg').width();
		const orgY = $('#originImg').height();

		object = i;
		object.width = orgX;
		object.height = orgY;

		let ratio = 1;
		if(orgX > 680){
			ratio = parseInt(maxWidth) / parseInt(orgX);
			object.scale(ratio);
		}
		object.left = 0;
		object.top = 0;
		object.selectable = true;
		object.title = $('#id').val();

		canvas.setWidth(orgX*ratio);
		canvas.setHeight(orgY*ratio);

		canvas.renderAll();
		canvas.setActiveObject(object);

		//현재 크기 표시
		$('#originX').val(i.width);
		$('#originY').val(i.height);
		$('#canvasX').val(Number(canvas.width).toFixed(1));
		$('#canvasY').val(Number(canvas.height).toFixed(1));
		$('#imageX').val(Number(i.width*i.scaleX).toFixed(1));
		$('#imageY').val(Number(i.height*i.scaleY).toFixed(1));

		i.on('mousedown', function(){
			//if(!drawSelected){
				object = this;
				selectedObject(object);
			//}
		});
		i.on('mousedblclick', function(){
			//if(!drawSelected){
				object = this;;
				selectedObject(object);
			//}
		});

		selectedObject(object);

		historyPush();
		canvasChange = false;
	});
}

let setRect = (flag,/*option*/ratio)=>{
	bel = new fabric.Rect({
		fill: 'rgba(255,255,255,0.1)',
		opacity: 10,
		width: canvas.width,
		height: canvas.height,
		left: 0,
		top: 0,
		hasControls:false
	});
	
	if(ratio){
		let visibilityOption;
		let width;
		let height;
		switch(ratio){
			case "1":
				visibilityOption = {'mb':false,'ml':false,'mr':false,'mt':false,'mtr':false};
				width = object.width/Math.pow(10,Number(String(object.width).length-2));
				height = object.height/Math.pow(10,Number(String(object.height).length-2));
				if(width<100){
					width=width+100;
					height=height+100;
				}
				break;
			case "2":
				visibilityOption = {'mtr':false};
				width = 100;
				height = 100;
				break;
			case "3":
				visibilityOption = {'mb':false,'ml':false,'mr':false,'mt':false,'mtr':false};
				width = 100;
				height = 75;
				break;
			case "4":
				visibilityOption = {'mb':false,'ml':false,'mr':false,'mt':false,'mtr':false};
				width = 75;
				height = 100;
				break;
			case "5":
				visibilityOption = {'mb':false,'ml':false,'mr':false,'mt':false,'mtr':false};
				width = 96;
				height = 54;
				break;
			case "6":
				width = 100;
				height = 100;
				visibilityOption = {'mb':false,'ml':false,'mr':false,'mt':false,'mtr':false};
				break;
			default:
				width = 100;
				height = 100;
				visibilityOption = {'mtr':false};
		}

		el = new fabric.Rect({
			fill: 'rgba(255,255,255,0.1)',
			originX: 'left',
			originY: 'top',
			opacity: 1,
			width: width,
			height: height,
			//left: object.left,
			//top: object.top,
			left:(canvas.width/2-width),
			top:(canvas.height/2-height),
			borderColor: 'red',
			cornerColor: 'red'
		});
		el.setControlsVisibility(visibilityOption);
		el.cornerSize=10;
	}else{
		el = new fabric.Rect({
			fill: 'rgba(255,255,255,0.1)',
			originX: 'left',
			originY: 'top',
			opacity: 1,
			width: 100,
			height: 100,
			//left: object.left,
			//top: object.top,
			left:(canvas.width/2-100),
			top:(canvas.height/2-100),
			borderColor: 'red',
			cornerColor: 'red'
		});
		el.setControlsVisibility({'mtr':false});
		el.cornerSize=10;
	}

	//object.selectable = false;
	bel.selectable = false;

	el.selectable = true;
	canvas.add(bel);
	canvas.add(el);

	canvas.setActiveObject(el);

	bel.on('mousedown', function(){canvas.setActiveObject(el);});
	bel.on('mousedblclick', function(){canvas.setActiveObject(el);});

	el.on('mousedblclick', function(){
		canvas.remove(bel);
		canvas.remove(el);
		eval('do'+ flag +'()');
	});

	bel.on('mousedown', function(){canvas.setActiveObject(el);});
	bel.on('mousedblclick', function(){canvas.setActiveObject(el);});
	canvas.renderAll();
}


let doMosaic = function(selected){
	canvas.remove(object3);
	object3 = null;

	let sx = object.scaleX;
	let sy = object.scaleY;
	object.clone(function(clone) {

		if (Math.max(clone.width, clone.height) > 2048) {
			let scale = 2048 / Math.max(clone.width, clone.height);
			clone.filters.push(
				new fabric.Image.filters.Resize({ scaleX: scale*clone.scaleX, scaleY: scale*clone.scaleY })
			);
		}
		clone.filters.push(new fabric.Image.filters.Pixelate({blocksize: parseInt($('#mosaicAmount').val())}));
		clone.applyFilters();
		object3 = clone;

		let type = $('input[name="mosaicr"]:checked').val();

		// 모자이크 영역이 이미지 영역 밖에 있을 경우
		if(object.left > el.left){
			el.width = el.width-(object.left-el.left);
			el.left = object.left;
		}
		if(object.top > el.top){
			el.height = el.height -(object.top-el.top);
			el.top = object.top;
		}
		if((object.left+object.width) < (el.left+el.width)){
			el.width = el.width -((el.left+el.width)-(object.left+object.width));
		}
		if((object.top+object.height) < (el.top+el.height)){
			el.height = el.height - ((el.top+el.height)-(object.top+object.height));
		}
		// 모자이크 영역이 이미지 영역 밖에 있을 경우

		object3.cropX = object.cropX+el.left/sx - object.left/sx;
		object3.cropY = object.cropY+el.top/sy - object.top/sy;
		object3.left = el.left;
		object3.top = el.top;
		if(type=='circle'){
			object3.clipPath = new fabric.Circle({
				radius:(el.width/sx*el.scaleX)/2,
				originX : 'center',
				originY : 'center',
				alpha : 0.0
			})
		}
		object3.width = el.width/sx*el.scaleX;
		object3.height = el.height/sy*el.scaleY;
		object3.title = object.title;

		canvas.add(object3);
		canvas.renderAll()

		if(selected!=false){
			imageChange = false;

			transformProc();
		}
	})
}
	
let transformImg;
let globalLeft=0;
let globalTop=0;
async function transformProc(){
	globalLeft=object.left;
	globalTop=object.top;

	await drawConvertImage();
	await canvasImageTransform();
	await historyPush();
}

let drawConvertImage = function(){
	let objs = [object, object3];
	let groupObj = new fabric.Group(objs,{originX:'center',originY:'center',title:object.title});
	$('.transformImg').attr('src',groupObj.toDataURL());
	transformImg = new Image();
	transformImg.crossOrigin = "anonymous";
	transformImg.src = document.querySelectorAll(".transformImg")[0].src;
}

let canvasImageTransform = function(){
	canvas.remove(bel);
	canvas.remove(el);

	fabric.Image.fromURL(document.querySelectorAll(".transformImg")[0].src,function(img){
		img.left = globalLeft;
		img.top = globalTop;
		img.title = object.title;

		canvas.remove(object);
		canvas.remove(object2);
		canvas.remove(object3);
		object =  null;
		object2 = null;
		object3 = null;

		object = img;
		object.selectable=true;
		object.onSelect();
		
		canvas.add(object);

		object.on('mousedown', function(){
			object = this;
			selectedObject(object);
		});

		object.on('mousedblclick', function(){
			object = this;
			selectedObject(object);
		});

		if(we){
			we.bringToFront();
		}
		canvas.renderAll();

		//canvasHistory.saveState(object);
		canvasChange = true; //이미지 변경

		// 모자이크 선택 영역 초기화
		$('.mosaicZone').removeClass('selected');
		$("#mosaicRangebox").slider({value:0});
		$("#mosaicAmount").val(0);
	},null,{ crossOrigin: 'anonymous'});
}

let doCrop = function(){
	var l = object.left;
	var t = object.top;
	var sx = object.scaleX;
	var sy = object.scaleY;

    var left = el.left/sx - object.left/sx;
    var top = el.top/sy - object.top/sy;
    var width = el.width/sx*el.scaleX;
    var height = el.height/sy*el.scaleY;
    
	var type = $('input[name="r4a"]:checked').val();
	object.cropX = object.cropX+left;
	object.cropY = object.cropY+top;
	object.left = l;
	object.top = t;
	if(type=='circle'){
		object.clipPath = new fabric.Circle({
			radius:width/2,
			originX : 'center',
			originY : 'center'
		})
	}
	object.width = width;
	object.height = height;
	object.setCoords();
    object.selectable = true;
	
	clearObject();

	object.on('mousedown', function(e){
		$('#imageX').val(Number(this.width*this.scaleX).toFixed(0));
		$('#imageY').val(Number(this.height*this.scaleY).toFixed(0));
	});
	object.on('mousedblclick', function(e){
		$('#imageX').val(Number(this.width*this.scaleX).toFixed(0));
		$('#imageY').val(Number(this.height*this.scaleY).toFixed(0));
	});

	//object.scaleX = sx;
	//object.scaleY = sy;
	imageChange = false;
	//canvas.setActiveObject(object);
	selectedObject(object);
    canvas.renderAll();

	canvasChange = true; //이미지 변경
	$('.cropBox label').removeClass('selected');

	scalingCanvas(object);
}

var completeAction = function(flag){
	canvas.remove(bel);
	canvas.remove(el);
	eval('do'+ flag +'()');
}

let clearObject = ()=>{
	if(object){
		object.selectable = true;
		canvas.setActiveObject(object);
	}

	if(el){
		canvas.remove(el);
		el = null;
	}
	if(bel){
		canvas.remove(bel);
		bel=null;
	}
	if(object2){
		canvas.remove(object2);
		object2=null;
	}
	if(object3){
		canvas.remove(object3);
		object3=null;
	}
	if(we){
		we.bringForward();
	}
	canvas.renderAll();
}

/**
 * Image Object Selected
 * @param  Object obj
 */
let selectedObject = (obj)=>{
	canvas.setActiveObject(obj);
	canvas.renderAll();

	$('#originX').val(Number(obj.width).toFixed(0));
	$('#originY').val(Number(obj.height).toFixed(0));
	$('#imageX').val(Number(obj.width*obj.scaleX).toFixed(0));
	$('#imageY').val(Number(obj.height*obj.scaleY).toFixed(0));

	$('#dropzone img').removeClass('selected');
	$('#dropzone div[fileid="'+obj.title+'"]').find('img').addClass('selected');
}

let deleteObject = (object, title)=>{
	$('#dropzone div[fileid="'+title+'"]').remove();
	canvas.remove(object);
	canvas.renderAll();
}

/**
 * 사용자의 키입력을 감지하여 이벤트를 발생시킨다.
 * @param Event e 
 */
let keyEvent = (e)=>{
	var sel = canvas.getActiveObject();
	if(e.target.localName!="textarea" && e.target.localName!="input"){
		event.preventDefault();
		event.stopPropagation();

		// Arrow Left
		if(e.keyCode == 37){
			sel.left = sel.left-1;
			canvas.renderAll();
		}

		// Arrow Up
		if(e.keyCode == 38){
			sel.top = sel.top-1;
			canvas.renderAll();
		}

		// Arrow Right
		if(e.keyCode == 39){
			sel.left = sel.left+1;
			canvas.renderAll();
		}

		// Arrow Down
		if(e.keyCode == 40){
			sel.top = sel.top+1;
			canvas.renderAll();
		}
		
		// Delete
		if(e.keyCode == 46){
			let title = canvas.getActiveObject().title?canvas.getActiveObject().title:'';
			deleteObject(sel, title);
		}
		
		// Check pressed button is Z - Ctrl+Z.
		if (e.keyCode === 90) {
			//undo()
		}
			
		// Check pressed button is Y - Ctrl+Y.
		if (e.keyCode === 89) {
			//redo()
		}
	}
}


/** modal event **/
$(document).on('click', "#btn_imgModal", ()=>{
    $("#imgSearchModal").modal('show');
    imageSearch($("#frm_imgSearch #category").val(), $("#frm_imgSearch #type").val(),$("#frm_imgSearch #searchItem").val(), $("#frm_imgSearch #keyword").val(), 1, 'imageRow' );
});

$(document).on('click', "#btn_imgSearch", ()=>{
    imageSearch($("#frm_imgSearch #category").val(), $("#frm_imgSearch #type").val(),$("#frm_imgSearch #searchItem").val(), $("#frm_imgSearch #keyword").val(), 1, 'imageRow' );
});

let imageSearch = (category, type, searchItem, keyword, page, listObjId)=>{
    params = "category="+category+"&type="+type+"&searchItem="+searchItem+"&keyword="+keyword+"&noapp=20&returnType=ajax&page="+page;
    $("#"+listObjId).empty();
    $.ajax({
        url: '/image/list/ajax',
        type: 'GET',
        data: params,
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        success: (data)=>{
            $(data.result.items).each( (key, value)=>{
                $("#"+listObjId).append(
                    "<div class='mg-5 search-imgcard' style='position: relative; width:140px;height:100px;display: flex;background-image: url("+value.listPath+");background-color: white;background-repeat: no-repeat;background-position: center;' />"+
                    "<input type='checkbox' class='imgCheckbox' name='fileId[]' value='"+value.id+"'>"+
                    "<input type='hidden' id='src_"+value.id+"' value='"+value.path+"'>"+
                    "<input type='hidden' id='thumbnail_"+value.id+"' value='"+value.listPath+"'>"+
                    "<input type='hidden' id='width_"+value.id+"' value='"+value.width+"'>"+
                    "<input type='hidden' id='height_"+value.id+"' value='"+value.height+"'>"+
                    "<input type='hidden' id='size_"+value.id+"' value='"+value.size+"'>"+
                    "<input type='hidden' id='watermarkPlace_"+value.id+"' value='"+value.watermarkPlace+"'>"+
                    "<input type='hidden' id='type_"+value.id+"' value='"+value.type+"'>"+
                    "<input type='hidden' id='ext_"+value.id+"' value='"+value.ext+"'>"+
                    "</div>"
                );
            });

            var paging ='';
            paging +='<div class="ht-60 d-flex align-items-center justify-content-center">';
            paging +=' <nav aria-label="Page navigation">';
            paging +='    <ul class="pagination pagination-basic mg-b-0">';
            if(data.result.page.prevPage > 1){
            paging +='       <li class="page-item">';
            paging +='         <a class="page-link" href=\'javascript:imageSearch("'+category+'" ,"'+type+'","'+searchItem+'", "'+keyword+'", '+data.result.page.prevPage+',"'+listObjId+'");\' aria-label="Previous">';
            paging +='          <i class="fa fa-angle-left"></i>';
            paging +='        </a>';
            paging +='      </li>';
            }	  
            $(data.result.page.navibar).each(function() {
                paging +='      <li class="page-item '+(page==this.page?'active':'')+'"><a class="page-link" href=\'javascript:imageSearch("'+category+'", "'+type+'", "'+searchItem+'", "'+keyword+'", '+this.page+',"'+listObjId+'");\'>'+this.page+'</a></li>';
            });
            if(data.result.page.nextPage > 1){
            paging +='      <li class="page-item">';
            paging +='        <a class="page-link" href=\'javascript:imageSearch("'+category+'", "'+type+'", "'+searchItem+'", "'+keyword+'", '+data.result.page.nextPage+',"'+listObjId+'");\' aria-label="Next">';
            paging +='          <i class="fa fa-angle-right"></i>';
            paging +='        </a>';
            paging +='      </li>';
            }

            paging +='    </ul>';
            paging +='  </nav>';
            paging +='</div>';

            $("#"+listObjId).parent().find('#paging').empty().append(paging);
        }
    });
}

$(document).on('click','.search-imgcard', (e)=>{
    $(e.target).parent().addClass('bd-1');
});

$(document).on("click", "#btn_imgSelectOk", ()=>{
    $("#imageRow input:checkbox:checked").each((key, item)=>{
        var fileId = $(item).val();
        var filePath = $("#src_"+fileId).val();
        var thumb = $("#thumbnail_"+fileId).val();
        var caption = $("#caption_"+fileId).val();
        var width = $("#width_"+fileId).val();
        var height = $("#height_"+fileId).val();
        var size = $("#size_"+fileId).val();
        var type = $("#type_"+fileId).val();
        var ext = $("#ext_"+fileId).val();
        var watermarkPlace = $("#watermarkPlace_"+fileId).val();
		if(watermarkPlace=="undefined")watermarkPlace="0";

        mockFile = {
            "fileId": fileId,
            "thumb": thumb,
            "caption": caption,
            "width": width,
            "height": height,
            "size": size,
            "filePath": filePath,
            "type": type,
            "ext": ext,
            "watermarkPlace": watermarkPlace,
        };

        imgDropzone.emit('addedfile', mockFile);
        $(mockFile.previewElement).attr("fileId", mockFile['fileId']);
        $(mockFile.previewElement).find('.filePath').val(mockFile['filePath']);
        $(mockFile.previewElement).find('.fileId').val(mockFile['fileId']);
        $(mockFile.previewElement).find('.imgWidth').val(mockFile['width']);
        $(mockFile.previewElement).find('.imgHeight').val(mockFile['height']);
        $(mockFile.previewElement).find('.imgsize').val(mockFile['size']);
        $(mockFile.previewElement).find('.imgCaption').val(mockFile['caption']);
        $(mockFile.previewElement).find('.type').val(mockFile['type']);
        $(mockFile.previewElement).find('.ext').val(mockFile['ext']);
        $(mockFile.previewElement).find('.imgWatermark').val(mockFile['watermarkPlace']);

        imgDropzone.emit('thumbnail', mockFile, mockFile['thumb']);
		
		imagePlus(mockFile['filePath'], mockFile['width'], mockFile['height'], mockFile['fileId']);
		$(".image-message").hide();
    });

	alert('이미지가 선택되었습니다.');
    $("#imgSearchModal").modal('hide');
    $(".image-message").hide();
});
/** modal event **/

// dropzone의 이미지 클릭시
$(document).on('click', '#dropzone img', (e)=>{
	let title = $(e.target).closest('.card').find('.fileId').val();
	canvas.getObjects().forEach((obj)=>{
		if(obj.title == title){
			selectedObject(obj);
			return false;
		}
	})
});

/** 오브젝트 객체를 그룹화한다. **/
async function groupAction(){
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

	//if(tcnt>1){
		await activeObject();
		await groupConvertImage();
		//await doAction();
	//}
}

var activeObject = function(){
	canvas.remove(we);
	canvas.getObjects().forEach(function(i){
		canvas.setActiveObject(i);
	});
	//canvas.add(we);
}

var groupConvertImage = function(){
	var ao = canvas.getActiveObject();
	
	if(ao){
		var activeImage = ao.cloneAsImage();
		new fabric.Image.fromURL(activeImage.canvas.toDataURL(), function(i){
			const title = canvas.getObjects()[0].title;
			canvas.getObjects().forEach(function(tmp){
				canvas.remove(tmp);
			});

			canvas.add(i);
			i.setControlsVisibility({'mtr':false});
			i.title = title;
			canvas.setActiveObject(i);
			object=i;
		});
	}
}
/** 오브젝트 객체를 그룹화한다. **/

/** 이미지를 선택하지 못 하게 한다. **/
let deselectImage = ()=>{
	canvas.getObjects().forEach(function(e){
		if(e.type){
			if(e.type == "image"){
				e.selectable = false;
			}
		}
	});
}
/**
 * 백그라운드 Element를 추가한다.
 */
let addBackgroundElement = ()=>{
	bel = new fabric.Rect({
		fill: 'rgba(255,255,255,0.1)',
		opacity: 1,
		width: canvas.width,
		height: canvas.height,
		left: 0,
		top: 0,
		hasControls:false
	});

	bel.selectable = false;
	bel.bringToFront();
	canvas.add(bel);
	canvas.renderAll();
}

let tempId = ()=>{
	return String(new Date().getDate())+String(new Date().getHours())+String(new Date().getMinutes())+String(new Date().getMilliseconds());
}

let getImageCount = ()=>{
	let imageCnt = 0;
	canvas.getObjects().forEach(function(e){
		if(e.type=="image"){
			if(we){
				if( we.getSrc()!=e.getSrc() ){
					imageCnt=imageCnt+1;
				}
			}else{
				imageCnt=imageCnt+1;
			}
		}
	});

	return imageCnt;
}

async function scalingCanvas(obj){
	await resizeCanvas(obj.width * obj.scaleX, obj.height * obj.scaleY);
	await transformImage(obj);
}

let resizeCanvas = (width, height)=>{
	canvas.setWidth(width);
    canvas.setHeight(height);
    canvas.renderAll();
}

let transformImage = (obj)=>{
	obj.left = 0;
	obj.top = 0;

	canvas.renderAll();
}