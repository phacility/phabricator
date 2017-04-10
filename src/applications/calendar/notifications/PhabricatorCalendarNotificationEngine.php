<?php

final class PhabricatorCalendarNotificationEngine
  extends Phobject {

  private $cursor;
  private $notifyWindow;

  public function getCursor() {
    if (!$this->cursor) {
      $now = PhabricatorTime::getNow();
      $this->cursor = $now - phutil_units('10 minutes in seconds');
    }

    return $this->cursor;
  }

  public function setCursor($cursor) {
    $this->cursor = $cursor;
    return $this;
  }

  public function setNotifyWindow($notify_window) {
    $this->notifyWindow = $notify_window;
    return $this;
  }

  public function getNotifyWindow() {
    if (!$this->notifyWindow) {
      return phutil_units('15 minutes in seconds');
    }

    return $this->notifyWindow;
  }

  public function publishNotifications() {
    $cursor = $this->getCursor();

    $now = PhabricatorTime::getNow();
    if ($cursor > $now) {
      return;
    }

    $calendar_class = 'PhabricatorCalendarApplication';
    if (!PhabricatorApplication::isClassInstalled($calendar_class)) {
      return;
    }

    try {
      $lock = PhabricatorGlobalLock::newLock('calendar.notify')
        ->lock(5);
    } catch (PhutilLockException $ex) {
      return;
    }

    $caught = null;
    try {
      $this->sendNotifications();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $lock->unlock();

    // Wait a little while before checking for new notifications to send.
    $this->setCursor($cursor + phutil_units('1 minute in seconds'));

    if ($caught) {
      throw $caught;
    }
  }

  private function sendNotifications() {
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

    $now = PhabricatorTime::getNow();
    $notify_min = $now;
    $notify_max = $now + $this->getNotifyWindow();
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

        $view = id(new PhabricatorCalendarEventNotificationView())
          ->setViewer($user)
          ->setEvent($event)
          ->setDateTime($user_datetime)
          ->setEpoch($user_epoch);

        $notify_map[$user_phid][] = $view;
      }
    }

    $mail_list = array();
    $mark_list = array();
    $now = PhabricatorTime::getNow();
    foreach ($notify_map as $user_phid => $events) {
      $user = $user_map[$user_phid];

      $locale = PhabricatorEnv::beginScopedLocale($user->getTranslation());
      $caught = null;
      try {
        $mail_list[] = $this->newMailMessage($user, $events);
      } catch (Exception $ex) {
        $caught = $ex;
      }

      unset($locale);

      if ($caught) {
        throw $ex;
      }

      foreach ($events as $view) {
        $event = $view->getEvent();
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
      $mail->saveAndSend();
    }
  }


  private function newMailMessage(PhabricatorUser $viewer, array $events) {
    $events = msort($events, 'getEpoch');

    $next_event = head($events);

    $body = new PhabricatorMetaMTAMailBody();
    foreach ($events as $event) {
      $body->addTextSection(
        null,
        pht(
          '%s is starting in %s minute(s), at %s.',
          $event->getEvent()->getName(),
          $event->getDisplayMinutes(),
          $event->getDisplayTimeWithTimezone()));

      $body->addLinkSection(
        pht('EVENT DETAIL'),
        PhabricatorEnv::getProductionURI($event->getEvent()->getURI()));
    }

    $next_event = head($events)->getEvent();
    $subject = $next_event->getName();
    if (count($events) > 1) {
      $more = pht(
        '(+%s more...)',
        new PhutilNumber(count($events) - 1));
      $subject = "{$subject} {$more}";
    }

    $calendar_phid = id(new PhabricatorCalendarApplication())
      ->getPHID();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->addTos(array($viewer->getPHID()))
      ->setSensitiveContent(false)
      ->setFrom($calendar_phid)
      ->setIsBulk(true)
      ->setSubjectPrefix(pht('[Calendar]'))
      ->setVarySubjectPrefix(pht('[Reminder]'))
      ->setThreadID($next_event->getPHID(), false)
      ->setRelatedPHID($next_event->getPHID())
      ->setBody($body->render())
      ->setHTMLBody($body->renderHTML());
  }

}
