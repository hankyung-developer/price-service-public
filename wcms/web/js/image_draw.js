var fillFlag;
var drawDelete = false;
let flag1=true, flag2=true, flag3=true, triangleFlag=true;

var tempDraw;
$(function(){
	drawMode.init();

	$(document).on('click','input[id="radio5a_1"]' ,function(obj){
		if($('input[id="radio5b_1"]').prop('checked')==true){
			$('#lineType').val('Pencil');
			drawMode.setDrawingMode();
		}
	});

	$(document).on('click','input[id="radio5a_2"]' ,function(obj){
		if($('input[id="radio5b_1"]').prop('checked')==true){
			$('#lineType').val('dashed');
			drawMode.setDrawingMode();
		}
	});

	$(document).on('click','input[name="r5a"]' ,function(obj){
		$('input[id="radio5b_1"]').prop('checked',true);
		$('input[name="r5"]').prop('checked',false);

		removeCanvasEvents();
		canvas.isDrawingMode = true;
		drawMode.setDrawingMode();
	})

	$(document).on('click','#radio5b_1' ,function(obj){
		drawDelete = false;
		if(flag1==true){
			//var line = new Line();
			removeCanvasEvents();
			canvas.isDrawingMode = true;
			drawMode.setDrawingMode();
			flag1 = false;
			$('input[name="r5"]').prop('checked',false);
			$('input[id="radio5a_1"]').trigger('click');

			canvas.on('mouse:up', function(e){
				if(canvas.isDrawingMode == true){
					historyPush();
				}
			});
		}else{
			flag1 = true;
			canvas.isDrawingMode = false;
			drawMode.setRelease();
			$(this).prop('checked',false);
		}
	});

	$(document).on('click','#radio5b_2' ,function(obj){
		drawDelete = false;
		canvas.isDrawingMode = false;
		var points = [100, 100, 210, 100];

		var dottedDash = [0,0];
		if($('input[name="r5a"]:checked').val()=="dotted"){
			dottedDash = [5,5];
		}
		var line = new fabric.Line(points, {
			strokeWidth: parseInt($('#lineWidth option:selected').val(),10),
			stroke: globalColor,
			strokeDashArray: dottedDash,
			fill: globalColor,
			originX: 'center',
			originY: 'center',
			hasBorders: false,
			hasControls: false
		});
		group = new fabric.Group([line]);
		group.setControlsVisibility({
					tr: false,
					tl: false,
					br: false,
					bl: false,
					ml: true,
					mr: true,
					mt: false,
					mb: false,
					mtr: false
				});
		canvas.add(group).setActiveObject(group).bringToFront(group);

		drawFlag=true;

		historyPush();

		//$(this).prop('checked',false);
		//$('input[name="r5"]').prop('checked',false);
	});

	$(document).on('click','#radio5b_3' ,function(obj){
		drawDelete = false;
		canvas.isDrawingMode = false;
		var points = [100, 100, 100, 200];

		var dottedDash = [0,0];
		if($('input[name="r5a"]:checked').val()=="dotted"){
			dottedDash = [5,5];
		}
		var line = new fabric.Line(points, {
			strokeWidth: parseInt($('#lineWidth option:selected').val(),10),
			stroke: globalColor,
			strokeDashArray: dottedDash,
			fill: globalColor,
			originX: 'center',
			originY: 'center',
			hasBorders: false,
			hasControls: false
		});
		group = new fabric.Group([line]);
		group.setControlsVisibility({
                    tr: false,
                    tl: false,
                    br: false,
                    bl: false,
                    ml: false,
                    mr: false,
                    mt: true,
                    mb: true,
                    mtr: false
                });
		canvas.add(group).setActiveObject(group).bringToFront(group);

		drawFlag=true;

		historyPush();
		//$(this).prop('checked',false);
		//$('input[name="r5"]').prop('checked',false);
	});

	
	$(document).on('click','#radio5_1, #radio5_2', function(){
		drawDelete = false;
		$('.shapeType label').removeClass('selected');
		$(this).parent().find('label').toggleClass('selected');
		if(flag2==true){
			drawMode.setRelease();
			removeCanvasEvents();

			fillFlag = $(this).attr('data-fill')
			new Circle();
		
			flag2 = false;
			$('input[name="r5"]').prop('checked', false)
			$(this).prop('checked',true);
			$('input[name="r5b"]').prop('checked',false);
		}else{
			flag2 = true;
			canvas.isDrawingMode = false;
			removeCanvasEvents();
			$(this).prop('checked',false);
		}
	});
	
	$(document).on('click','#radio5_3, #radio5_4', (e)=>{
		drawDelete = false;
		$('.shapeType label').removeClass('selected');
		$(e.target).parent().find('label').toggleClass('selected');
		if(flag3==true){
			drawMode.setRelease();
			removeCanvasEvents();

			fillFlag = $(e.target).attr('data-fill')
			new Rectangle();
		
			flag3 = false;
			$('input[name="r5b"]').prop('checked',false);
		}else{
			flag3 = true;
			canvas.isDrawingMode = false;
			removeCanvasEvents();
			$(e.target).prop('checked',false);
		}
	});
	
	$(document).on('click','#radio5_5, #radio5_6', (e)=>{
		drawDelete = false;
		$('.shapeType label').removeClass('selected');
		$(e.target).parent().find('label').toggleClass('selected');
		if(triangleFlag==true){
			drawMode.setRelease();
			removeCanvasEvents();

			fillFlag = $(e.target).attr('data-fill')
			new Triangle();
		
			triangleFlag = false;
			$('input[name="r5"]').prop('checked', false)
			$(e.target).prop('checked',true);
			$('input[name="r5b"]').prop('checked',false);
		}else{
			triangleFlag = true;
			canvas.isDrawingMode = false;
			removeCanvasEvents();
			$(e.target).prop('checked',false);
		}
	});

	$(document).on('change','#lineWidth' ,()=>{
		drawDelete = false;
		drawMode.setDrawingMode();
	});
	
	// 색상 박스
	// //www.jqueryscript.net/other/Color-Picker-Plugin-jQuery-MiniColors.html
	$('.demo').minicolors({
		control: $('.demo').attr('data-control') || 'hue',
		defaultValue: '#000000', //$(this).attr('data-defaultValue') || '',
		format: $('.demo').attr('data-format') || 'hex',
		keywords: $('.demo').attr('data-keywords') || '',
		inline: $('.demo').attr('data-inline') === 'true',
		letterCase: $('.demo').attr('data-letterCase') || 'lowercase',
		opacity: $('.demo').attr('data-opacity'),
		position: $('.demo').attr('data-position') || 'bottom',
		swatches: $('.demo').attr('data-swatches') ? $('.demo').attr('data-swatches').split('|') : [],
		change: function (hex, opacity) {
			let log;
			try {
				log = hex ? hex : 'transparent';
				globalColor = log;
				$('#currentDrawColor').css('background-color',log);
				if (opacity) log += ', ' + opacity;
			} catch (e) { }

			drawMode.setDrawingMode();
		},
		theme: 'default'
	});
	$('.demo').change();
	$('.current_color').css('background-color',globalColor);
});

