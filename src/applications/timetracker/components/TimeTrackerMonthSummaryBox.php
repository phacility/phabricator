<?php

class TimeTrackerMonthSummaryBox {
    private $user;
    
    public function __construct($user) {
        $this->user = $user;
    }
    
    /* Add first day and the last day of current week
     * CAREFUL! Not so easy as it seems to be
     * Results from built-in PHP functions may return incorrect values, depending on PHP version
     */
    public function getBox() {
        $firstDayOfMonth = strtotime(date('Y-m-01'));
        $lastDayOfMonth = strtotime(date('Y-m-t'));
        $today = TimeTrackerTimeUtils::getTodayTimestamp();
        
        $dao = new TimeTrackerTrackedTime();
        $connection = id($dao)->establishConnection('w');
        
        $result = queryfx_all(
            $connection,
            'SELECT SUM(numMinutes) numMinutes, dateWhenTrackedFor FROM timetracker_trackedtime WHERE userID = %d
                 AND dateWhenTrackedFor >= %d AND dateWhenTrackedFor <= %d
                 GROUP BY dateWhenTrackedFor ORDER BY dateWhenTrackedFor ASC', $this->user->getID(), $firstDayOfMonth, $lastDayOfMonth);
        
        $totalMinutesThisMonth = 0;
        foreach ($result as $row) {
           $totalMinutesThisMonth += $row['numMinutes'];
        }

        $totalMinutesToday = TimeTrackerStorageManager::getNumMinutesTrackedToday($this->user);
        
        $timeTrackedThisMonth = TimeTrackerTimeUtils::numMinutesToString($totalMinutesThisMonth);
        $timeTrackedToday = TimeTrackerTimeUtils::numMinutesToString($totalMinutesToday);
        
        $view = new PHUIInfoView();
        $view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $view->setTitle('Total tracked time');
        $view->setErrors(
            array(
                phutil_safe_html('This month: <b>' . $timeTrackedThisMonth . '</b>'),
                phutil_safe_html('Today: <b>' . $timeTrackedToday . '</b>'),
            ));
        return $view;
    }
}