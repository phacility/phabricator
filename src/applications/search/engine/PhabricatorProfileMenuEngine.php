<?php

abstract class PhabricatorProfileMenuEngine extends Phobject {

  private $viewer;
  private $profileObject;
  private $customPHID;
  private $items;
  private $defaultItem;
  private $controller;
  private $navigation;
  private $showNavigation = true;
  private $editMode;
  private $pageClasses = array();
  private $showContentCrumbs = true;

  const ITEM_CUSTOM_DIVIDER = 'engine.divider';
  const ITEM_MANAGE = 'item.configure';

  const MODE_COMBINED = 'combined';
  const MODE_GLOBAL = 'global';
  const MODE_CUSTOM = 'custom';

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

  public function setCustomPHID($custom_phid) {
    $this->customPHID = $custom_phid;
    return $this;
  }

  public function getCustomPHID() {
    return $this->customPHID;
  }

  private function getEditModeCustomPHID() {
    $mode = $this->getEditMode();

    switch ($mode) {
      case self::MODE_CUSTOM:
        $custom_phid = $this->getCustomPHID();
        break;
      case self::MODE_GLOBAL:
        $custom_phid = null;
        break;
    }

    return $custom_phid;
  }

  public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  public function getController() {
    return $this->controller;
  }

  private function setDefaultItem(
    PhabricatorProfileMenuItemConfiguration $default_item) {
    $this->defaultItem = $default_item;
    return $this;
  }

  public function getDefaultItem() {
    $this->getItems();
    return $this->defaultItem;
  }

  public function setShowNavigation($show) {
    $this->showNavigation = $show;
    return $this;
  }

  public function getShowNavigation() {
    return $this->showNavigation;
  }

  public function addContentPageClass($class) {
    $this->pageClasses[] = $class;
    return $this;
  }

  public function setShowContentCrumbs($show_content_crumbs) {
    $this->showContentCrumbs = $show_content_crumbs;
    return $this;
  }

  public function getShowContentCrumbs() {
    return $this->showContentCrumbs;
  }

  abstract public function getItemURI($path);
  abstract protected function isMenuEngineConfigurable();

  abstract protected function getBuiltinProfileItems($object);

  protected function getBuiltinCustomProfileItems(
    $object,
    $custom_phid) {
    return array();
  }

  protected function getEditMode() {
    return $this->editMode;
  }

