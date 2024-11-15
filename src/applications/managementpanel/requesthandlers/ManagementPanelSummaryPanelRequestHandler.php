<?php

class ManagementPanelSummaryPanelRequestHandler extends ManagementPanelRequestHandler {

    private $chartJsonData;
    private $userID;
    private $request;
    private $dateTo;
    private $dateFrom;
    
    public function handleRequest($request) {
        $this->request = $request;

        $isSent = $request->getStr('isSent') == '1';

        if ($isSent) {
            $from = $request->getStr('from');
            $to = $request->getStr('to');
            
            if ($to == '') {
                $to = $from;
            }


            $fromTimestamp = $this->getTimestampFromInput($from);
            $toTimestamp = $this->getTimestampFromInput($to);

            $this->dateTo = $toTimestamp;
            $this->dateFrom = $fromTimestamp;
            
            $userID = $request->getStr('userID');
            $this->userID = $userID;
            
            $dao =  new TimeTrackerTrackedTime();
            $connection = id($dao)->establishConnection('w');
            
            $result = queryfx_all(
                $connection,
                'SELECT SUM(numMinutes) numMinutes, dateWhenTrackedFor FROM timetracker_trackedtime WHERE userID = %d 
                 AND dateWhenTrackedFor >= %d AND dateWhenTrackedFor <= %d
                 GROUP BY dateWhenTrackedFor ORDER BY dateWhenTrackedFor ASC', $userID, $fromTimestamp, $toTimestamp);
            
            $data = array();
            foreach ($result as $row) {
                $data[] = $row;
            }
            
            $data = $this->fillEmptyDays($fromTimestamp, $toTimestamp, $data);
            $data = $this->sortData($data);
            $data = $this->timestampToReadableDate($data);
            
            $this->chartJsonData = json_encode($data);
        }

    }

    
    private function timestampToReadableDate($data) {
        foreach ($data as &$row) {
            $row['dateWhenTrackedFor'] = date('d-m-y', $row['dateWhenTrackedFor']);
        }
        return $data;
    }
    
    private function sortData($data) {
        usort($data, function($a, $b) {
            return $a['dateWhenTrackedFor'] < $b['dateWhenTrackedFor'] ? -1 : 1;
        });
        return $data;
    }
    
    private function fillEmptyDays($fromTimestamp, $toTimestamp, $data) {
        $rangeOfDays = ($toTimestamp - $fromTimestamp) / ManagementPanelTimeUtils::NUM_SECONDS_IN_DAY + 1;
        $dateWhenTrackedForColumn = array_column($data, 'dateWhenTrackedFor');
        
        for ($i = 0; $i < $rangeOfDays; $i++) {
            $currentDayInRangeDate = $fromTimestamp + $i * ManagementPanelTimeUtils::NUM_SECONDS_IN_DAY;
            if (array_search($currentDayInRangeDate, $dateWhenTrackedForColumn) === false) {
                $data[] = ['numMinutes' => '0', 'dateWhenTrackedFor' => $currentDayInRangeDate];
            }
        }
        return $data;
    }
    
    public function getDateTo(){
        return $this->dateTo;
    }

    public function getDateFrom(){
        return $this->dateFrom;
    }

    public function getChartJsonData() {
        return $this->chartJsonData;
    }
    
    public function getSelectedUserID(){
        return $this->userID;
    }
    private function getTimestampFromInput($dateInput) {
        $dateInput = trim($dateInput);
        $pieces = explode('/', $dateInput);
        
        $day = $pieces[1];
        $month = $pieces[0];
        $year = $pieces[2];
        
        return ManagementPanelTimeUtils::getTimestamp($day, $month, $year);
    }

    public function getRequest() {
        return $this->request;
    }
}
