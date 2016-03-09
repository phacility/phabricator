<?php

final class PhabricatorDashboardPanelRenderingEngine extends Phobject {

  const HEADER_MODE_NORMAL = 'normal';
  const HEADER_MODE_NONE   = 'none';
  const HEADER_MODE_EDIT   = 'edit';

  private $panel;
  private $viewer;
  private $enableAsyncRendering;
  private $parentPanelPHIDs;
  private $headerMode = self::HEADER_MODE_NORMAL;
  private $dashboardID;

  public function setDashboardID($id) {
    $this->dashboardID = $id;
    return $this;
  }

  public function getDashboardID() {
    return $this->dashboardID;
  }

  public function setHeaderMode($header_mode) {
    $this->headerMode = $header_mode;
    return $this;
  }

  public function getHeaderMode() {
    return $this->headerMode;
  }

  /**
   * Allow the engine to render the panel via Ajax.
   */
  public function setEnableAsyncRendering($enable) {
    $this->enableAsyncRendering = $enable;
    return $this;
  }

  public function setParentPanelPHIDs(array $parents) {
    $this->parentPanelPHIDs = $parents;
    return $this;
  }

  public function getParentPanelPHIDs() {
    return $this->parentPanelPHIDs;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setPanel(PhabricatorDashboardPanel $panel) {
    $this->panel = $panel;
    return $this;
  }

  public function getPanel() {
    return $this->panel;
  }

  public function renderPanel() {
    $panel = $this->getPanel();
    $viewer = $this->getViewer();

    if (!$panel) {
      return $this->renderErrorPanel(
        pht('Missing Panel'),
        pht('This panel does not exist.'));
    }

    $panel_type = $panel->getImplementation();
    if (!$panel_type) {
      return $this->renderErrorPanel(
        $panel->getName(),
        pht(
          'This panel has type "%s", but that panel type is not known to '.
          'Phabricator.',
          $panel->getPanelType()));
    }

    try {
      $this->detectRenderingCycle($panel);

      if ($this->enableAsyncRendering) {
        if ($panel_type->shouldRenderAsync()) {
          return $this->renderAsyncPanel();
        }
      }

      return $this->renderNormalPanel($viewer, $panel, $this);
    } catch (Exception $ex) {
      return $this->renderErrorPanel(
        $panel->getName(),
        pht(
          '%s: %s',
          phutil_tag('strong', array(), get_class($ex)),
          $ex->getMessage()));
    }
  }

  private function renderNormalPanel() {
    $panel = $this->getPanel();
    $panel_type = $panel->getImplementation();

    $content = $panel_type->renderPanelContent(
      $this->getViewer(),
      $panel,
      $this);
    $header = $this->renderPanelHeader();

    return $this->renderPanelDiv(
      $content,
      $header);
  }


  private function renderAsyncPanel() {
    $panel = $this->getPanel();

    $panel_id = celerity_generate_unique_node_id();
    $dashboard_id = $this->getDashboardID();

    Javelin::initBehavior(
      'dashboard-async-panel',
      array(
        'panelID' => $panel_id,
        'parentPanelPHIDs' => $this->getParentPanelPHIDs(),
        'headerMode' => $this->getHeaderMode(),
        'dashboardID' => $dashboard_id,
        'uri' => '/dashboard/panel/render/'.$panel->getID().'/',
      ));

    $header = $this->renderPanelHeader();
    $content = id(new PHUIPropertyListView())
      ->addTextContent(pht('Loading...'));

    return $this->renderPanelDiv(
      $content,
      $header,
      $panel_id);
  }

  private function renderErrorPanel($title, $body) {
    switch ($this->getHeaderMode()) {
      case self::HEADER_MODE_NONE:
        $header = null;
        break;
      case self::HEADER_MODE_EDIT:
        $header = id(new PHUIHeaderView())
          ->setHeader($title);
        $header = $this->addPanelHeaderActions($header);
        break;
      case self::HEADER_MODE_NORMAL:
      default:
        $header = id(new PHUIHeaderView())
          ->setHeader($title);
        break;
    }
    $icon = id(new PHUIIconView())
      ->setIcon('fa-warning red msr');
    $content = id(new PHUIBoxView())
      ->addClass('dashboard-box')
      ->appendChild($icon)
      ->appendChild($body);
    return $this->renderPanelDiv(
      $content,
      $header);
  }

  private function renderPanelDiv(
    $content,
    $header = null,
    $id = null) {
    require_celerity_resource('phabricator-dashboard-css');

    $panel = $this->getPanel();
    if (!$id) {
      $id = celerity_generate_unique_node_id();
    }

    $box = new PHUIObjectBoxView();

    $interface = 'PhabricatorApplicationSearchResultView';
    if ($content instanceof $interface) {
      if ($content->getObjectList()) {
        $box->setObjectList($content->getObjectList());
      }
      if ($content->getTable()) {
        $box->setTable($content->getTable());
      }
      if ($content->getContent()) {
        $box->appendChild($content->getContent());
      }
    } else {
      $box->appendChild($content);
    }

    $box->setHeader($header)
      ->setID($id)
      ->addSigil('dashboard-panel')
      ->setMetadata(array('objectPHID' => $panel->getPHID()));

    return phutil_tag_div('dashboard-pane', $box);
  }


  private function renderPanelHeader() {

    $panel = $this->getPanel();
    switch ($this->getHeaderMode()) {
      case self::HEADER_MODE_NONE:
        $header = null;
        break;
      case self::HEADER_MODE_EDIT:
        $header = id(new PHUIHeaderView())
          ->setHeader($panel->getName());
        $header = $this->addPanelHeaderActions($header);
        break;
      case self::HEADER_MODE_NORMAL:
      default:
        $header = id(new PHUIHeaderView())
          ->setHeader($panel->getName());
        $panel_type = $panel->getImplementation();
        $header = $panel_type->adjustPanelHeader(
          $this->getViewer(),
          $panel,
          $this,
          $header);
        break;
    }
    return $header;
  }

  private function addPanelHeaderActions(
    PHUIHeaderView $header) {
    $panel = $this->getPanel();

    $dashboard_id = $this->getDashboardID();
    $edit_uri = id(new PhutilURI(
      '/dashboard/panel/edit/'.$panel->getID().'/'));
    if ($dashboard_id) {
      $edit_uri->setQueryParam('dashboardID', $dashboard_id);
    }
    $action_edit = id(new PHUIIconView())
      ->setIcon('fa-pencil')
      ->setWorkflow(true)
      ->setHref((string)$edit_uri);
    $header->addActionItem($action_edit);

    if ($dashboard_id) {
      $uri = id(new PhutilURI(
        '/dashboard/removepanel/'.$dashboard_id.'/'))
        ->setQueryParam('panelPHID', $panel->getPHID());
      $action_remove = id(new PHUIIconView())
        ->setIcon('fa-trash-o')
        ->setHref((string)$uri)
        ->setWorkflow(true);
      $header->addActionItem($action_remove);
    }
    return $header;
  }


  /**
   * Detect graph cycles in panels, and deeply nested panels.
   *
   * This method throws if the current rendering stack is too deep or contains
   * a cycle. This can happen if you embed layout panels inside each other,
   * build a big stack of panels, or embed a panel in remarkup inside another
   * panel. Generally, all of this stuff is ridiculous and we just want to
   * shut it down.
   *
   * @param PhabricatorDashboardPanel Panel being rendered.
   * @return void
   */
  private function detectRenderingCycle(PhabricatorDashboardPanel $panel) {
    if ($this->parentPanelPHIDs === null) {
      throw new PhutilInvalidStateException('setParentPanelPHIDs');
    }

    $max_depth = 4;
    if (count($this->parentPanelPHIDs) >= $max_depth) {
      throw new Exception(
        pht(
          'To render more than %s levels of panels nested inside other '.
          'panels, purchase a subscription to Phabricator Gold.',
          new PhutilNumber($max_depth)));
    }

    if (in_array($panel->getPHID(), $this->parentPanelPHIDs)) {
      throw new Exception(
        pht(
          'You awake in a twisting maze of mirrors, all alike. '.
          'You are likely to be eaten by a graph cycle. '.
          'Should you escape alive, you resolve to be more careful about '.
          'putting dashboard panels inside themselves.'));
    }
  }


}
