var cHistoryArray = new Array();
var cStep = -1;
$(function(){
	cHistoryArray.push("-1");
});
	
var historyPush = function(){
    cStep++;
    if (cStep < cHistoryArray.length) { cHistoryArray.length = cStep; }

	var tempData = new Object();
	tempData.width = canvas.width;
	tempData.height = canvas.height;

	var tempObject = new Array();
	canvas.getObjects().forEach(function(e){
		const obj = new Object();
		obj.data = e.type=="group"?e:fabric.util.object.clone(e);
		obj.filters = e.filters;
		obj.width = e.width;
		obj.height = e.height;
		obj.scaclX = e.scaleX;
		obj.scaleY = e.scaleY;
		obj.cropX = e.cropX;
		obj.cropY = e.cropY;
		tempObject.push(obj);
	});
	tempData.data = tempObject;
	tempData.list = $('#dropzone').html();
	tempData.caption = $('#caption').val();
	tempData.canvasX = $('#canvasX').val();
	tempData.canvasY = $('#canvasY').val();
	tempData.canvasXY = $('#canvasXY option:selected').text();
	tempData.originXY = $('#originXY').html();

	tempData.isDrawingMode = canvas.isDrawingMode;
	tempData.bel = fabric.util.object.clone(bel);

    cHistoryArray.push(tempData);
	canvasChange = true;
}

function undo() {
    if (cStep > -1) {
		cStep--;
		historyStep1();
		historyStep2();
		historyStep3()
    }
}

var redo = function() {
    if (cStep < cHistoryArray.length-1) {
        cStep++;
		historyStep1();
		historyStep2();
		historyStep3();
    }
}

var historyStep1 = function(){
	canvas.getObjects().forEach(function(e){
		canvas.remove(e);
	});
}

var historyStep2 = function(){
	var tempObject = cHistoryArray[cStep];
	if(tempObject){
		tempObject.data.forEach(function(e){
			const obj = e.data;
			if(obj.filters) obj.filters = e.data.filters;
			obj.widht = e.data.widht;
			obj.height = e.data.height
			obj.scaleX = e.data.scaleX;
			obj.scaleY = e.data.scaleY;
			if(obj.cropX) obj.cropX = e.data.cropX;
			if(obj.cropY) obj.cropY = e.data.cropY;

			canvas.add(obj);

			if(obj.type){
				if(obj.type=="image"){
					obj.on('mousedown', function(){
						if(!drawSelected){
							object = this;
							canvas.setActiveObject(object);
							$('#imageX').val(Number(this.width*this.scaleX).toFixed(0));
							$('#imageY').val(Number(this.height*this.scaleY).toFixed(0));
						}
					});
					obj.on('mousedblclick', function(){
						if(!drawSelected){
							object = this;
							canvas.setActiveObject(object);
							$('#imageX').val(Number(this.width*this.scaleX).toFixed(0));
							$('#imageY').val(Number(this.height*this.scaleY).toFixed(0));
						}
					});
				}
			}
		});;
		//drawMode.setRelease();

		canvas.setWidth(tempObject.canvasX);
		canvas.setHeight(tempObject.canvasㅛ);
		
		canvas.isDrawingMode = tempObject.isDrawingMode;

		canvas.renderAll();

		$('#canvasX').val(Number(tempObject.canvasX).toFixed(0));
		$('#canvasY').val(Number(tempObject.canvasY).toFixed(0));
		$('.canvers_b').width(tempObject.width);
		$('.canvers_b').height(tempObject.height>500?500:tempObject.height);
		$('.caption').width(tempObject.width);

		$('#originXY').empty().append(cHistoryArray[cStep].originXY);
	}
}

var historyStep3 = function(){
	if(cHistoryArray[cStep]){
		$('#dropzone').empty().append(cHistoryArray[cStep].list);
		$('#caption').val(cHistoryArray[cStep].caption);
		$("select[id='canvasXY'] option").removeAttr("selected");
		$("select[id='canvasXY'] option:contains('"+cHistoryArray[cStep].canvasXY+"')").prop("selected", "selected");
	}
}