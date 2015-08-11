<?php

final class PhabricatorDaemonLogViewController
  extends PhabricatorDaemonController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $log = id(new PhabricatorDaemonLogQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->setAllowStatusWrites(true)
      ->executeOne();
    if (!$log) {
      return new Aphront404Response();
    }

    $events = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      'logID = %d ORDER BY id DESC LIMIT 1000',
      $log->getID());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Daemon %s', $log->getID()));

    $header = id(new PHUIHeaderView())
      ->setHeader($log->getDaemon());

    $tag = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_STATE);

    $status = $log->getStatus();
    switch ($status) {
      case PhabricatorDaemonLog::STATUS_UNKNOWN:
        $tag->setBackgroundColor(PHUITagView::COLOR_ORANGE);
        $tag->setName(pht('Unknown'));
        break;
      case PhabricatorDaemonLog::STATUS_RUNNING:
        $tag->setBackgroundColor(PHUITagView::COLOR_GREEN);
        $tag->setName(pht('Running'));
        break;
      case PhabricatorDaemonLog::STATUS_DEAD:
        $tag->setBackgroundColor(PHUITagView::COLOR_RED);
        $tag->setName(pht('Dead'));
        break;
      case PhabricatorDaemonLog::STATUS_WAIT:
        $tag->setBackgroundColor(PHUITagView::COLOR_BLUE);
        $tag->setName(pht('Waiting'));
        break;
      case PhabricatorDaemonLog::STATUS_EXITING:
        $tag->setBackgroundColor(PHUITagView::COLOR_YELLOW);
        $tag->setName(pht('Exiting'));
        break;
      case PhabricatorDaemonLog::STATUS_EXITED:
        $tag->setBackgroundColor(PHUITagView::COLOR_GREY);
        $tag->setName(pht('Exited'));
        break;
    }

    $header->addTag($tag);
    $env_hash = PhabricatorEnv::calculateEnvironmentHash();
    if ($log->getEnvHash() != $env_hash) {
      $tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_STATE)
        ->setBackgroundColor(PHUITagView::COLOR_YELLOW)
        ->setName(pht('Stale Config'));
      $header->addTag($tag);
    }

    $properties = $this->buildPropertyListView($log);

    $event_view = id(new PhabricatorDaemonLogEventsView())
      ->setUser($viewer)
      ->setEvents($events);

    $event_panel = new PHUIObjectBoxView();
    $event_panel->setHeaderText(pht('Events'));
    $event_panel->appendChild($event_view);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $event_panel,
      ),
      array(
        'title' => pht('Daemon Log'),
      ));
  }

  private function buildPropertyListView(PhabricatorDaemonLog $daemon) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $id = $daemon->getID();
    $c_epoch = $daemon->getDateCreated();
    $u_epoch = $daemon->getDateModified();

    $unknown_time = PhabricatorDaemonLogQuery::getTimeUntilUnknown();
    $dead_time = PhabricatorDaemonLogQuery::getTimeUntilDead();
    $wait_time = PhutilDaemonHandle::getWaitBeforeRestart();

    $details = null;
    $status = $daemon->getStatus();
    switch ($status) {
      case PhabricatorDaemonLog::STATUS_RUNNING:
        $details = pht(
          'This daemon is running normally and reported a status update '.
          'recently (within %s).',
          phutil_format_relative_time($unknown_time));
        break;
      case PhabricatorDaemonLog::STATUS_UNKNOWN:
        $details = pht(
          'This daemon has not reported a status update recently (within %s). '.
          'It may have exited abruptly. After %s, it will be presumed dead.',
          phutil_format_relative_time($unknown_time),
          phutil_format_relative_time($dead_time));
        break;
      case PhabricatorDaemonLog::STATUS_DEAD:
        $details = pht(
          'This daemon did not report a status update for %s. It is '.
          'presumed dead. Usually, this indicates that the daemon was '.
          'killed or otherwise exited abruptly with an error. You may '.
          'need to restart it.',
          phutil_format_relative_time($dead_time));
        break;
      case PhabricatorDaemonLog::STATUS_WAIT:
        $details = pht(
          'This daemon is running normally and reported a status update '.
          'recently (within %s). However, it encountered an error while '.
          'doing work and is waiting a little while (%s) to resume '.
          'processing. After encountering an error, daemons wait before '.
          'resuming work to avoid overloading services.',
          phutil_format_relative_time($unknown_time),
          phutil_format_relative_time($wait_time));
        break;
      case PhabricatorDaemonLog::STATUS_EXITING:
        $details = pht('This daemon is shutting down gracefully.');
        break;
      case PhabricatorDaemonLog::STATUS_EXITED:
        $details = pht('This daemon exited normally and is no longer running.');
        break;
    }

    $view->addProperty(pht('Status Details'), $details);

    $view->addProperty(pht('Daemon Class'), $daemon->getDaemon());
    $view->addProperty(pht('Host'), $daemon->getHost());
    $view->addProperty(pht('PID'), $daemon->getPID());
    $view->addProperty(pht('Running as'), $daemon->getRunningAsUser());
    $view->addProperty(pht('Started'), phabricator_datetime($c_epoch, $viewer));
    $view->addProperty(
      pht('Seen'),
      pht(
        '%s ago (%s)',
        phutil_format_relative_time(time() - $u_epoch),
        phabricator_datetime($u_epoch, $viewer)));

    $argv = $daemon->getArgv();
    if (is_array($argv)) {
      $argv = implode("\n", $argv);
    }

    $view->addProperty(
      pht('Argv'),
      phutil_tag(
        'textarea',
        array(
          'style'   => 'width: 100%; height: 12em;',
        ),
        $argv));

    $view->addProperty(
      pht('View Full Logs'),
      phutil_tag(
        'tt',
        array(),
        "phabricator/ $ ./bin/phd log --id {$id}"));


    return $view;
  }

}
