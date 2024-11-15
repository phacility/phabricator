<?php

class VacationMonthSummaryBox {

    private $user;
    
    public function __construct($user) {
        $this->user = $user;
    }
    public function getBox() {       

        $totalMinutesToday = VacationStorageManager::getNumMinutesTrackedToday($this->user);

        $totalMinutesForAllTime = VacationTimeUtils::getNumMinutesVacation($this->user);

        $timeTrackedThisMonth = VacationTimeUtils::numMinutesToString($totalMinutesForAllTime);

        $timeTrackedToday = VacationTimeUtils::numMinutesToString($totalMinutesToday);
        
        $view = new PHUIInfoView();
        $view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $view->setTitle('Vacation hours');
        $view->setErrors(
            array(
                phutil_safe_html('All time: <b>' . $timeTrackedThisMonth . '</b>'),
                phutil_safe_html('Used today: <b>' . $timeTrackedToday . '</b>'),
            ));
        return $view;
    }
}