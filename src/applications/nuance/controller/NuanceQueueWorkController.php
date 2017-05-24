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

    $curtain = $this->buildCurtain($queue, $item);

    $timeline = $this->buildTransactionTimeline(
      $item,
      new NuanceItemTransactionQuery());
    $timeline->setShouldTerminate(true);

    $impl = $item->getImplementation()
      ->setViewer($viewer);

    $work_content = $impl->buildItemWorkView($item);

    $view = id(new PHUITwoColumnView())
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $work_content,
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtain(NuanceQueue $queue, NuanceItem $item) {
    $viewer = $this->getViewer();
    $id = $queue->getID();

    $curtain = $this->newCurtainView();

    $impl = $item->getImplementation();
    $commands = $impl->buildWorkCommands($item);

    foreach ($commands as $command) {
      $command_key = $command->getCommandKey();

      $item_id = $item->getID();

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($command->getName())
          ->setIcon($command->getIcon())
          ->setHref("queue/command/{$id}/{$command_key}/{$item_id}/"))
          ->setWorkflow(true);
    }

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
