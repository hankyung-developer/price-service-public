/**
 * 기사 AI 번역
 */
var originTitle="";
var originContent="";

$(".btn_translate").on("click",function(){
    // let sourceLanguageName = $("#sourceLanguage option:selected").text();
    let targetLanguageName = $("#targetLanguage option:selected").text();

    if(targetLanguageName==""){
        targetLanguageName = "한국어";
    }

    let title = $("#title").val();
    let subTitle = $("#subTitle").val();
    let content = tinymce.get('content').getContent().replace(/[<][\/| ]*br[ ]*[\/]*[>]/gmi,"\n").replace("&nbsp;"," ");
    // content = strip_tags(content,[]);

    // if(content==""){
    //     content = strip_tags(tinymce.get('content').getContent()).substring(1000);
    // }

    $.ajax({
        url: '/AIData/translate',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            title: title,
            subTitle: subTitle,
            content: content,
            // sourceLanguageName: sourceLanguageName,
            targetLanguageName: targetLanguageName,
        },
        beforeSend: function () {
            $("#load-status").empty().text('AI가 번역중입니다.');
            $('#workingpopup').show();
        }
    }).done(function(data, textStatus, jqXHR) {
        // let content = data.body.TranslatedText;
        let content = data.translate_contents;
        console.log(data)
        
        $("#title").val(data.translate_title);
        $("#subTitle").val(data.translate_subTitle);
        tinymce.activeEditor.setContent(content.replace(/[\n]+/igm,"<br />"));

    }).fail(function(jqXHR, textStatus, errorThrown){
        console.log('err??')
        if(jqXHR.responseJSON) {
            console.log(jqXHR.responseJSON.result.msg);
        } else {
            console.log(textStatus+' : '+errorThrown);
        }
    }).always(function(jqXHR, textStatus, errorThrown) {
        $('#workingpopup').hide();
    });
});

/**
 * AI 분석 버튼 클릭
 */ 
$(".btn_aiAssist").click(function (){
    aiAssist();
});

/**
 * AI 추천제목 동작 함수
 */
function aiTitle(){
    let content = $("#title").val()+"\n";
    content += $("#subTitle").val()+"\n";
    content += strip_tags(tinymce.get('content').getContent(),[]).trim();

    if(content.replaceAll("\n","")==""){
        alert("기사 제목 및 본문을 입력하세요");
        return false;
    }

    $.ajax({
        url: '/AIData/titles',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            'content':content,
        },
        beforeSend: function () {
            $(".btn_aiAssist").hide();
            $(".aiProcessing").show();
            // $("#load-status").empty().append('AI가 분석중 입니다.');
            // $('#workingpopup').show();
        },
    }).done(function(data, textStatus, jqXHR) {
        if(Array.isArray(data)){aiAssist();return false;}

        $(".aiProcessing").hide();
        $(".aiAssistResult").show();
        $(".aiAsistant").css('display','block');
        // 추천제목
        $(".aiAssistResult [data-section=aiTitle] .aiTitleList").empty();
        data.title.forEach(function(val,index){
            $(".aiAssistResult [data-section=aiTitle] .aiTitleList").append('<li><label><input type="checkbox" name="titleItem" data-index="'+index+'">'+val+'</label></li>');
        });
     }).fail(function(jqXHR, textStatus, errorThrown) {
        //console.log(jqXHR.responseJSON.result.msg);
        $(".btn_aiAssist").show();
        $(".aiProcessing").hide();
        $(".aiAssistResult").hide();

        let msg = '오류가 발생하였습니다.\n';
        if(!!jqXHR?.responseJSON?.result?.msg) {
            alert(msg + jqXHR.responseJSON.result.msg);
        } else if (!!jqXHR?.responseJSON?.msg) {
            alert(msg + jqXHR.responseJSON.msg);
        } else {
            alert(msg + textStatus+' : '+errorThrown);
        }
    }).always(function(jqXHR, textStatus, errorThrown) {
        $("#aiTitleModalCloseBtn").click(); // 사용여부 확인 필요
        $('#workingpopup').hide();
    });
}

