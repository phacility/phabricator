<?php

final class PhabricatorUINotificationExample extends PhabricatorUIExample {

  public function getName() {
    return 'Notifications';
  }

  public function getDescription() {
    return hsprintf('Use <tt>JX.Notification</tt> to create notifications.');
  }

  public function renderExample() {

    require_celerity_resource('phabricator-notification-css');
    Javelin::initBehavior('phabricator-notification-example');

    $content = javelin_tag(
      'a',
      array(
        'sigil' => 'notification-example',
        'class' => 'button green',
      ),
      'Show Notification');

    $content = hsprintf('<div style="padding: 1em 3em;">%s</div>', $content);

    return $content;
  }
}
