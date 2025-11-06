var canvas;
var object, object2, bel, el, we;
var imageChange = false;

$(document).ready(function(){
	fabric.textureSize = 8192;
	let _fabricConfig = {
		crossOrigin:'anonymous'
    };
	canvas = new fabric.Canvas('imgCanvas',_fabricConfig);
	canvas.setBackgroundColor('rgba(255, 255, 255, 1.0)', canvas.renderAll.bind(canvas));

	reset();

	$('#mozRunBtn').click(function(){
		if(imageChange){
			canvas.remove(el);
			canvas.remove(bel);
		}else{
			canvasChange=true;
			imageChange=true;
			setRect('Blur');
		}
    });

	
    $('#pixelRunBtn').click(function(){
		if(imageChange){
			canvas.remove(el);
			canvas.remove(bel);
		}else{
			canvasChange=true;
			imageChange=true;
			setRect('Mosaic');
		}
	});
        
    $('#cropBtn').click(function(){
		if(imageChange){
			canvas.remove(el);
			canvas.remove(bel);
		}else{
			canvasChange=true;
			imageChange=true;
			setRect('Crop');
		}
    });

	// 이미지 회전 (+)
	$('#RotatePlusBtn').click(function(){
		let w = object.width;
		let h = object.height;
		object.rotate(object.angle+90);
		if(Math.abs(object.angle)/90 %2 == 1){
			canvas.setWidth(object.height);
			canvas.setHeight(object.width);
			if(Math.abs(object.angle)/90 %4 == 1){
				object.left = object.height;
				object.top = 0;
			}else if(Math.abs(object.angle)/90 %4 == 3){
				object.left = 0;
				object.top = object.width;
			}
		}else{
			object.left = 0;
			object.top = 0;
			canvas.setWidth(object.width);
			canvas.setHeight(object.height);
			if(Math.abs(object.angle)/90 %4 == 2){
				object.left = object.width;
				object.top = object.height;
			}else if(Math.abs(object.angle)/90 %4 == 0){
				object.left = 0;
				object.top = 0;
			}
		}
		canvas.renderAll();
	});

	// 이미지 회전 (-)
    $('#RotateMinusBtn').click(function(){
		let w = object.width;
		let h = object.height;
		object.rotate(object.angle-90);
		if(Math.abs(object.angle)/90 %2 == 1){
			canvas.setWidth(object.height);
			canvas.setHeight(object.width);
			if(Math.abs(object.angle)/90 %4 == 1){
				object.left = 0;
				object.top = object.width;
			}else if(Math.abs(object.angle)/90 %4 == 3){
				object.left = object.height;
				object.top = 0;
			}
		}else{
			object.left = 0;
			object.top = 0;
			canvas.setWidth(object.width);
			canvas.setHeight(object.height);
			if(Math.abs(object.angle)/90 %4 == 2){
				object.left = object.width;
				object.top = object.height;
			}else if(Math.abs(object.angle)/90 %4 == 0){
				object.left = 0;
				object.top = 0;
			}
		}
		canvas.renderAll();
	});
});

var reset = function(){
	canvas.setWidth($('#originImg').width());
	canvas.setHeight($('#originImg').height());

	new fabric.Image.fromURL($('#originImg').attr('src'), function(i){
		canvas.add(i);
		i.setControlsVisibility({'bl':false,'br':false,'mb':false,'ml':false,'mr':false,'mt':false,'bl':false,'tl':false,'tr':false,'mtr':false});
		i.selectable = false;
		
		object = i;
		object.width = $('#originImg').width();
		object.height = $('#originImg').height();
		object.left = 0;
		object.top = 0;
		console.log(object);

		canvas.renderAll();
	})
}

var setRect = function(flag){
	bel = new fabric.Rect({
		fill: 'rgba(255,255,255,0.1)',
		opacity: 1,
		width: canvas.width,
		height: canvas.height,
		left: 0,
		top: 0,
		hasControls:false
	});
	
	el = new fabric.Rect({
		fill: 'rgba(255,255,255,0.1)',
		originX: 'left',
		originY: 'top',
		opacity: 1,
		width: 100,
		height: 100,
		left: object.left,
		top: object.top,
		borderColor: 'red',
		cornerColor: 'red'
	});
	el.setControlsVisibility({'mtr':false});
	el.cornerSize=10;

	object.selectable = false;
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

	canvas.renderAll();
}

