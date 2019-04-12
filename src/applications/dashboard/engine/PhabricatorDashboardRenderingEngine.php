<?php

final class PhabricatorDashboardRenderingEngine extends Phobject {

  private $dashboard;
  private $viewer;
  private $arrangeMode;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setDashboard(PhabricatorDashboard $dashboard) {
    $this->dashboard = $dashboard;
    return $this;
  }

  public function getDashboard() {
    return $this->dashboard;
  }

  public function setArrangeMode($mode) {
    $this->arrangeMode = $mode;
    return $this;
  }

  public function renderDashboard() {
    require_celerity_resource('phabricator-dashboard-css');
    $dashboard = $this->getDashboard();
    $viewer = $this->getViewer();

    $is_editable = $this->arrangeMode;

    if ($is_editable) {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_EDIT;
    } else {
      $h_mode = PhabricatorDashboardPanelRenderingEngine::HEADER_MODE_NORMAL;
    }

    $panel_phids = $dashboard->getPanelPHIDs();
    if ($panel_phids) {
      $panels = id(new PhabricatorDashboardPanelQuery())
        ->setViewer($viewer)
        ->withPHIDs($panel_phids)
        ->execute();
      $panels = mpull($panels, null, 'getPHID');

      $handles = $viewer->loadHandles($panel_phids);
    } else {
      $panels = array();
      $handles = array();
    }

    $ref_list = $dashboard->getPanelRefList();
    $columns = $ref_list->getColumns();

    $dashboard_id = celerity_generate_unique_node_id();

    $result = id(new AphrontMultiColumnView())
      ->setID($dashboard_id)
      ->setFluidLayout(true)
      ->setGutter(AphrontMultiColumnView::GUTTER_LARGE);

    foreach ($columns as $column) {
      $column_views = array();
      foreach ($column->getPanelRefs() as $panel_ref) {
        $panel_phid = $panel_ref->getPanelPHID();

        $panel_engine = id(new PhabricatorDashboardPanelRenderingEngine())
          ->setViewer($viewer)
          ->setEnableAsyncRendering(true)
          ->setContextObject($dashboard)
          ->setPanelKey($panel_ref->getPanelKey())
          ->setPanelPHID($panel_phid)
          ->setParentPanelPHIDs(array())
          ->setHeaderMode($h_mode)
          ->setEditMode($is_editable)
          ->setPanelHandle($handles[$panel_phid]);

        $panel = idx($panels, $panel_phid);
        if ($panel) {
          $panel_engine->setPanel($panel);
        }

        $column_views[] = $panel_engine->renderPanel();
      }

      $column_classes = $column->getClasses();

      if ($is_editable) {
        $column_views[] = $this->renderAddPanelPlaceHolder();
        $column_views[] = $this->renderAddPanelUI($column);
      }

      $sigil = 'dashboard-column';

      $metadata = array(
        'columnKey' => $column->getColumnKey(),
      );

      $result->addColumn(
        $column_views,
        implode(' ', $column_classes),
        $sigil,
        $metadata);
    }

    if ($is_editable) {
      Javelin::initBehavior(
        'dashboard-move-panels',
        array(
          'dashboardID' => $dashboard_id,
          'moveURI' => '/dashboard/movepanel/'.$dashboard->getID().'/',
        ));
    }

    $view = id(new PHUIBoxView())
      ->addClass('dashboard-view')
      ->appendChild(
        array(
          $result,
        ));

    return $view;
  }

  private function renderAddPanelPlaceHolder() {
    return javelin_tag(
      'span',
      array(
        'sigil' => 'workflow',
        'class' => 'drag-ghost dashboard-panel-placeholder',
      ),
      pht('This column does not have any panels yet.'));
  }

  private function renderAddPanelUI(PhabricatorDashboardColumn $column) {
    $dashboard = $this->getDashboard();
    $column_key = $column->getColumnKey();

    $create_uri = id(new PhutilURI('/dashboard/panel/edit/'))
      ->replaceQueryParam('contextPHID', $dashboard->getPHID())
      ->replaceQueryParam('columnKey', $column_key);

    $add_uri = id(new PhutilURI('/dashboard/adjust/add/'))
      ->replaceQueryParam('contextPHID', $dashboard->getPHID())
      ->replaceQueryParam('columnKey', $column_key);

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
