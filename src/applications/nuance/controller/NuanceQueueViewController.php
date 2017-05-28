<?php

final class NuanceQueueViewController
  extends NuanceQueueController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $queue = id(new NuanceQueueQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$queue) {
      return new Aphront404Response();
    }

    $title = $queue->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Queues'), $this->getApplicationURI('queue/'));
    $crumbs->addTextCrumb($queue->getName());
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView($queue);
    $curtain = $this->buildCurtain($queue);

    $timeline = $this->buildTransactionTimeline(
      $queue,
      new NuanceQueueTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(NuanceQueue $queue) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($queue->getName())
      ->setPolicyObject($queue);

    return $header;
  }

  private function buildCurtain(NuanceQueue $queue) {
    $viewer = $this->getViewer();
    $id = $queue->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $queue,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($queue);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Queue'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("queue/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Begin Work'))
        ->setIcon('fa-play-circle-o')
        ->setHref($this->getApplicationURI("queue/work/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $curtain;
  }

}