/**
 * AI 맞춤범 탭 클릭시
 */
$('li .tab[data-target="aiSpellCheck"]').on("click",function(){
    aiSpellCheck();
});

/**
 * AI 맞춤범 검사 동작 함수
 */
function aiSpellCheck(){
    let content = $("#title").val()+"\n";
    content += $("#subTitle").val()+"\n";
    content += strip_tags(tinymce.get('content').getContent(),[]).trim();

    if(content.replaceAll("\n","")==""){
        alert("기사 제목 및 본문을 입력하세요");
        return false;
    }

    $.ajax({
        url: '/AIData/spellcheck',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            'content':content,
        },
        beforeSend: function () {
            $(".btn_aiAssist").hide();
            $(".aiProcessing").show();
        },
    }).done(function(data, textStatus, jqXHR) {
        if(Array.isArray(data)){aiAssist();return false;}

        $(".aiProcessing").hide();
        $(".aiAssistResult").show();
        $(".aiAsistant").css('display','block');
        // 맞춤법검사
        $(".aiAssistResult [data-section=aiSpellCheck] .aiSpellCheckList").empty();
        data.spellcheck_details.forEach(function(val,index){
            if(val.origin != val.modify){
                $(".aiAssistResult [data-section=aiSpellCheck] .aiSpellCheckList").append('<li><div class="tool"><input type="checkbox" data-origin="'+val.origin+'" data-modify="'+val.modify+'"></div><div class="origin">'+val.origin+'</div><div class="modify">'+val.modify+'</div><div class="desc">'+val.desc+'</div></li>');
            }
        });
    }).fail(function(jqXHR, textStatus, errorThrown) {
        // console.log(jqXHR.responseJSON.result.msg);
        // console.log(jqXHR.responseText);
        $(".btn_aiAssist").show();
        $(".aiProcessing").hide();
        $(".aiAssistResult").hide();

        let msg = '오류가 발생하였습니다.\n';
        if(!!jqXHR?.responseJSON?.result?.msg) {
            alert(msg + jqXHR.responseJSON.result.msg);
        } else if (!!jqXHR?.responseJSON?.msg) {
            alert(msg + jqXHR.responseJSON.msg);
        } else if (!!jqXHR?.responseText){
            alert(msg + jqXHR.responseText);
        } else {
            alert(msg + textStatus+' : '+errorThrown);
        }
    }).always(function(jqXHR, textStatus, errorThrown) {
        $("#aiTitleModalCloseBtn").click(); // 사용여부 확인 필요
        $('#workingpopup').hide();
    });
}

/**
 * AI 추천태그 탭 클릭시
 */
$('li .tab[data-target="aiTags"]').on("click",function(){
    aiTags();
});


/**
 * AI 추천태그 동작 함수
 */
function aiTags(){
    let content = $("#title").val()+"\n";
    content += $("#subTitle").val()+"\n";
    content += strip_tags(tinymce.get('content').getContent(),[]).trim();

    if(content.replaceAll("\n","")==""){
        alert("기사 제목 및 본문을 입력하세요");
        return false;
    }

    $.ajax({
        url: '/AIData/tags',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            'content':content,
        },
        beforeSend: function () {
            $(".btn_aiAssist").hide();
            $(".aiProcessing").show();
            // $("#load-status").empty().append('AI가 분석중 입니다.');
            // $('#workingpopup').show();
        },
    }).done(function(data, textStatus, jqXHR) {

        $(".aiProcessing").hide();
        $(".aiAssistResult").show();
        $(".aiAsistant").css('display','block');

        if(Array.isArray(data)){aiAssist();return false;}
        // 추천태그
        $(".aiAssistResult [data-section=aiTags] .aiTagList").empty();
        if (!!data?.tags) {
            data.tags.forEach(function(val,index){
                $(".aiAssistResult [data-section=aiTags] .aiTagList").append('<span class="tag label label-info">'+val+'<span data-role="remove"></span></span>');
                $('#tags').tagsinput('add',val);
                // $(".aiAssistResult [data-section=aiTags]").append('<div class="bootstrap-tagsinput" id="selectedCategory" style="border: 0 none !important;box-shadow: none;padding:  0;"><span class="tag label label-info" data-category="'+val+'">'+val+'<span data-role="remove"></span></span></div>');
            });
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        //console.log(jqXHR.responseJSON.result.msg);
        $(".btn_aiAssist").show();
        $(".aiProcessing").hide();
        $(".aiAssistResult").hide();

        let msg = '오류가 발생하였습니다.\n';
        if(!!jqXHR?.responseJSON?.result?.msg) {
            alert(msg + jqXHR.responseJSON.result.msg);
        } else if (!!jqXHR?.responseJSON?.msg) {
            alert(msg + jqXHR.responseJSON.msg);
        } else {
            alert(msg + textStatus+' : '+errorThrown);
        }
    }).always(function(jqXHR, textStatus, errorThrown) {
        $("#aiTitleModalCloseBtn").click(); // 사용여부 확인 필요
        $('#workingpopup').hide();
    });
}


