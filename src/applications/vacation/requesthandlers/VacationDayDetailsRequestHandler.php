<?php

class VacationDayDetailsRequestHandler extends VacationRequestHandler {
    
    private $dayDetailsBox = null;
    
    public function handleRequest($request) {
        
        $dayTimestamp = $this->getTimestampFromInput($request->getStr('day'));
        $userID = $request->getUser()->getID();
            
        $box = new VacationDayHistoryDetailsBox($userID, $dayTimestamp);
        $this->dayDetailsBox = $box->getDetailsBox();
    }
    
    private function getTimestampFromInput($dateInput) {
        $dateInput = trim($dateInput);
        $pieces = explode('-', $dateInput);
        
        $day = $pieces[0];
        $month = $pieces[1];
        $year = $pieces[2];
        
        return VacationTimeUtils::getTimestamp($day, $month, $year);
    }
    
    public function getDayDetailsBox() {
        return $this->dayDetailsBox;
    }
}
