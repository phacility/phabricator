<?php

final class PhabricatorNotificationStoryView
extends PhabricatorNotificationView {

  private $title;
  private $phid;
  private $epoch;
  private $viewed;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function setViewed($viewed) {
    $this->viewed = $viewed;
  }

  public function render() {

    $classes = array(
      'phabricator-notification',
    );

    if (!$this->viewed) {
      $classes[] = 'phabricator-notification-unread';
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $this->title);
  }

}
