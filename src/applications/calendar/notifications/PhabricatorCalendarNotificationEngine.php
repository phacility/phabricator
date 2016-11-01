<?php

final class PhabricatorCalendarNotificationEngine
  extends Phobject {

  private $cursor;

  public function getCursor() {
    if (!$this->cursor) {
      $now = PhabricatorTime::getNow();
      $this->cursor = $now - phutil_units('5 minutes in seconds');
    }

    return $this->cursor;
  }

  public function publishNotifications() {
    $cursor = $this->getCursor();

    $window_min = $cursor - phutil_units('16 hours in seconds');
    $window_max = $cursor + phutil_units('16 hours in seconds');

    $viewer = PhabricatorUser::getOmnipotentUser();

    $events = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withDateRange($window_min, $window_max)
      ->withIsCancelled(false)
      ->withIsImported(false)
      ->setGenerateGhosts(true)
      ->execute();
    if (!$events) {
      // No events are starting soon in any timezone, so there is nothing
      // left to be done.
      return;
    }

    $attendee_map = array();
    foreach ($events as $key => $event) {
      $notifiable_phids = array();
      foreach ($event->getInvitees() as $invitee) {
        if (!$invitee->isAttending()) {
          continue;
        }
        $notifiable_phids[] = $invitee->getInviteePHID();
      }
      if (!$notifiable_phids) {
        unset($events[$key]);
      }
      $attendee_map[$key] = array_fuse($notifiable_phids);
    }
    if (!$attendee_map) {
      // None of the events have any notifiable attendees, so there is no
      // one to notify of anything.
      return;
    }

    $all_attendees = array();
    foreach ($attendee_map as $key => $attendee_phids) {
      foreach ($attendee_phids as $attendee_phid) {
        $all_attendees[$attendee_phid] = $attendee_phid;
      }
    }

    $user_map = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs($all_attendees)
      ->withIsDisabled(false)
      ->needUserSettings(true)
      ->execute();
    $user_map = mpull($user_map, null, 'getPHID');
    if (!$user_map) {
      // None of the attendees are valid users: they're all imported users
      // or projects or invalid or some other kind of unnotifiable entity.
      return;
    }

    $all_event_phids = array();
    foreach ($events as $key => $event) {
      foreach ($event->getNotificationPHIDs() as $phid) {
        $all_event_phids[$phid] = $phid;
      }
    }

    $table = new PhabricatorCalendarNotification();
    $conn = $table->establishConnection('w');

    $rows = queryfx_all(
      $conn,
      'SELECT * FROM %T WHERE eventPHID IN (%Ls) AND targetPHID IN (%Ls)',
      $table->getTableName(),
      $all_event_phids,
      $all_attendees);
    $sent_map = array();
    foreach ($rows as $row) {
      $event_phid = $row['eventPHID'];
      $target_phid = $row['targetPHID'];
      $initial_epoch = $row['utcInitialEpoch'];
      $sent_map[$event_phid][$target_phid][$initial_epoch] = $row;
    }

    $notify_min = $cursor;
    $notify_max = $cursor + phutil_units('15 minutes in seconds');
    $notify_map = array();
    foreach ($events as $key => $event) {
      $initial_epoch = $event->getUTCInitialEpoch();
      $event_phids = $event->getNotificationPHIDs();

      // Select attendees who actually exist, and who we have not sent any
      // notifications to yet.
      $attendee_phids = $attendee_map[$key];
      $users = array_select_keys($user_map, $attendee_phids);
      foreach ($users as $user_phid => $user) {
        foreach ($event_phids as $event_phid) {
          if (isset($sent_map[$event_phid][$user_phid][$initial_epoch])) {
            unset($users[$user_phid]);
            continue 2;
          }
        }
      }

      if (!$users) {
        continue;
      }

      // Discard attendees for whom the event start time isn't soon. Events
      // may start at different times for different users, so we need to
      // check every user's start time.
      foreach ($users as $user_phid => $user) {
        $user_datetime = $event->newStartDateTime()
          ->setViewerTimezone($user->getTimezoneIdentifier());

        $user_epoch = $user_datetime->getEpoch();
        if ($user_epoch < $notify_min || $user_epoch > $notify_max) {
          unset($users[$user_phid]);
          continue;
        }

        $notify_map[$user_phid][] = array(
          'event' => $event,
          'datetime' => $user_datetime,
          'epoch' => $user_epoch,
        );
      }
    }

    $mail_list = array();
    $mark_list = array();
    $now = PhabricatorTime::getNow();
    foreach ($notify_map as $user_phid => $events) {
      $user = $user_map[$user_phid];
      $events = isort($events, 'epoch');

      // TODO: This is just a proof-of-concept that gets dumped to the console;
      // it will be replaced with a nice fancy email and notification.

      $body = array();
      $body[] = pht('%s, these events start soon:', $user->getUsername());
      $body[] = null;
      foreach ($events as $spec) {
        $event = $spec['event'];
        $body[] = $event->getName();
      }
      $body = implode("\n", $body);

      $mail_list[] = $body;

      foreach ($events as $spec) {
        $event = $spec['event'];
        foreach ($event->getNotificationPHIDs() as $phid) {
          $mark_list[] = qsprintf(
            $conn,
            '(%s, %s, %d, %d)',
            $phid,
            $user_phid,
            $event->getUTCInitialEpoch(),
            $now);
        }
      }
    }

    // Mark all the notifications we're about to send as delivered so we
    // do not double-notify.
    foreach (PhabricatorLiskDAO::chunkSQL($mark_list) as $chunk) {
      queryfx(
        $conn,
        'INSERT IGNORE INTO %T
          (eventPHID, targetPHID, utcInitialEpoch, didNotifyEpoch)
          VALUES %Q',
        $table->getTableName(),
        $chunk);
    }

    foreach ($mail_list as $mail) {
      echo $mail;
      echo "\n\n";
    }
  }

}
