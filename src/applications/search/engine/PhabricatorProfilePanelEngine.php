<?php

abstract class PhabricatorProfilePanelEngine extends Phobject {

  private $viewer;
  private $profileObject;
  private $panels;
  private $defaultPanel;
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

  private function setDefaultPanel(
    PhabricatorProfilePanelConfiguration $default_panel) {
    $this->defaultPanel = $default_panel;
    return $this;
  }

  public function getDefaultPanel() {
    $this->loadPanels();
    return $this->defaultPanel;
  }

  abstract protected function getPanelURI($path);

  abstract protected function isPanelEngineConfigurable();

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
      case 'default':
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
      case 'default':
        $content = $this->buildPanelDefaultContent(
          $selected_panel,
          $panel_list);
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

    foreach ($stored_panels as $stored_panel) {
      $impl = $stored_panel->getPanel();
      $impl->setViewer($viewer);
    }

    // Merge the stored panels into the builtin panels. If a builtin panel has
    // a stored version, replace the defaults with the stored changes.
    foreach ($stored_panels as $stored_panel) {
      if (!$stored_panel->shouldEnableForObject($object)) {
        continue;
      }

      $builtin_key = $stored_panel->getBuiltinKey();
      if ($builtin_key !== null) {
        // If this builtin actually exists, replace the builtin with the
        // stored configuration. Otherwise, we're just going to drop the
        // stored config: it corresponds to an out-of-date or uninstalled
        // panel.
        if (isset($panels[$builtin_key])) {
          $panels[$builtin_key] = $stored_panel;
        } else {
          continue;
        }
      } else {
        $panels[] = $stored_panel;
      }
    }

    $panels = msort($panels, 'getSortKey');

    // Normalize keys since callers shouldn't rely on this array being
    // partially keyed.
    $panels = array_values($panels);


    // Make sure exactly one valid panel is marked as default.
    $default = null;
    $first = null;
    foreach ($panels as $panel) {
      if (!$panel->canMakeDefault()) {
        continue;
      }

      if ($panel->isDefault()) {
        $default = $panel;
        break;
      }

      if ($first === null) {
        $first = $panel;
      }
    }

    if (!$default) {
      $default = $first;
    }

    if ($default) {
      $this->setDefaultPanel($default);
    }