/**
 * AI 기사요약 탭 클릭시
 */
$('li .tab[data-target="aiSummary"]').on("click",function(){
    aiSummary();
});

/**
 * AI 기사요약 동작 함수
 */
function aiSummary(){
    let content = $("#title").val()+"\n";
    content += $("#subTitle").val()+"\n";
    content += strip_tags(tinymce.get('content').getContent(),[]).trim();

    if(content.replaceAll("\n","")==""){
        alert("기사 제목 및 본문을 입력하세요");
        return false;
    }

    $.ajax({
        url: '/AIData/summary',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            'content':content,
        },
        beforeSend: function () {
            $(".btn_aiAssist").hide();
            $(".aiProcessing").show();
            // $("#load-status").empty().append('AI가 분석중 입니다.');
            // $('#workingpopup').show();
        },
    }).done(function(data, textStatus, jqXHR) {
        $(".aiProcessing").hide();
        $(".aiAssistResult").show();
        $(".aiAsistant").css('display','block');

        if(Array.isArray(data)){aiAssist();return false;}
        // 요약문
        $(".aiAssistResult [data-section=aiSummary] .summary").text(!!data?.summary ? data.summary : '');

    }).fail(function(jqXHR, textStatus, errorThrown) {
        //console.log(jqXHR.responseJSON.result.msg);
        $(".btn_aiAssist").show();
        $(".aiProcessing").hide();
        $(".aiAssistResult").hide();

        let msg = '오류가 발생하였습니다.\n';
        if(!!jqXHR?.responseJSON?.result?.msg) {
            alert(msg + jqXHR.responseJSON.result.msg);
        } else if (!!jqXHR?.responseJSON?.msg) {
            alert(msg + jqXHR.responseJSON.msg);
        } else {
            alert(msg + textStatus+' : '+errorThrown);
        }
    }).always(function(jqXHR, textStatus, errorThrown) {
        $("#aiTitleModalCloseBtn").click(); // 사용여부 확인 필요
        $('#workingpopup').hide();
    });
}


/**
 * AI 기사 전체 분석 동작 함수
 **/
