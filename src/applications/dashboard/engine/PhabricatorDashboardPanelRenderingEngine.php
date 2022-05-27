<?php

final class PhabricatorDashboardPanelRenderingEngine extends Phobject {

  const HEADER_MODE_NORMAL = 'normal';
  const HEADER_MODE_NONE   = 'none';
  const HEADER_MODE_EDIT   = 'edit';

  private $panel;
  private $panelPHID;
  private $viewer;
  private $enableAsyncRendering;
  private $parentPanelPHIDs;
  private $headerMode = self::HEADER_MODE_NORMAL;
  private $movable;
  private $panelHandle;
  private $editMode;
  private $contextObject;
  private $panelKey;

  public function setContextObject($object) {
    $this->contextObject = $object;
    return $this;
  }

  public function getContextObject() {
    return $this->contextObject;
  }

  public function setPanelKey($panel_key) {
    $this->panelKey = $panel_key;
    return $this;
  }

  public function getPanelKey() {
    return $this->panelKey;
  }

  public function setHeaderMode($header_mode) {
    $this->headerMode = $header_mode;
    return $this;
  }

  public function getHeaderMode() {
    return $this->headerMode;
  }

  public function setPanelHandle(PhabricatorObjectHandle $panel_handle) {
    $this->panelHandle = $panel_handle;
    return $this;
  }

  public function getPanelHandle() {
    return $this->panelHandle;
  }

  public function isEditMode() {
    return $this->editMode;
  }

  public function setEditMode($mode) {
    $this->editMode = $mode;
    return $this;
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

  public function setMovable($movable) {
    $this->movable = $movable;
    return $this;
  }

  public function getMovable() {
    return $this->movable;
  }

  public function getPanel() {
    return $this->panel;
  }

  public function setPanelPHID($panel_phid) {
    $this->panelPHID = $panel_phid;
    return $this;
  }

  public function getPanelPHID() {
    return $this->panelPHID;
  }

  public function renderPanel() {
    $panel = $this->getPanel();

    if (!$panel) {
      $handle = $this->getPanelHandle();
      if ($handle->getPolicyFiltered()) {
        return $this->renderErrorPanel(
          pht('Restricted Panel'),
          pht(
            'You do not have permission to see this panel.'));
      } else {
        return $this->renderErrorPanel(
          pht('Invalid Panel'),
          pht(
            'This panel is invalid or does not exist. It may have been '.
            'deleted.'));
      }
    }

    $panel_type = $panel->getImplementation();
    if (!$panel_type) {
      return $this->renderErrorPanel(
        $panel->getName(),
        pht(
          'This panel has type "%s", but that panel type is unknown.',
          $panel->getPanelType()));
    }

    try {
      $this->detectRenderingCycle($panel);

      if ($this->enableAsyncRendering) {
        if ($panel_type->shouldRenderAsync()) {
          return $this->renderAsyncPanel();
        }
      }

      return $this->renderNormalPanel();
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
    $context_phid = $this->getContextPHID();
    $panel = $this->getPanel();

    $panel_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'dashboard-async-panel',
      array(
        'panelID' => $panel_id,
        'parentPanelPHIDs' => $this->getParentPanelPHIDs(),
        'headerMode' => $this->getHeaderMode(),
        'contextPHID' => $context_phid,
        'panelKey' => $this->getPanelKey(),
        'movable' => $this->getMovable(),
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
      ->addMargin(PHUI::MARGIN_LARGE)
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

    $box
      ->setHeader($header)
      ->setID($id)
      ->addClass('dashboard-box')
      ->addSigil('dashboard-panel');

    if ($this->getMovable()) {
      $box->addSigil('panel-movable');
    }

    if ($panel) {
      $box->setMetadata(
        array(
          'panelKey' => $this->getPanelKey(),
        ));
    }

    return $box;
  }


  private function renderPanelHeader() {

    $panel = $this->getPanel();
    switch ($this->getHeaderMode()) {
      case self::HEADER_MODE_NONE:
        $header = null;
        break;
      case self::HEADER_MODE_EDIT:
        // In edit mode, include the panel monogram to make managing boards
        // a little easier.
        $header_text = pht('%s %s', $panel->getMonogram(), $panel->getName());
        $header = id(new PHUIHeaderView())
          ->setHeader($header_text);
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

    $viewer = $this->getViewer();
    $panel = $this->getPanel();
    $context_phid = $this->getContextPHID();

    $actions = array();

    if ($panel) {
      try {
        $panel_actions = $panel->newHeaderEditActions(
          $viewer,
          $context_phid);
      } catch (Exception $ex) {
        $error_action = id(new PhabricatorActionView())
          ->setIcon('fa-exclamation-triangle red')
          ->setName(pht('<Rendering Exception>'));
        $panel_actions[] = $error_action;
      }

      if ($panel_actions) {
        foreach ($panel_actions as $panel_action) {
          $actions[] = $panel_action;
        }
        $actions[] = id(new PhabricatorActionView())
          ->setType(PhabricatorActionView::TYPE_DIVIDER);
      }

      $panel_id = $panel->getID();

      $edit_uri = "/dashboard/panel/edit/{$panel_id}/";
      $params = array(
        'contextPHID' => $context_phid,
      );
      $edit_uri = new PhutilURI($edit_uri, $params);

      $actions[] = id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Panel'))
        ->setHref($edit_uri);

      $actions[] = id(new PhabricatorActionView())
        ->setIcon('fa-window-maximize')
        ->setName(pht('View Panel Details'))
        ->setHref($panel->getURI());
    }

    if ($context_phid) {
      $panel_phid = $this->getPanelPHID();

      $remove_uri = urisprintf('/dashboard/adjust/remove/');
      $params = array(
        'contextPHID' => $context_phid,
        'panelKey' => $this->getPanelKey(),
      );
      $remove_uri = new PhutilURI($remove_uri, $params);

      $actions[] = id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setHref($remove_uri)
        ->setName(pht('Remove Panel'))
        ->setWorkflow(true);
    }

    $dropdown_menu = id(new PhabricatorActionListView())
      ->setViewer($viewer);

    foreach ($actions as $action) {
      $dropdown_menu->addAction($action);
    }

    $action_menu = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-cog')
      ->setText(pht('Manage Panel'))
      ->setDropdownMenu($dropdown_menu);

    $header->addActionLink($action_menu);

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
          'panels, purchase a subscription to %s Gold.',
          new PhutilNumber($max_depth),
          PlatformSymbols::getPlatformServerName()));
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

  private function getContextPHID() {
    $context = $this->getContextObject();

    if ($context) {
      return $context->getPHID();
    }

    return null;
  }

}
