<?php

final class PhabricatorDaemonLogEventViewController
  extends PhabricatorDaemonController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $event = id(new PhabricatorDaemonLogEvent())->load($this->id);
    if (!$event) {
      return new Aphront404Response();
    }

    $event_view = id(new PhabricatorDaemonLogEventsView())
      ->setEvents(array($event))
      ->setUser($request->getUser())
      ->setCombinedLog(true)
      ->setShowFullMessage(true);

    $log_panel = new AphrontPanelView();
    $log_panel->appendChild($event_view);
    $log_panel->setNoBackground();

    $daemon_id = $event->getLogID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Daemon %s', $daemon_id))
        ->setHref($this->getApplicationURI("log/{$daemon_id}/")));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Event %s', $event->getID())));


    return $this->buildApplicationPage(
      array(
        $crumbs,
        $log_panel,
      ),
      array(
        'title' => pht('Combined Daemon Log'),
      ));
  }

}