  public function buildResponse() {
    $controller = $this->getController();

    $viewer = $controller->getViewer();
    $this->setViewer($viewer);

    $request = $controller->getRequest();

    $item_action = $request->getURIData('itemAction');
    if (!$item_action) {
      $item_action = 'view';
    }

    $is_view = ($item_action == 'view');

    // If the engine is not configurable, don't respond to any of the editing
    // or configuration routes.
    if (!$this->isMenuEngineConfigurable()) {
      if (!$is_view) {
        return new Aphront404Response();
      }
    }

    $item_id = $request->getURIData('itemID');

    // If we miss on the MenuEngine route, try the EditEngine route. This will
    // be populated while editing items.
    if (!$item_id) {
      $item_id = $request->getURIData('id');
    }

    $item_list = $this->getItems();

    $selected_item = null;
    if (strlen($item_id)) {
      $item_id_int = (int)$item_id;
      foreach ($item_list as $item) {
        if ($item_id_int) {
          if ((int)$item->getID() === $item_id_int) {
            $selected_item = $item;
            break;
          }
        }

        $builtin_key = $item->getBuiltinKey();
        if ($builtin_key === (string)$item_id) {
          $selected_item = $item;
          break;
        }
      }
    }

    if (!$selected_item) {
      if ($is_view) {
        $selected_item = $this->getDefaultItem();
      }
    }

    switch ($item_action) {
      case 'view':
      case 'info':
      case 'hide':
      case 'default':
      case 'builtin':
        if (!$selected_item) {
          return new Aphront404Response();
        }
        break;
      case 'edit':
        if (!$request->getURIData('id')) {
          // If we continue along the "edit" pathway without an ID, we hit an
          // unrelated exception because we can not build a new menu item out
          // of thin air. For menus, new items are created via the "new"
          // action. Just catch this case and 404 early since there's currently
          // no clean way to make EditEngine aware of this.
          return new Aphront404Response();
        }
        break;
    }

    $navigation = $this->buildNavigation();

    $crumbs = $controller->buildApplicationCrumbsForEditEngine();

    if (!$is_view) {
      $navigation->selectFilter(self::ITEM_MANAGE);

      if ($selected_item) {
        if ($selected_item->getCustomPHID()) {
          $edit_mode = 'custom';
        } else {
          $edit_mode = 'global';
        }
      } else {
        $edit_mode = $request->getURIData('itemEditMode');
      }

      $available_modes = $this->getViewerEditModes($viewer);
      if ($available_modes) {
        $available_modes = array_fuse($available_modes);
        if (isset($available_modes[$edit_mode])) {
          $this->editMode = $edit_mode;
        } else {
          if ($item_action != 'configure') {
            return new Aphront404Response();
          }
        }
      }
      $page_title = pht('Configure Menu');
    } else {
      $page_title = $selected_item->getDisplayName();
    }

    switch ($item_action) {
      case 'view':
        $navigation->selectFilter($selected_item->getDefaultMenuItemKey());

        $content = $this->buildItemViewContent($selected_item);
        $crumbs->addTextCrumb($selected_item->getDisplayName());
        if (!$content) {
          return new Aphront404Response();
        }
        break;
      case 'configure':
        $mode = $this->getEditMode();
        if (!$mode) {
          $crumbs->addTextCrumb(pht('Configure Menu'));
          $content = $this->buildMenuEditModeContent();
        } else {
          if (count($available_modes) > 1) {
            $crumbs->addTextCrumb(
              pht('Configure Menu'),
              $this->getItemURI('configure/'));

            switch ($mode) {
              case self::MODE_CUSTOM:
                $crumbs->addTextCrumb(pht('Personal'));
                break;
              case self::MODE_GLOBAL:
                $crumbs->addTextCrumb(pht('Global'));
                break;
            }
          } else {
            $crumbs->addTextCrumb(pht('Configure Menu'));
          }
          $edit_list = $this->loadItems($mode);
          $content = $this->buildItemConfigureContent($edit_list);
        }
        break;
      case 'reorder':
        $mode = $this->getEditMode();
        $edit_list = $this->loadItems($mode);
        $content = $this->buildItemReorderContent($edit_list);
        break;
      case 'new':
        $item_key = $request->getURIData('itemKey');
        $mode = $this->getEditMode();
        $content = $this->buildItemNewContent($item_key, $mode);
        break;
      case 'builtin':
        $content = $this->buildItemBuiltinContent($selected_item);
        break;
      case 'hide':
        $content = $this->buildItemHideContent($selected_item);
        break;
      case 'default':
        if (!$this->isMenuEnginePinnable()) {
          return new Aphront404Response();
        }
        $content = $this->buildItemDefaultContent(
          $selected_item,
          $item_list);
        break;
      case 'edit':
        $content = $this->buildItemEditContent();
        break;
      default:
        throw new Exception(
          pht(
            'Unsupported item action "%s".',
            $item_action));
    }

    if ($content instanceof AphrontResponse) {
      return $content;
    }

    if ($content instanceof AphrontResponseProducerInterface) {
      return $content;
    }

    $crumbs->setBorder(true);

    $page = $controller->newPage()
      ->setTitle($page_title)
      ->appendChild($content);

    if (!$is_view || $this->getShowContentCrumbs()) {
      $page->setCrumbs($crumbs);
    }

    if ($this->getShowNavigation()) {
      $page->setNavigation($navigation);
    }

    if ($is_view) {
      foreach ($this->pageClasses as $class) {
        $page->addClass($class);
      }
    }

    return $page;
  }

  public function buildNavigation() {
    if ($this->navigation) {
      return $this->navigation;
    }
    $nav = id(new AphrontSideNavFilterView())
      ->setIsProfileMenu(true)
      ->setBaseURI(new PhutilURI($this->getItemURI('')));

    $menu_items = $this->getItems();

    $filtered_items = array();
    foreach ($menu_items as $menu_item) {
      if ($menu_item->isDisabled()) {
        continue;
      }
      $filtered_items[] = $menu_item;
    }
    $filtered_groups = mgroup($filtered_items, 'getMenuItemKey');
    foreach ($filtered_groups as $group) {
      $first_item = head($group);
      $first_item->willBuildNavigationItems($group);
    }

    foreach ($menu_items as $menu_item) {
      if ($menu_item->isDisabled()) {
        continue;
      }

      $items = $menu_item->buildNavigationMenuItems();
      foreach ($items as $item) {
        $this->validateNavigationMenuItem($item);
      }

      // If the item produced only a single item which does not otherwise
      // have a key, try to automatically assign it a reasonable key. This
      // makes selecting the correct item simpler.

      if (count($items) == 1) {
        $item = head($items);
        if ($item->getKey() === null) {
          $default_key = $menu_item->getDefaultMenuItemKey();
          $item->setKey($default_key);
        }
      }

      foreach ($items as $item) {
        $nav->addMenuItem($item);
      }
    }

    $nav->selectFilter(null);

    $this->navigation = $nav;
    return $this->navigation;
  }

