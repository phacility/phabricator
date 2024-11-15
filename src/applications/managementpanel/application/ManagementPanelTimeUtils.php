<?php

class ManagementPanelTimeUtils {
    
    const NUM_SECONDS_IN_DAY = 86400;
    
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
        $currentDay = ManagementPanelTimeUtils::getCurrentDay();
        $currentMonth = ManagementPanelTimeUtils::getCurrentMonth();
        $currentYear = ManagementPanelTimeUtils::getCurrentYear();
        
        return ManagementPanelTimeUtils::getTimestamp($currentDay, $currentMonth, $currentYear);
    }
    
    public static function getTimestamp($day, $month, $year) {
        return strtotime($year . '-' . $month . '-' . $day);
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
}