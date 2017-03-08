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
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_LARGE);

    if ($this->arrangeMode) {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_EDIT;
    } else {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_NORMAL;
    }

    foreach ($panel_grid_locations as $column => $panel_column_locations) {
      $panel_phids = $panel_column_locations;

      // TODO: This list may contain duplicates when the dashboard itself
      // does not? Perhaps this is related to T10612. For now, just unique
      // the list before moving on.
      $panel_phids = array_unique($panel_phids);

      $column_result = array();
      foreach ($panel_phids as $panel_phid) {
        $panel_engine = id(new PhabricatorDashboardPanelRenderingEngine())
          ->setViewer($viewer)
          ->setDashboardID($dashboard->getID())
          ->setEnableAsyncRendering(true)
          ->setPanelPHID($panel_phid)
          ->setParentPanelPHIDs(array())
          ->setHeaderMode($h_mode);

        $panel = idx($panels, $panel_phid);
        if ($panel) {
          $panel_engine->setPanel($panel);
        }

        $column_result[] = $panel_engine->renderPanel();
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
    $dashboard = $this->dashboard;
    $panels = $dashboard->getPanels();

    return javelin_tag(
      'span',
      array(
        'sigil' => 'workflow',
        'class' => 'drag-ghost dashboard-panel-placeholder',
      ),
      pht('This column does not have any panels yet.'));
  }

  private function renderAddPanelUI($column) {
    $dashboard_id = $this->dashboard->getID();

    $create_uri = id(new PhutilURI('/dashboard/panel/create/'))
      ->setQueryParam('dashboardID', $dashboard_id)
      ->setQueryParam('column', $column);

    $add_uri = id(new PhutilURI('/dashboard/addpanel/'.$dashboard_id.'/'))
      ->setQueryParam('column', $column);

    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($create_uri)
      ->setWorkflow(true)
      ->setText(pht('Create Panel'))
      ->addClass(PHUI::MARGIN_MEDIUM);

    $add_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($add_uri)
      ->setWorkflow(true)
      ->setText(pht('Add Existing Panel'))
      ->addClass(PHUI::MARGIN_MEDIUM);

    return phutil_tag(
      'div',
      array(
        'style' => 'text-align: center;',
      ),
      array(
        $create_button,
        $add_button,
      ));
  }

}
