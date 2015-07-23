<?php

final class NuanceQueueViewController extends NuanceController {

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

    $header = $this->buildHeaderView($queue);
    $actions = $this->buildActionView($queue);
    $properties = $this->buildPropertyView($queue, $actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $queue,
      new NuanceQueueTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildHeaderView(NuanceQueue $queue) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($queue->getName())
      ->setPolicyObject($queue);

    return $header;
  }

  private function buildActionView(NuanceQueue $queue) {
    $viewer = $this->getViewer();
    $id = $queue->getID();

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI($queue->getURI())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $queue,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Queue'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("queue/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }

  private function buildPropertyView(
    NuanceQueue $queue,
    PhabricatorActionListView $actions) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($queue)
      ->setActionList($actions);

    return $properties;
  }
}