  private function getItems() {
    if ($this->items === null) {
      $this->items = $this->loadItems(self::MODE_COMBINED);
    }

    return $this->items;
  }

  private function loadItems($mode) {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();

    $items = $this->loadBuiltinProfileItems($mode);

    $query = id(new PhabricatorProfileMenuItemConfigurationQuery())
      ->setViewer($viewer)
      ->withProfilePHIDs(array($object->getPHID()));

    switch ($mode) {
      case self::MODE_GLOBAL:
        $query->withCustomPHIDs(array(), true);
        break;
      case self::MODE_CUSTOM:
        $query->withCustomPHIDs(array($this->getCustomPHID()), false);
        break;
      case self::MODE_COMBINED:
        $query->withCustomPHIDs(array($this->getCustomPHID()), true);
        break;
    }

    $stored_items = $query->execute();

    foreach ($stored_items as $stored_item) {
      $impl = $stored_item->getMenuItem();
      $impl->setViewer($viewer);
      $impl->setEngine($this);
    }

    // Merge the stored items into the builtin items. If a builtin item has
    // a stored version, replace the defaults with the stored changes.
    foreach ($stored_items as $stored_item) {
      if (!$stored_item->shouldEnableForObject($object)) {
        continue;
      }

      $builtin_key = $stored_item->getBuiltinKey();
      if ($builtin_key !== null) {
        // If this builtin actually exists, replace the builtin with the
        // stored configuration. Otherwise, we're just going to drop the
        // stored config: it corresponds to an out-of-date or uninstalled
        // item.
        if (isset($items[$builtin_key])) {
          $items[$builtin_key] = $stored_item;
        } else {
          continue;
        }
      } else {
        $items[] = $stored_item;
      }
    }

    $items = $this->arrangeItems($items, $mode);

    // Make sure exactly one valid item is marked as default.
    $default = null;
    $first = null;
    foreach ($items as $item) {
      if (!$item->canMakeDefault()) {
        continue;
      }

      // If this engine doesn't support pinning items, don't respect any
      // setting which might be present in the database.
      if ($this->isMenuEnginePinnable()) {
        if ($item->isDefault()) {
          $default = $item;
          break;
        }
      }

      if ($first === null) {
        $first = $item;
      }
    }

    if (!$default) {
      $default = $first;
    }

    if ($default) {
      $this->setDefaultItem($default);
    }

    return $items;
  }

