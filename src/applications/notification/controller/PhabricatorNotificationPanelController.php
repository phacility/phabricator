<?php

final class PhabricatorNotificationPanelController
  extends PhabricatorNotificationController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $query = id(new PhabricatorNotificationQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->setLimit(10);

    $stories = $query->execute();

    $clear_ui_class = 'phabricator-notification-clear-all';
    $clear_uri = id(new PhutilURI('/notification/clear/'));
    if ($stories) {
      $builder = id(new PhabricatorNotificationBuilder($stories))
        ->setUser($viewer);

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
        'href' => (string)$clear_uri,
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

    $connection_ui = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-notification-footer',
      ),
      $connection_status);

    $header = phutil_tag(
      'div',
      array(
        'class' => 'phabricator-notification-header',
      ),
      array(
        $notifications_link,
        $clear_ui,
      ));

    $content = hsprintf(
      '%s%s%s',
      $header,
      $content,
      $connection_ui);

    $unread_count = $viewer->getUnreadNotificationCount();

    $json = array(
      'content' => $content,
      'number'  => (int)$unread_count,
    );

    return id(new AphrontAjaxResponse())->setContent($json);
  }
}
