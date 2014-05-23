<?php

final class PhabricatorDashboardRenderingEngine extends Phobject {

  private $dashboard;
  private $viewer;
  private $arrangeMode;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function setArrangeMode($mode) {
    $this->arrangeMode = $mode;
    return $this;
  }

  public function renderDashboard() {
    require_celerity_resource('phabricator-dashboard-css');
    $dashboard = $this->dashboard;
    $viewer = $this->viewer;

    $layout_config = $dashboard->getLayoutConfigObject();
    $panel_grid_locations = $layout_config->getPanelLocations();
    $panels = mpull($dashboard->getPanels(), null, 'getPHID');
    $dashboard_id = celerity_generate_unique_node_id();
    $result = id(new AphrontMultiColumnView())
      ->setID($dashboard_id)
      ->setFluidlayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_LARGE);

    if ($this->arrangeMode) {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_EDIT;
    } else {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_NORMAL;
    }
    foreach ($panel_grid_locations as $column => $panel_column_locations) {
      $panel_phids = $panel_column_locations;
      $column_panels = array_select_keys($panels, $panel_phids);
      $column_result = array();
      foreach ($column_panels as $panel) {
        $column_result[] = id(new PhabricatorDashboardPanelRenderingEngine())
          ->setViewer($viewer)
          ->setPanel($panel)
          ->setDashboardID($dashboard->getID())
          ->setEnableAsyncRendering(true)
          ->setParentPanelPHIDs(array())
          ->setHeaderMode($h_mode)
          ->renderPanel();
      }
      $column_class = $layout_config->getColumnClass(
        $column,
        $this->arrangeMode);
      if ($this->arrangeMode) {
        $column_result[] = $this->renderAddPanelPlaceHolder($column);
        $column_result[] = $this->renderAddPanelUI($column);
      }
      $result->addColumn(
        $column_result,
        $column_class,
        $sigil = 'dashboard-column',
        $metadata = array('columnID' => $column));
    }

    if ($this->arrangeMode) {
      Javelin::initBehavior(
        'dashboard-move-panels',
        array(
          'dashboardID' => $dashboard_id,
          'moveURI' => '/dashboard/movepanel/'.$dashboard->getID().'/',
        ));
    }

    $view = id(new PHUIBoxView())
      ->addClass('dashboard-view')
      ->appendChild($result);

    return $view;
  }

  private function renderAddPanelPlaceHolder($column) {
    $uri = $this->getAddPanelURI($column);

    $dashboard = $this->dashboard;
    $panels = $dashboard->getPanels();
    $layout_config = $dashboard->getLayoutConfigObject();
    if ($layout_config->isMultiColumnLayout() && count($panels)) {
      $text = pht('Drag a panel here or click to add a panel.');
    } else {
      $text = pht('Click to add a panel.');
    }
    return javelin_tag(
      'a',
      array(
        'sigil' => 'workflow',
        'class' => 'drag-ghost dashboard-panel-placeholder',
        'href' => (string) $uri),
      $text);
  }

  private function renderAddPanelUI($column) {
    $uri = $this->getAddPanelURI($column);

    return id(new PHUIButtonView())
      ->setTag('a')
      ->setHref((string) $uri)
      ->setWorkflow(true)
      ->setColor(PHUIButtonView::GREY)
      ->setIcon(id(new PHUIIconView())
        ->setIconFont('fa-plus'))
      ->setText(pht('Add Panel'))
      ->addClass(PHUI::MARGIN_LARGE);
  }

  private function getAddPanelURI($column) {
    $dashboard = $this->dashboard;
    $uri = id(new PhutilURI('/dashboard/addpanel/'.$dashboard->getID().'/'))
      ->setQueryParam('column', $column)
      ->setQueryParam('src', 'arrange');
    return $uri;
  }

}
