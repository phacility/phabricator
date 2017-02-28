<?php

/**
 * Provides a navigation sidebar. For example:
 *
 *    $nav = new AphrontSideNavFilterView();
 *    $nav
 *      ->setBaseURI($some_uri)
 *      ->addLabel('Cats')
 *      ->addFilter('meow', 'Meow')
 *      ->addFilter('purr', 'Purr')
 *      ->addLabel('Dogs')
 *      ->addFilter('woof', 'Woof')
 *      ->addFilter('bark', 'Bark');
 *    $valid_filter = $nav->selectFilter($user_selection, $default = 'meow');
 *
 */
final class AphrontSideNavFilterView extends AphrontView {

  private $items = array();
  private $baseURI;
  private $selectedFilter = false;
  private $flexible;
  private $collapsed = false;
  private $active;
  private $menu;
  private $crumbs;
  private $classes = array();
  private $menuID;
  private $mainID;
  private $isProfileMenu;
  private $footer = array();

  public function setMenuID($menu_id) {
    $this->menuID = $menu_id;
    return $this;
  }

  public function getMenuID() {
    return $this->menuID;
  }

  public function __construct() {
    $this->menu = new PHUIListView();
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setCrumbs(PHUICrumbsView $crumbs) {
    $this->crumbs = $crumbs;
    return $this;
  }

  public function getCrumbs() {
    return $this->crumbs;
  }

  public function setIsProfileMenu($is_profile) {
    $this->isProfileMenu = $is_profile;
    return $this;
  }

  public function getIsProfileMenu() {
    return $this->isProfileMenu;
  }

  public function setActive($active) {
    $this->active = $active;
    return $this;
  }

  public function setFlexible($flexible) {
    $this->flexible = $flexible;
    return $this;
  }

  public function setCollapsed($collapsed) {
    $this->collapsed = $collapsed;
    return $this;
  }

  public function getMenuView() {
    return $this->menu;
  }

  public function addMenuItem(PHUIListItemView $item) {
    $this->menu->addMenuItem($item);
    return $this;
  }

  public function getMenu() {
    return $this->menu;
  }

  public function addFilter($key, $name, $uri = null, $icon = null) {
    return $this->addThing(
      $key, $name, $uri, PHUIListItemView::TYPE_LINK, $icon);
  }

  public function addButton($key, $name, $uri = null) {
    return $this->addThing(
      $key, $name, $uri, PHUIListItemView::TYPE_BUTTON);
  }

  private function addThing($key, $name, $uri, $type, $icon = null) {
    $item = id(new PHUIListItemView())
      ->setName($name)
      ->setType($type);

    if (strlen($icon)) {
      $item->setIcon($icon);
    }


    if (strlen($key)) {
      $item->setKey($key);
    }

    if ($uri) {
      $item->setHref($uri);
    } else {
      $href = clone $this->baseURI;
      $href->setPath(rtrim($href->getPath().$key, '/').'/');
      $href = (string)$href;

      $item->setHref($href);
    }

    return $this->addMenuItem($item);
  }

  public function addCustomBlock($block) {
    $this->menu->addMenuItem(
      id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_CUSTOM)
        ->appendChild($block));
    return $this;
  }

  public function addLabel($name) {
    return $this->addMenuItem(
      id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LABEL)
        ->setName($name));
  }

  public function setBaseURI(PhutilURI $uri) {
    $this->baseURI = $uri;
    return $this;
  }

  public function getBaseURI() {
    return $this->baseURI;
  }

  public function selectFilter($key, $default = null) {
    $this->selectedFilter = $default;
    if ($this->menu->getItem($key) && strlen($key)) {
      $this->selectedFilter = $key;
    }
    return $this->selectedFilter;
  }

  public function getSelectedFilter() {
    return $this->selectedFilter;
  }

  public function appendFooter($footer) {
    $this->footer[] = $footer;
    return $this;
  }

  public function getMainID() {
    if (!$this->mainID) {
      $this->mainID = celerity_generate_unique_node_id();
    }
    return $this->mainID;
  }

  public function render() {
    if ($this->menu->getItems()) {
      if (!$this->baseURI) {
        throw new PhutilInvalidStateException('setBaseURI');
      }
      if ($this->selectedFilter === false) {
        throw new PhutilInvalidStateException('selectFilter');
      }
    }

    if ($this->selectedFilter !== null) {
      $selected_item = $this->menu->getItem($this->selectedFilter);
      if ($selected_item) {
        $selected_item->addClass('phui-list-item-selected');
      }
    }

    require_celerity_resource('phui-basic-nav-view-css');

    return $this->renderFlexNav();
  }

  private function renderFlexNav() {
    require_celerity_resource('phabricator-nav-view-css');

    $nav_classes = array();
    $nav_classes[] = 'phabricator-nav';

    $nav_id = null;
    $drag_id = null;
    $content_id = celerity_generate_unique_node_id();
    $local_id = null;
    $background_id = null;
    $local_menu = null;
    $main_id = $this->getMainID();

    if ($this->flexible) {
      $drag_id = celerity_generate_unique_node_id();
      $flex_bar = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-nav-drag',
          'id' => $drag_id,
        ),
        '');
    } else {
      $flex_bar = null;
    }

    $nav_menu = null;
    if ($this->menu->getItems()) {
      $local_id = celerity_generate_unique_node_id();
      $background_id = celerity_generate_unique_node_id();

      if (!$this->collapsed) {
        $nav_classes[] = 'has-local-nav';
      }

      $local_menu =
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-nav-local phabricator-side-menu',
            'id'    => $local_id,
          ),
          $this->menu->setID($this->getMenuID()));
    }

    $crumbs = null;
    if ($this->crumbs) {
      $crumbs = $this->crumbs->render();
      $nav_classes[] = 'has-crumbs';
    }

    if ($this->flexible) {
      if (!$this->collapsed) {
        $nav_classes[] = 'has-drag-nav';
      } else {
        $nav_classes[] = 'has-closed-nav';
      }

      Javelin::initBehavior(
        'phabricator-nav',
        array(
          'mainID'        => $main_id,
          'localID'       => $local_id,
          'dragID'        => $drag_id,
          'contentID'     => $content_id,
          'backgroundID'  => $background_id,
          'collapsed'     => $this->collapsed,
        ));

      if ($this->active) {
        Javelin::initBehavior(
          'phabricator-active-nav',
          array(
            'localID' => $local_id,
          ));
      }
    }

    $nav_classes = array_merge($nav_classes, $this->classes);

    $menu = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $nav_classes),
        'id'    => $main_id,
      ),
      array(
        $local_menu,
        $flex_bar,
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-nav-content plb',
            'id' => $content_id,
          ),
          array(
            $crumbs,
            $this->renderChildren(),
            $this->footer,
          )),
      ));

    $classes = array();
    $classes[] = 'phui-navigation-shell';

    if ($this->getIsProfileMenu()) {
      $classes[] = 'phui-profile-menu phui-basic-nav';
    } else {
      $classes[] = 'phui-basic-nav';
    }

    $shell = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      array(
        $menu,
      ));

    return $shell;
  }

}
