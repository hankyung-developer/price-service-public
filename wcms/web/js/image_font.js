let fontSize = [20, 30, 40, 50, 60];
let fontStyle = ['굴림', '굴림체', '돋움', '고딕', '궁서', 'Roboto', "Helvetica Neue", 'Arial', 'sans-serif', 'Lobster', 'Hoefler Text', 'Courier'];

$(function(){
  $('#fontColor').minicolors({
		control: $('#fontColor').attr('data-control') || 'hue',
		defaultValue: '#000000', //$(this).attr('data-defaultValue') || '',
		format: $('#fontColor').attr('data-format') || 'hex',
		keywords: $('#fontColor').attr('data-keywords') || '',
		inline: $('#fontColor').attr('data-inline') === 'true',
		letterCase: $('#fontColor').attr('data-letterCase') || 'lowercase',
		opacity: $('#fontColor').attr('data-opacity'),
		position: $('#fontColor').attr('data-position') || 'bottom',
		swatches: $('#fontColor').attr('data-swatches') ? $('#fontColor').attr('data-swatches').split('|') : [],
		change: function (hex, opacity) {
			let log;
			try {
				log = hex ? hex : 'transparent';

        if(document.activeElement.id == 'fontText'){
          $('#fontText').css('color',log)
        }else{
          let objcet = canvas.getActiveObject();
          setStyle(objcet, 'fill', log);
        }
        $('#currnetFontColor').css('background-color',log);
        currnetFontColor
				if (opacity) log += ', ' + opacity;
			} catch (e) {

      }
		},
		theme: 'default'
	});

  fontSize.forEach((e)=>{
    $('#fontSize').append('<option value="'+e+'">'+e+'pt</option>');
  });
  
  fontStyle.forEach((e)=>{
    $('#fontStyle').append('<option value="'+e+'">'+e+'</option>');
  });

  $(document).on('change','#fontStyle', (e)=>{
    $('#fontText').css('font-family',$(e.target).val())
  })


  $(document).on('click','#fontR_1', (e)=>{
		$(e.target).parent().find('label').toggleClass('selected');
    if( $(e.target).prop('checked') ){
      $('#fontText').css('font-weight',600);
    }else{
      $('#fontText').css('font-weight',100);
    }
  });
  $(document).on('click','#fontR_2', (e)=>{
		$(e.target).parent().find('label').toggleClass('selected');
    if( $(e.target).prop('checked') ){
      $('#fontText').css('font-style','italic');
    }else{
      $('#fontText').css('font-style','');
    }
  });
  $(document).on('click','#fontR_3', (e)=>{
		$(e.target).parent().find('label').toggleClass('selected');
    if( $(e.target).prop('checked') ){
      $('#fontText').css('text-decoration','underline');
    }else{
      $('#fontText').css('text-decoration','');
    }
  });
});

let newFont = ()=>{
  let content = $('#fontText').val();
  let itext = new fabric.IText(content, {
    left: 100,
    top: 100,
    padding: 7,
    fontFamily: $('#fontStyle').val(),
    fill: $('#fontColor').val(),
  });
	itext.title = tempId();	
  canvas.add(itext);
  canvas.setActiveObject(itext);
  setStyle(itext, 'fontSize', parseInt($('#fontSize').val(), 10));

  //setStyle(itext, 'fontFamily', $('#fontStyle').val());

  let isBold = $('#fontR_1').prop('checked') ?'bold':'';
  setStyle(itext, 'fontWeight', isBold );

  let isItalic = $('#fontR_2').prop('checked') ?'italic':'';
  setStyle(itext, 'fontStyle', isItalic);

  let isUnderline = $('#fontR_3').prop('checked') ?'underline':'';
  setStyle(itext, 'textDecoration', isUnderline);
  canvas.renderAll();

  historyPush();
}

function setStyle(object, styleName, value) {
  if (object.setSelectionStyles && object.isEditing) {
    let style = { };
    style[styleName] = value;
    object.setSelectionStyles(style);
  }
  else {
    object[styleName] = value;
  }
}