function aiAssist(){
    let content = $("#title").val()+"\n";
    content += $("#subTitle").val()+"\n";
    content += strip_tags(tinymce.get('content').getContent(),[]).trim();

    if(content.replaceAll("\n","")==""){
        alert("기사 제목 및 본문을 입력하세요");
        return false;
    }

    $.ajax({
        url: '/AIData/titletags',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            'content':content,
        },
        beforeSend: function () {
            $(".btn_aiAssist").hide();
            $(".aiProcessing").show();
            // $("#load-status").empty().append('AI가 분석중 입니다.');
            // $('#workingpopup').show();
        },
    }).done(function(data, textStatus, jqXHR) {
        if(Array.isArray(data)){aiAssist();return false;}

        $(".aiProcessing").hide();
        $(".aiAssistResult").show();
        $(".aiTitleAsistantArea").show();
        
        $(".aiAsistant").css('display','block');
        // 추천제목
        $(".aiAssistResult [data-section=aiTitle] .aiTitleList").empty();
        data.title.forEach(function(val,index){
            $(".aiAssistResult [data-section=aiTitle] .aiTitleList").append('<li><label><input type="checkbox" name="titleItem" data-index="'+index+'">'+val+'</label></li>');
        });
        // 맞춤법검사
/*        $(".aiAssistResult [data-section=aiSpellCheck] .aiSpellCheckList").empty();
        data.spellcheck_details.forEach(function(val,index){
            if(val.origin != val.modify){
                $(".aiAssistResult [data-section=aiSpellCheck] .aiSpellCheckList").append('<li><div class="tool"><input type="checkbox" data-origin="'+val.origin+'" data-modify="'+val.modify+'"></div><div class="origin">'+val.origin+'</div><div class="modify">'+val.modify+'</div><div class="desc">'+val.desc+'</div></li>');
            }
        });*/
        // 추천태그
        $(".aiAssistResult [data-section=aiTags] .aiTagList").empty();
        if (!!data?.tags) {
            data.tags.forEach(function(val,index){
                $(".aiAssistResult  [data-section=aiTitle] .aiTagList").append('<span class="tag label label-info">'+val+'<span data-role="remove"></span></span>');
                $('#tags').tagsinput('add',val);
            });
        }
        // 요약문
        $(".aiAssistResult [data-section=aiTitle] .summary").text(!!data?.summary ? data.summary : '');
        $("#summary").text(!!data?.summary ? data.summary : '');

    }).fail(function(jqXHR, textStatus, errorThrown) {
        //console.log(jqXHR.responseJSON.result.msg);
        $(".btn_aiAssist").show();
        $(".aiProcessing").hide();
        $(".aiAssistResult").hide();

        let msg = '오류가 발생하였습니다.\n';
        if(!!jqXHR?.responseJSON?.result?.msg) {
            alert(msg + jqXHR.responseJSON.result.msg);
        } else if (!!jqXHR?.responseJSON?.msg) {
            alert(msg + jqXHR.responseJSON.msg);
        } else {
            alert(msg + textStatus+' : '+errorThrown);
        }
    }).always(function(jqXHR, textStatus, errorThrown) {
        $("#aiTitleModalCloseBtn").click(); // 사용여부 확인 필요
        $('#workingpopup').hide();
    });
}

/**
 * 추천타이틀 변경 및  원래 제목으로 변경
 **/
var orgTitle="";
$(".aiTitleList").on("click","input[name=titleItem]",function(){
    if(orgTitle == ""){
        orgTitle = $("#title").val();
    }
    $(this).closest('li').siblings().find('input[type=checkbox]').prop('checked', false);
    $("#title").val( $(this).parent().text() );
    if(!$(this).prop('checked')){
        $("#title").val(orgTitle);
    }
});

/**
 * 맞춤법 검사 선택시 변경
 **/
$(".aiSpellCheckList").on("change",".tool input",function(){            
    let source = $(this).attr("data-origin");
    let target = $(this).attr("data-modify");

    if(!$(this).is(":checked")) {
        target = $(this).attr("data-origin");
        source = $(this).attr("data-modify");
    }

    $("#title").val( $("#title").val().replace(source,target) );
    $("#subTitle").val( $("#subTitle").val().replace(source,target) );
    tinymce.activeEditor.setContent(tinymce.get('content').getContent().replaceAll(source,target));
});

/**
 * ai 태그 적용
 */
$(".aiAssistResult").on("click","[data-section=aiTags] .aiTagList .tag",function(){
    let obj = $(this).closest(".tag");
    let tag = obj.text().trim();
    $('#tags').tagsinput('add',tag);
});

/**
 * ai 태그 삭제
 */
$(".aiAssistResult").on("click","[data-role=remove]",function(e){
    let obj = $(this).closest("div");
    let tag = obj.text().trim();
    $('#tags').tagsinput('remove',tag);
    obj.remove();
    e.stopPropagation();
});

