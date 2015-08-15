<?php

final class PhabricatorDaemonLogEventViewController
  extends PhabricatorDaemonController {

  public function handleRequest(AphrontRequest $request) {
    $id = $request->getURIData('id');

    $event = id(new PhabricatorDaemonLogEvent())->load($id);
    if (!$event) {
      return new Aphront404Response();
    }

    $event_view = id(new PhabricatorDaemonLogEventsView())
      ->setEvents(array($event))
      ->setUser($request->getUser())
      ->setCombinedLog(true)
      ->setShowFullMessage(true);

    $log_panel = new PHUIObjectBoxView();
    $log_panel->setHeaderText(pht('Combined Log'));
    $log_panel->appendChild($event_view);

    $daemon_id = $event->getLogID();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(
        pht('Daemon %s', $daemon_id),
        $this->getApplicationURI("log/{$daemon_id}/"))
      ->addTextCrumb(pht('Event %s', $event->getID()));


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
