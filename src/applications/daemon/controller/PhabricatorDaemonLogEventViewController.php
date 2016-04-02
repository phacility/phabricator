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

    $log_panel = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($event_view);

    $daemon_id = $event->getLogID();

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(
        pht('Daemon %s', $daemon_id),
        $this->getApplicationURI("log/{$daemon_id}/"))
      ->addTextCrumb(pht('Event %s', $event->getID()))
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Combined Log'))
      ->setHeaderIcon('fa-file-text');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($log_panel);

    return $this->newPage()
      ->setTitle(pht('Combined Daemon Log'))
      ->appendChild($view);

  }

}
