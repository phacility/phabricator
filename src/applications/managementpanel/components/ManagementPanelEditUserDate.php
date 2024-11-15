<?php

class ManagementPanelEditUserDate extends ManagementPanel
{

  private $userID = -1;

  public function getName()
  {
    return pht('Edit registration date');
  }

  protected function getPanelType()
  {
    return ManagementPanelPanelType::SECOND;
  }

  public function getDescription()
  {
    return phutil_safe_html('Here you can change the user registration date.<br>
     First, select a user. Then enter/select a new registration date.');
  }

  public function renderPage($user)
  {

    $timeTrackingFormBox = $this->getFormFindUser($user);

    $view = new PHUIInfoView();
    $view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
    $view->setTitle('Examples how to edit user registration date');
    $view->setErrors(
      array(
        pht('Select needed user -> click FIND'),
        pht('Select new regestration date (01/01/2000)'),
        pht('Click SAVE')
      )
    );
    $arr = array($view, $timeTrackingFormBox);

    $responseBox = $this->getResponseBox();
    $userSetter = new ManagementPanelUser($this->userID);
    if ($responseBox != null) {
    }

    if ($userSetter != null && $userSetter->getID() != -1) {

      $arr[] = $this->getUserName($this->userID);  
      $arr[] = $this->getUserDateRegistrationBox($this->userID);
      $arr[] = $this->getFormEditUserDateRegistration($user);
    }
    return $arr;
  }

  private function getFormFindUser($user)
  {
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
          ->setDatasource(new PhabricatorPeopleDatasource())
      )
      ->addHiddenInput('isSet', '1')
      ->addHiddenInput('panelType', $this->getPanelType())
      ->appendChild($submit);

    $box = id(new PHUIObjectBoxView())
      ->setForm($form)
      ->setHeaderText('Select a user')
      ->appendChild(id(new PHUIBoxView()));

    return $box;
  }


  private function getFormEditUserDateRegistration($user)
  {
    require_celerity_resource('jquery-js');
    require_celerity_resource('jquery-ui-js');
    require_celerity_resource('timetracker-js');
    require_celerity_resource('jquery-ui-css');

    $submit = id(new AphrontFormSubmitControl());
    $submit->setValue(pht('SAVE'))
      ->setControlStyle('width: 13%; margin-left: 3%;');

    $fromDateInput = (id(new AphrontFormTextControl())
      ->setLabel(pht('Date:'))
      ->setDisableAutocomplete(true)
      ->setName('newDate')
      ->setValue('')
      ->setID('datepicker')
      ->setControlStyle('width: 13%; margin-left: 3%;'));

    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->appendChild($fromDateInput)
      ->addHiddenInput('isSent', '1')
      ->addHiddenInput('userID', $this->userID)
      ->addHiddenInput('panelType', $this->getPanelType())
      ->appendChild($submit);

    $box = id(new PHUIObjectBoxView())
      ->setForm($form)
      ->setHeaderText('Edit vacation hours')
      ->appendChild(id(new PHUIBoxView()));

    return $box;
  }

  private function getUserName($user)
  {

    $summaryBox = new ManagementPanelUserNameBox($user);
    return $summaryBox->getBox();
  }

  private function getUserDateRegistrationBox($user)
  {

    $summaryBox = new ManagementPanelUserDateRegistrationBox($user);
    return $summaryBox->getBox();
  }

  private function getCurrentDate()
  {
    $currentDay = ManagementPanelTimeUtils::getCurrentDay();
    $currentMonth = ManagementPanelTimeUtils::getCurrentMonth();
    $currentYear = ManagementPanelTimeUtils::getCurrentYear();
    return $currentMonth . '/' . $currentDay . '/' . $currentYear;
  }

  private function getResponseBox()
  {
    $handler = $this->getRequestHandler();
    if ($handler != null) {

      $info = new ManagementPanelUserRequestInfo($handler->getRequest());
      $this->userID = $info->getUserID();

      return $handler->getResponsePanel();
    }
    return null;
  }

  private function getPrefilledDate()
  {
    $handler = $this->getRequestHandler();
    if ($handler != null) {
      return $handler->getRequest()->getStr('date');
    }
    return $this->getCurrentDate();
  }
}
