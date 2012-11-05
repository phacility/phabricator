<?php

final class PhabricatorFeedStoryUnknown extends PhabricatorFeedStory {

  public function renderView() {
    $data = $this->getStoryData();

    $view = new PhabricatorFeedStoryView();

    $view->setTitle('Unknown Story');
    $view->setEpoch($data->getEpoch());

    $view->appendChild(
      'This is an unrenderable feed story of type '.
      '"'.phutil_escape_html($data->getStoryType()).'".');


    return $view;
  }

  public function renderNotificationView() {
    $data = $this->getStoryData();

    $view = new PhabricatorNotificationStoryView();

    $view->setTitle('A wild notifcation appeared!');
    $view->setEpoch($data->getEpoch());

    $view->appendChild(
      'This is an unrenderable feed story of type '.
      '"'.phutil_escape_html($data->getStoryType()).'".');


    return $view;

  }

}
