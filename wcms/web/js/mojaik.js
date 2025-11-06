var ncomma = function(n){
    if(n==0) return 0;
	return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
};

var aSum = function(num){
	return num.length==0 ? 0 : num.reduce((p,c) => Number(p) + Number(c));
};

var rates = function(r){
	var rr=r;
	if(rr!=0){rr= rr.toString();rr=rr.substr(0,rr.indexOf('.')+3)+'%';}
	return rr;
};

var reateString = function(r){
	var rr=r;
	if(rr!=0){rr= (rr-100).toString();rr=rr.substr(0,rr.indexOf('.')+3)+'%';
		rr=rr.substr(0,1)=='-'?'<span style="color:blue"><i class="fa fa-sort-down"></i> '+rr+'<span>': '<span style="color:red"><i class="fa fa-sort-up"></i> '+rr+'<span>';}
	return rr;
};


function query(params) {
	return new Promise(function(resolve, reject) {
		var data = new gapi.analytics.report.Data({query: params});
		data.once('success', function(response) { resolve(response); })
			.once('error', function(response) { reject(response); })
			.execute();
	});
}

function makeCanvas(id) {
	var container = document.getElementById(id);
	var canvas = document.createElement('canvas');
	var ctx = canvas.getContext('2d');

	container.innerHTML = '';
	canvas.width = container.offsetWidth;
	canvas.height = container.offsetHeight;
	container.appendChild(canvas);

	return ctx;
}

function escapeHtml(str) {
	var div = document.createElement('div');
	div.appendChild(document.createTextNode(str));
	return div.innerHTML;
}

$(document).ready(()=>{
	// 숫자만 입력되도록
	$("input[data-type='num']").on("keyup", (e)=>{
		$(e.target).val($(e.target).val().replace(/[^0-9]/g,""));
	});
});

// 시간을 SNS시간표기법으로 변경 (10초전, 10분전, 10시간전, 1일전, Y-m-d H:i)
let getSnsDate = function(dateTime){
	const nowTime = new Date(); 
	const writeTime = new Date(dateTime);
	const diff = parseInt(nowTime - writeTime) / 1000;

	const s = 60; //1분 = 60초
	const h = s * 60; //1시간 = 60분
	const d = h * 24; //1일 = 24시간
	const y = d * 7; // = 1일 * 2일

	if (diff < s) {
		result = Math.round(diff) + '초전';
	} else if (h > diff && diff >= s) {
		result = Math.round(diff/s) + '분전';
	} else if (d > diff && diff >= h) {
		result = Math.round(diff/h) + '시간전';
	} else if (y > diff && diff >= d) {
		result = Math.round(diff/d) + '일전';
	} else {
		result = dateTime.substring(0,10);
	}
	return result;
}
		
let convertSpecialChar = function (str){
	if(str!=undefined){
		str = str.replace(/&/g, "&amp;").replace(/>/g, "&gt;").replace(/</g, "&lt;").replace(/"/g, "&quot;").replace(/'/g, "&apos;");
	}
	return str;
}


/**
 * html tag를 제거하는 함수
 * @param string str 
 * @param array notStripTags   유지할 태그 배열
 */
let strip_tags = function (str, /*option*/notStripTags){
	// 태그 이름들을 |로 구분된 문자열로 변환 (예: 'br|p|div')
	const tagsPattern = notStripTags.map(tag => `\/?${tag}`).join('|');
	// 정규식 패턴 생성
	console.log(tagsPattern);

	const regex = new RegExp(`<(?!(?:${tagsPattern})\\b)[^>]+>`, 'gi');
	// 정규식을 사용하여 태그 제거
	return str.replace(regex, '');
}