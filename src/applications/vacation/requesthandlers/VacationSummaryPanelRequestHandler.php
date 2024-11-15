<?php

class VacationSummaryPanelRequestHandler extends VacationRequestHandler {

    private $chartJsonData;
    
    public function handleRequest($request) {
        $isSent = $request->getStr('isSent') == '1';
        if ($isSent) {
            $from = $request->getStr('from');
            $to = $request->getStr('to');
            
            if ($to == '') {
                $to = $from;
            }
            
            $fromTimestamp = $this->getTimestampFromInput($from);
            $toTimestamp = $this->getTimestampFromInput($to);
            $userID = $request->getUser()->getID();
            
            $dao = new VacationVacationDay();
            $connection = id($dao)->establishConnection('w');
            
            $result = queryfx_all(
                $connection,
                'SELECT SUM(numMinutes) numMinutes, dateWhenTrackedFor FROM vacation_day WHERE userID = %d 
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
        $rangeOfDays = ($toTimestamp - $fromTimestamp) / VacationTimeUtils::NUM_SECONDS_IN_DAY + 1;
        $dateWhenTrackedForColumn = array_column($data, 'dateWhenTrackedFor');
        
        for ($i = 0; $i < $rangeOfDays; $i++) {
            $currentDayInRangeDate = $fromTimestamp + $i * VacationTimeUtils::NUM_SECONDS_IN_DAY;
            if (array_search($currentDayInRangeDate, $dateWhenTrackedForColumn) === false) {
                $data[] = ['numMinutes' => '0', 'dateWhenTrackedFor' => $currentDayInRangeDate];
            }
        }
        return $data;
    }
    
    public function getChartJsonData() {
        return $this->chartJsonData;
    }
    
    private function getTimestampFromInput($dateInput) {
        $dateInput = trim($dateInput);
        $pieces = explode('/', $dateInput);
        
        $day = $pieces[1];
        $month = $pieces[0];
        $year = $pieces[2];
        
        return VacationTimeUtils::getTimestamp($day, $month, $year);
    }
}
