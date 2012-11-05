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
          ->setLabel('Daemon')
          ->setValue($log->getDaemon()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Host')
          ->setValue($log->getHost()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PID')
          ->setValue($log->getPID()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Started')
          ->setValue(
            phabricator_datetime($log->getDateCreated(), $user)))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Argv')
          ->setValue($argv));

    $panel = new AphrontPanelView();
    $panel->setHeader('Daemon Details');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    $content[] = $panel;

    $event_view = new PhabricatorDaemonLogEventsView();
    $event_view->setUser($user);
    $event_view->setEvents($events);

    $log_panel = new AphrontPanelView();
    $log_panel->setHeader('Daemon Logs');
    $log_panel->appendChild($event_view);

    $content[] = $log_panel;

    $nav = $this->buildSideNavView();
    $nav->selectFilter('log');
    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Daemon Log',
      ));
  }

}
