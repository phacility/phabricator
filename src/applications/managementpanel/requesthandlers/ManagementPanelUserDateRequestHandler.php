<?php

class ManagementPanelUserDateRequestHandler extends ManagementPanelRequestHandler {


    private $responsePanel = null;
    private $request = null;
    private $user;
    
    public function handleRequest($request) {
        $this->request = $request;
        
        $isSent = $request->getStr('isSent') == '1';

        if ($isSent) {

            $this->user = new ManagementPanelUser($request->getStr('userID'));
            $newDate = $request->getStr('newDate');

            $newDateUserRegistration = $this->getTimestampFromInput($newDate); 

            $this->setNewDate($this->user, $newDateUserRegistration);

           $this->responsePanel = $this->createResponsePanel(true, $newDate);
        }
    }

     private function setNewDate($user, $date) {
      $userID = $user->getID();

      $dao = new PhabricatorUser();
      $connection = id($dao)->establishConnection('w');
      $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
      $dao->openTransaction();

      queryfx($connection, 'UPDATE user SET dateCreated = %d WHERE id = %d',
        $date, $userID);

      $dao->saveTransaction();
      unset($guard);
  }

  private function createResponsePanel($success, $date) {
    $severity = $success ? PHUIInfoView::SEVERITY_SUCCESS : PHUIInfoView::SEVERITY_ERROR;
    $responseText = '';
    if ($success) {
        $responseText = 'New date tracked '.$date;
    }
    else {
        $responseText = 'Incorrect input';
    }
    
    $view = new PHUIInfoView();
    $view->setSeverity($severity);
    $view->setErrors(array(pht($responseText)));
    return $view;
}
  
    private function getTimestampFromInput($dateInput) {
        $dateInput = trim($dateInput);
        $pieces = explode('/', $dateInput);
        
        $day = $pieces[1];
        $month = $pieces[0];
        $year = $pieces[2];
        
        return ManagementPanelTimeUtils::getTimestamp($day, $month, $year);
    }

    public function getResponsePanel() {
        return $this->responsePanel;
    }
    
    public function getRequest() {
        return $this->request;
    }
}