/**
 * 요약문 사용
 */
$(".aiAssistResult").on("click","#btn_useAiSummary",function(){
    $('#summary').val($.trim($(".aiAssistResult [data-section=aiSummary] .summary").text()));
});

/**
 * AI 이미지 생성탭 클릭시
 * */
var createdImage=false;
$('li .tab[data-target="aiImage"]').on("click",function(){
    if(!createdImage){
        makeAIimage();
    }
});

/**
 * AI 추천 이미지 생성
 * */
function makeAIimage(){
    //let content = $("#title").val()+"\n";
    let content = $.trim($(".aiAssistResult .summary").text());

    if(!content){
        content = strip_tags(tinymce.get('content').getContent(),[]).substring(0, 500);
    }
    $.ajax({
        url: '/AIData/image_multiple',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            'content':content,
        },
        beforeSend: function () {
            $(".btn_aiAssist").hide();
            $(".aiProcessing").show();
        },
    }).done(function(data, textStatus, jqXHR) {
        $(".aiProcessing").hide();
        $(".aiAssistResult").show();
        $(".aiAsistant").css('display','block');

        data.data.forEach(function(val,index){
            $("div [data-section=aiImage]").append('<img class="suggestAI" src="'+val.url+'" title="'+val.revised_prompt+'">');
        });
        // createdImage = true; // 1회만 생성하도록 할 경우

    }).fail(function(jqXHR, textStatus, errorThrown) {
        //console.log(jqXHR.responseJSON.result.msg);
        $(".btn_aiAssist").show();
        $(".aiProcessing").hide();
        $(".aiAssistResult").hide();

        let msg = '오류가 발생하였습니다.\n';
        if(!!jqXHR?.responseJSON?.result?.msg) {
            alert(msg + jqXHR.responseJSON.result.msg);
        } else if (!!jqXHR?.responseJSON?.msg) {
            alert(msg + jqXHR.responseJSON.msg);
        } else {
            alert(msg + textStatus+' : '+errorThrown);
        }
    }).always(function(jqXHR, textStatus, errorThrown) {
        $("#aiTitleModalCloseBtn").click(); // 사용여부 확인 필요
        $('#workingpopup').hide();
    });
}

/**
 * AI 이미지 클릭시 
**/
$(".aiImage").on("dblclick",".suggestAI",function(){
    // 사진&파일에 이미지 삽입
    let default_width = 800;
    let caption = '[AI DALL-E3가 생성한 이미지]';
    $.ajax({
        url: '/file/url/ajax',
        method: 'post',
        contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
        dataType: 'json',
        async: true,
        data: {
            'url':$(this).attr("src"),
            caption: caption
        },
        beforeSend: function () {
            $("#load-status").empty().append('AI가 생성한 이미지를 반영중입니다.');
            $('#workingpopup').show();
        },
    }).done(function(data, textStatus, jqXHR) {
        uploadInfo = data.result;
        registFile(fileDropzone, uploadInfo);
        // 에디터에 이미지 삽입 (업로드 이미지)
        let default_width = 800;
        var src = uploadInfo.path.replace(/(.+)[.]([a-z]+)$/g, "$1." + ((!!uploadInfo?.width && uploadInfo.width < default_width)?uploadInfo.width:default_width.toString()) + "x.0.$2");
        tinymce.execCommand('mceInsertContent', true, '<figure class="image align-center"><img contenteditable="true" src="' + src + '" /><figcaption>' + caption + '</figcaption></figure>');

    }).always(function(jqXHR, textStatus, errorThrown) {
        $('#workingpopup').hide();
    });

    // 에디터에 이미지 삽입 (생성된 이미지 url)
    // tinymce.execCommand('mceInsertContent', true, '<figure class="image align-center"><img contenteditable="true" src="' + $(this).attr("src") + '" style="width:560px;"/><figcaption>'+caption+'</figcaption></figure>');
});

