<?php

final class NuanceQueueWorkController
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
    $crumbs->addTextCrumb($queue->getName(), $queue->getURI());
    $crumbs->addTextCrumb(pht('Work'));
    $crumbs->setBorder(true);

    // For now, just pick the first open item.

    $items = id(new NuanceItemQuery())
      ->setViewer($viewer)
      ->withQueuePHIDs(
        array(
          $queue->getPHID(),
        ))
      ->withStatuses(
        array(
          NuanceItem::STATUS_OPEN,
        ))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->setLimit(5)
      ->execute();

    if (!$items) {
      return $this->newDialog()
        ->setTitle(pht('Queue Empty'))
        ->appendParagraph(
          pht(
            'This queue has no open items which you have permission to '.
            'work on.'))
        ->addCancelButton($queue->getURI());
    }

    $item = head($items);

    $curtain = $this->buildCurtain($queue);

    $timeline = $this->buildTransactionTimeline(
      $item,
      new NuanceItemTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setCurtain($curtain)
      ->setMainColumn($timeline);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtain(NuanceQueue $queue) {
    $viewer = $this->getViewer();
    $id = $queue->getID();

    $curtain = $this->newCurtainView();

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setType(PhabricatorActionView::TYPE_DIVIDER));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setType(PhabricatorActionView::TYPE_LABEL)
        ->setName(pht('Queue Actions')));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Manage Queue'))
        ->setIcon('fa-cog')
        ->setHref($this->getApplicationURI("queue/view/{$id}/")));

    return $curtain;
  }

}
