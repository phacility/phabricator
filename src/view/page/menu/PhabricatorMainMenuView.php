<?php

final class PhabricatorMainMenuView extends AphrontView {

  private $user;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
  }

  public function render() {
    $user = $this->user;

    require_celerity_resource('phabricator-main-menu-view');

    $header_id = celerity_generate_unique_node_id();
    $extra = '';

    $group = new PhabricatorMainMenuGroupView();
    $group->addClass('phabricator-main-menu-group-logo');
    $group->setCollapsible(false);

    $group->appendChild(
      phutil_render_tag(
        'a',
        array(
          'class' => 'phabricator-main-menu-logo',
          'href'  => '/',
        ),
        '<span>Phabricator</span>'));

    if (PhabricatorEnv::getEnvConfig('notification.enabled') &&
        $user->isLoggedIn()) {
      list($menu, $dropdown) = $this->renderNotificationMenu();
      $group->appendChild($menu);
      $extra .= $dropdown;
    }

    $group->appendChild(
      javelin_render_tag(
        'a',
        array(
          'class' => 'phabricator-main-menu-expand-button',
          'sigil' => 'jx-toggle-class',
          'meta'  => array(
            'map' => array(
              $header_id => 'phabricator-main-menu-reveal',
            ),
          ),
        ),
        '<span>Expand</span>'));
    $logo = $group->render();

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-main-menu',
        'id'    => $header_id,
      ),
      $logo.$this->renderChildren()).
      $extra;
  }

  private function renderNotificationMenu() {
    $user = $this->user;

    require_celerity_resource('phabricator-notification-css');
    require_celerity_resource('phabricator-notification-menu-css');

    $indicator_id = celerity_generate_unique_node_id();
    $dropdown_id = celerity_generate_unique_node_id();
    $menu_id = celerity_generate_unique_node_id();

    $notification_count = id(new PhabricatorFeedStoryNotification())
      ->countUnread($user);

    $classes = array(
      'phabricator-main-menu-alert-indicator',
    );
    if ($notification_count) {
      $classes[] = 'phabricator-main-menu-alert-indicator-unread';
    }

    $notification_indicator = javelin_render_tag(
      'span',
      array(
        'id' => $indicator_id,
        'class' => implode(' ', $classes),
      ),
      $notification_count);

    $classes = array();
    $classes[] = 'phabricator-main-menu-alert-item';
    $classes[] = 'phabricator-main-menu-alert-item-notification';
    $classes[] = 'autosprite';
    $classes[] = 'main-menu-item-icon-notifications';

    $notification_icon = javelin_render_tag(
      'a',
      array(
        'href'  => '/notification/',
        'class' => implode(' ', $classes),
        'id'    => $menu_id,
      ),
      $notification_indicator);

    $notification_menu = javelin_render_tag(
      'div',
      array(
        'class' => 'phabricator-main-menu-alert',
      ),
      $notification_icon);

    Javelin::initBehavior(
      'aphlict-dropdown',
      array(
        'menuID'      => $menu_id,
        'indicatorID' => $indicator_id,
        'dropdownID'  => $dropdown_id,
      ));

    $notification_dropdown = javelin_render_tag(
      'div',
      array(
        'id'    => $dropdown_id,
        'class' => 'phabricator-notification-menu',
        'sigil' => 'phabricator-notification-menu',
        'style' => 'display: none;',
      ),
      '');

    return array($notification_menu, $notification_dropdown);
  }

}
