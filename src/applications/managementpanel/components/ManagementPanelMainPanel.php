<?php

 class ManagementPanelMainPanel extends ManagementPanel {
  
  private $userID = -1;

  public function getName() {
    return pht('Edit vacation hours');
  }
  
  protected function getPanelType() {
      return ManagementPanelPanelType::MAIN;
  }

  public function getDescription() {
      return phutil_safe_html('Here you can change the users vacation hours. <br>
       First select a user. Enter the required number of hours. If you need to add time to the user, enter the value with a minus.<br>
       If you need decrease, then enter the required amount.');
  }
  
  public function renderPage($user) {

      $timeTrackingFormBox = $this->getFormFindUser($user);
      
      $view = new PHUIInfoView();
      $view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $view->setTitle('Examples how to track your time');
      $view->setErrors(
          array(
              phutil_safe_html('<b>8h</b> - if you need <b>decrease</b>'),
              phutil_safe_html('<b>-8h</b> - if you need <b>add</b> time'),
          ));
      $arr = array($view, $timeTrackingFormBox);
      
      $responseBox = $this->getResponseBox();
      $userSetter = new ManagementPanelUser($this->userID);
      if ($responseBox != null) {
          $arr[] = $responseBox;
          
          $date = $this->getRequest()->getStr('date');
          $pieces = explode('/', $date);
          
          $day = $pieces[1];
          $month = $pieces[0];
          $year = $pieces[2];
          
          $timestamp = VacationTimeUtils::getTimestamp($day, $month, $year);

          $dayHistoryDetails = new VacationDayHistoryDetailsBox($this->userID, $timestamp);
          $box = $dayHistoryDetails->getDetailsBox();
          $arr[] = $box;
              
      }

      if($userSetter != null && $userSetter->getID() != -1){

        $arr[] = $this->getUserName($this->userID);  // TODO added needed userID
        $arr[] = $this->getSummaryHoursBox($userSetter); 
        $arr[] = $this->getFormEditVacationTime($user); 
      }
      return $arr;
  }
  
  private function getFormFindUser($user) {
      require_celerity_resource('jquery-js');
      require_celerity_resource('jquery-ui-js');
      require_celerity_resource('timetracker-js');
      require_celerity_resource('jquery-ui-css');
      
      $submit = id(new AphrontFormSubmitControl());
      $submit->setValue(pht('FIND'))
          ->setControlStyle('width: 13%; margin-left: 40%;');

          $form = id(new AphrontFormView())
          ->setUser($user)
          ->addHiddenInput('add', '1')
          ->appendControl(
            id(new AphrontFormTokenizerControl())
              ->setName('add_person')
              ->setUser($user)
              ->setLimit(1)
              ->setDatasource(new PhabricatorPeopleDatasource()))
              ->addHiddenInput('isSet', '1')
              ->addHiddenInput('panelType', $this->getPanelType())
          ->appendChild($submit);

      $box = id(new PHUIObjectBoxView())
        ->setForm($form)
        ->setHeaderText('Select a user')
        ->appendChild(id(new PHUIBoxView()));
      
      return $box;
  }


  private function getFormEditVacationTime($user) {
    require_celerity_resource('jquery-js');
    require_celerity_resource('jquery-ui-js');
    require_celerity_resource('timetracker-js');
    require_celerity_resource('jquery-ui-css');
    
    $submit = id(new AphrontFormSubmitControl());
    $submit->setValue(pht('SAVE'))
        ->setControlStyle('width: 13%; margin-left: 3%;');
    
    $dateFormComponent = (id(new AphrontFormTextControl())
        ->setLabel(pht('Date:'))
        ->setDisableAutocomplete(true)
        ->setName('date')
        ->setValue($this->getPrefilledDate())
        ->setControlStyle('width: 13%; margin-left: 3%;')
        ->setID('datepicker'));

    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->appendChild(id(new AphrontFormTextControl())
      ->setDisableAutocomplete(true)
      ->setControlStyle('width: 13%; margin-left: 3%;')
      ->setLabel(pht('Time:'))
      ->setName('timeTracked')
      ->setValue(''))
      ->addHiddenInput('isSent', '1')
      ->addHiddenInput('userID', $this->userID)
      ->addHiddenInput('panelType', $this->getPanelType())
      ->appendChild($dateFormComponent)
      ->appendChild($submit);        

    $box = id(new PHUIObjectBoxView())
      ->setForm($form)
      ->setHeaderText('Edit vacation hours')
      ->appendChild(id(new PHUIBoxView()));
    
    return $box;
}

  private function getUserName($user) {
      
    $summaryBox = new ManagementPanelUserNameBox($user);
    return $summaryBox->getBox();
}

  private function getSummaryHoursBox($user) {
      
      $summaryBox = new VacationMonthSummaryBox($user);
      return $summaryBox->getBox();
  }
  
  private function getCurrentDate() {
      $currentDay = ManagementPanelTimeUtils::getCurrentDay();
      $currentMonth = ManagementPanelTimeUtils::getCurrentMonth();
      $currentYear = ManagementPanelTimeUtils::getCurrentYear();
      return $currentMonth . '/' . $currentDay . '/' . $currentYear;
  }
  
  private function getResponseBox() {
      $handler = $this->getRequestHandler();
      if ($handler != null) {

        $info = new ManagementPanelUserRequestInfo($handler->getRequest());
        $this->userID = $info->getUserID();

          return $handler->getResponsePanel();
      }
      return null;
  }
  
  private function getPrefilledDate() {
      $handler = $this->getRequestHandler();
      if ($handler != null) {
          return $handler->getRequest()->getStr('date');
      }
      return $this->getCurrentDate();
  }
}