    return $panels;
  }

  private function loadBuiltinProfilePanels() {
    $object = $this->getProfileObject();
    $builtins = $this->getBuiltinProfilePanels($object);

    $panels = PhabricatorProfilePanel::getAllPanels();
    $viewer = $this->getViewer();

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

      $panel = clone $panel;
      $panel->setViewer($viewer);

      $builtin
        ->setProfilePHID($object->getPHID())
        ->attachPanel($panel)
        ->attachProfileObject($object)
        ->setPanelOrder($order);

      if (!$builtin->shouldEnableForObject($object)) {
        continue;
      }

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
      ->setIcon('fa-chevron-left');

    $expand_icon = id(new PHUIIconCircleView())
      ->addClass('phui-list-item-icon')
      ->addClass('phui-profile-menu-visible-when-collapsed')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Expand'),
          'align' => 'E',
        ))
      ->setIcon('fa-chevron-right');

    $items[] = id(new PHUIListItemView())
      ->setName('Collapse')
      ->addIcon($collapse_icon)
      ->addIcon($expand_icon)
      ->setID($collapse_id)
      ->addClass('phui-profile-menu-footer')
      ->addClass('phui-profile-menu-footer-1')
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
          $default_uri = $this->getPanelURI("default/{$id}/");
        } else {
          $default_uri = $this->getPanelURI("default/{$builtin_key}/");
        }

        if ($panel->isDefault()) {
          $default_icon = 'fa-thumb-tack green';
          $default_text = pht('Current Default');
        } else if ($panel->canMakeDefault()) {
          $default_icon = 'fa-thumb-tack';
          $default_text = pht('Make Default');
        } else {
          $default_text = null;
        }

        if ($default_text !== null) {
          $item->addAction(
            id(new PHUIListItemView())
              ->setHref($default_uri)
              ->setWorkflow(true)
              ->setName($default_text)
              ->setIcon($default_icon));
        }

        if ($id) {
          $item->setHref($this->getPanelURI("edit/{$id}/"));
          $hide_uri = $this->getPanelURI("hide/{$id}/");
        } else {
          $item->setHref($this->getPanelURI("builtin/{$builtin_key}/"));
          $hide_uri = $this->getPanelURI("hide/{$builtin_key}/");
        }

        if ($panel->isDisabled()) {
          $hide_icon = 'fa-plus';
          $hide_text = pht('Enable');
        } else if ($panel->getBuiltinKey() !== null) {
          $hide_icon = 'fa-times';
          $hide_text = pht('Disable');
        } else {
          $hide_icon = 'fa-times';
          $hide_text = pht('Delete');
        }

        $can_disable = $panel->canHidePanel();

        $item->addAction(
          id(new PHUIListItemView())
            ->setHref($hide_uri)
            ->setWorkflow(true)
            ->setDisabled(!$can_disable)
            ->setName($hide_text)
            ->setIcon($hide_icon));
      }

      if ($panel->isDisabled()) {
        $item->setDisabled(true);
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

    $doc_link = PhabricatorEnv::getDoclink('Profile Menu User Guide');
    $doc_name = pht('Profile Menu User Guide');

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-book')
        ->setHref($doc_link)
        ->setName($doc_name));

    $action_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Configure Menu'))
      ->setHref('#')
      ->setIcon('fa-gear')
      ->setDropdownMenu($action_view);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Profile Menu Items'))
      ->setSubHeader(pht('Drag tabs to reorder menu'))
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

    if (!$configuration->canHidePanel()) {
      return $controller->newDialog()
        ->setTitle(pht('Mandatory Panel'))
        ->appendParagraph(
          pht('This panel is very important, and can not be disabled.'))
        ->addCancelButton($this->getConfigureURI());
    }

    if ($configuration->getBuiltinKey() === null) {
      $new_value = null;

      $title = pht('Delete Menu Item');
      $body = pht('Delete this menu item?');
      $button = pht('Delete Menu Item');
    } else if ($configuration->isDisabled()) {
      $new_value = PhabricatorProfilePanelConfiguration::VISIBILITY_VISIBLE;

      $title = pht('Enable Menu Item');
      $body = pht(
        'Enable this menu item? It will appear in the menu again.');
      $button = pht('Enable Menu Item');
    } else {
      $new_value = PhabricatorProfilePanelConfiguration::VISIBILITY_DISABLED;

      $title = pht('Disable Menu Item');
      $body = pht(
        'Disable this menu item? It will no longer appear in the menu, but '.
        'you can re-enable it later.');
      $button = pht('Disable Menu Item');
    }

    $v_visibility = $configuration->getVisibility();
    if ($request->isFormPost()) {
      if ($new_value === null) {
        $configuration->delete();
      } else {
        $type_visibility =
          PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY;

        $xactions = array();

        $xactions[] = id(new PhabricatorProfilePanelConfigurationTransaction())
          ->setTransactionType($type_visibility)
          ->setNewValue($new_value);

        $editor = id(new PhabricatorProfilePanelEditor())
          ->setContentSourceFromRequest($request)
          ->setActor($viewer)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true)
          ->applyTransactions($configuration, $xactions);
      }

      return id(new AphrontRedirectResponse())
        ->setURI($this->getConfigureURI());
    }

    return $controller->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($this->getConfigureURI())
      ->addSubmitButton($button);
  }

  private function buildPanelDefaultContent(
    PhabricatorProfilePanelConfiguration $configuration,
    array $panels) {

    $controller = $this->getController();
    $request = $controller->getRequest();
    $viewer = $this->getViewer();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $configuration,
      PhabricatorPolicyCapability::CAN_EDIT);

    $done_uri = $this->getConfigureURI();

    if (!$configuration->canMakeDefault()) {
      return $controller->newDialog()
        ->setTitle(pht('Not Defaultable'))
        ->appendParagraph(
          pht(
            'This item can not be set as the default item. This is usually '.
            'because the item has no page of its own, or links to an '.
            'external page.'))
        ->addCancelButton($done_uri);
    }

    if ($configuration->isDefault()) {
      return $controller->newDialog()
        ->setTitle(pht('Already Default'))
        ->appendParagraph(
          pht(
            'This item is already set as the default item for this menu.'))
        ->addCancelButton($done_uri);
    }

    if ($request->isFormPost()) {
      $key = $configuration->getID();
      if (!$key) {
        $key = $configuration->getBuiltinKey();
      }

      $this->adjustDefault($key);

      return id(new AphrontRedirectResponse())
        ->setURI($done_uri);
    }

    return $controller->newDialog()
      ->setTitle(pht('Make Default'))
      ->appendParagraph(
        pht(
          'Set this item as the default for this menu? Users arriving on '.
          'this page will be shown the content of this item by default.'))
      ->addCancelButton($done_uri)
      ->addSubmitButton(pht('Make Default'));
  }

  protected function newPanel() {
    return PhabricatorProfilePanelConfiguration::initializeNewBuiltin();
  }

  public function adjustDefault($key) {
    $controller = $this->getController();
    $request = $controller->getRequest();
    $viewer = $request->getViewer();

    $panels = $this->loadPanels();

    // To adjust the default panel, we first change any existing panels that
    // are marked as defaults to "visible", then make the new default panel
    // the default.

    $default = array();
    $visible = array();

    foreach ($panels as $panel) {
      $builtin_key = $panel->getBuiltinKey();
      $id = $panel->getID();

      $is_target =
        (($builtin_key !== null) && ($builtin_key === $key)) ||
        (($id !== null) && ((int)$id === (int)$key));

      if ($is_target) {
        if (!$panel->isDefault()) {
          $default[] = $panel;
        }
      } else {
        if ($panel->isDefault()) {
          $visible[] = $panel;
        }
      }
    }

    $type_visibility =
      PhabricatorProfilePanelConfigurationTransaction::TYPE_VISIBILITY;

    $v_visible = PhabricatorProfilePanelConfiguration::VISIBILITY_VISIBLE;
    $v_default = PhabricatorProfilePanelConfiguration::VISIBILITY_DEFAULT;

    $apply = array(
      array($v_visible, $visible),
      array($v_default, $default),
    );

    foreach ($apply as $group) {
      list($value, $panels) = $group;
      foreach ($panels as $panel) {
        $xactions = array();

        $xactions[] = id(new PhabricatorProfilePanelConfigurationTransaction())
          ->setTransactionType($type_visibility)
          ->setNewValue($value);

        $editor = id(new PhabricatorProfilePanelEditor())
          ->setContentSourceFromRequest($request)
          ->setActor($viewer)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true)
          ->applyTransactions($panel, $xactions);
      }
    }

    return $this;
  }

}
