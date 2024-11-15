<?php

class TimeTrackerProjectDateSummaryBox {
    private $user;
    private $firstDayTracked;
    private $lastDayTracked;
    
    public function __construct($user, $firstDayTracked, $lastDayTracked) {
        $this->user = $user;
        $this->firstDayTracked = $firstDayTracked;
        $this->lastDayTracked = $lastDayTracked;
    }
    
    /* Add first day and the last day of current week
     * CAREFUL! Not so easy as it seems to be
     * Results from built-in PHP functions may return incorrect values, depending on PHP version
     */
    public function getBox() {
        $firstDayOfMonth = $this->firstDayTracked;
        $lastDayOfMonth = $this->lastDayTracked;
         
        $dao = new TimeTrackerTrackedTime();
        $connection = id($dao)->establishConnection('w');
        $projects = queryfx_all(
            $connection,
            'SELECT projectPHID FROM timetracker_trackedtime WHERE userID = %d
                 AND dateWhenTrackedFor >= %d AND dateWhenTrackedFor <= %d AND projectPHID != %s
                 GROUP BY projectPHID', $this->user->getID(), $firstDayOfMonth, $lastDayOfMonth, '');

        $list = new PHUIStatusListView();

        foreach ($projects as $row) {
           $projectPHID = $row['projectPHID'];

           $projectName = TimeTrackerStorageManager::getNameSelectedProject($projectPHID);
           
         $result = queryfx_all(
            $connection,
            'SELECT SUM(numMinutes) numMinutes, dateWhenTrackedFor FROM timetracker_trackedtime WHERE userID = %d
                 AND dateWhenTrackedFor >= %d AND dateWhenTrackedFor <= %d AND projectPHID = %s
                 GROUP BY dateWhenTrackedFor ORDER BY dateWhenTrackedFor ASC', $this->user->getID(), $firstDayOfMonth, $lastDayOfMonth, $projectPHID);
        
        $totalMinutesThisProjectOnMonth = 0;
        foreach ($result as $row) {
             $totalMinutesThisProjectOnMonth += $row['numMinutes'];
        }
        
        if($totalMinutesThisProjectOnMonth > 0){

            $timeTrackedThisMonth = TimeTrackerTimeUtils::numMinutesToString($totalMinutesThisProjectOnMonth);

            $list->addItem(id(new PHUIStatusItemView())
            ->setIcon(PHUIStatusItemView::ICON_CLOCK, 'black', pht(''))
            ->setTarget(pht($projectName))
            ->setNote(pht($timeTrackedThisMonth.' tracked')));
        }
        

        }
        
        $box = id(new PHUIObjectBoxView())
           ->setHeaderText('All tracked time on projects from '. date("m-d-Y", $this->firstDayTracked) . ' to '.date("m-d-Y", $this->lastDayTracked))
           ->appendChild($list);
        return $box;
    }
}