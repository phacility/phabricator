<?php

abstract class PhabricatorProfilePanelEngine extends Phobject {

  private $viewer;
  private $profileObject;
  private $panels;
  private $controller;
  private $navigation;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setProfileObject($profile_object) {
    $this->profileObject = $profile_object;
    return $this;
  }

  public function getProfileObject() {
    return $this->profileObject;
  }

  public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  public function getController() {
    return $this->controller;
  }

  abstract protected function getPanelURI($path);

  protected function isPanelEngineConfigurable() {
    return PhabricatorEnv::getEnvConfig('phabricator.show-prototypes');
  }

  public function buildResponse() {
    $controller = $this->getController();

    $viewer = $controller->getViewer();
    $this->setViewer($viewer);

    $request = $controller->getRequest();

    $panel_action = $request->getURIData('panelAction');

    // If the engine is not configurable, don't respond to any of the editing
    // or configuration routes.
    if (!$this->isPanelEngineConfigurable()) {
      switch ($panel_action) {
        case 'view':
          break;
        default:
          return new Aphront404Response();
      }
    }

    $panel_id = $request->getURIData('panelID');

    $panel_list = $this->loadPanels();

    $selected_panel = null;
    if (strlen($panel_id)) {
      $panel_id_int = (int)$panel_id;
      foreach ($panel_list as $panel) {
        if ($panel_id_int) {
          if ((int)$panel->getID() === $panel_id_int) {
            $selected_panel = $panel;
            break;
          }
        }

        $builtin_key = $panel->getBuiltinKey();
        if ($builtin_key === (string)$panel_id) {
          $selected_panel = $panel;
          break;
        }
      }
    }

    switch ($panel_action) {
      case 'view':
      case 'info':
      case 'hide':
      case 'builtin':
        if (!$selected_panel) {
          return new Aphront404Response();
        }
        break;
    }

    $navigation = $this->buildNavigation();
    $navigation->selectFilter('panel.configure');

    $crumbs = $controller->buildApplicationCrumbsForEditEngine();

    switch ($panel_action) {
      case 'view':
        $content = $this->buildPanelViewContent($selected_panel);
        break;
      case 'configure':
        $content = $this->buildPanelConfigureContent($panel_list);
        $crumbs->addTextCrumb(pht('Configure Menu'));
        break;
      case 'reorder':
        $content = $this->buildPanelReorderContent($panel_list);
        break;
      case 'new':
        $panel_key = $request->getURIData('panelKey');
        $content = $this->buildPanelNewContent($panel_key);
        break;
      case 'builtin':
        $content = $this->buildPanelBuiltinContent($selected_panel);
        break;
      case 'hide':
        $content = $this->buildPanelHideContent($selected_panel);
        break;
      case 'edit':
        $content = $this->buildPanelEditContent();
        break;
      default:
        throw new Exception(
          pht(
            'Unsupported panel action "%s".',
            $panel_action));
    }

    if ($content instanceof AphrontResponse) {
      return $content;
    }

    if ($content instanceof AphrontResponseProducerInterface) {
      return $content;
    }

    return $controller->newPage()
      ->setTitle(pht('Profile Stuff'))
      ->setNavigation($navigation)
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

  public function buildNavigation() {
    if ($this->navigation) {
      return $this->navigation;
    }

    $nav = id(new AphrontSideNavFilterView())
      ->setIsProfileMenu(true)
      ->setBaseURI(new PhutilURI($this->getPanelURI('')));

    $panels = $this->getPanels();

    foreach ($panels as $panel) {
      if ($panel->isDisabled()) {
        continue;
      }

      $items = $panel->buildNavigationMenuItems();
      foreach ($items as $item) {
        $this->validateNavigationMenuItem($item);
      }

      // If the panel produced only a single item which does not otherwise
      // have a key, try to automatically assign it a reasonable key. This
      // makes selecting the correct item simpler.

      if (count($items) == 1) {
        $item = head($items);
        if ($item->getKey() === null) {
          $builtin_key = $panel->getBuiltinKey();
          $panel_phid = $panel->getPHID();
          if ($builtin_key !== null) {
            $item->setKey($builtin_key);
          } else if ($panel_phid !== null) {
            $item->setKey($panel_phid);
          }
        }
      }

      foreach ($items as $item) {
        $nav->addMenuItem($item);
      }
    }

    $more_items = $this->newAutomaticMenuItems($nav);
    foreach ($more_items as $item) {
      $nav->addMenuItem($item);
    }

    $nav->selectFilter(null);

    $this->navigation = $nav;
    return $this->navigation;
  }

  private function getPanels() {
    if ($this->panels === null) {
      $this->panels = $this->loadPanels();
    }

    return $this->panels;
  }

  private function loadPanels() {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();

    $panels = $this->loadBuiltinProfilePanels();

    $stored_panels = id(new PhabricatorProfilePanelConfigurationQuery())
      ->setViewer($viewer)
      ->withProfilePHIDs(array($object->getPHID()))
      ->execute();

    // Merge the stored panels into the builtin panels. If a builtin panel has
    // a stored version, replace the defaults with the stored changes.
    foreach ($stored_panels as $stored_panel) {
      $builtin_key = $stored_panel->getBuiltinKey();
      if ($builtin_key !== null) {
        $panels[$builtin_key] = $stored_panel;
      } else {
        $panels[] = $stored_panel;
      }
    }

    foreach ($panels as $panel) {
      $impl = $panel->getPanel();

      $impl->setViewer($viewer);
    }

    $panels = msort($panels, 'getSortKey');

    // Normalize keys since callers shouldn't rely on this array being
    // partially keyed.
    $panels = array_values($panels);

    return $panels;
  }

  private function loadBuiltinProfilePanels() {
    $object = $this->getProfileObject();
    $builtins = $this->getBuiltinProfilePanels($object);

    $panels = PhabricatorProfilePanel::getAllPanels();

    $order = 1;
    $map = array();
    foreach ($builtins as $builtin) {
      $builtin_key = $builtin->getBuiltinKey();

      if (!$builtin_key) {
        throw new Exception(
          pht(
            'Object produced a builtin panel with no builtin panel key! '.
            'Builtin panels must have a unique key.'));
      }

      if (isset($map[$builtin_key])) {
        throw new Exception(
          pht(
            'Object produced two panels with the same builtin key ("%s"). '.
            'Each panel must have a unique builtin key.',
            $builtin_key));
      }

      $panel_key = $builtin->getPanelKey();

      $panel = idx($panels, $panel_key);
      if (!$panel) {
        throw new Exception(
          pht(
            'Builtin panel ("%s") specifies a bad panel key ("%s"); there '.
            'is no corresponding panel implementation available.',
            $builtin_key,
            $panel_key));
      }

      $builtin
        ->setProfilePHID($object->getPHID())
        ->attachPanel($panel)
        ->attachProfileObject($object)
        ->setPanelOrder($order);

      $map[$builtin_key] = $builtin;

      $order++;
    }

    return $map;
  }

  private function validateNavigationMenuItem($item) {
    if (!($item instanceof PHUIListItemView)) {
      throw new Exception(
        pht(
          'Expected buildNavigationMenuItems() to return a list of '.
          'PHUIListItemView objects, but got a surprise.'));
    }
  }

  private function newAutomaticMenuItems(AphrontSideNavFilterView $nav) {
    $items = array();

    // NOTE: We're adding a spacer item for the fixed footer, so that if the
    // menu taller than the page content you can still scroll down the page far
    // enough to access the last item without the content being obscured by the
    // fixed items.
    $items[] = id(new PHUIListItemView())
      ->setHideInApplicationMenu(true)
      ->addClass('phui-profile-menu-spacer');

    if ($this->isPanelEngineConfigurable()) {
      $viewer = $this->getViewer();
      $object = $this->getProfileObject();

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $object,
        PhabricatorPolicyCapability::CAN_EDIT);

      $expanded_edit_icon = id(new PHUIIconCircleView())
        ->addClass('phui-list-item-icon')
        ->addClass('phui-profile-menu-visible-when-expanded')
        ->setIconFont('fa-pencil');

      $collapsed_edit_icon = id(new PHUIIconCircleView())
        ->addClass('phui-list-item-icon')
        ->addClass('phui-profile-menu-visible-when-collapsed')
        ->setIconFont('fa-pencil')
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => pht('Edit Menu'),
            'align' => 'E',
          ));

