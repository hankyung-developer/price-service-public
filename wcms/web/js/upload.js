/**
 * 단일파일 업로드
 * 
 * @param uploadDir : [선택] 업로드 디렉토리
 * @param uploadId : [필수] 업로드 영역ID (dropzone id)
 * @param pathId : 변수 저장용 input ID (uploadId 하위 요소)
 * @param path : 초기화 파일 경로
 * @param funcInsertEditor : [선택] 파일을 에디터에 입력하기 위한 함수명
 * @param useFileDownload : [선택] 파일다운로드 버튼 사용 안할 경우 'false'
 * @param global : [uploadUrl='/file/save/ajax' 선택] 업로드 홈 디렉토리를 '/webData'로 변경 (기본 업로드 홈 = '/webData/{coId}/upload')
 * @param uploadUrl : [선택] 파일업로드 url (기본값 = '/file/save/ajax')
 * @param maxFilesize : [선택] 최대 파일 크기 (기본값 = 100)
 * @param acceptedFiles : [선택] 파일 확장자 (기본값 = '.jpeg,.jpg,.png,.gif')
 */
let singleFileUpload = function(init) {
    init.maxFiles = 1;
    return new fileUpload2(init);
};

/**
 * 파일 업로드2
 * 
 * uploadUrl 설명 (File class 참조)
 * - '/file/upload/ajax'
     기사용 파일 업로드, file 컬렉션에 저장
 *   [선택] init.isDisplay
 * - '/file/save/ajax'
 *   범용 파일 업로드, db저장 안함
 *   [필수] init.uploadDir
 *   [선택] init.global
 * - '/file/save/ajax'
 *   업로드 시 원본 파일명을 유지해야 하는 경우, db저장 안함
 * 
 * @param init.uploadId : [필수] 업로드 영역ID (dropzone id)
 * @param init.uploadUrl : [선택] 파일업로드 action url (기본값 = '/file/save/ajax')
 * @param init.maxFiles : [선택] 최대 파일 수 (기본값 = 10)
 * @param init.maxFilesize : [선택] 최대 파일 크기 (기본값 = 100)
 * @param init.acceptedFiles : [선택] 파일 확장자 (기본값 = '.jpeg,.jpg,.png,.gif')
 * @param init.thumbnailWidth : [선택] 썸네일 가로 크기 (기본값 = 200)
 * @param init.thumbnailHeight : [선택] 썸네일 세로 크기 (기본값 = 200)
 * @param init.uploadDir : [uploadUrl='/file/save/ajax' 필수] 업로드 디렉토리 ('/file/save/ajax' 해당)
 * @param init.global : [uploadUrl='/file/save/ajax' 선택] 업로드 홈 디렉토리를 '/webData'로 변경 (기본 업로드 홈 = '/webData/{coId}/upload')
 * @param init.coId : [선택] 회사코드 (기본값 세션)
 * @param init.funcInsertEditor : [선택] 파일을 에디터에 입력하기 위한 함수명
 * @param init.useFileDownload : [선택] 파일 다운로드 사용 안할 경우 'false'
 * @param init.previewTemplate : [선택] preview 템플릿 (없으면 기본값으로 설정)
 * @param init.isDisplay : [uploadUrl='/file/upload/ajax' 선택] file 컬렉션에 비노출 처리(isDisplay = 'N', 기사 이미지에서 사용)
 * 
 * - 단일 파일 업로드용
 * @param pathId : 변수 저장용 input ID (값이 있으면 단일파일로 동작) (uploadId 하위 요소)
 * @param path : preview 파일 경로
 * 
 * - 다중 파일 업로드용
 * @param files : preview 파일 array (path, orgName, ext, type, mimeType, size, width, height, fileId, watermarkPlace)
 */
