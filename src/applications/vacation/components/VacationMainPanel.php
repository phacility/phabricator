<?php

final class VacationMainPanel extends Vacation {

  public function getName() {
    return pht('Hours tracking');
  }
  
  protected function getPanelType() {
      return VacationPanelType::MAIN;
  }

  public function getDescription() {
      return phutil_safe_html('Here you can track your vacation time. Simply put amount of time you want to track and click SAVE.<br />
          You can also subtract time if you make a mistake, you have <b>one</b> day for that.<br />
          To check how much time you already tracked, use <b>summary</b> page.');
  }
  
  public function renderPage($user) {

      $timeTrackingFormBox = $this->getTimeTrackingFormBox($user);
      
      $view = new PHUIInfoView();
      $view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $view->setTitle('Examples how to track your vacation hours');
      $view->setErrors(
          array(
              pht('8h'),
              phutil_safe_html('-8h <i>(deduct 8hours, use when you made a mistake)</i>'),
          ));
      $arr = array($view, $timeTrackingFormBox);

      $arr[] = $this->getSummaryHoursBox($user);
      
      $responseBox = $this->getResponseBox();
      if ($responseBox != null) {
          $arr[] = $responseBox;
          
          $date = $this->getRequest()->getStr('isDate');
          $pieces = explode('/', $date);
          
          $day = $pieces[1];
          $month = $pieces[0];
          $year = $pieces[2];
          
          $timestamp = VacationTimeUtils::getTimestamp($day, $month, $year);
          $dayHistoryDetails = new VacationDayHistoryDetailsBox($user->getID(), $timestamp);
          $box = $dayHistoryDetails->getDetailsBox();
          $arr[] = $box;
      }
     
      return $arr;
  }
  
  private function getTimeTrackingFormBox($user) {
      require_celerity_resource('jquery-js');
      require_celerity_resource('jquery-ui-js');
      require_celerity_resource('timetracker-js');
      require_celerity_resource('jquery-ui-css');
      
      $submit = id(new AphrontFormSubmitControl());
      $submit->setValue(pht('SAVE'))
          ->setControlStyle('width: 13%; margin-left: 3%; box-sizing : content-box;');
      
      $dateFormComponent = (id(new AphrontFormTextControl())
          ->setLabel(pht('Date:'))
          ->setDisabled('disabled')
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
        ->addHiddenInput('isDate', $this->getPrefilledDate())
        ->addHiddenInput('panelType', $this->getPanelType())
        ->appendChild($dateFormComponent)
        ->appendChild($submit);        

      $box = id(new PHUIObjectBoxView())
        ->setForm($form)
        ->setHeaderText('Track your vacation hours')
        ->appendChild(id(new PHUIBoxView()));
      
      return $box;
  }

  private function getSummaryHoursBox($user) {
      
      $summaryBox = new VacationMonthSummaryBox($user);
      return $summaryBox->getBox();
  }
  
  private function getCurrentDate() {
      $currentDay = VacationTimeUtils::getCurrentDay();
      $currentMonth = VacationTimeUtils::getCurrentMonth();
      $currentYear = VacationTimeUtils::getCurrentYear();
      return $currentMonth . '/' . $currentDay . '/' . $currentYear;
  }
  
  private function getResponseBox() {
      $handler = $this->getRequestHandler();
      if ($handler != null) {
          return $handler->getResponsePanel();
      }
      return null;
  }
  
  private function getPrefilledDate() {
      $handler = $this->getRequestHandler();
      if ($handler != null) {
          return $handler->getRequest()->getStr('isDate');
      }
      return $this->getCurrentDate();
  }
}