      $items[] = id(new PHUIListItemView())
        ->setName('Edit Menu')
        ->setKey('panel.configure')
        ->addIcon($expanded_edit_icon)
        ->addIcon($collapsed_edit_icon)
        ->addClass('phui-profile-menu-footer')
        ->addClass('phui-profile-menu-footer-1')
        ->setHref($this->getPanelURI('configure/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit);
    }

    $collapse_id = celerity_generate_unique_node_id();

    $viewer = $this->getViewer();

    $collapse_key =
      PhabricatorUserPreferences::PREFERENCE_PROFILE_MENU_COLLAPSED;

    $preferences = $viewer->loadPreferences();
    $is_collapsed = $preferences->getPreference($collapse_key, false);

    if ($is_collapsed) {
      $nav->addClass('phui-profile-menu-collapsed');
    } else {
      $nav->addClass('phui-profile-menu-expanded');
    }

    if ($viewer->isLoggedIn()) {
      $settings_uri = '/settings/adjust/?key='.$collapse_key;
    } else {
      $settings_uri = null;
    }

    Javelin::initBehavior(
      'phui-profile-menu',
      array(
        'menuID' => $nav->getMainID(),
        'collapseID' => $collapse_id,
        'isCollapsed' => (bool)$is_collapsed,
        'settingsURI' => $settings_uri,
      ));

    $collapse_icon = id(new PHUIIconCircleView())
      ->addClass('phui-list-item-icon')
      ->addClass('phui-profile-menu-visible-when-expanded')
      ->setIconFont('fa-angle-left');

    $expand_icon = id(new PHUIIconCircleView())
      ->addClass('phui-list-item-icon')
      ->addClass('phui-profile-menu-visible-when-collapsed')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Expand'),
          'align' => 'E',
        ))
      ->setIconFont('fa-angle-right');

    $items[] = id(new PHUIListItemView())
      ->setName('Collapse')
      ->addIcon($collapse_icon)
      ->addIcon($expand_icon)
      ->setID($collapse_id)
      ->addClass('phui-profile-menu-footer')
      ->addClass('phui-profile-menu-footer-2')
      ->setHideInApplicationMenu(true)
      ->setHref('#');

    return $items;
  }

  public function getConfigureURI() {
    return $this->getPanelURI('configure/');
  }

  private function buildPanelReorderContent(array $panels) {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    $controller = $this->getController();
    $request = $controller->getRequest();

    $request->validateCSRF();

    $order = $request->getStrList('order');

    $by_builtin = array();
    $by_id = array();

    foreach ($panels as $key => $panel) {
      $id = $panel->getID();
      if ($id) {
        $by_id[$id] = $key;
        continue;
      }

      $builtin_key = $panel->getBuiltinKey();
      if ($builtin_key) {
        $by_builtin[$builtin_key] = $key;
        continue;
      }
    }

    $key_order = array();
    foreach ($order as $order_item) {
      if (isset($by_id[$order_item])) {
        $key_order[] = $by_id[$order_item];
        continue;
      }
      if (isset($by_builtin[$order_item])) {
        $key_order[] = $by_builtin[$order_item];
        continue;
      }
    }

    $panels = array_select_keys($panels, $key_order) + $panels;

    $type_order =
      PhabricatorProfilePanelConfigurationTransaction::TYPE_ORDER;

    $order = 1;
    foreach ($panels as $panel) {
      $xactions = array();

      $xactions[] = id(new PhabricatorProfilePanelConfigurationTransaction())
        ->setTransactionType($type_order)
        ->setNewValue($order);

      $editor = id(new PhabricatorProfilePanelEditor())
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($panel, $xactions);

      $order++;
    }

    return id(new AphrontRedirectResponse())
      ->setURI($this->getConfigureURI());
  }


  private function buildPanelConfigureContent(array $panels) {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    $list_id = celerity_generate_unique_node_id();

    Javelin::initBehavior(
      'reorder-profile-menu-items',
      array(
        'listID' => $list_id,
        'orderURI' => $this->getPanelURI('reorder/'),
      ));

    $list = id(new PHUIObjectItemListView())
      ->setID($list_id);

    foreach ($panels as $panel) {
      $id = $panel->getID();
      $builtin_key = $panel->getBuiltinKey();

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $panel,
        PhabricatorPolicyCapability::CAN_EDIT);

      $item = id(new PHUIObjectItemView());

      $name = $panel->getDisplayName();
      $type = $panel->getPanelTypeName();
      if (!strlen(trim($name))) {
        $name = pht('Untitled "%s" Item', $type);
      }

      $item->setHeader($name);
      $item->addAttribute($type);

      if ($can_edit) {
        $item
          ->setGrippable(true)
          ->addSigil('profile-menu-item')
          ->setMetadata(
            array(
              'key' => nonempty($id, $builtin_key),
            ));

        if ($id) {
          $item->setHref($this->getPanelURI("edit/{$id}/"));
          $hide_uri = $this->getPanelURI("hide/{$id}/");
        } else {
          $item->setHref($this->getPanelURI("builtin/{$builtin_key}/"));
          $hide_uri = $this->getPanelURI("hide/{$builtin_key}/");
        }

        $item->addAction(
          id(new PHUIListItemView())
            ->setHref($hide_uri)
            ->setWorkflow(true)
            ->setIcon(pht('fa-eye')));
      }

      if ($panel->isDisabled()) {
        $item->setDisabled(true);
        $item->addIcon('fa-times grey', pht('Disabled'));
      }

      $list->addItem($item);
    }

    $action_view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $panel_types = PhabricatorProfilePanel::getAllPanels();

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setLabel(true)
        ->setName(pht('Add New Menu Item...')));

    foreach ($panel_types as $panel_type) {
      if (!$panel_type->canAddToObject($object)) {
        continue;
      }

      $panel_key = $panel_type->getPanelKey();

      $action_view->addAction(
        id(new PhabricatorActionView())
          ->setIcon($panel_type->getPanelTypeIcon())
          ->setName($panel_type->getPanelTypeName())
          ->setHref($this->getPanelURI("new/{$panel_key}/")));
    }

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setLabel(true)
        ->setName(pht('Documentation')));

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-book')
        ->setName(pht('TODO: Write Documentation')));

    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Configure Menu'))
      ->setHref('#')
      ->setIconFont('fa-gear')
      ->setDropdownMenu($action_view);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Profile Menu Items'))
      ->addActionLink($action_button);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setObjectList($list);

    return $box;
  }

  private function buildPanelNewContent($panel_key) {
    $panel_types = PhabricatorProfilePanel::getAllPanels();
    $panel_type = idx($panel_types, $panel_key);
    if (!$panel_type) {
      return new Aphront404Response();
    }

    $object = $this->getProfileObject();
    if (!$panel_type->canAddToObject($object)) {
      return new Aphront404Response();
    }

    $configuration =
      PhabricatorProfilePanelConfiguration::initializeNewPanelConfiguration(
        $object,
        $panel_type);

    $viewer = $this->getViewer();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $configuration,
      PhabricatorPolicyCapability::CAN_EDIT);

    $controller = $this->getController();

    return id(new PhabricatorProfilePanelEditEngine())
      ->setPanelEngine($this)
      ->setProfileObject($object)
      ->setNewPanelConfiguration($configuration)
      ->setController($controller)
      ->buildResponse();
  }

  private function buildPanelEditContent() {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();
    $controller = $this->getController();

    return id(new PhabricatorProfilePanelEditEngine())
      ->setPanelEngine($this)
      ->setProfileObject($object)
      ->setController($controller)
      ->buildResponse();
  }

  private function buildPanelBuiltinContent(
    PhabricatorProfilePanelConfiguration $configuration) {

    // If this builtin panel has already been persisted, redirect to the
    // edit page.
    $id = $configuration->getID();
    if ($id) {
      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI("edit/{$id}/"));
    }

    // Otherwise, act like we're creating a new panel, we're just starting
    // with the builtin template.
    $viewer = $this->getViewer();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $configuration,
      PhabricatorPolicyCapability::CAN_EDIT);

    $object = $this->getProfileObject();
    $controller = $this->getController();

    return id(new PhabricatorProfilePanelEditEngine())
      ->setIsBuiltin(true)
      ->setPanelEngine($this)
      ->setProfileObject($object)
      ->setNewPanelConfiguration($configuration)
      ->setController($controller)
      ->buildResponse();
  }

  private function buildPanelHideContent(
    PhabricatorProfilePanelConfiguration $configuration) {

    $controller = $this->getController();
    $request = $controller->getRequest();
    $viewer = $this->getViewer();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $configuration,
      PhabricatorPolicyCapability::CAN_EDIT);

    $v_visibility = $configuration->getVisibility();
    if ($request->isFormPost()) {
      $v_visibility = $request->getStr('visibility');

      $type_visibility =
        PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY;

      $xactions = array();

      $xactions[] = id(new PhabricatorProfilePanelConfigurationTransaction())
        ->setTransactionType($type_visibility)
        ->setNewValue($v_visibility);

      $editor = id(new PhabricatorProfilePanelEditor())
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($configuration, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($this->getConfigureURI());
    }

    $map = PhabricatorProfilePanelConfiguration::getVisibilityNameMap();

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormSelectControl())
          ->setName('visibility')
          ->setLabel(pht('Visibility'))
          ->setValue($v_visibility)
          ->setOptions($map));

    return $controller->newDialog()
      ->setTitle(pht('Change Item Visibility'))
      ->appendForm($form)
      ->addCancelButton($this->getConfigureURI())
      ->addSubmitButton(pht('Save Changes'));
  }

  protected function newPanel() {
    return PhabricatorProfilePanelConfiguration::initializeNewBuiltin();
  }

}
