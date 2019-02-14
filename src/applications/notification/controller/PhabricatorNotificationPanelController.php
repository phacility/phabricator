<?php

final class PhabricatorNotificationPanelController
  extends PhabricatorNotificationController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $unread_count = $viewer->getUnreadNotificationCount();

    $warning = $this->prunePhantomNotifications($unread_count);

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
      $clear_uri->replaceQueryParam(
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
      '%s%s%s%s',
      $header,
      $warning,
      $content,
      $connection_ui);

    $json = array(
      'content' => $content,
      'number'  => (int)$unread_count,
    );

    return id(new AphrontAjaxResponse())->setContent($json);
  }

  private function prunePhantomNotifications($unread_count) {
    // See T8953. If you have an unread notification about an object you
    // do not have permission to view, it isn't possible to clear it by
    // visiting the object. Identify these notifications and mark them as
    // read.

    $viewer = $this->getViewer();

    if (!$unread_count) {
      return null;
    }

    $table = new PhabricatorFeedStoryNotification();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT chronologicalKey, primaryObjectPHID FROM %T
        WHERE userPHID = %s AND hasViewed = 0',
      $table->getTableName(),
      $viewer->getPHID());
    if (!$rows) {
      return null;
    }

    $map = array();
    foreach ($rows as $row) {
      $map[$row['primaryObjectPHID']][] = $row['chronologicalKey'];
    }

    $handles = $viewer->loadHandles(array_keys($map));
    $purge_keys = array();
    foreach ($handles as $handle) {
      $phid = $handle->getPHID();
      if ($handle->isComplete()) {
        continue;
      }

      foreach ($map[$phid] as $chronological_key) {
        $purge_keys[] = $chronological_key;
      }
    }

    if (!$purge_keys) {
      return null;
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $conn = $table->establishConnection('w');
    queryfx(
      $conn,
      'UPDATE %T SET hasViewed = 1
        WHERE userPHID = %s AND chronologicalKey IN (%Ls)',
      $table->getTableName(),
      $viewer->getPHID(),
      $purge_keys);

    PhabricatorUserCache::clearCache(
      PhabricatorUserNotificationCountCacheType::KEY_COUNT,
      $viewer->getPHID());

    unset($unguarded);

    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-notification phabricator-notification-warning',
      ),
      pht(
        '%s notification(s) about objects which no longer exist or which '.
        'you can no longer see were discarded.',
        phutil_count($purge_keys)));
  }


}
