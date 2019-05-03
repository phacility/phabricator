<?php

abstract class PhabricatorChartEngine
  extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function getChartEngineKey() {
    return $this->getPhobjectClassConstant('CHARTENGINEKEY', 32);
  }

  abstract protected function newChart();

  final public function buildChart() {
    $viewer = $this->getViewer();

    $chart = $this->newChart();

    $rendering_engine = id(new PhabricatorChartRenderingEngine())
      ->setViewer($viewer)
      ->setChart($chart);

    return $rendering_engine->getStoredChart();
  }

  final public function buildChartPanel() {
    $chart = $this->buildChart();

    $panel_type = id(new PhabricatorDashboardChartPanelType())
      ->getPanelTypeKey();

    $chart_panel = id(new PhabricatorDashboardPanel())
      ->setPanelType($panel_type)
      ->setProperty('chartKey', $chart->getChartKey());

    return $chart_panel;
  }

}
