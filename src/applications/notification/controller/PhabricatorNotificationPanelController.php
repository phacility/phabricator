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
      $content = phutil_tag_div(
        'phabricator-notification no-notifications',
        pht('You have no notifications.'));
    }

    $notifications_link = phutil_tag(
      'a',
      array(
        'href' => '/notification/',
      ),
      pht('Notifications'));

    $connection_status = new PhabricatorNotificationStatusView();

    $header = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-notification-header',
      ),
      array(
        $connection_status,
        $notifications_link,
      ));

    $content = hsprintf(
      '%s'.
      '%s'.
      '<div class="phabricator-notification-view-all">%s %s %s</div>',
      $header,
      $content,
      javelin_tag(
        'a',
        array(
          'sigil' => 'workflow',
          'href' => '/notification/clear/',
          'class' => 'phabricator-notification-clear-all'
        ),
        pht('Mark All Read')),
      " \xC2\xB7 ",
      phutil_tag(
        'a',
        array(
          'href' => '/notification/',
        ),
        pht('View All Notifications')));

    $unread_count = id(new PhabricatorFeedStoryNotification())
      ->countUnread($user);

    $json = array(
      'content' => $content,
      'number'  => (int)$unread_count,
    );

    return id(new AphrontAjaxResponse())->setContent($json);
  }
}