let fileUpload2 = function(init) {
    let uploadId = (!!init?.uploadId ? init.uploadId : null);
    let uploadUrl = (!!init?.uploadUrl ? init.uploadUrl : '/file/save/ajax');
    let maxFiles = (!!init?.maxFiles ? init.maxFiles : 10);
    let maxFilesize = (!!init?.maxFilesize ? init.maxFilesize : 100);
    let acceptedFiles = (!!init?.acceptedFiles ? init.acceptedFiles : '.jpeg,.jpg,.png,.gif');
    let thumbnailWidth = (!!init?.thumbnailWidth ? init.thumbnailWidth : 190);
    let thumbnailHeight = (!!init?.thumbnailHeight ? init.thumbnailHeight : 130);
    let uploadDir = (!!init?.uploadDir ? init.uploadDir : null);
    let global = (!!init?.global ? init.global : null);
    let coId = (!!init?.coId ? init.coId : null);
    let funcInsertEditor = (!!init?.funcInsertEditor ? init.funcInsertEditor : null);
    let useFileDownload = (!!init?.useFileDownload ? init.useFileDownload : null);
    // 단일파일 업로드
    let pathId = (!!init?.pathId ? init.pathId : null);
    if (pathId) maxFiles = 1;
    let path = (!!init?.path ? init.path : null);
    // 다중파일 업로드
    let files = (!!init?.files ? init.files : null);
    let isDisplay = (!!init?.isDisplay ? init.isDisplay : null);

    if (uploadUrl == '/file/save/ajax') {
        if (!uploadDir) {
            alert('fileUpload2 : /file/save/ajax 사용 시 uploadDir은 필수입니다.');
            return false;
        }
    }

    // preview 템플릿
    // let previewTemplate = 
    //     (!!init?.previewTemplate ? init.previewTemplate
    //     :'<article class="dz-preview dz-image-preview dz-file-preview">'+
    //     '   <img class="thumbnail" data-dz-thumbnail onerror="javascript:this.src=\'/img/etc.png\'" />'+
    //     '   <div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>'+
    //     '   <div class="dz-error-message"><span data-dz-errormessage></span></div>'+
    //     '   <div class="file-info">'+
    //     (!pathId ? '       <div class="orgFilename"></div>' : '')+
    //     '       <div class="flex justify-between">'+
    //     '           <div class="dz-size" data-dz-size></div>'+
    //     '           <div>'+
    //     '               <i class="imagePasteEditor fa-light fa-image"  data-toggle="tooltip" data-placement="down" title="에디터에 입력하기"></i>'+
    //     '		        <i class="imageDownload fa-light fa-download"  data-toggle="tooltip" data-placement="down" title="다운로드"></i>'+
    //     '               <i class="imageDel fa-light fa-trash-can" title="삭제"></i>'+
    //     '           </div>'+
    //     '       </div>'+
    //     (!pathId ? 
    //     '       <input type="hidden" class="orgName" name="orgName[]" />'+
    //     '       <input type="hidden" class="path" name="path[]" />'+
    //     '       <input type="hidden" class="ext" name="ext[]" />'+
    //     '       <input type="hidden" class="type" name="type[]" />'+
    //     '       <input type="hidden" class="mimeType" name="mimeType[]" />'+
    //     '       <input type="hidden" class="size" name="size[]" />'+
    //     '       <input type="hidden" class="width" name="width[]" />'+
    //     '       <input type="hidden" class="height" name="height[]" />'
    //         (uploadUrl == '/file/upload/ajax'?
    //         '       <input type="hidden" class="fileId" name="fileId[]" />'+
    //         '       <input type="hidden" class="watermarkPlace" name="watermarkPlace[]" />'
    //         :'')
    //     :'')+
    //     '   </div>'+
    //     '</article>');

    // preview 템플릿
    let previewTemplate = null;
    if (!!init?.previewTemplate) {
        previewTemplate = init.previewTemplate;
    } else {
        let fileInfoTemplate = '';
        if (!pathId) {
            fileInfoTemplate += 
                '       <div class="orgFilename"></div>'+
                '       <input type="hidden" class="orgName" name="file_orgName[]" />'+
                '       <input type="hidden" class="path" name="file_path[]" />'+
                '       <input type="hidden" class="ext" name="file_ext[]" />'+
                '       <input type="hidden" class="type" name="file_type[]" />'+
                '       <input type="hidden" class="mimeType" name="file_mimeType[]" />'+
                '       <input type="hidden" class="size" name="file_size[]" />'+
                '       <input type="hidden" class="width" name="file_width[]" />'+
                '       <input type="hidden" class="height" name="file_height[]" />';
            // 기사 이미지용
            if (uploadUrl == '/file/upload/ajax') {
                fileInfoTemplate += 
                    '       <input type="hidden" class="fileId" name="file_id[]" />'+
                    '       <input type="hidden" class="watermarkPlace" name="file_watermarkPlace[]" />';
            }
        }
        fileInfoTemplate += 
            '       <div class="flex justify-between">'+
            '           <div class="dz-size" data-dz-size></div>'+
            '           <div>'+
            '               <i class="imagePasteEditor fa-light fa-image"  data-toggle="tooltip" data-placement="down" title="에디터에 입력하기"></i>'+
            '		        <i class="imageDownload fa-light fa-download"  data-toggle="tooltip" data-placement="down" title="다운로드"></i>'+
            '               <i class="imageDel fa-light fa-trash-can" title="삭제"></i>'+
            '           </div>'+
            '       </div>';
        previewTemplate = 
            '<article class="dz-preview dz-image-preview dz-file-preview">'+
            '   <img class="thumbnail" data-dz-thumbnail onerror="javascript:this.src=\'/img/etc.png\'" />'+
            '   <div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>'+
            '   <div class="dz-error-message"><span data-dz-errormessage></span></div>'+
            '   <div class="file-info">'+
            fileInfoTemplate+
            '   </div>'+
            '</article>';
    }

    let fileDropzone = new Dropzone(uploadId, {
        url: uploadUrl,
        maxFiles: maxFiles,
        maxFilesize: maxFilesize,
        acceptedFiles: acceptedFiles,
        thumbnailWidth: thumbnailWidth,
        thumbnailHeight: thumbnailHeight,
        previewTemplate: previewTemplate,
        // addRemoveLinks: true,
        init: function() {
            // init preview : 하단에서 this.add()를 사용
            /*
            if (!!pathId && !!path) {
                // preview 추가
                let fileInfo = getFileInfo(path);
                mockFile = {
                    path: path,
                    orgName: (!!fileInfo?.orgName?fileInfo.orgName:''),
                    ext: (!!fileInfo?.ext?fileInfo.ext:''),
                    type: (!!fileInfo?.type?fileInfo.type:''),
                    mimeType: (!!fileInfo?.mimeType?fileInfo.mimeType:''),
                    size: (!!fileInfo?.size?fileInfo.size:''),
                    width: (!!fileInfo?.width?fileInfo.width:''),
                    height: (!!fileInfo?.height?fileInfo.height:''),
                };

                let src = mockFile.path;
                let reg = /(.*?)\.(jpg|jpeg|png|gif|bmp)$/i;
                if (mockFile.path.match(reg)) {
                } else {
                    src = "/img/"+mockFile.ext+".png";
                }

                // @todo 호환성 문제가 발생 시 각 preview 추가 방법의 테스트가 필요할 수 있음

                // preview 추가 방법 1
                this.displayExistingFile(mockFile, src);

                // preview 추가 방법 2
                // this.options.addedfile.call(this, mockFile);
                // this.options.thumbnail.call(this, mockFile, src);

                // preview 추가 방법 3
                // this.emit('addedfile', mockFile);
                // this.emit('thumbnail', mockFile, src);

                // 에디터에 입력 버튼 설정
                if (!!funcInsertEditor && typeof funcInsertEditor == 'function') {
                    $(mockFile.previewElement).find('.imagePasteEditor').show();
                } else {
                    $(mockFile.previewElement).find('.imagePasteEditor').hide();
                }
                // 업로드 메시지 숨김
                $(uploadId+' .boxMessage').hide();
                // path input 설정
                $(uploadId+' '+pathId).val(mockFile.path);
            } else if (!!files) {
                $(files).each((i, item)=>{
                    // preview 추가
                    if (!!!item?.size) {
                        let fileInfo = getFileInfo(path);
                        item.orgName = (!!fileInfo?.orgName?fileInfo.orgName:'');
                        item.ext = (!!fileInfo?.ext?fileInfo.ext:'');
                        item.type = (!!fileInfo?.type?fileInfo.type:'');
                        item.mimeType = (!!fileInfo?.mimeType?fileInfo.mimeType:'');
                        item.size = (!!fileInfo?.size?fileInfo.size:'');
                        item.width = (!!fileInfo?.width?fileInfo.width:'');
                        item.height = (!!fileInfo?.height?fileInfo.height:'');
                    }
                    mockFile = {
                        path: item.path,
                        orgName: (!!item?.orgName?item.orgName:''),
                        ext: (!!item?.ext?item.ext:''),
                        type: (!!item?.type?item.type:''),
                        mimeType: (!!item?.mimeType?item.mimeType:''),
                        size: (!!item?.size?item.size:''),
                        width: (!!item?.width?item.width:''),
                        height: (!!item?.height?item.height:''),
                    };

                    let src = mockFile.path;
                    let reg = /(.*?)\.(jpg|jpeg|png|gif|bmp)$/i;
                    if (mockFile.path.match(reg) || mockFile.type == 'image' || mockFile.mimeType.indexOf('image') > -1) {
                    } else {
                        src = "/img/"+mockFile.ext+".png";
                    }

                    // @todo 호환성 문제가 발생 시 각 preview 추가 방법의 테스트가 필요할 수 있음

                    // preview 추가 방법 1
                    this.displayExistingFile(mockFile, src);

                    // preview 추가 방법 2
                    // this.options.addedfile.call(this, mockFile);
                    // this.options.thumbnail.call(this, mockFile, src);

                    // preview 추가 방법 3
                    // this.emit('addedfile', mockFile);
                    // this.emit('thumbnail', mockFile, src);

                    $(mockFile.previewElement).find('.orgFilename').text(mockFile.orgName);
                    $(mockFile.previewElement).find('.orgName').val(mockFile.orgName);
                    $(mockFile.previewElement).find('.path').val(mockFile.path);
                    $(mockFile.previewElement).find('.ext').val(mockFile.ext);
                    $(mockFile.previewElement).find('.type').val(mockFile.type);
                    $(mockFile.previewElement).find('.mimeType').val(mockFile.mimeType);
                    $(mockFile.previewElement).find('.size').val(mockFile.size);
                    $(mockFile.previewElement).find('.width').val(mockFile.width);
                    $(mockFile.previewElement).find('.height').val(mockFile.height);
                    // 에디터에 입력 버튼 설정
                    if (!!funcInsertEditor && typeof funcInsertEditor == 'function') {
                        $(mockFile.previewElement).find('.imagePasteEditor').show();
                    } else {
                        $(mockFile.previewElement).find('.imagePasteEditor').hide();
                    }
                    // 업로드 메시지 숨김
                    $(uploadId+' .boxMessage').hide();
                });
            }
            */
        },
    });

    // 업로드 시작
    fileDropzone.on('addedfile', (file)=>{
        $("#saveBtn").prop("disabled",true);
        // maxFiles을 초과하여 업로드 하는 경우 마지막 파일을 제거
        if (fileDropzone.files.length > maxFiles) {
            fileDropzone.removeFile(fileDropzone.files[maxFiles - 1]);
        }
        // 최초 로딩된 파일은 fileDropzone.files에 인식되지 않으므로 처리 (미사용)
        // if ($(uploadId+' article').length > maxFiles) {
        //     $(uploadId+' article:eq('+(maxFiles - 1)+')').remove();
        // }
    });

    // 업로드 중
    fileDropzone.on('sending', (file, xhr, formData)=>{
        // 작업중 로딩 : 시작
        formData.append('path', uploadDir);
        if (!!global) {
            formData.append('global', global);
        }
        if (!!coId) {
            formData.append('coId', coId);
        }
        if (!!isDisplay) {
            formData.append('isDisplay', isDisplay);
        }
        $('#load-status').empty().append('파일을 업로드중입니다.');
        $('#workingpopup').show();
    });

    // 업로드 성공
    fileDropzone.on("success", (file, responseText)=>{
        // console.log('fileUpload2 success', responseText);
        let uploadInfo = JSON.parse(file.xhr.responseText)['result'];
        if(!!uploadInfo?.path && uploadInfo.size > 0){
            file.path = uploadInfo.path; // 파일 식별을 위해서 추가
            $(file.previewElement).attr('data-path', uploadInfo.path);
            $(file.previewElement).find('.orgFilename').text(uploadInfo.orgName);
            $(file.previewElement).find('.orgName').val(uploadInfo.orgName);
            $(file.previewElement).find('.path').val(uploadInfo.path);
            $(file.previewElement).find('.ext').val(uploadInfo.ext);
            $(file.previewElement).find('.type').val(uploadInfo.type);
            $(file.previewElement).find('.mimeType').val(uploadInfo.mimeType);
            $(file.previewElement).find('.size').val(uploadInfo.size);
            $(file.previewElement).find('.width').val(uploadInfo.width);
            $(file.previewElement).find('.height').val(uploadInfo.height);
            // 기사 이미지 전용
            $(file.previewElement).attr('data-file-id', uploadInfo.id);
            $(file.previewElement).find('.fileId').val(uploadInfo.id);
            $(file.previewElement).find('.watermarkPlace').val(uploadInfo.watermarkPlace);

            let reg = /(.*?)\.(jpg|jpeg|png|gif|bmp|ico)$/i;
            if (uploadInfo.path.match(reg) || uploadInfo.type == 'image' || uploadInfo.mimeType.indexOf('image') > -1) {
            } else {
                $(file.previewElement).find('.thumbnail').attr('src','/img/'+uploadInfo.ext+'.png');
            }
            // 에디터에 입력 버튼 설정
            if (!!funcInsertEditor && typeof funcInsertEditor == 'function') {
                $(file.previewElement).find('.imagePasteEditor').show();
            } else {
                $(file.previewElement).find('.imagePasteEditor').hide();
            }
            if (!!pathId) {
                $(uploadId+' '+pathId).val(uploadInfo.path);
            }
            // 파일다운로드버튼 설정
            if (!!useFileDownload && useFileDownload == 'false') {
                $(file.previewElement).find('.imageDownload').hide();
            } else {
                $(file.previewElement).find('.imageDownload').show();
            }
        }else{
            $(file.previewElement).remove();
            alert("파일 업로드를 실패하였습니다.\n관리자에게 문의하세요.");
        }
        $('#load-status').empty();
        $('#workingpopup').hide();
    });

    // 업로드 실패
    fileDropzone.on("error", (file, errorMessage)=>{
        console.log('fileUpload2 error', errorMessage);
        if (errorMessage == 'You can\'t upload files of this type.') {
            alert('해당 유형의 파일은 업로드할 수 없습니다.\n업로드 가능 확장자 : '+acceptedFiles);
        } else {
            alert('오류가 발생하였습니다.\n'+errorMessage);
        }
        fileDropzone.removeFile(file); // 오류 발생 시 file 삭제
    });

    // 업로드 완료(성공/실패 공통)
    fileDropzone.on("complete", (file)=>{
        $("#saveBtn").prop("disabled",false);
        if ($(uploadId+' article').length == 0) {
            $(uploadId+' .boxMessage').show();
        }
        // console.log('fileUpload2 complete', file);
        // console.log('fileUpload2 complete', fileDropzone.files);
    });

    // 파일 삭제 후
    fileDropzone.on('removedfile', (file)=>{
        // console.log('fileUpload2 removedfile');
    });

    // 에디터에 입력
    $(document).on('click', uploadId+' .imagePasteEditor', function(e) {
        e.preventDefault();
        let filePath = '';
        let fileType = '';
        let fileMimeType = '';
        let fileOrgName = '';
        if (!!pathId) {
            filePath = $(uploadId+' '+pathId).val();
        } else {
            filePath = $(this).closest('.file-info').find('.path').val();
            fileType = $(this).closest('.file-info').find('.type').val();
            fileMimeType = $(this).closest('.file-info').find('.mimeType').val();
            fileOrgName = $(this).closest('.file-info').find('.orgName').val();
        }
        if (!fileOrgName && !!filePath) {
            let temp = filePath.split(/[\\/]/);
            fileOrgName = temp.pop();
        }
        let html = null;
        let reg = /(.*?)\.(jpg|jpeg|png|gif|bmp)$/i; // 이관된 데이터를 처리하기 위함
        if (!!filePath) {
            if (filePath.match(reg) || fileType == 'image' || fileMimeType.indexOf('image') > -1) {
                html = "<img contenteditable='true' src='" + filePath + "' />";
            } else {
                html = "<a href='/file/download?path=" + filePath + "' />"+fileOrgName+"</a>";
            }
        }
        if (!!funcInsertEditor && typeof funcInsertEditor == 'function') {
            if (html) {
                funcInsertEditor(html);
            }
        } else {
            alert('에디터 입력 오류가 발생하였습니다.\n관리자에게 문의하세요.');
        }
    });

    // 이미지 다운로드
    $(document).on('click', uploadId+' .imageDownload', function(e) {
        e.preventDefault();
        let filePath = '';
        let fileOrgName = '';
        if (!!pathId) {
            filePath = $(uploadId+' '+pathId).val();
            location.href = "/file/download?path="+filePath;
        } else {
            filePath = $(this).closest('.file-info').find('.path').val();
            fileOrgName = $(this).closest('.file-info').find('.orgName').val();
            location.href = "/file/download?path="+filePath+"&orgName="+fileOrgName;
        }
    });

    // 파일 삭제
    $(document).on('click', uploadId+' .imageDel', function(e) {
        e.preventDefault();
        if (confirm('파일을 삭제하시겠습니까?')) {
            let filePath = $(this).closest('article').data('path');
            $(fileDropzone.files).each((i, item)=>{
                if (item.path == filePath) {
                    fileDropzone.removeFile(item);
                    return false;
                }
            });
            // $(this).closest('article').remove(); // 삭제되지 않을 경우 처리 (미사용)

            if (!!pathId) {
                $(uploadId+' '+pathId).val('');
            }
            if ($(uploadId+' article').length == 0) {
                $(uploadId+' .boxMessage').show();
            }
        }
    });

    // 파일 추가
    this.add = function(path, files=null) {
        // preview 추가
        if (!!pathId && !!path) {
            // size 없으면 getFileInfo 조회하여 처리
            let fileInfo = getFileInfo(path);
            mockFile = {
                path: path,
                orgName: (!!fileInfo?.orgName?fileInfo.orgName:''),
                ext: (!!fileInfo?.ext?fileInfo.ext:''),
                type: (!!fileInfo?.type?fileInfo.type:''),
                mimeType: (!!fileInfo?.mimeType?fileInfo.mimeType:''),
                size: (!!fileInfo?.size?fileInfo.size:''),
                width: (!!fileInfo?.width?fileInfo.width:''),
                height: (!!fileInfo?.height?fileInfo.height:''),
            };
            fileDropzone.files.push(mockFile);

            let src = mockFile.path;
            let reg = /(.*?)\.(jpg|jpeg|png|gif|bmp|ico)$/i;
            if (mockFile.path.match(reg)) {
            } else {
                src = "/img/"+mockFile.ext+".png";
            }

            // @todo 호환성 문제가 발생 시 각 preview 추가 방법의 테스트가 필요할 수 있음

            // preview 추가 방법 1
            fileDropzone.displayExistingFile(mockFile, src);

            // preview 추가 방법 2
            // fileDropzone.options.addedfile.call(fileDropzone, mockFile);
            // fileDropzone.options.thumbnail.call(fileDropzone, mockFile, src);

            // preview 추가 방법 3
            // fileDropzone.emit('addedfile', mockFile);
            // fileDropzone.emit('thumbnail', mockFile, src);

            // 에디터에 입력 버튼 설정
            // console.log(mockFile.path);
            $(mockFile.previewElement).attr('data-path', mockFile.path);
            if (!!funcInsertEditor && typeof funcInsertEditor == 'function') {
                $(mockFile.previewElement).find('.imagePasteEditor').show();
            } else {
                $(mockFile.previewElement).find('.imagePasteEditor').hide();
            }
            // 업로드 메시지 숨김
            $(uploadId+' .boxMessage').hide();
            // path input 설정
            if (!!pathId) {
                $(uploadId+' '+pathId).val(mockFile.path);
            }
        } else if (!!files) {
            $(files).each((i, item)=>{
                if (!!!item?.size) {
                    // size 없으면 getFileInfo 조회하여 처리
                    let fileInfo = getFileInfo(path);
                    item.orgName = (!!fileInfo?.orgName?fileInfo.orgName:'');
                    item.ext = (!!fileInfo?.ext?fileInfo.ext:'');
                    item.type = (!!fileInfo?.type?fileInfo.type:'');
                    item.mimeType = (!!fileInfo?.mimeType?fileInfo.mimeType:'');
                    item.size = (!!fileInfo?.size?fileInfo.size:'');
                    item.width = (!!fileInfo?.width?fileInfo.width:'');
                    item.height = (!!fileInfo?.height?fileInfo.height:'');
                }
                mockFile = {
                    path: item.path,
                    orgName: (!!item?.orgName?item.orgName:''),
                    ext: (!!item?.ext?item.ext:''),
                    type: (!!item?.type?item.type:''),
                    mimeType: (!!item?.mimeType?item.mimeType:''),
                    size: (!!item?.size?item.size:''),
                    width: (!!item?.width?item.width:''),
                    height: (!!item?.height?item.height:''),
                    fileId: (!!item?.fileId?item.fileId:''),
                    watermarkPlace: (!!item?.watermarkPlace?item.watermarkPlace:'0'),
                };
                fileDropzone.files.push(mockFile);

                let src = mockFile.path;
                let reg = /(.*?)\.(jpg|jpeg|png|gif|bmp|ico)$/i;
                if (mockFile.path.match(reg) || mockFile.type == 'image' || mockFile.mimeType.indexOf('image') > -1) {
                } else {
                    src = "/img/"+mockFile.ext+".png";
                }

                // @todo 호환성 문제가 발생 시 각 preview 추가 방법의 테스트가 필요할 수 있음

                // preview 추가 방법 1
                fileDropzone.displayExistingFile(mockFile, src);

                // preview 추가 방법 2
                // fileDropzone.options.addedfile.call(fileDropzone, mockFile);
                // fileDropzone.options.thumbnail.call(fileDropzone, mockFile, src);

                // preview 추가 방법 3
                // fileDropzone.emit('addedfile', mockFile);
                // fileDropzone.emit('thumbnail', mockFile, src);

                $(mockFile.previewElement).attr('data-path', mockFile.path);
                $(mockFile.previewElement).find('.orgFilename').text(mockFile.orgName);
                $(mockFile.previewElement).find('.orgName').val(mockFile.orgName);
                $(mockFile.previewElement).find('.path').val(mockFile.path);
                $(mockFile.previewElement).find('.ext').val(mockFile.ext);
                $(mockFile.previewElement).find('.type').val(mockFile.type);
                $(mockFile.previewElement).find('.mimeType').val(mockFile.mimeType);
                $(mockFile.previewElement).find('.size').val(mockFile.size);
                $(mockFile.previewElement).find('.width').val(mockFile.width);
                $(mockFile.previewElement).find('.height').val(mockFile.height);
                // 기사 이미지 전용
                $(mockFile.previewElement).attr('data-file-id', mockFile.fileId);
                $(mockFile.previewElement).find('.fileId').val(mockFile.fileId);
                $(mockFile.previewElement).find('.watermarkPlace').val(mockFile.watermarkPlace);
                // 에디터에 입력 버튼 설정
                if (!!funcInsertEditor && typeof funcInsertEditor == 'function') {
                    $(mockFile.previewElement).find('.imagePasteEditor').show();
                } else {
                    $(mockFile.previewElement).find('.imagePasteEditor').hide();
                }
                // 업로드 메시지 숨김
                $(uploadId+' .boxMessage').hide();
            });
        }
    }

    // 파일 전체 삭제
    this.empty = function() {
        // console.log('fileUpload2 empty');
        fileDropzone.removeAllFiles();
        // $(uploadId+' article').remove(); // removeAllFiles로 삭제되지 않는 경우가 있음 (미사용)
        $(uploadId+' .boxMessage').show();
        if (!!pathId) {
            $(uploadId+' '+pathId).val('');
        }
    }

    // 파일 목록 조회
    this.getFiles = function() {
        return fileDropzone.files;
    }

    // init preview
    this.add(path, files);
};

