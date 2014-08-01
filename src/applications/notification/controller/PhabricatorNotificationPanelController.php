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

    $clear_ui_class = 'phabricator-notification-clear-all';
    $clear_uri = id(new PhutilURI('/notification/clear/'));
    if ($stories) {
      $builder = new PhabricatorNotificationBuilder($stories);
      $notifications_view = $builder->buildView();
      $content = $notifications_view->render();
      $clear_uri->setQueryParam(
        'chronoKey',
        head($stories)->getChronologicalKey());
    } else {
      $content = phutil_tag_div(
        'phabricator-notification no-notifications',
        pht('You have no notifications.'));
      $clear_ui_class .= ' disabled';
    }
    $clear_ui = javelin_tag(
      'a',
      array(
        'sigil' => 'workflow',
        'href' => (string) $clear_uri,
        'class' => $clear_ui_class,
      ),
      pht('Mark All Read'));

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
      $clear_ui,
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
