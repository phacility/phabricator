<?php

final class TimeTrackerSummaryPanel extends TimeTracker {

  public function getName() {
      return pht('Summary');
  }

  public function getDescription() {
      return phutil_safe_html('Pick the date range and click GO to check your working hours between those dates.<br />
                  Click on concrete day to view tracked time history.');
  }
  
  protected function getPanelType() {
      return TimeTrackerPanelType::SUMMARY;
  }
  
  public function renderPage($user) {

      $dateRangeFormBox = $this->getDateRangeFormBox($user);
      $chartBox = $this->getChartBox();
      $dayDetailsBox = $this->getDayDetailsBox();
      $dateDetailsBox = $this->getDateDatailsBox($user);

      $elements = array();
      $elements[] = $dateRangeFormBox;
      if ($chartBox != null) {
          $elements[] = $chartBox;
      }
      if ($dayDetailsBox != null) {
          $elements[] = $dayDetailsBox;
      }
      if($dateDetailsBox != null){
        $elements[] = $dateDetailsBox;
      }
      return $elements;
  }
  
  private function getDateRangeFormBox($user) {
      require_celerity_resource('jquery-js');
      require_celerity_resource('jquery-ui-js');
      require_celerity_resource('timetracker-js');
      require_celerity_resource('jquery-ui-css');
      
      $submit = id(new AphrontFormSubmitControl());
      $submit->setValue(pht('GO'))
          ->setControlStyle('width: 13%; margin-left: 3%;');
      
      $fromDateInput = (id(new AphrontFormTextControl())
          ->setLabel(pht('From: '))
          ->setDisableAutocomplete(true)
          ->setName('from')
          ->setValue('')
          ->setID('datepicker')
          ->setControlStyle('width: 13%; margin-left: 3%;'));
      
      $toDateInput = (id(new AphrontFormTextControl())
          ->setLabel(pht('To: '))
          ->setDisableAutocomplete(true)
          ->setName('to')
          ->setValue('')
          ->setID('datepicker2')
          ->setControlStyle('width: 13%; margin-left: 3%;'));
      
      $form = id(new AphrontFormView())
        ->setUser($this->getRequest()->getUser())
        ->appendChild($fromDateInput)
        ->appendChild($toDateInput)
        ->addHiddenInput('isSent', '1')
        ->addHiddenInput('panelType', $this->getPanelType())
        ->appendChild($submit);
          
      $box = id(new PHUIObjectBoxView())
        ->setForm($form)
        ->setHeaderText('Pick days range')
        ->appendChild(id(new PHUIBoxView()));
      
      return $box;
  }
  
  private function getChartBox() {
      $requestHandler = $this->getRequestHandler();
      if ($requestHandler == null || !($requestHandler instanceof TimeTrackerSummaryPanelRequestHandler)) {
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
      
      $box = id(new PHUIObjectBoxView())
        ->setHeader('Title')
        ->appendChild($content)
        ->appendChild(id(new PHUIBoxView()));
      
      return $box;
  }

  private function getDateDatailsBox($user){
    $requestHandler = $this->getRequestHandler();
      if ($requestHandler == null || !($requestHandler instanceof TimeTrackerSummaryPanelRequestHandler)) {
          return null;
      }

      $form = $requestHandler->getDateFrom();
      $to = $requestHandler->getDateTo();

    $dateBox = new TimeTrackerProjectDateSummaryBox($user, $form, $to);
    return $dateBox->getBox();

  }
  
  private function getDayDetailsBox() {
      $requestHandler = $this->getRequestHandler();
      if ($requestHandler == null || !($requestHandler instanceof TimeTrackerDayDetailsRequestHandler)) {
          return null;
      }
      
      $box = $requestHandler->getDayDetailsBox();
      return $box;
  }
}