// 파일정보 조회
function getFileInfo(path)
{
    let fileInfo = null;
    if (!!path) {
        $.ajax({
            url : '/file/info/ajax',
            type : 'POST',
            dataType : 'json',
            data: {path: path},
            async: false,
        }).done((data)=>{
            // console.log(data.result);
            fileInfo = data.result;
        }).fail((jqXHR, textStatus, errorThrown)=>{
            if (!!jqXHR?.responseJSON?.result?.msg) {
                alert(jqXHR.responseJSON.result.msg);
            } else {
                alert(textStatus+' : '+errorThrown);
            }
        });
    }
    return fileInfo;
}

/**
 * objectId : dropzone이 생성되는 div ID
 * path : 파일 업로드 url
 * preview : 이미지 로드 후 보여지는 이미지 구성화면
 * inputData : 파일 업로드 후 값을 세팅하는 요소
 * formData : 파일 업로드시 추가로 보내는 정보
 */
function fileUpload (objectId, path, preview, inputData, option, formData){
    this.objectId = objectId;
    this.path = path;
    this.preview = preview;
    this.inputData = inputData;
    this.option = option;
    this.formData = formData;
    this.fileDropzone = null;

    this.init = function(){
        let max = this.option["maxFile"]?this.option["maxFile"]:9999;
        this.fileDropzone = new Dropzone('#'+this.objectId+" #dropzone", {
            url: this.path,
            previewTemplate: this.preview,
            maxFiles : max,
            init: function() {
                
            },
        });

        if(this.option["maxFile"]){
            this.fileDropzone.on('addedfile', (file)=>{
                if (this.fileDropzone.files.length > this.option["maxFile"]) {
                    this.fileDropzone.removeFile(this.fileDropzone.files[this.option["maxFile"]-1]);
                }
            });
            this.fileDropzone.maxFiles = this.option["maxFile"];
        }

        this.fileDropzone.on("complete", (file)=>{
            uploadInfo = JSON.parse(file.xhr.responseText)['result'];

            for (const [key, value] of Object.entries(this.inputData)){
                $('#'+this.objectId+' #'+key).val(uploadInfo[value]);
            }
            // 작업중 로딩 : 종료
            $(this.objectId+' #workingpopup').hide();
        });

        if(this.formData){
            this.fileDropzone.on('sending', (file, xhr, formData)=>{
                // 작업중 로딩 : 시작
                for (const [key, value] of Object.entries(this.inputData)){
                    formData.append(key, value);
                }
                $('#'+this.objectId+" #load-status").empty().append('파일을 업로드중입니다.');
                $('#'+this.objectId+' #workingpopup').show();
            });
        }

        $('#'+this.objectId).on('click', '.trash-icon', (e)=>{
            $(e.target).closest('.dz-image-preview').remove();
            for (const [key, value] of Object.entries(this.inputData)){
                $('#'+this.objectId+' #'+key).val('');
            }
        })
    }

    this.fileAdd = function(path, fileData){
        path = path.replace(/(.+)[.]([a-z]+)$/g,"$1.x138.0.$2")

        let mockFile = {
            "thumb" : path
        };
        this.fileDropzone.files.push(mockFile);
        this.fileDropzone.emit('addedfile', mockFile);
        this.fileDropzone.emit('thumbnail', mockFile, mockFile.thumb);
        //this.fileDropzone.emit("complete", mockFile);

        for (const [key, value] of Object.entries(fileData)){
            $('#'+this.objectId+' #'+key).val(value);
        }
    }

    this.fileAllDelete = function(){
        $('#'+this.objectId+' #dropzone').empty();
        for (const [key, value] of Object.entries(this.inputData)){
            $('#'+this.objectId+' #'+key).val('');
        }
    }
};

