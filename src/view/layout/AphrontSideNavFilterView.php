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

  public function addFilter(
    $key,
    $name,
    $uri = null,
    $relative = false,
    $class = null) {

    $this->items[] = array(
      'filter',
      $key,
      $name,
      'uri' => $uri,
      'relative' => $relative,
      'class' => $class,
    );

    return $this;
  }

  public function addFilters(array $views) {
    foreach ($views as $view) {
      $uri = isset($view['uri']) ? $view['uri'] : null;
      $relative = isset($view['relative']) ? $view['relative'] : false;
      $this->addFilter(
        $view['key'],
        $view['name'],
        $uri,
        $relative);
    }
  }

  public function addCustomBlock($block) {
    $this->items[] = array('custom', null, $block);
    return $this;
  }

  public function addLabel($name) {
    $this->items[] = array('label', null, $name);
    return $this;
  }

  public function addSpacer() {
    $this->items[] = array('spacer', null, null);
    return $this;
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
    if ($key !== null) {
      foreach ($this->items as $item) {
        if ($item[0] == 'filter') {
          if ($item[1] == $key) {
            $this->selectedFilter = $key;
            break;
          }
        }
      }
    }
    return $this->selectedFilter;
  }

  public function render() {
    if ($this->items) {
      if (!$this->baseURI) {
        throw new Exception("Call setBaseURI() before render()!");
      }
      if ($this->selectedFilter === false) {
        throw new Exception("Call selectFilter() before render()!");
      }
    }

    if ($this->flexNav) {
      return $this->renderFlexNav();
    } else {
      return $this->renderLegacyNav();
    }
  }

  private function renderNavItems() {
    $results = array();
    foreach ($this->items as $item) {
      list($type, $key, $name) = $item;
      switch ($type) {
        case 'custom':
          $results[] = $name;
          break;
        case 'spacer':
          $results[] = '<br />';
          break;
        case 'label':
          $results[] = phutil_render_tag(
            'span',
            array(),
            phutil_escape_html($name));
          break;
        case 'filter':
          $class = ($key == $this->selectedFilter)
            ? 'aphront-side-nav-selected'
            : null;

          $class = trim($class.' '.idx($item, 'class', ''));

          if (empty($item['uri'])) {
            $href = clone $this->baseURI;
            $href->setPath(rtrim($href->getPath().$key, '/').'/');
            $href = (string)$href;
          } else {
            if (empty($item['relative'])) {
              $href = $item['uri'];
            } else {
              $href = clone $this->baseURI;
              $href->setPath($href->getPath().$item['uri']);
              $href = (string)$href;
            }
          }

          $results[] = phutil_render_tag(
            'a',
            array(
              'href'  => $href,
              'class' => $class,
            ),
            phutil_escape_html($name));
          break;
        default:
          throw new Exception("Unknown item type '{$type}'.");
      }
    }
    return $results;
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
    if ($this->items) {
      $local_id = celerity_generate_unique_node_id();
      $nav_classes[] = 'has-local-nav';
      $local_menu = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-nav-col phabricator-nav-local',
          'id'    => $local_id,
        ),
        self::renderSingleView($this->renderNavItems()));
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
            self::renderSingleView($this->renderNavItems()).
          '</th>'.
          '<td class="aphront-side-nav-content">'.
            $this->renderChildren().
          '</td>'.
        '</tr>'.
      '</table>';
  }

}
