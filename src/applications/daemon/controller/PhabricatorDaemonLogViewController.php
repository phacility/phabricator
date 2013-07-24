<?php

final class PhabricatorDaemonLogViewController
  extends PhabricatorDaemonController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $log = id(new PhabricatorDaemonLogQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$log) {
      return new Aphront404Response();
    }

    $events = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      'logID = %d ORDER BY id DESC LIMIT 1000',
      $log->getID());

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Daemon %s', $log->getID())));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($log->getDaemon());

    $tag = id(new PhabricatorTagView())
      ->setType(PhabricatorTagView::TYPE_STATE);

    $status = $log->getStatus();
    switch ($status) {
      case PhabricatorDaemonLog::STATUS_UNKNOWN:
        $tag->setBackgroundColor(PhabricatorTagView::COLOR_ORANGE);
        $tag->setName(pht('Unknown'));
        break;
      case PhabricatorDaemonLog::STATUS_RUNNING:
        $tag->setBackgroundColor(PhabricatorTagView::COLOR_GREEN);
        $tag->setName(pht('Running'));
        break;
      case PhabricatorDaemonLog::STATUS_DEAD:
        $tag->setBackgroundColor(PhabricatorTagView::COLOR_RED);
        $tag->setName(pht('Dead'));
        break;
      case PhabricatorDaemonLog::STATUS_WAIT:
        $tag->setBackgroundColor(PhabricatorTagView::COLOR_BLUE);
        $tag->setName(pht('Waiting'));
        break;
      case PhabricatorDaemonLog::STATUS_EXITED:
        $tag->setBackgroundColor(PhabricatorTagView::COLOR_GREY);
        $tag->setName(pht('Exited'));
        break;
    }

    $header->addTag($tag);

    $properties = $this->buildPropertyListView($log);

    $event_view = id(new PhabricatorDaemonLogEventsView())
      ->setUser($user)
      ->setEvents($events);

    $event_panel = new AphrontPanelView();
    $event_panel->setNoBackground();
    $event_panel->appendChild($event_view);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $properties,
        $event_panel,
      ),
      array(
        'title' => pht('Daemon Log'),
      ));
  }

  private function buildPropertyListView(PhabricatorDaemonLog $daemon) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer);

    $id = $daemon->getID();
    $c_epoch = $daemon->getDateCreated();
    $u_epoch = $daemon->getDateModified();

    $unknown_time = PhabricatorDaemonLogQuery::getTimeUntilUnknown();
    $dead_time = PhabricatorDaemonLogQuery::getTimeUntilDead();
    $wait_time = PhutilDaemonOverseer::RESTART_WAIT;

    $details = null;
    $status = $daemon->getStatus();
    switch ($status) {
      case PhabricatorDaemonLog::STATUS_RUNNING:
        $details = pht(
          'This daemon is running normally and reported a status update '.
          'recently (within %s).',
          phabricator_format_relative_time($unknown_time));
        break;
      case PhabricatorDaemonLog::STATUS_UNKNOWN:
        $details = pht(
          'This daemon has not reported a status update recently (within %s). '.
          'It may have exited abruptly. After %s, it will be presumed dead.',
          phabricator_format_relative_time($unknown_time),
          phabricator_format_relative_time($dead_time));
        break;
      case PhabricatorDaemonLog::STATUS_DEAD:
        $details = pht(
          'This daemon did not report a status update for %s. It is '.
          'presumed dead. Usually, this indicates that the daemon was '.
          'killed or otherwise exited abruptly with an error. You may '.
          'need to restart it.',
          phabricator_format_relative_time($dead_time));
        break;
      case PhabricatorDaemonLog::STATUS_WAIT:
        $details = pht(
          'This daemon is running normally and reported a status update '.
          'recently (within %s). However, it encountered an error while '.
          'doing work and is waiting a little while (%s) to resume '.
          'processing. After encountering an error, daemons wait before '.
          'resuming work to avoid overloading services.',
          phabricator_format_relative_time($unknown_time),
          phabricator_format_relative_time($wait_time));
        break;
      case PhabricatorDaemonLog::STATUS_EXITED:
        $details = pht(
          'This daemon exited normally and is no longer running.');
        break;
    }

    $view->addProperty(pht('Status Details'), $details);

    $view->addProperty(pht('Daemon Class'), $daemon->getDaemon());
    $view->addProperty(pht('Host'), $daemon->getHost());
    $view->addProperty(pht('PID'), $daemon->getPID());
    $view->addProperty(pht('Started'), phabricator_datetime($c_epoch, $viewer));
    $view->addProperty(
      pht('Seen'),
      pht(
        '%s ago (%s)',
        phabricator_format_relative_time(time() - $u_epoch),
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
        "phabricator/ $ ./bin/phd log {$id}"));


    return $view;
  }

}
