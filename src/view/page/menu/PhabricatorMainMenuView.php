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
    require_celerity_resource('sprite-menu-css');

    $count_id = celerity_generate_unique_node_id();
    $dropdown_id = celerity_generate_unique_node_id();
    $bubble_id = celerity_generate_unique_node_id();

    $count_number = id(new PhabricatorFeedStoryNotification())
      ->countUnread($user);

    if ($count_number > 999) {
      $count_number = "\xE2\x88\x9E";
    }

    $count_tag = phutil_render_tag(
      'span',
      array(
        'id'    => $count_id,
        'class' => 'phabricator-main-menu-alert-count'
      ),
      phutil_escape_html($count_number));

    $icon_tag = phutil_render_tag(
      'span',
      array(
        'class' => 'sprite-menu phabricator-main-menu-alert-icon',
      ),
      '');

    $container_classes = array(
      'phabricator-main-menu-alert-bubble',
      'sprite-menu',
      'alert-notifications',
    );
    if ($count_number) {
      $container_classes[] = 'alert-unread';
    }

    $bubble_tag = phutil_render_tag(
      'a',
      array(
        'href'  => '/notification/',
        'class' => implode(' ', $container_classes),
        'id'    => $bubble_id,
      ),
      $icon_tag.$count_tag);

    Javelin::initBehavior(
      'aphlict-dropdown',
      array(
        'bubbleID'    => $bubble_id,
        'countID'     => $count_id,
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

    return array($bubble_tag, $notification_dropdown);
  }

}
