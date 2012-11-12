<?php

final class PhabricatorUINotificationExample extends PhabricatorUIExample {

  public function getName() {
    return 'Notifications';
  }

  public function getDescription() {
    return 'Use <tt>JX.Notification</tt> to create notifications.';
  }

  public function renderExample() {

    require_celerity_resource('phabricator-notification-css');
    Javelin::initBehavior('phabricator-notification-example');

    $content = javelin_render_tag(
      'a',
      array(
        'sigil' => 'notification-example',
        'class' => 'button green',
      ),
      'Show Notification');

    $content = '<div style="padding: 1em 3em;">'.$content.'</content>';

    return $content;
  }
}