var drawMode = {
	freeDrawingMode : 'Pencil',
	color : '#ffffff',
	vLinePatternBrush : null,
	hLinePatternBrush : null,
	squarePatternBrush : null,
	diamondPatternBrush : null,
	texturePatternBrush : null,
	dashedPatternBrush : null,
	drawDelete : false,

	init : function(){
		if (!fabric.PatternBrush) return;

		this.initVLinePatternBrush();
		this.initHLinePatternBrush();
		this.initSquarePatternBrush();
		this.initDiamondPatternBrush();
		this.initDashedPatternBrush();
		this.addCanvasEvents();
	},

	addCanvasEvents : function(){
		canvas.on('mouse:up', function(e){
			if(canvas.isDrawingMode == true){
				historyPush();
			}
		});
	},

	initVLinePatternBrush : function(){
		this.vLinePatternBrush = new fabric.PatternBrush(canvas);
		this.vLinePatternBrush.getPatternSrc = function() {
			var patternCanvas = fabric.document.createElement('canvas');
			patternCanvas.width = patternCanvas.height = 10;
			var ctx = patternCanvas.getContext('2d');
			ctx.strokeStyle = this.color;
			ctx.lineWidth = 5;
			ctx.beginPath();
			ctx.moveTo(0, 5);
			ctx.lineTo(10, 5);
			ctx.closePath();
			ctx.stroke();
			return patternCanvas;
		};
	},
	
	initHLinePatternBrush : function() {
		this.hLinePatternBrush = new fabric.PatternBrush(canvas);
		this.hLinePatternBrush.getPatternSrc = function() {
			var patternCanvas = fabric.document.createElement('canvas');
			patternCanvas.width = patternCanvas.height = 10;
			var ctx = patternCanvas.getContext('2d');
			ctx.strokeStyle = this.color;
			ctx.lineWidth = 5;
			ctx.beginPath();
			ctx.moveTo(5, 0);
			ctx.lineTo(5, 10);
			ctx.closePath();
			ctx.stroke();
			return patternCanvas;
		};
	},
	
	initSquarePatternBrush : function(){
		this.squarePatternBrush = new fabric.PatternBrush(canvas);
		this.squarePatternBrush.getPatternSrc = function() {
			var squareWidth = 10, squareDistance = 2;
			var patternCanvas = fabric.document.createElement('canvas');
			patternCanvas.width = patternCanvas.height = squareWidth + squareDistance;
			var ctx = patternCanvas.getContext('2d');
			ctx.fillStyle = this.color;
			ctx.fillRect(0, 0, squareWidth, squareWidth);
			return patternCanvas;
		};
	},
	
	initDiamondPatternBrush : function(){
		this.diamondPatternBrush = new fabric.PatternBrush(canvas);
		this.diamondPatternBrush.getPatternSrc = function() {
			var squareWidth = 10, squareDistance = 5;
			var patternCanvas = fabric.document.createElement('canvas');
			var rect = new fabric.Rect({
				width: squareWidth,
				height: squareWidth,
				angle: 45,
				fill: this.color
			});
				
			var canvasWidth = rect.getBoundingRect().width;
			patternCanvas.width = patternCanvas.height = canvasWidth + squareDistance;
			rect.set({ left: canvasWidth / 2, top: canvasWidth / 2 });
			
			var ctx = patternCanvas.getContext('2d');
			rect.render(ctx);
			
			return patternCanvas;
		};
	},
	
	initDashedPatternBrush : function(){
		this.dashedPatternBrush = new fabric.PatternBrush(canvas);
		this.dashedPatternBrush.getPatternSrc = function() {
			var patternCanvas = fabric.document.createElement('canvas');
			patternCanvas.width = patternCanvas.height = 10;
			var ctx = patternCanvas.getContext('2d');
			ctx.strokeStyle = this.color;
			ctx.lineWidth = 5;
			ctx.beginPath();
			ctx.setLineDash([10, 10]);
			//ctx.moveTo(0, 5);
			//ctx.lineTo(10, 5);
			ctx.moveTo(5, 0);
			ctx.lineTo(5, 10);
			ctx.closePath();
			ctx.stroke();
			
			return patternCanvas;
		};
	},

	setFreeDrawingMode : function(value){
		canvas.isDrawingMode = !!value;
		this.setDrawingMode();
	},

	setDrawingMode : function() {
		let type =  $('#lineType').val();
		this.freeDrawingMode = type;

		if(type === 'hline'){
			canvas.freeDrawingBrush = this.hLinePatternBrush;
		}
		else if(type === 'vline'){
			canvas.freeDrawingBrush = this.vLinePatternBrush;
		}
		else if(type === 'square'){
			canvas.freeDrawingBrush = this.squarePatternBrush;
		}
		else if(type === 'diamond'){
			canvas.freeDrawingBrush = this.diamondPatternBrush;
		}
		else if(type === 'texture'){
			canvas.freeDrawingBrush = this.texturePatternBrush;
		}
		else if(type === 'dashed'){
			canvas.freeDrawingBrush = this.dashedPatternBrush;
		}
		else{
			canvas.freeDrawingBrush = new fabric[type + 'Brush'](canvas);
		}
		
		if(canvas.freeDrawingBrush){
			var brush = canvas.freeDrawingBrush;
			brush.color = globalColor;
			if (brush.getPatternSrc) {
				brush.source = brush.getPatternSrc.call(brush);
			}
			brush.width = parseInt($('#lineWidth option:selected').val(),10) || 1;
			brush.shadow = new fabric.Shadow({
				blur: 0,
				offsetX: 0,
				offsetY: 0,
				affectStroke: true,
				color: globalColor,
			});
		}
	},
	
	setDrawingDelete : function(){
		if(this.drawDelete == true){
			var sel = canvas.getActiveObject();
			if(sel.title){
				alert("지울 그리기를 선택해주세요");
			}else{
				canvas.remove(canvas.getActiveObject());
				drawMode.setFreeDrawingMode(this.drawDelete);
				$('#drawingDelete').css({'border':'2px solid #ffffff'});
			}
		}else if(this.drawDelete == false){
			drawMode.setFreeDrawingMode(this.drawDelete);
			$('#drawingDelete').css({'border':'2px solid #4e4e4e'});
		}

		this.drawDelete = !this.drawDelete;
	},

	setRelease(){
		canvas.isDrawingMode = false;
	}
}

