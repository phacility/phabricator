<?php

final class ManagementPanelSummaryPanel extends ManagementPanel
{

  private $userID = -1;

  public function getName()
  {
    return pht('Working time summary');
  }

  public function getDescription()
  {
    return phutil_safe_html('Here you can view the number of tracked hours on projects over a period of time.<br>
    First, select a user. Then pick the date range and click GO to check your working hours between those dates.<br />');
  }

  protected function getPanelType()
  {
    return ManagementPanelPanelType::SUMMARY;
  }

  public function renderPage($user)
  {
    $formFindUser = $this->getFormFindUser($user);

    $elements = array();

    $elements[] = $formFindUser;

    $this->getResponseBox();

    $userSetter = new ManagementPanelUser($this->userID);

    if ($userSetter != null && $userSetter->getID() != -1) {
      $elements[] = $this->getUserName($this->userID);

      $dateRangeFormBox = $this->getDateRangeFormBox($userSetter->getID());
      $elements[] = $dateRangeFormBox;
    }
    $requestHandler = $this->getRequestHandler();
    if ($requestHandler != null) {

      $chartBox = null;

      $isSent = $requestHandler->getRequest()->getStr('isSent') == '1';

     if ($isSent != null && $isSent) {
        $chartBox = $this->getChartBox();
      }
        
      if ($chartBox != null) {
        $elements[] = $chartBox;
      }
    }

    return $elements;
  }

  private function getDateRangeFormBox($user)
  {
    require_celerity_resource('jquery-js');
    require_celerity_resource('jquery-ui-js');
    require_celerity_resource('timetracker-js');
    require_celerity_resource('jquery-ui-css');

    $submit = id(new AphrontFormSubmitControl());
    $submit->setValue(pht('GO'))
      ->setControlStyle('width: 13%; margin-left: 3%;');

    $fromDateInput = (id(new AphrontFormTextControl())
      ->setLabel(pht('From: '))
      ->setDisableAutocomplete(false)
      ->setName('from')
      ->setValue('')
      ->setID('datepicker')
      ->setControlStyle('width: 13%; margin-left: 3%;'));

    $toDateInput = (id(new AphrontFormTextControl())
      ->setLabel(pht('To: '))
      ->setDisableAutocomplete(false)
      ->setName('to')
      ->setValue('')
      ->setID('datepicker2')
      ->setControlStyle('width: 13%; margin-left: 3%;'));
      
    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->appendChild($fromDateInput)
      ->appendChild($toDateInput)
      ->addHiddenInput('isSent', '1')
      ->addHiddenInput('userID', $this->userID)
      ->addHiddenInput('panelType', $this->getPanelType())
      ->appendChild($submit);

    $box = id(new PHUIObjectBoxView())
      ->setForm($form)
      ->setHeaderText('Pick days range')
      ->appendChild(id(new PHUIBoxView()));

    return $box;
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
      ->setHeaderText('Select user')
      ->appendChild(id(new PHUIBoxView()));

    return $box;
  }


  private function getUserName($user)
  {

    $summaryBox = new ManagementPanelUserNameBox($user);
    return $summaryBox->getBox();
  }

  private function getChartBox()
  {
    $requestHandler = $this->getRequestHandler();
    if ($requestHandler == null || !($requestHandler instanceof ManagementPanelSummaryPanelRequestHandler)) {
      return null;
    }
    $chartJsonData = $requestHandler->getChartJsonData();

    $map = CelerityResourceMap::getNamedInstance('phabricator');
    $chart = CelerityAPI::getStaticResourceResponse()->getURI($map, 'rsrc/js/application/timetracker/chart/Chart.min.js');
    $jquery = CelerityAPI::getStaticResourceResponse()->getURI($map, 'rsrc/js/application/timetracker/chart/jquery.min.js');
    $showGraph = CelerityAPI::getStaticResourceResponse()->getURI($map, 'rsrc/js/application/timetracker/chart/show-graph.js');

    $widthPercentage = '65%';
    $content = phutil_safe_html(pht('<div style="width: %s;" id="chart-container">
        <canvas id="graphCanvas"></canvas></div>
        <script type=text/javascript src="%s"></script>
        <script type=text/javascript src="%s"></script>
        <script type=text/javascript src="%s"></script>
        <script type=text/javascript>showGraph(%s);</script>', $widthPercentage, $jquery, $chart, $showGraph, $chartJsonData));

    $userSelected = new ManagementPanelUser($requestHandler->getSelectedUserID());
    $from = $requestHandler->getDateFrom();
    $to = $requestHandler->getDateTo();
    $projectsInfo = $this->getDetailsTrackedHoursOnProjects($userSelected, $from, $to);

    $box = id(new PHUIObjectBoxView())
      ->setHeader('Title')
      ->appendChild($content)
      ->appendChild($projectsInfo)
      ->appendChild(id(new PHUIBoxView()));

    return $box;
  }

  private function getDetailsTrackedHoursOnProjects($user, $fromDateInput, $toDateInput)
  {
    $projectsInfo = new TimeTrackerProjectDateSummaryBox($user, $fromDateInput, $toDateInput);
    return $projectsInfo->getBox();
  }
  private function getResponseBox()
  {
    $handler = $this->getRequestHandler();
    if ($handler != null) {

      $info = new ManagementPanelUserRequestInfo($handler->getRequest());
      $this->userID = $info->getUserID();
    // $this->userID = $handler->getRequest()->getStr('userID');
    }
  }

  private function getDayDetailsBox()
  {
    $requestHandler = $this->getRequestHandler();
    if ($requestHandler == null || !($requestHandler instanceof ManagementPanelDayDetailsRequestHandler)) {
      return null;
    }

    $box = $requestHandler->getDayDetailsBox();
    return $box;
  }
}
