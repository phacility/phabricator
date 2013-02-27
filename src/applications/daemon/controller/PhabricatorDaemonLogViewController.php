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

    $log = id(new PhabricatorDaemonLog())->load($this->id);
    if (!$log) {
      return new Aphront404Response();
    }

    $events = id(new PhabricatorDaemonLogEvent())->loadAllWhere(
      'logID = %d ORDER BY id DESC LIMIT 1000',
      $log->getID());

    $content = array();

    $argv = $log->getArgv();
    if (is_array($argv)) {
      $argv = implode("\n", $argv);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Daemon'))
          ->setValue($log->getDaemon()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Host'))
          ->setValue($log->getHost()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('PID'))
          ->setValue($log->getPID()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('Started'))
          ->setValue(
            phabricator_datetime($log->getDateCreated(), $user)))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Argv'))
          ->setValue($argv));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Daemon Details'));
    $panel->setNoBackground();
    $panel->appendChild($form);

    $content[] = $panel;

    $event_view = new PhabricatorDaemonLogEventsView();
    $event_view->setUser($user);
    $event_view->setEvents($events);

    $log_panel = new AphrontPanelView();
    $log_panel->setHeader(pht('Daemon Logs'));
    $log_panel->setNoBackground();
    $log_panel->appendChild($event_view);

    $content[] = $log_panel;

    $nav = $this->buildSideNavView();
    $nav->selectFilter('log');
    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Daemon Log'),
      ));
  }

}