var Rectangle = (function () {
	function Rectangle() {
        var inst=this;
        this.canvas = canvas;
        this.className= 'Rectangle';
        this.isDrawing = false;
        this.bindEvents();
    }
	Rectangle.prototype.bindEvents = function() {
		var inst = this;
		inst.canvas.on('mouse:down', function(o) {
			inst.onMouseDown(o);
		});
		inst.canvas.on('mouse:move', function(o) {
			inst.onMouseMove(o);
		});
		inst.canvas.on('mouse:up', function(o) {
			inst.onMouseUp(o);
		});
		inst.canvas.on('object:moving', function(o) {
			inst.disable();
		})
	}
		
	Rectangle.prototype.onMouseUp = function (o) {
		var inst = this;
		inst.disable();

		$('input[name="r5"]').prop('checked',false);
		removeCanvasEvents();
		historyPush();
		$('.shapeType label').removeClass('selected');
		flag3 = true;
	};

	Rectangle.prototype.onMouseMove = function (o) {
		var inst = this;
		if(!inst.isEnable()){ return; }
		
		var pointer = inst.canvas.getPointer(o.e);
		var activeObj = inst.canvas.getActiveObject();
		activeObj.stroke= globalColor;
		if($('input[name="r5a"]:checked').val()=="dotted"){activeObj.strokeDashArray = [5, 5];}
		activeObj.strokeWidth= parseInt($('#lineWidth option:selected').val(),10);
		//activeObj.fill = 'transparent';
		if(fillFlag == 'true'){
			activeObj.fill = globalColor;
		}else{
			activeObj.fill = 'transparent';
		}

		if(origX > pointer.x){
			activeObj.set({ left: Math.abs(pointer.x) });
		}
		if(origY > pointer.y){
			activeObj.set({ top: Math.abs(pointer.y) });
		}
		
		activeObj.set({ width: Math.abs(origX - pointer.x) });
		activeObj.set({ height: Math.abs(origY - pointer.y) });
		
		activeObj.setCoords();
		inst.canvas.renderAll();
	};

	Rectangle.prototype.onMouseDown = function (o) {
		var inst = this;
		inst.enable();
		
		var pointer = inst.canvas.getPointer(o.e);
		origX = pointer.x;
		origY = pointer.y;
		
		//var tempId = String(new Date().getDate())+String(new Date().getHours())+String(new Date().getMinutes())+String(new Date().getMilliseconds());
		var rect = new fabric.Rect({
			left: origX,
			top: origY,
			originX: 'left',
			originY: 'top',
			width: pointer.x-origX,
			height: pointer.y-origY,
			angle: 0,
			transparentCorners: false,
			hasBorders: false,
			hasControls: true,
			title : tempId()
		});

		$('.shapeType label').removeClass('selected');

		inst.canvas.add(rect).setActiveObject(rect);
	};

	Rectangle.prototype.isEnable = function(){
		return this.isDrawing;
	}
	
	Rectangle.prototype.enable = function(){
		this.isDrawing = true;
	}

    Rectangle.prototype.disable = function(){
		this.isDrawing = false;
	}

    return Rectangle;
}());