var doMosaic = function(selected){
	canvas.remove(object2);
	object2 = null;

	var sx = object.scaleX;
	var sy = object.scaleY;
	object.clone(function(clone) {

		if (Math.max(clone.width, clone.height) > 2048) {
			let scale = 2048 / Math.max(clone.width, clone.height);
			clone.filters.push(
				new fabric.Image.filters.Resize({ scaleX: scale*clone.scaleX, scaleY: scale*clone.scaleY })
			);
		}
		//clone.filters.push(new fabric.Image.filters.Pixelate({blocksize: parseInt($('#mosaicAmount').val())}));
		clone.filters.push(new fabric.Image.filters.Pixelate({blocksize: parseInt(5)}));
		clone.applyFilters();
		object2 = clone;

		var type = $('input[name="r3a"]:checked').val();
		object2.cropX = object.cropX+el.left/sx - object.left/sx;
		object2.cropY = object.cropY+el.top/sy - object.top/sy;
		object2.left = el.left;
		object2.top = el.top;
		if(type=='circle'){
			object2.clipPath = new fabric.Circle({
				radius:(el.width/sx*el.scaleX)/2,
				originX : 'center',
				originY : 'center',
				alpha : 0.0
			})
		}
		object2.width = el.width/sx*el.scaleX;
		object2.height = el.height/sy*el.scaleY;
		object2.title = object.title;

		canvas.add(object2);
		canvas.renderAll()

		if(selected!=false){
			imageChange = false;

			transformProc();
		}
	})
}

var doBlur = function(selected){
	canvas.remove(object2);
	object2 = null;

	var sx = object.scaleX;
	var sy = object.scaleY;
	object.clone(function(clone) {

		if (Math.max(clone.width, clone.height) > 2048) {
			let scale = 2048 / Math.max(clone.width, clone.height);
			clone.filters.push(
				new fabric.Image.filters.Resize({ scaleX: scale*clone.scaleX, scaleY: scale*clone.scaleY })
			);
		}
		//clone.filters.push(new fabric.Image.filters.Blur({blur: parseFloat(parseInt($('#blurAmount').val())/10)}));
		clone.filters.push(new fabric.Image.filters.Blur({blur: parseFloat(parseInt(5)/10)}));
		clone.applyFilters();
		object2 = clone;

		var type = $('input[name="r3a"]:checked').val();
		object2.cropX = object.cropX+el.left/sx - object.left/sx;
		object2.cropY = object.cropY+el.top/sy - object.top/sy;
		object2.left = el.left;
		object2.top = el.top;
		if(type=='circle'){
			object2.clipPath = new fabric.Circle({
				radius:(el.width/sx*el.scaleX)/2,
				originX : 'center',
				originY : 'center',
				alpha : 0.0
			})
		}
		object2.width = el.width/sx*el.scaleX;
		object2.height = el.height/sy*el.scaleY;
		object2.title = object.title;

		canvas.add(object2);
		canvas.renderAll()

		if(selected!=false){
			imageChange = false;

			transformProc();
		}
	});

}

var transformImg;
var globalLeft=0;
var globalTop=0;
async function transformProc(){
	globalLeft=object.left;
	globalTop=object.top;

	await drawConvertImage();
	await canvasImageTransform();
}

var drawConvertImage = function(){
	var objs = [object, object2];
	var groupObj = new fabric.Group(objs,{originX:'center',originY:'center',title:object.title});
	$('.transformImg').attr('src',groupObj.toDataURL());
	transformImg = new Image();
	transformImg.crossOrigin = "anonymous";
	transformImg.src = document.querySelectorAll(".transformImg")[0].src;
}

var canvasImageTransform = function(){
	canvas.remove(bel);
	canvas.remove(el);

	fabric.Image.fromURL(document.querySelectorAll(".transformImg")[0].src,function(img){
		canvas.remove(object);
		canvas.remove(object2);
		object =  null;
		object2 = null;

		object = img;
		object.selectable=false;
		//object.onSelect();
		
		canvas.add(object);

		if(we){
			we.bringToFront();
		}
		canvas.renderAll();

		//canvasHistory.saveState(object); 
	},null,{ crossOrigin: 'anonymous'});
}

var doCrop = function(){
	var l = el.left;
	var t = el.top;
	var sx = object.scaleX;
	var sy = object.scaleY;

    var left = el.left/sx - object.left/sx;
    var top = el.top/sy - object.top/sy;
    var width = el.width/sx*el.scaleX;
    var height = el.height/sy*el.scaleY;
    
	var type = $('input[name="r4a"]:checked').val();
	object.cropX = left;
	object.cropY = top;
	object.left = 0;
	object.top = 0;
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
    object.selectable = false;

	canvas.remove(bel);
	canvas.remove(el);
	//object.scaleX = sx;
	//object.scaleY = sy;
	//canvas.setActiveObject(object);

	canvas.setWidth(width);
	canvas.setHeight(height);

	imageChange = false;
    canvas.renderAll();
	
	canvasHistory.saveState(object);
}

var imageResize = function(/*option*/w){
	var width = parseInt(w?w:$('#resizeX').val());
	var originwidth = parseInt(object.width);
    var ratio = width / originwidth;
	var height = Math.floor(parseInt(object.height)*ratio);

	canvas.setWidth(width);
	canvas.setHeight(height);
	object.scale(ratio);
	

}