  private function loadBuiltinProfileItems($mode) {
    $object = $this->getProfileObject();

    switch ($mode) {
      case self::MODE_GLOBAL:
        $builtins = $this->getBuiltinProfileItems($object);
        break;
      case self::MODE_CUSTOM:
        $builtins = $this->getBuiltinCustomProfileItems(
          $object,
          $this->getCustomPHID());
        break;
      case self::MODE_COMBINED:
        $builtins = array();
        $builtins[] = $this->getBuiltinCustomProfileItems(
          $object,
          $this->getCustomPHID());
        $builtins[] = $this->getBuiltinProfileItems($object);
        $builtins = array_mergev($builtins);
        break;
    }

    $items = PhabricatorProfileMenuItem::getAllMenuItems();
    $viewer = $this->getViewer();

    $order = 1;
    $map = array();
    foreach ($builtins as $builtin) {
      $builtin_key = $builtin->getBuiltinKey();

      if (!$builtin_key) {
        throw new Exception(
          pht(
            'Object produced a builtin item with no builtin item key! '.
            'Builtin items must have a unique key.'));
      }

      if (isset($map[$builtin_key])) {
        throw new Exception(
          pht(
            'Object produced two items with the same builtin key ("%s"). '.
            'Each item must have a unique builtin key.',
            $builtin_key));
      }

      $item_key = $builtin->getMenuItemKey();

      $item = idx($items, $item_key);
      if (!$item) {
        throw new Exception(
          pht(
            'Builtin item ("%s") specifies a bad item key ("%s"); there '.
            'is no corresponding item implementation available.',
            $builtin_key,
            $item_key));
      }

      $item = clone $item;
      $item->setViewer($viewer);
      $item->setEngine($this);

      $builtin
        ->setProfilePHID($object->getPHID())
        ->attachMenuItem($item)
        ->attachProfileObject($object)
        ->setMenuItemOrder($order);

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

  public function getConfigureURI() {
    $mode = $this->getEditMode();

    switch ($mode) {
      case self::MODE_CUSTOM:
        return $this->getItemURI('configure/custom/');
      case self::MODE_GLOBAL:
        return $this->getItemURI('configure/global/');
    }

    return $this->getItemURI('configure/');
  }

  private function buildItemReorderContent(array $items) {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();

    // If you're reordering global items, you need to be able to edit the
    // object the menu appears on. If you're reordering custom items, you only
    // need to be able to edit the custom object. Currently, the custom object
    // is always the viewing user's own user object.
    $custom_phid = $this->getEditModeCustomPHID();

    if (!$custom_phid) {
      PhabricatorPolicyFilter::requireCapability(
        $viewer,
        $object,
        PhabricatorPolicyCapability::CAN_EDIT);
    } else {
      $policy_object = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($custom_phid))
        ->executeOne();

      if (!$policy_object) {
        throw new Exception(
          pht(
            'Failed to load custom PHID "%s"!',
            $custom_phid));
      }

      PhabricatorPolicyFilter::requireCapability(
        $viewer,
        $policy_object,
        PhabricatorPolicyCapability::CAN_EDIT);
    }

    $controller = $this->getController();
    $request = $controller->getRequest();

    $request->validateCSRF();

    $order = $request->getStrList('order');

    $by_builtin = array();
    $by_id = array();

    foreach ($items as $key => $item) {
      $id = $item->getID();
      if ($id) {
        $by_id[$id] = $key;
        continue;
      }

      $builtin_key = $item->getBuiltinKey();
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

    $items = array_select_keys($items, $key_order) + $items;

    $type_order =
      PhabricatorProfileMenuItemConfigurationTransaction::TYPE_ORDER;

    $order = 1;
    foreach ($items as $item) {
      $xactions = array();

      $xactions[] = id(new PhabricatorProfileMenuItemConfigurationTransaction())
        ->setTransactionType($type_order)
        ->setNewValue($order);

      $editor = id(new PhabricatorProfileMenuEditor())
        ->setContentSourceFromRequest($request)
        ->setActor($viewer)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->applyTransactions($item, $xactions);

      $order++;
    }

    return id(new AphrontRedirectResponse())
      ->setURI($this->getConfigureURI());
  }

  protected function buildItemViewContent(
    PhabricatorProfileMenuItemConfiguration $item) {
    return $item->newPageContent();
  }

  private function getViewerEditModes() {
    $modes = array();

    $viewer = $this->getViewer();

    if ($viewer->isLoggedIn() && $this->isMenuEnginePersonalizable()) {
      $modes[] = self::MODE_CUSTOM;
    }

    $object = $this->getProfileObject();
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object,
      PhabricatorPolicyCapability::CAN_EDIT);

    if ($can_edit) {
      $modes[] = self::MODE_GLOBAL;
    }

    return $modes;
  }

  protected function isMenuEnginePersonalizable() {
    return true;
  }

  /**
   * Does this engine support pinning items?
   *
   * Personalizable menus disable pinning by default since it creates a number
   * of weird edge cases without providing many benefits for current menus.
   *
   * @return bool True if items may be pinned as default items.
   */
  protected function isMenuEnginePinnable() {
    return !$this->isMenuEnginePersonalizable();
  }

  private function buildMenuEditModeContent() {
    $viewer = $this->getViewer();

    $modes = $this->getViewerEditModes($viewer);
    if (!$modes) {
      return new Aphront404Response();
    }

    if (count($modes) == 1) {
      $mode = head($modes);
      return id(new AphrontRedirectResponse())
        ->setURI($this->getItemURI("configure/{$mode}/"));
    }

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $modes = array_fuse($modes);

    if (isset($modes['custom'])) {
      $menu->addItem(
        id(new PHUIObjectItemView())
          ->setHeader(pht('Personal Menu Items'))
          ->setHref($this->getItemURI('configure/custom/'))
          ->setImageURI($viewer->getProfileImageURI())
          ->addAttribute(pht('Edit the menu for your personal account.')));
    }

    if (isset($modes['global'])) {
      $icon = id(new PHUIIconView())
        ->setIcon('fa-globe')
        ->setBackground('bg-blue');

      $menu->addItem(
        id(new PHUIObjectItemView())
          ->setHeader(pht('Global Menu Items'))
          ->setHref($this->getItemURI('configure/global/'))
          ->setImageIcon($icon)
          ->addAttribute(pht('Edit the global default menu for all users.')));
    }

    $box = id(new PHUIObjectBoxView())
      ->setObjectList($menu);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Manage Menu'))
      ->setHeaderIcon('fa-list');

    return id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($box);
  }

  private function buildItemConfigureContent(array $items) {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();

    $filtered_groups = mgroup($items, 'getMenuItemKey');
    foreach ($filtered_groups as $group) {
      $first_item = head($group);
      $first_item->willBuildNavigationItems($group);
    }

    // Users only need to be able to edit the object which this menu appears
    // on if they're editing global menu items. For example, users do not need
    // to be able to edit the Favorites application to add new items to the
    // Favorites menu.
    if (!$this->getCustomPHID()) {
      PhabricatorPolicyFilter::requireCapability(
        $viewer,
        $object,
        PhabricatorPolicyCapability::CAN_EDIT);
    }

    $list_id = celerity_generate_unique_node_id();

    $mode = $this->getEditMode();

    Javelin::initBehavior(
      'reorder-profile-menu-items',
      array(
        'listID' => $list_id,
        'orderURI' => $this->getItemURI("reorder/{$mode}/"),
      ));

    $list = id(new PHUIObjectItemListView())
      ->setID($list_id)
      ->setNoDataString(pht('This menu currently has no items.'));

    foreach ($items as $item) {
      $id = $item->getID();
      $builtin_key = $item->getBuiltinKey();

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $item,
        PhabricatorPolicyCapability::CAN_EDIT);

      $view = id(new PHUIObjectItemView());

      $name = $item->getDisplayName();
      $type = $item->getMenuItemTypeName();
      if (!strlen(trim($name))) {
        $name = pht('Untitled "%s" Item', $type);
      }

      $view->setHeader($name);
      $view->addAttribute($type);

      if ($can_edit) {
        $view
          ->setGrippable(true)
          ->addSigil('profile-menu-item')
          ->setMetadata(
            array(
              'key' => nonempty($id, $builtin_key),
            ));

        if ($id) {
          $default_uri = $this->getItemURI("default/{$id}/");
        } else {
          $default_uri = $this->getItemURI("default/{$builtin_key}/");
        }

        $default_text = null;

        if ($this->isMenuEnginePinnable()) {
          if ($item->isDefault()) {
            $default_icon = 'fa-thumb-tack green';
            $default_text = pht('Current Default');
          } else if ($item->canMakeDefault()) {
            $default_icon = 'fa-thumb-tack';
            $default_text = pht('Make Default');
          }
        }

        if ($default_text !== null) {
          $view->addAction(
            id(new PHUIListItemView())
              ->setHref($default_uri)
              ->setWorkflow(true)
              ->setName($default_text)
              ->setIcon($default_icon));
        }

        if ($id) {
          $view->setHref($this->getItemURI("edit/{$id}/"));
          $hide_uri = $this->getItemURI("hide/{$id}/");
        } else {
          $view->setHref($this->getItemURI("builtin/{$builtin_key}/"));
          $hide_uri = $this->getItemURI("hide/{$builtin_key}/");
        }

        if ($item->isDisabled()) {
          $hide_icon = 'fa-plus';
          $hide_text = pht('Enable');
        } else if ($item->getBuiltinKey() !== null) {
          $hide_icon = 'fa-times';
          $hide_text = pht('Disable');
        } else {
          $hide_icon = 'fa-times';
          $hide_text = pht('Delete');
        }

        $can_disable = $item->canHideMenuItem();

        $view->addAction(
          id(new PHUIListItemView())
            ->setHref($hide_uri)
            ->setWorkflow(true)
            ->setDisabled(!$can_disable)
            ->setName($hide_text)
            ->setIcon($hide_icon));
      }

      if ($item->isDisabled()) {
        $view->setDisabled(true);
      }

      $list->addItem($view);
    }

    $action_view = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $item_types = PhabricatorProfileMenuItem::getAllMenuItems();
    $object = $this->getProfileObject();

    $action_list = id(new PhabricatorActionListView())
      ->setViewer($viewer);

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setLabel(true)
        ->setName(pht('Add New Menu Item...')));

