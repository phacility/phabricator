<?php

final class PhabricatorDashboardChartPanelType
  extends PhabricatorDashboardPanelType {

  public function getPanelTypeKey() {
    return 'chart';
  }

  public function getPanelTypeName() {
    return pht('Chart Panel');
  }

  public function getIcon() {
    return 'fa-area-chart';
  }

  public function getPanelTypeDescription() {
    return pht('Show a chart.');
  }

  protected function newEditEngineFields(PhabricatorDashboardPanel $panel) {
    $chart_field = id(new PhabricatorTextEditField())
      ->setKey('chartKey')
      ->setLabel(pht('Chart'))
      ->setTransactionType(
        PhabricatorDashboardChartPanelChartTransaction::TRANSACTIONTYPE)
      ->setValue($panel->getProperty('chartKey', ''));

    return array(
      $chart_field,
    );
  }

  public function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine) {

    $engine = id(new PhabricatorChartRenderingEngine())
      ->setViewer($viewer);

    $chart = $engine->loadChart($panel->getProperty('chartKey'));
    if (!$chart) {
      return pht('no such chart!');
    }

    return $engine->newChartView();
  }

  public function adjustPanelHeader(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine,
    PHUIHeaderView $header) {

    $key = $panel->getProperty('chartKey');
    $uri = PhabricatorChartRenderingEngine::getChartURI($key);

    $icon = id(new PHUIIconView())
      ->setIcon('fa-area-chart');

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Chart'))
      ->setIcon($icon)
      ->setHref($uri)
      ->setColor(PHUIButtonView::GREY);

    $header->addActionLink($button);

    return $header;
  }


}
