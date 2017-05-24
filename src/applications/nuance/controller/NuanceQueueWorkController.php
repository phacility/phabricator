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

    $commands = $this->buildCommands($item);
    $work_content = $impl->buildItemWorkView($item);

    $view = id(new PHUITwoColumnView())
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $commands,
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

      $action_uri = "queue/action/{$id}/{$command_key}/{$item_id}/";
      $action_uri = $this->getApplicationURI($action_uri);

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($command->getName())
          ->setIcon($command->getIcon())
          ->setHref($action_uri)
          ->setWorkflow(true));
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

  private function buildCommands(NuanceItem $item) {
    $viewer = $this->getViewer();

    $commands = id(new NuanceItemCommandQuery())
      ->setViewer($viewer)
      ->withItemPHIDs(array($item->getPHID()))
      ->withStatuses(
        array(
          NuanceItemCommand::STATUS_ISSUED,
          NuanceItemCommand::STATUS_EXECUTING,
          NuanceItemCommand::STATUS_FAILED,
        ))
      ->execute();
    $commands = msort($commands, 'getID');

    if (!$commands) {
      return null;
    }

    $rows = array();
    foreach ($commands as $command) {
      $icon = $command->getStatusIcon();
      $color = $command->getStatusColor();

      $rows[] = array(
        $command->getID(),
        id(new PHUIIconView())
          ->setIcon($icon, $color),
        $viewer->renderHandle($command->getAuthorPHID()),
        $command->getCommand(),
        phabricator_datetime($command->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          null,
          pht('Actor'),
          pht('Command'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          null,
          'icon',
          null,
          'pri',
          'wide right',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Pending Commands'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($table);
  }

}