var Circle = (function() {
	function Circle() {
		this.canvas = canvas;
		this.className = 'Circle';
		this.isDrawing = false;
		this.bindEvents();
	}
	
	Circle.prototype.bindEvents = function() {
		var inst = this;
		inst.canvas.on('mouse:down', function(o) {
			inst.onMouseDown(o);
		});
		inst.canvas.on('mouse:move', function(o) {
			inst.onMouseMove(o);
		});
		inst.canvas.on('mouse:up', function(o) {
			inst.onMouseUp(o);
		});
		inst.canvas.on('object:moving', function(o) {
			inst.disable();
		})
	}
	
	Circle.prototype.onMouseUp = function(o) {
		var inst = this;
		inst.disable();
		
		$('input[name="r5"]').prop('checked',false);
		removeCanvasEvents();
		historyPush();
		$('.shapeType label').removeClass('selected');
		flag2 = true;
	};
	
	Circle.prototype.onMouseMove = function(o) {
		var inst = this;
		if (!inst.isEnable()){return;}
		
		var pointer = inst.canvas.getPointer(o.e);
		var activeObj = inst.canvas.getActiveObject();
		
		activeObj.stroke= globalColor;
		if($('input[name="r5a"]:checked').val()=="dotted"){activeObj.strokeDashArray = [5, 5];}
		activeObj.strokeWidth= parseInt($('#lineWidth option:selected').val(),10);
		if(fillFlag == 'true'){
			activeObj.fill = globalColor;
		}else{
			activeObj.fill = 'transparent';
		}


		if (origX > pointer.x) {
			activeObj.set({left: Math.abs(pointer.x)});
		}
		if (origY > pointer.y) {
			activeObj.set({top: Math.abs(pointer.y)});
		}
		
		activeObj.set({rx: Math.abs(origX - pointer.x) / 2});
		activeObj.set({ry: Math.abs(origY - pointer.y) / 2});
		activeObj.setCoords();
		inst.canvas.renderAll();
	};
	
	Circle.prototype.onMouseDown = function(o) {
		var inst = this;
		inst.enable();
		
		var pointer = inst.canvas.getPointer(o.e);
		origX = pointer.x;
		origY = pointer.y;
	
		var ellipse = new fabric.Ellipse({
			top: origY,
			left: origX,
			rx: 0,
			ry: 0,
			transparentCorners: false,
			hasBorders: false,
			hasControls: false,
			fillRule : 'nonzero',
			title : tempId()
		});
			
		inst.canvas.add(ellipse).setActiveObject(ellipse);
	};
	
	Circle.prototype.isEnable = function() {
		return this.isDrawing;
	}
	
	Circle.prototype.enable = function() {
		this.isDrawing = true;
	}
	
	Circle.prototype.disable = function() {
		this.isDrawing = false;
		var inst = this;
	}
	
	return Circle;
}());

