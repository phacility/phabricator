<?php

final class AphrontSideNavView extends AphrontView {

  private $items = array();
  private $flexNav;
  private $isFlexible;
  private $user;
  private $active;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function addNavItem($item) {
    $this->items[] = $item;
    return $this;
  }

  public function setFlexNav($flex) {
    $this->flexNav = $flex;
    return $this;
  }

  public function setFlexible($flexible) {
    $this->isFlexible = $flexible;
    return $this;
  }

  public function setActive($active) {
    $this->active = $active;
    return $this;
  }

  public function render() {
    $view = new AphrontNullView();
    $view->appendChild($this->items);

    if ($this->flexNav) {
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

      if ($this->isFlexible) {
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
          $view->render());
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
    } else {

      require_celerity_resource('aphront-side-nav-view-css');

      return
        '<table class="aphront-side-nav-view">'.
          '<tr>'.
            '<th class="aphront-side-nav-navigation">'.
              $view->render().
            '</th>'.
            '<td class="aphront-side-nav-content">'.
              $this->renderChildren().
            '</td>'.
          '</tr>'.
        '</table>';
    }
  }

}