/**
 * 파일을 파일존에 등록
 * path : 파일 업로드 url
 * preview : 이미지 로드 후 보여지는 이미지 구성화면
 * inputData : 파일 업로드 후 값을 세팅하는 요소
 * formData : 파일 업로드시 추가로 보내는 정보
 */
let registFile = (obj, uploadInfo)=>{
    let fileDropzone = obj;
    mockFile = {
            "id": uploadInfo.id,
            "thumb": uploadInfo.path.replace(/([.][a-z]+)$/, '.120x.0$1'),
            "path": uploadInfo.path,
            "caption": uploadInfo.caption,
            "ext": uploadInfo.ext,
            "type": uploadInfo.type,
            "width": uploadInfo.width,
            "height": uploadInfo.height,
            "size": uploadInfo.size,
            "orgName": uploadInfo.orgName,
            "watermarkPlace": 0,
    };
    fileDropzone.emit('addedfile', mockFile);

    $(mockFile.previewElement).attr("file_id", uploadInfo.id);
    // $(mockFile.previewElement).find('.btnPopover').attr("data-file", uploadInfo.id);
    $(mockFile.previewElement).find('.file_id').val(uploadInfo.id);
    $(mockFile.previewElement).find('.file_path').val(uploadInfo.path);
    $(mockFile.previewElement).find('.file_caption').val(uploadInfo.caption);
    $(mockFile.previewElement).find('.file_ext').val(uploadInfo.ext);
    $(mockFile.previewElement).find('.file_type').val(uploadInfo.type);
    $(mockFile.previewElement).find('.file_width').val(uploadInfo.width);
    $(mockFile.previewElement).find('.file_height').val(uploadInfo.height);
    $(mockFile.previewElement).find('.file_size').val(uploadInfo.size);
    $(mockFile.previewElement).find('.file_orgName').val(uploadInfo.orgName);
    $(mockFile.previewElement).find('.file_watermarkPlace').val(0);
    $(mockFile.previewElement).find('.orgFilename').text(uploadInfo.orgFileName);
    $(mockFile.previewElement).find('.caption').text(uploadInfo.caption);
    fileDropzone.emit('thumbnail', mockFile, mockFile.thumb)
}

let blobUpload = (blobInfo)=>{

    let xhr, formData;

    xhr = new XMLHttpRequest();
    xhr.withCredentials = false;
    xhr.open('POST', '/file/upload/ajax');

    xhr.onload = function() {
        let json;
        
        if (xhr.status !== 200) {
            failure('HTTP Error: ' + xhr.status);
            return;
        }
        
        json = JSON.parse(xhr.responseText);
        
        uploadInfo = JSON.parse(xhr.responseText)['result'];

        if( uploadInfo.size > 0){
            registFile(fileDropzone, uploadInfo);

            $(".image-message").hide();
        }
    };

    formData = new FormData();
    formData.append('file', blobInfo.blob(), blobInfo.filename());

    xhr.send(formData);
}