let Triangle = (function() {
	function Triangle() {
		this.canvas = canvas;
		this.className = 'Triangle';
		this.isDrawing = false;
		this.bindEvents();
	}
	
	Triangle.prototype.bindEvents = function() {
		var inst = this;
		inst.canvas.on('mouse:down', function(o) {
			inst.onMouseDown(o);
		});
		inst.canvas.on('mouse:move', function(o) {
			inst.onMouseMove(o);
		});
		inst.canvas.on('mouse:up', function(o) {
			inst.onMouseUp(o);
		});
		inst.canvas.on('object:moving', function(o) {
			inst.disable();
		})
	}
	
	Triangle.prototype.onMouseUp = function(o) {
		var inst = this;
		inst.disable();
		
		$('input[name="r5"]').prop('checked',false);
		removeCanvasEvents();
		historyPush();
		$('.shapeType label').removeClass('selected');
		triangleFlag = false;
	};
	
	Triangle.prototype.onMouseMove = function(o) {
		var inst = this;
		if (!inst.isEnable()){return;}
		
		var pointer = inst.canvas.getPointer(o.e);
		var activeObj = inst.canvas.getActiveObject();
		
		activeObj.stroke= globalColor;
		if($('input[name="r5a"]:checked').val()=="dotted"){activeObj.strokeDashArray = [5, 5];}
		activeObj.strokeWidth= parseInt($('#lineWidth option:selected').val(),10);
		if(fillFlag == 'true'){
			activeObj.fill = globalColor;
		}else{
			activeObj.fill = 'transparent';
		}

		if (origX > pointer.x) {
			activeObj.set({left: Math.abs(pointer.x)});
		}
		if (origY > pointer.y) {
			activeObj.set({top: Math.abs(pointer.y)});
		}
		
		activeObj.set({ width: Math.abs(origX - pointer.x) });
		activeObj.set({ height: Math.abs(origY - pointer.y) });
		activeObj.setCoords();
		inst.canvas.renderAll();
	};
	
	Triangle.prototype.onMouseDown = function(o) {
		var inst = this;
		inst.enable();
		
		var pointer = inst.canvas.getPointer(o.e);
		origX = pointer.x;
		origY = pointer.y;
		
		var ellipse = new fabric.Triangle({
			top: origY,
			left: origX,
			width: pointer.x-origX,
			height: pointer.y-origY,
			transparentCorners: false,
			hasBorders: false,
			hasControls: true,
			fillRule : 'nonzero',
			title : tempId()
		});
			
		inst.canvas.add(ellipse).setActiveObject(ellipse);
	};
	
	Triangle.prototype.isEnable = function() {
		return this.isDrawing;
	}
	
	Triangle.prototype.enable = function() {
		this.isDrawing = true;
	}
	
	Triangle.prototype.disable = function() {
		this.isDrawing = false;
		var inst = this;
	}
	
	return Triangle;
}());

