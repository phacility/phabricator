<?php

final class PhabricatorNotificationPanelController
  extends PhabricatorNotificationController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorNotificationQuery();
    $query->setViewer($user);
    $query->setUserPHID($user->getPHID());
    $query->setLimit(15);

    $stories = $query->execute();

    if ($stories) {
      $builder = new PhabricatorNotificationBuilder($stories);
      $notifications_view = $builder->buildView();
      $content = $notifications_view->render();
    } else {
      $content =
        '<div class="phabricator-notification no-notifications">'.
          'You have no notifications.'.
        '</div>';
    }

    $content .=
      '<div class="phabricator-notification view-all-notifications">'.
        phutil_render_tag(
          'a',
          array(
            'href' => '/notification/',
          ),
          'View All Notifications').
      '</div>';

    $unread_count = id(new PhabricatorFeedStoryNotification())
      ->countUnread($user);

    $json = array(
      'content' => $content,
      'number'  => $unread_count,
    );

    return id(new AphrontAjaxResponse())->setContent($json);
  }
}
