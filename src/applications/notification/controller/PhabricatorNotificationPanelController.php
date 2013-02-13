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
      $content = hsprintf(
        '<div class="phabricator-notification no-notifications">%s</div>',
        pht('You have no notifications.'));
    }

    $content = hsprintf(
      '<div class="phabricator-notification-header">%s</div>'.
      '%s'.
      '<div class="phabricator-notification-view-all">%s</div>',
      pht('Notifications'),
      $content,
      phutil_tag(
        'a',
        array(
          'href' => '/notification/',
        ),
        'View All Notifications'));

    $unread_count = id(new PhabricatorFeedStoryNotification())
      ->countUnread($user);

    $json = array(
      'content' => $content,
      'number'  => $unread_count,
    );

    return id(new AphrontAjaxResponse())->setContent($json);
  }
}
