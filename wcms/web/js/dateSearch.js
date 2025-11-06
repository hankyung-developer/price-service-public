/**
 * 날짜 계산을 위한 함수
 * @author  Kodes <kodesinfo@gmail.com>
 * @version 1.0
 *
 * @license 해당 프로그램은 kodes에서 제작된 프로그램으로 저작원은 코드스(https://www.kode.co.kr)에 있습니다.
 */
dateSearch = ()=>{
    dateSearch.date = new Date();

    dateSearch.getToday = ()=>{
        return dateSearch.makeDate(dateSearch.date);
    }

    dateSearch.getThisWeekStart = () =>{
        const weekDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth(), dateSearch.date.getDate() - dateSearch.date.getDay());
        return dateSearch.makeDate(weekDay);
    }

    dateSearch.getThisWeekEnd = () =>{
        const weekDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth(), dateSearch.date.getDate() + (6 - dateSearch.date.getDay()) );
        return dateSearch.makeDate(weekDay);
    }

    dateSearch.getLastWeekStart = () =>{
        const weekDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth(), dateSearch.date.getDate() - dateSearch.date.getDay() -7);
        return dateSearch.makeDate(weekDay);
    }

    dateSearch.getLastWeekEnd = () =>{
        const weekDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth(), dateSearch.date.getDate() + (6 - dateSearch.date.getDay())-7 );
        return dateSearch.makeDate(weekDay);
    }

    dateSearch.getThisMonthStart = () =>{
        const monthDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth(), 1 );
        return dateSearch.makeDate(monthDay);
    }

    dateSearch.getThisMonthEnd = () =>{
        const monthDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth()+1, 0);
        return dateSearch.makeDate(monthDay);
    }

    dateSearch.getDiffDay = (day) =>{
        const diffDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth(), dateSearch.date.getDate() + day) ;
        return dateSearch.makeDate(diffDay);
    }

    dateSearch.getDiffMonthStart = (month) =>{
        let monthOption = month ? month : 0;
        const diffDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth()+monthOption, 1) ;
        return dateSearch.makeDate(diffDay);
    }

    dateSearch.getDiffMonthEnd = (month) =>{
        let monthOption = month ? month : 0;
        const diffDay = new Date(dateSearch.date.getFullYear(), dateSearch.date.getMonth()+monthOption+1, 0) ;
        return dateSearch.makeDate(diffDay);
    }

    dateSearch.getDiffYear = (year) =>{
        const diffDay = new Date(dateSearch.date.getFullYear()+year, dateSearch.date.getMonth(), dateSearch.date.getDate() + 1) ;
        return dateSearch.makeDate(diffDay);
    }

    dateSearch.makeDate = (date)=>{
        return date.getFullYear()+ "-" + (date.getMonth()+1+"").padStart(2, "0") + "-" + (date.getDate()+"").padStart( 2, "0");
    }
}
dateSearch();