/**
**  문서 파일 업로드 처리
 **/
/*
$(document).ready(function(){
    document.getElementById('file-input').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const fileType = file.name.split('.').pop().toLowerCase();

        if (fileType == 'pdf') {
            readPDF(file); //pdf.js사용시 본문만 가능 이미지는 너무 많이 나옴(예:한글한자씩 이미지로 뽑히는 증상 있음), pdf-lib.js은 않됨
        }else if(fileType == 'docx'){
            readFileInputEventAsArrayBuffer(event, function(arrayBuffer) {
                mammoth.convertToHtml({arrayBuffer: arrayBuffer})
                    .then(displayResult, function(error) {
                        console.error(error);
                    });
            });
        }else{
            alert("pdf 또는 docx 파일을 업로드 해주세요");
            return;
        }
    });
});
*/

/**
 * pdf 파일 읽어 text 처리
 * */
function readPDF(file){
    const fileReader = new FileReader();
    fileReader.onload = function() {
        const typedArray = new Uint8Array(this.result);

        pdfjsLib.getDocument(typedArray).promise.then(function(pdf) {
            const pdfText = document.getElementById('pdf-text');
            pdfText.value = '';

            for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                pdf.getPage(pageNum).then(function(page) {
                    const viewport = page.getViewport({ scale: 1.5 });

                    page.getTextContent().then(function(textContent) {
                        textContent.items.forEach(function(item) {
                            pdfText.value += item.str + ' ';
                        });
                        pdfText.value += '\n\n';
                    });
                });
            }
        });
    };
    fileReader.readAsArrayBuffer(file);
}

function readFileInputEventAsArrayBuffer(event, callback) {
    var file = event.target.files[0];
    var reader = new FileReader();

    reader.onload = function(loadEvent) {
        var arrayBuffer = loadEvent.target.result;
        callback(arrayBuffer);
    };

    reader.readAsArrayBuffer(file);
}

function displayResult(result) {
    // 모든 이미지 src 추출
    let imgs = [];
    let imgMatches = result.value.match(/<img.+?src=['"]([^'"]+)['"][^>]*>/gi);
    if (imgMatches) {
        imgs = imgMatches.map(img => {
            let srcMatch = img.match(/src=['"]([^'"]+)['"]/i);
            return srcMatch ? srcMatch[1] : null;
        }).filter(src => src !== null);
    }
    // 이미지 저장
    if (imgs.length > 0) {
        imgs.forEach(img => {
            $.ajax({
                url: '/file/base64Img/ajax',
                method: 'post',
                contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
                dataType: 'json',
                async: true,
                data: {
                    'base64':img
                },
            }).done(function(data, textStatus, jqXHR) {
                uploadInfo = data.result;
                registFile(fileDropzone, uploadInfo);
            });
        });
    }
    // 태그 제거하고 text 입력
    str = strip_tags(result.value,['p','br']);
    str = str.replace(/<\/p>/sig,'');
    str = str.replace(/<p>/sig,'\n\n');
    str = str.replace(/<br[\/| ]*>/sig,'\n');
    str = str.replace(/\n{3,}/g, '\n\n');
    document.getElementById('pdf-text').value = str.trim();
}

function escapeHtml(value) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/'/g, '&#x27;');
}

