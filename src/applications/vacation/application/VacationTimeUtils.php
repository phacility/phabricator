<?php

class VacationTimeUtils {
    
    const NUM_SECONDS_IN_DAY = 86400;
    const NUM_YEARS_FOR_BONUS = 6;
    const COEF_BASE_VACATION = 11;
    const COEF_BONUS_VACATION = 9.57;
    const NUM_DAYS_ABLE_UNDO = 2;
    
    public static function getCurrentYear() {
        $date = new DateTime('@' . time());
        return PhutilTranslator::getInstance()->translateDate('Y', $date);
    }
    
    public static function getCurrentMonth() {
        $date = new DateTime('@' . time());
        return PhutilTranslator::getInstance()->translateDate('m', $date);
    }
    
    public static function getCurrentDay() {
        $date = new DateTime('@' . time());
        return PhutilTranslator::getInstance()->translateDate('d', $date);
    }
    
    public static function getTodayTimestamp() {
        $currentDay = VacationTimeUtils::getCurrentDay();
        $currentMonth = VacationTimeUtils::getCurrentMonth();
        $currentYear = VacationTimeUtils::getCurrentYear();
        
        return VacationTimeUtils::getTimestamp($currentDay, $currentMonth, $currentYear);
    }
    
    public static function getTimestamp($day, $month, $year) {
        return strtotime($year . '-' . $month . '-' . $day);
    }
    
    public static function getDateRegistrationUser($user){
        return $user->getDateCreated();
    }

    public static function numMinutesToString($numMinutes) {
        $isNegative = $numMinutes < 0;
        
        $numMinutes = abs($numMinutes);
        if ($numMinutes < 60) {
            $str = '';
            if ($isNegative) {
                $str .= '-';
            }
            $str .= $numMinutes . ' minutes';
            return $str;
        }
        
        $numHours = floor($numMinutes / 60);
        $remainingMinutes = $numMinutes % 60;
        
        $str = '';
        if ($isNegative) {
            $str .= '-';
        }
        $str .= $numHours . ' hours';
        if ($remainingMinutes > 0) {
            $str .= ' ' . $remainingMinutes . ' minutes';
        }
        return $str;
    }

    public static function getNumTrackedMinutes($user){
        
        $firstDayOfStartWork = strtotime(date('1998-m-01'));  // Should be changed as soon as possible to an adaptive value
        $lastDayOfMonth = strtotime(date('Y-m-t'));
        $today = VacationTimeUtils::getTodayTimestamp();
        
        $dao = new TimeTrackerTrackedTime();
        $connection = id($dao)->establishConnection('w');
        
        $result = queryfx_all(
            $connection,
            'SELECT SUM(numMinutes) numMinutes, dateWhenTrackedFor FROM timetracker_trackedtime WHERE userID = %d
                 AND dateWhenTrackedFor >= %d AND dateWhenTrackedFor <= %d
                 GROUP BY dateWhenTrackedFor ORDER BY dateWhenTrackedFor ASC', $user->getID(), $firstDayOfStartWork, $lastDayOfMonth);
        
        $totalMinutesForAllTime = 0;
        foreach ($result as $row) {
           $totalMinutesForAllTime += $row['numMinutes'];
        }

        return $totalMinutesForAllTime;
    }


    public static function getNumTrackedMinutesVacation($user){

        $firstDayOfStartWork = strtotime(date('1998-m-01'));  // Should be changed as soon as possible to an adaptive value
        $lastDayOfMonth = strtotime(date('Y-m-t'));
        
        $dao = new VacationVacationDay();
        $connection = id($dao)->establishConnection('w');

        $result = queryfx_all(
            $connection,
            'SELECT SUM(numMinutes) numMinutes, dateWhenTrackedFor FROM vacation_day WHERE userID = %d
                 AND dateWhenTrackedFor >= %d AND dateWhenTrackedFor <= %d
                 GROUP BY dateWhenTrackedFor ORDER BY dateWhenTrackedFor ASC', $user->getID(), $firstDayOfStartWork, $lastDayOfMonth);
        
       $totalMinutesVacationTracked = 0;
        foreach ($result as $row) {
            $totalMinutesVacationTracked += $row['numMinutes'];
        }

        return $totalMinutesVacationTracked;
    }

    public static function getNumMinutesTrackedForDateRange($user){  

        $d = '-'.VacationTimeUtils::NUM_DAYS_ABLE_UNDO.'days';
        $date = strtotime($d);
        $firstDay = date('Y-m-d', $date);

        $date = $firstDay;
                $date = trim($date);
                $pieces = explode('-', $date);
                
                $day = $pieces[2];
                $month = $pieces[1];
                $year = $pieces[0];

        $date = VacationTimeUtils::getTimestamp($day,$month,$year);

        $firstDay = $date;
        $lastDayOfMonth = strtotime(date('Y-m-t'));

        $dao = new VacationVacationDay();
        $connection = id($dao)->establishConnection('w');

        $result = queryfx_all(
            $connection,
            'SELECT SUM(numMinutes) numMinutes, dateWhenTrackedFor FROM vacation_day WHERE userID = %d
                 AND dateWhenTrackedFor >= %d AND dateWhenTrackedFor <= %d
                 GROUP BY dateWhenTrackedFor ORDER BY dateWhenTrackedFor ASC', $user->getID(), $firstDay, $lastDayOfMonth);
        
       $totalMinutesVacationTracked = 0;
        foreach ($result as $row) {
            $totalMinutesVacationTracked += $row['numMinutes'];
        }

        return $totalMinutesVacationTracked;
    }
    public static function getNumMinutesVacation($user){

       $totalMinutesForAllTime = VacationTimeUtils::getNumTrackedMinutes($user);
        
       $totalMinutesVacationTracked = VacationTimeUtils::getNumTrackedMinutesVacation($user);
        
       $dataCreatedUser = VacationTimeUtils::getDateRegistrationUser($user);

       $date = date("Y-m-d  H:i:s", $dataCreatedUser);     
       $date = new DateTime($date);
       $dateDiff = date_diff(new DateTime(), $date)->y;      

       if($dateDiff >= VacationTimeUtils::NUM_YEARS_FOR_BONUS){
        $totalMinutesForAllTime = $totalMinutesForAllTime / VacationTimeUtils::COEF_BONUS_VACATION;
        }
       else{
        $totalMinutesForAllTime = $totalMinutesForAllTime / VacationTimeUtils::COEF_BASE_VACATION;
        }

        $totalMinutesForAllTime = $totalMinutesForAllTime - $totalMinutesVacationTracked;

        return $totalMinutesForAllTime;
        
    }

    
}