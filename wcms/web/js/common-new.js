
// event =========================================================================================================================================================
$(function(){

  docMarginTop();
  var deviceWidth = getDeviceWidth();
  if(deviceWidth >= 992){
      if($('body').hasClass('wide')){
          $('body').removeClass('wide');
      }
  }else{
      if(!$('body').hasClass('wide')){
          $('body').addClass('wide');
      }
  }

  // #toggle-nav (메뉴버튼) : click
  $(document).on('click','#toggle-nav',function(){
      $('body').toggleClass('wide');
      if(deviceWidth < 768){
          $('body').removeClass('goUp');
          $('[role="menu"]').css({'height':'100%','min-height':'100vh'});
      }
  });

  // 모바일화면 스크롤 즐겨찾기메뉴
  if(deviceWidth < 768){
    var lastScrollTop = 0;
    var likeHeight = $('[role="menu"] .like-menu').outerHeight();
    var height = likeHeight+'px';
    $(window).scroll(function(){
      var mobileTop = $(this).scrollTop();
      if($('body').hasClass('wide')){
        if (mobileTop > lastScrollTop){
          $('body').removeClass('goUp');
        } else {
          $('body').addClass('goUp');
        }
        lastScrollTop = mobileTop;
      }
      $('.wide.goUp [role="menu"]').css({'height':height,'min-height':height});
      console.log(height)
    });
  }
  
});



// .aside-menu details : click, 클릭된 요소를 제외한 다른 모든 details 요소의 'open' 속성을 제거
$(document).on('click', '.aside-menu details', function() {
  $('.aside-menu details').not(this).removeAttr('open');
});


// 화면 width 감지 : resize
$(window).resize(function(){
  docMarginTop();
  var deviceWidth = getDeviceWidth();
  if(deviceWidth >= 992){
      if($('body').hasClass('wide') && $('body').attr('id') != 'layout'){
          $('body').removeClass('wide');
      }
  }else if(deviceWidth < 992){
      if(!$('body').hasClass('wide') && $('body').attr('id') != 'layout'){
          $('body').addClass('wide');
      }
  }
});



//  datetimepicker 사용시 input에 포커스 할 경우 캘린더 버튼 click 처리
// <div class="search-date" data-target-input="#startDate" id="datetimepicker1" class="datetimepicker-input">
//     <span class="icon-calendar" data-target="#datetimepicker1" data-toggle="datetimepicker"><i class="fa-duotone fa-calendar-day"></i></span>
//     <input type="text" class="half" data-target="#datetimepicker1" id="startDate" name="startDate" value="{_GET.startDate}" placeholder="시작일" autocomplete="off">
// </div>
$(document).on('focus','.datetimepicker-input input', function() {
  $(this).closest('.datetimepicker-input').find('.icon-calendar').click();
});

//  daterangepicker 사용시 캘린더 버튼 click 시 input에 focus 처리
// <div class="search-date datetimerange-input">
// 	<div class="icon-calendar"><i class="fa-duotone fa-calendar-day"></i></div>
// 	<input type="text" id="reservation">
// </div>
$(document).on('click','.datetimerange-input .icon-calendar', function() {
  $(this).closest('.datetimerange-input').find('input').click();
});

// [role="document"] summary[role="button"](아코디언) summary(타이틀) 클릭 시 해당 영역으로 스크롤
// $(document).on('click','[role="document"] summary[role="button"]', function(){
//     var targetDetail = $(this).offset().top - 70;
//     $("html, body").animate({
//         scrollTop: targetDetail
//     }, 500);
// });

// function ======================================================================================================================================================
// mobile device
function isMobile() {
return /iPhone|iPod|Android|Windows CE|BlackBerry|Symbian|Windows Phone|webOS|Opera Mini|Opera Mobi|POLARIS|IEMobile|lgtelecom|nokia|SonyEricsson/i.test(navigator.userAgent)
}

// device width 감지 px단위
function getDeviceWidth() {
  let deviceWidth = $(window).innerWidth();
  return deviceWidth;
}

// [role="document"] margin-top
function docMarginTop(){
  let headerHight = $('[role="header"]').outerHeight();
  let marginTop = headerHight + 'px';
  const doc = $('[role="document"]');
  doc.css('margin-top', marginTop);
  $('body').attr('style','min-height:calc(100vh - '+marginTop+');height:max-content;');
}


/*
* Modal 
*
* Pico.css - https://picocss.com
* Copyright 2019-2023 - Licensed under MIT
*/

// Config
const isOpenClass = "modal-is-open";
const openingClass = "modal-is-opening";
const closingClass = "modal-is-closing";
const animationDuration = 400; // ms
let visibleModal = null;

// Toggle modal
const toggleModal = (event) => {
event.preventDefault();
// button에 trigger('click') 사용 시 오류 발생하여 수정
let target = !!event?.currentTarget ? event.currentTarget.getAttribute("data-target") : event.target.getAttribute("data-target");
const modal = document.getElementById(target);
// const modal = document.getElementById(event.currentTarget.getAttribute("data-target"));
typeof modal != "undefined" && modal != null && isModalOpen(modal)
  ? closeModal(modal)
  : openModal(modal);
};

// Is modal open
const isModalOpen = (modal) => {
return modal.hasAttribute("open") && modal.getAttribute("open") != "false" ? true : false;
};

// Open modal
const openModal = (modal) => {
// if (isScrollbarVisible()) {
//   document.documentElement.style.setProperty("--scrollbar-width", `${getScrollbarWidth()}px`);
// }
document.documentElement.classList.add(isOpenClass, openingClass);
setTimeout(() => {
  visibleModal = modal;
  document.documentElement.classList.remove(openingClass);
}, animationDuration);
modal.setAttribute("open", true);
};

// Close modal
const closeModal = (modal) => {
visibleModal = null;
document.documentElement.classList.add(closingClass);
setTimeout(() => {
  document.documentElement.classList.remove(closingClass, isOpenClass);
  // document.documentElement.style.removeProperty("--scrollbar-width");
  modal.removeAttribute("open");
}, animationDuration);
};

// Close with a click outside
// document.addEventListener("click", (event) => {
//   if (visibleModal != null) {
//     const modalContent = visibleModal.querySelector("article");
//     const isClickInside = modalContent.contains(event.target);
//     !isClickInside && closeModal(visibleModal);
//   }
// });

// Close with Esc key
document.addEventListener("keydown", (event) => {
if (event.key === "Escape" && visibleModal != null) {
  closeModal(visibleModal);
}
});

// Get scrollbar width
const getScrollbarWidth = () => {
// Creating invisible container
const outer = document.createElement("div");
outer.style.visibility = "hidden";
outer.style.overflow = "scroll"; // forcing scrollbar to appear
outer.style.msOverflowStyle = "scrollbar"; // needed for WinJS apps
document.body.appendChild(outer);

// Creating inner element and placing it in the container
const inner = document.createElement("div");
outer.appendChild(inner);

// Calculating difference between container's full width and the child width
const scrollbarWidth = outer.offsetWidth - inner.offsetWidth;

// Removing temporary elements from the DOM
outer.parentNode.removeChild(outer);

return scrollbarWidth;
};

// Is scrollbar visible
const isScrollbarVisible = () => {
return document.body.scrollHeight > screen.height;
};
/* // Modal */