function pressRelease(){
    let content = $("#pdf-text").val();

    if($("#file-input").val()==""){
        $.ajax({
            url: '/AIData/draft',
            method: 'post',
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            dataType: 'json',
            async: true,
            data: {
                'content':content,
                'maxTextLength':2000
            },
            beforeSend: function () {
                $("#aiWriteModal .aiProcessing").show();
                $("[data-section=aiDoc] .aiProcessing").show();
                $("[data-section=aiDoc] .aiReady").hide();
            },
        }).done(function(data, textStatus, jqXHR) {
            let titleObj = "#title";
            let subTitleObj = "#subTitle";
            let contentObj = "#content";
            let tableObj = "#table";

            if($("#aiForm")!==undefined){
                titleObj = "#aiForm #title";
                subTitleObj = "#aiForm #subTitle";
                contentObj = "#aiForm #content";
                tableObj = "#aiForm #table";
            }

            if(!!data.title){
                $(titleObj).val(data.title);
            }
            
            if(!!data.sub_title){
                $(subTitleObj).val(data.sub_title.join("\n"));
            }
            if(!!data.contents){
                if(typeof tinymce == "undefined"){
                    $(contentObj).val(data.contents.replaceAll("<br>", "<br /><br />"));
                }else{
                    tinymce.execCommand('mceInsertContent', true, data.contents.replaceAll("<br>", "<br /><br />"));
                }
            }

            if(!!data.table){
                $(tableObj).val(data.table);
            }

            // 리스트 aiForm
            if($("#aiForm")!==undefined){
                $("#aiForm").attr("accept-chartset","utf-8");
                $("#aiForm").attr("method","post");
                //$("#aiForm").attr("target","_blank");
                $("#aiForm").attr("action","/article/editor2");
                $("#aiForm").submit();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            let msg = '오류가 발생하였습니다.\n';
            if(!!jqXHR?.responseJSON?.result?.msg) {
                alert(msg + jqXHR.responseJSON.result.msg);
            } else if (!!jqXHR?.responseJSON?.msg) {
                alert(msg + jqXHR.responseJSON.msg);
            } else {
                alert(msg + textStatus+' : '+errorThrown);
            }
        }).always(function(jqXHR, textStatus, errorThrown) {
            $("#aiWriteModal .aiProcessing").hide();
            $("[data-section=aiDoc] .aiProcessing").hide();
            $("[data-section=aiDoc] .aiReady").show();
        });
    }else{
        $.ajax({
            url: '/AIData/press_release',
            method: 'post',
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            dataType: 'json',
            async: true,
            data: {
                'content':content,
                'maxTextLength':1500
            },
            beforeSend: function () {
                $("#aiWriteModal .aiProcessing").show();
                $("[data-section=aiDoc] .aiProcessing").show();
                $("[data-section=aiDoc] .aiReady").hide();
            },
        }).done(function(data, textStatus, jqXHR) {
            let titleObj = "#title";
            let subTitleObj = "#subTitle";
            let contentObj = "#content";
            let tableObj = "#table";

            if($("#aiForm")!==undefined){
                titleObj = "#aiForm #title";
                subTitleObj = "#aiForm #subTitle";
                contentObj = "#aiForm #content";
                tableObj = "#aiForm #table";
            }

            if(!!data.title){
                $(titleObj).val(data.title);
            }
            
            if(!!data.sub_title){
                $(subTitleObj).val(data.sub_title.join("\n"));
            }
            if(!!data.contents){
                if(typeof tinymce == "undefined"){
                    $(contentObj).val(data.contents.replaceAll("<br>", "<br /><br />"));
                }else{
                    tinymce.execCommand('mceInsertContent', true, data.contents.replaceAll("<br>", "<br /><br />"));
                }
            }

            if(!!data.table){
                $(tableObj).val(data.table);
            }

            // 리스트 aiForm
            if($("#aiForm")!==undefined){
                $("#aiForm").attr("accept-chartset","utf-8");
                $("#aiForm").attr("method","post");
                //$("#aiForm").attr("target","_blank");
                $("#aiForm").attr("action","/article/editor2");
                $("#aiForm").submit();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            let msg = '오류가 발생하였습니다.\n';
            if(!!jqXHR?.responseJSON?.result?.msg) {
                alert(msg + jqXHR.responseJSON.result.msg);
            } else if (!!jqXHR?.responseJSON?.msg) {
                alert(msg + jqXHR.responseJSON.msg);
            } else {
                alert(msg + textStatus+' : '+errorThrown);
            }
        }).always(function(jqXHR, textStatus, errorThrown) {
            $("#aiWriteModal .aiProcessing").hide();
            $("[data-section=aiDoc] .aiProcessing").hide();
            $("[data-section=aiDoc] .aiReady").show();
        });
    }
}