var Line = (function() {
	function Line() {
		this.canvas = canvas;
		this.isDrawing = false;
		this.bindEvents();
	}
	
	Line.prototype.bindEvents = function() {
		var inst = this;
		inst.canvas.on('mouse:down', function(o) {
			inst.onMouseDown(o);
		});
		inst.canvas.on('mouse:move', function(o) {
			inst.onMouseMove(o);
		});
		inst.canvas.on('mouse:up', function(o) {
			inst.onMouseUp(o);
		});
		inst.canvas.on('object:moving', function(o) {
			inst.disable();
		})
	}
		
	Line.prototype.onMouseUp = function(o) {
		var inst = this;
		if (inst.isEnable()) {
			inst.disable();
			historyPush();
		}
	};

	Line.prototype.onMouseMove = function(o) {
		var inst = this;
		if (!inst.isEnable()) {
			return;
		}
		
		var pointer = inst.canvas.getPointer(o.e);
		var activeObj = inst.canvas.getActiveObject();
		
		activeObj.set({
			x2: pointer.x,
			y2: pointer.y
		});
		activeObj.setCoords();
		inst.canvas.renderAll();
	};
	
	Line.prototype.onMouseDown = function(o) {
		var inst = this;
		inst.enable();
		
		var pointer = inst.canvas.getPointer(o.e);
		origX = pointer.x;
		origY = pointer.y;
		
		var points = [pointer.x, pointer.y, pointer.x, pointer.y];
		var line = new fabric.Line(points, {
			strokeWidth: parseInt($('#lineWidth option:selected').val(),10),
			stroke: globalColor,
			strokeDashArray: [5, 5],  //dotted
			fill: globalColor,
			originX: 'center',
			originY: 'center',
			hasBorders: false,
			hasControls: false
		});
		inst.canvas.add(line).setActiveObject(line);
	};
	
	Line.prototype.isEnable = function() {
		return this.isDrawing;
	}
	
	Line.prototype.enable = function() {
		this.isDrawing = true;
	}
	
	Line.prototype.disable = function() {
		this.isDrawing = false;
	}
	
	return Line;
}());

let removeCanvasEvents = ()=>{
	canvas.off('mouse:down');
	canvas.off('mouse:move');
	canvas.off('mouse:up');
	canvas.off('object:moving');
}

var removeObject = function(){
	/*if(!drawDelete){
		drawMode.setRelease();
		removeCanvasEvents();
		$('input[name="r5"]').prop('checked',false);
		$('input[name="r5b"]').prop('checked',false);
		drawDelete = true;
	}else{*/
		//drawDelete = false;
	//}

	var flag1=false;
	var flag2=false;
	$('input[name="r5b"]').each(function(){
		if(!flag1){
			flag1 = $(this).is(':checked');
		}
	});
	$('input[name="r5"]').each(function(){
		if(!flag2){
			flag2 = $(this).is(':checked');
		}
	});

	if(flag1 || flag2){
		$('input[name="r5b"]').each(function(){
			$(this).prop('checked',false);
		});
		$('input[name="r5"]').each(function(){
			$(this).prop('checked',false);
		});
		removeCanvasEvents();
		canvas.isDrawingMode = false;
	}else{
		if(canvas.getActiveObject()){
			if(canvas.getActiveObjects().length==1){
				if(canvas.getActiveObject().type!="image"){
					canvas.remove(canvas.getActiveObject());
				}
			}else{
				canvas.getActiveObject().forEachObject(function(el){
					if(el.type!="image"){
						canvas.remove(el);
					}
				});
			}
		}

		historyPush();
	}
}