    foreach ($item_types as $item_type) {
      if (!$item_type->canAddToObject($object)) {
        continue;
      }

      $item_key = $item_type->getMenuItemKey();
      $edit_mode = $this->getEditMode();

      $action_list->addAction(
        id(new PhabricatorActionView())
          ->setIcon($item_type->getMenuItemTypeIcon())
          ->setName($item_type->getMenuItemTypeName())
          ->setHref($this->getItemURI("new/{$edit_mode}/{$item_key}/"))
          ->setWorkflow(true));
    }

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setLabel(true)
        ->setName(pht('Documentation')));

    $doc_link = PhabricatorEnv::getDoclink('Profile Menu User Guide');
    $doc_name = pht('Profile Menu User Guide');

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-book')
        ->setHref($doc_link)
        ->setName($doc_name));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Menu Items'))
      ->setHeaderIcon('fa-list');

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Current Menu Items'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);

    $panel = id(new PHUICurtainPanelView())
      ->appendChild($action_view);

    $curtain = id(new PHUICurtainView())
      ->setViewer($viewer)
      ->setActionList($action_list);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $box,
        ));

    return $view;
  }

  private function buildItemNewContent($item_key, $mode) {
    $item_types = PhabricatorProfileMenuItem::getAllMenuItems();
    $item_type = idx($item_types, $item_key);
    if (!$item_type) {
      return new Aphront404Response();
    }

    $object = $this->getProfileObject();
    if (!$item_type->canAddToObject($object)) {
      return new Aphront404Response();
    }

    $custom_phid = $this->getEditModeCustomPHID();

    $configuration = PhabricatorProfileMenuItemConfiguration::initializeNewItem(
      $object,
      $item_type,
      $custom_phid);

    $viewer = $this->getViewer();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $configuration,
      PhabricatorPolicyCapability::CAN_EDIT);

    $controller = $this->getController();

    return id(new PhabricatorProfileMenuEditEngine())
      ->setMenuEngine($this)
      ->setProfileObject($object)
      ->setNewMenuItemConfiguration($configuration)
      ->setCustomPHID($custom_phid)
      ->setController($controller)
      ->buildResponse();
  }

  private function buildItemEditContent() {
    $viewer = $this->getViewer();
    $object = $this->getProfileObject();
    $controller = $this->getController();
    $custom_phid = $this->getEditModeCustomPHID();

    return id(new PhabricatorProfileMenuEditEngine())
      ->setMenuEngine($this)
      ->setProfileObject($object)
      ->setController($controller)
      ->setCustomPHID($custom_phid)
      ->buildResponse();
  }

  private function buildItemBuiltinContent(
    PhabricatorProfileMenuItemConfiguration $configuration) {

    // If this builtin item has already been persisted, redirect to the
    // edit page.
    $id = $configuration->getID();
    if ($id) {
      return id(new AphrontRedirectResponse())
        ->setURI($this->getItemURI("edit/{$id}/"));
    }

    // Otherwise, act like we're creating a new item, we're just starting
    // with the builtin template.
    $viewer = $this->getViewer();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $configuration,
      PhabricatorPolicyCapability::CAN_EDIT);

    $object = $this->getProfileObject();
    $controller = $this->getController();
    $custom_phid = $this->getEditModeCustomPHID();

    return id(new PhabricatorProfileMenuEditEngine())
      ->setIsBuiltin(true)
      ->setMenuEngine($this)
      ->setProfileObject($object)
      ->setNewMenuItemConfiguration($configuration)
      ->setController($controller)
      ->setCustomPHID($custom_phid)
      ->buildResponse();
  }

  private function buildItemHideContent(
    PhabricatorProfileMenuItemConfiguration $configuration) {

    $controller = $this->getController();
    $request = $controller->getRequest();
    $viewer = $this->getViewer();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $configuration,
      PhabricatorPolicyCapability::CAN_EDIT);

    if (!$configuration->canHideMenuItem()) {
      return $controller->newDialog()
        ->setTitle(pht('Mandatory Item'))
        ->appendParagraph(
          pht('This menu item is very important, and can not be disabled.'))
        ->addCancelButton($this->getConfigureURI());
    }

    if ($configuration->getBuiltinKey() === null) {
      $new_value = null;

      $title = pht('Delete Menu Item');
      $body = pht('Delete this menu item?');
      $button = pht('Delete Menu Item');
    } else if ($configuration->isDisabled()) {
      $new_value = PhabricatorProfileMenuItemConfiguration::VISIBILITY_VISIBLE;

      $title = pht('Enable Menu Item');
      $body = pht(
        'Enable this menu item? It will appear in the menu again.');
      $button = pht('Enable Menu Item');
    } else {
      $new_value = PhabricatorProfileMenuItemConfiguration::VISIBILITY_DISABLED;

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
          PhabricatorProfileMenuItemConfigurationTransaction::TYPE_VISIBILITY;

        $xactions = array();

        $xactions[] =
          id(new PhabricatorProfileMenuItemConfigurationTransaction())
            ->setTransactionType($type_visibility)
            ->setNewValue($new_value);

        $editor = id(new PhabricatorProfileMenuEditor())
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

  private function buildItemDefaultContent(
    PhabricatorProfileMenuItemConfiguration $configuration,
    array $items) {

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

  protected function newItem() {
    return PhabricatorProfileMenuItemConfiguration::initializeNewBuiltin();
  }

  protected function newManageItem() {
    return $this->newItem()
      ->setBuiltinKey(self::ITEM_MANAGE)
      ->setMenuItemKey(PhabricatorManageProfileMenuItem::MENUITEMKEY);
  }

  public function adjustDefault($key) {
    $controller = $this->getController();
    $request = $controller->getRequest();
    $viewer = $request->getViewer();

    $items = $this->loadItems(self::MODE_COMBINED);

    // To adjust the default item, we first change any existing items that
    // are marked as defaults to "visible", then make the new default item
    // the default.

    $default = array();
    $visible = array();

    foreach ($items as $item) {
      $builtin_key = $item->getBuiltinKey();
      $id = $item->getID();

      $is_target =
        (($builtin_key !== null) && ($builtin_key === $key)) ||
        (($id !== null) && ((int)$id === (int)$key));

      if ($is_target) {
        if (!$item->isDefault()) {
          $default[] = $item;
        }
      } else {
        if ($item->isDefault()) {
          $visible[] = $item;
        }
      }
    }

    $type_visibility =
      PhabricatorProfileMenuItemConfigurationTransaction::TYPE_VISIBILITY;

    $v_visible = PhabricatorProfileMenuItemConfiguration::VISIBILITY_VISIBLE;
    $v_default = PhabricatorProfileMenuItemConfiguration::VISIBILITY_DEFAULT;

    $apply = array(
      array($v_visible, $visible),
      array($v_default, $default),
    );

    foreach ($apply as $group) {
      list($value, $items) = $group;
      foreach ($items as $item) {
        $xactions = array();

        $xactions[] =
          id(new PhabricatorProfileMenuItemConfigurationTransaction())
            ->setTransactionType($type_visibility)
            ->setNewValue($value);

        $editor = id(new PhabricatorProfileMenuEditor())
          ->setContentSourceFromRequest($request)
          ->setActor($viewer)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true)
          ->applyTransactions($item, $xactions);
      }
    }

    return $this;
  }

  private function arrangeItems(array $items, $mode) {
    // Sort the items.
    $items = msortv($items, 'getSortVector');

    $object = $this->getProfileObject();

    // If we have some global items and some custom items and are in "combined"
    // mode, put a hard-coded divider item between them.
    if ($mode == self::MODE_COMBINED) {
      $list = array();
      $seen_custom = false;
      $seen_global = false;
      foreach ($items as $item) {
        if ($item->getCustomPHID()) {
          $seen_custom = true;
        } else {
          if ($seen_custom && !$seen_global) {
            $list[] = $this->newItem()
              ->setBuiltinKey(self::ITEM_CUSTOM_DIVIDER)
              ->setMenuItemKey(PhabricatorDividerProfileMenuItem::MENUITEMKEY)
              ->attachProfileObject($object)
              ->attachMenuItem(
                new PhabricatorDividerProfileMenuItem());
          }
          $seen_global = true;
        }
        $list[] = $item;
      }
      $items = $list;
    }

    // Normalize keys since callers shouldn't rely on this array being
    // partially keyed.
    $items = array_values($items);

    return $items;
  }


}
