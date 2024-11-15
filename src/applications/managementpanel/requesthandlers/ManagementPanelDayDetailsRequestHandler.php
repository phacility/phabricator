<?php

class ManagementPanelDayDetailsRequestHandler extends ManagementPanelRequestHandler {
    
    private $dayDetailsBox = null;

    private $request;
    private $userID;
    
    public function handleRequest($request) {
        
        $this->request = $request;

        $dayTimestamp = $this->getTimestampFromInput($request->getStr('day'));
        $box = new ManagementPanelDayHistoryDetailsBox($this->userID, $dayTimestamp);
        $this->dayDetailsBox = $box->getDetailsBox();
        $box = new ManagementPanelUserNameBox($this->userID);
    }
    
    private function getTimestampFromInput($dateInput) {
        $dateInput = trim($dateInput);
        $pieces = explode('-', $dateInput);
        
        $day = $pieces[0];
        $month = $pieces[1];
        $year = $pieces[2];
        
        return ManagementPanelTimeUtils::getTimestamp($day, $month, $year);
    }
    
    
    public function getDayDetailsBox() {
        return $this->dayDetailsBox;
    }

    public function getRequest() {
        return $this->request;
    }
}
