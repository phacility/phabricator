<?php

/**
 * Like an @{class:AphrontSideNavView}, but with a little bit of logic for the
 * common case where you're using the side nav to filter some view of objects.
 *
 * For example:
 *
 *    $nav = new AphrontSideNavFilterView();
 *    $nav
 *      ->setBaseURI($some_uri)
 *      ->addLabel('Cats')
 *      ->addFilter('meow', 'Meow')
 *      ->addFilter('purr', 'Purr')
 *      ->addSpacer()
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
  private $flexNav;
  private $flexible;
  private $user;
  private $active;
  private $menu;

  public function __construct() {
    $this->menu = new PhabricatorMenuView();
  }

  public function setActive($active) {
    $this->active = $active;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setFlexNav($flex_nav) {
    $this->flexNav = $flex_nav;
    return $this;
  }

  public function setFlexible($flexible) {
    $this->flexible = $flexible;
    return $this;
  }

  public function addMenuItem(PhabricatorMenuItemView $item) {
    $this->menu->addMenuItem($item);
    return $this;
  }

  public function addFilter(
    $key,
    $name,
    $uri = null) {

    $item = id(new PhabricatorMenuItemView())
      ->setKey($key)
      ->setName($name);

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
    $this->menu->appendChild($block);
    return $this;
  }

  public function addLabel($name) {
    return $this->addMenuItem(
      id(new PhabricatorMenuItemView())
        ->setType(PhabricatorMenuItemView::TYPE_LABEL)
        ->setName($name));
  }

  public function addSpacer() {
    return $this->addMenuItem(
      id(new PhabricatorMenuItemView())
        ->setType(PhabricatorMenuItemView::TYPE_SPACER));
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
    if ($this->menu->getItem($key)) {
      $this->selectedFilter = $key;
    }
    return $this->selectedFilter;
  }

  public function render() {
    if ($this->menu->getItems()) {
      if (!$this->baseURI) {
        throw new Exception("Call setBaseURI() before render()!");
      }
      if ($this->selectedFilter === false) {
        throw new Exception("Call selectFilter() before render()!");
      }
    }

    $selected_item = $this->menu->getItem($this->selectedFilter);
    if ($selected_item) {
      $selected_item->addClass('phabricator-menu-item-selected');
    }

    if ($this->flexNav) {
      return $this->renderFlexNav();
    } else {
      return $this->renderLegacyNav();
    }
  }

  private function renderFlexNav() {

    $user = $this->user;

    require_celerity_resource('phabricator-nav-view-css');

    $nav_classes = array();
    $nav_classes[] = 'phabricator-nav';

    $nav_id = null;
    $drag_id = null;
    $content_id = celerity_generate_unique_node_id();
    $local_id = null;
    $local_menu = null;
    $main_id = celerity_generate_unique_node_id();

    if ($this->flexible) {
      $drag_id = celerity_generate_unique_node_id();
      $flex_bar = phutil_render_tag(
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
      $nav_classes[] = 'has-local-nav';
      $local_menu = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-nav-col phabricator-nav-local',
          'id'    => $local_id,
        ),
        self::renderSingleView($this->menu));
    }

    Javelin::initBehavior(
      'phabricator-nav',
      array(
        'mainID'      => $main_id,
        'localID'     => $local_id,
        'dragID'      => $drag_id,
        'contentID'   => $content_id,
      ));

    if ($this->active && $local_id) {
      Javelin::initBehavior(
        'phabricator-active-nav',
        array(
          'localID' => $local_id,
        ));
    }

    $header_part =
      '<div class="phabricator-nav-head">'.
        '<div class="phabricator-nav-head-tablet">'.
          '<a href="#" class="nav-button nav-button-w nav-button-menu" '.
            'id="tablet-menu1"></a>'.
          '<a href="#" class="nav-button nav-button-e nav-button-content '.
            'nav-button-selected" id="tablet-menu2"></a>'.
        '</div>'.
      '</div>';

    return $header_part.phutil_render_tag(
      'div',
      array(
        'class' => implode(' ', $nav_classes),
        'id'    => $main_id,
      ),
      $local_menu.
      $flex_bar.
      phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-nav-content',
          'id' => $content_id,
        ),
        $this->renderChildren()));
  }

  public function renderLegacyNav() {
    require_celerity_resource('aphront-side-nav-view-css');

    return
      '<table class="aphront-side-nav-view">'.
        '<tr>'.
          '<th class="aphront-side-nav-navigation">'.
            self::renderSingleView($this->menu).
          '</th>'.
          '<td class="aphront-side-nav-content">'.
            $this->renderChildren().
          '</td>'.
        '</tr>'.
      '</table>';
  }

}
