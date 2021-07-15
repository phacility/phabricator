<?php

final class HarbormasterBuildableViewController
  extends HarbormasterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    $id = $buildable->getID();

    // Pull builds and build targets.
    $builds = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($buildable->getPHID()))
      ->needBuildTargets(true)
      ->execute();

    list($lint, $unit) = $this->renderLintAndUnit($buildable, $builds);

    $buildable->attachBuilds($builds);
    $object = $buildable->getBuildableObject();

    $build_list = $this->buildBuildList($buildable);

    $title = pht('Buildable %d', $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($buildable)
      ->setStatus(
        $buildable->getStatusIcon(),
        $buildable->getStatusColor(),
        $buildable->getStatusDisplayName())
      ->setHeaderIcon('fa-recycle');

    $timeline = $this->buildTransactionTimeline(
      $buildable,
      new HarbormasterBuildableTransactionQuery());
    $timeline->setShouldTerminate(true);

    $curtain = $this->buildCurtainView($buildable);
    $properties = $this->buildPropertyList($buildable);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($buildable->getMonogram());
    $crumbs->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $properties,
        $lint,
        $unit,
        $build_list,
        $timeline,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

  private function buildCurtainView(HarbormasterBuildable $buildable) {
    $viewer = $this->getViewer();
    $id = $buildable->getID();

    $curtain = $this->newCurtainView($buildable);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $buildable,
      PhabricatorPolicyCapability::CAN_EDIT);

    $messages = array(
      new HarbormasterBuildMessageRestartTransaction(),
      new HarbormasterBuildMessagePauseTransaction(),
      new HarbormasterBuildMessageResumeTransaction(),
      new HarbormasterBuildMessageAbortTransaction(),
    );

    foreach ($messages as $message) {

      // Messages are enabled if they can be sent to at least one build.
      $can_send = false;
      foreach ($buildable->getBuilds() as $build) {
        $can_send = $message->canSendMessage($viewer, $build);
        if ($can_send) {
          break;
        }
      }

      $message_uri = urisprintf(
        '/buildable/%d/%s/',
        $id,
        $message->getHarbormasterBuildMessageType());
      $message_uri = $this->getApplicationURI($message_uri);

      $action = id(new PhabricatorActionView())
        ->setName($message->getHarbormasterBuildableMessageName())
        ->setIcon($message->getIcon())
        ->setHref($message_uri)
        ->setDisabled(!$can_send || !$can_edit)
        ->setWorkflow(true);

      $curtain->addAction($action);
    }

    return $curtain;
  }

  private function buildPropertyList(HarbormasterBuildable $buildable) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $container_phid = $buildable->getContainerPHID();
    $buildable_phid = $buildable->getBuildablePHID();

    if ($container_phid) {
      $properties->addProperty(
        pht('Container'),
        $viewer->renderHandle($container_phid));
    }

    $properties->addProperty(
      pht('Buildable'),
      $viewer->renderHandle($buildable_phid));

    $properties->addProperty(
      pht('Origin'),
      $buildable->getIsManualBuildable()
        ? pht('Manual Buildable')
        : pht('Automatic Buildable'));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Properties'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);
  }

  private function buildBuildList(HarbormasterBuildable $buildable) {
    $viewer = $this->getRequest()->getUser();

    $build_list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($buildable->getBuilds() as $build) {
      $view_uri = $this->getApplicationURI('/build/'.$build->getID().'/');

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Build %d', $build->getID()))
        ->setHeader($build->getName())
        ->setHref($view_uri);

      $status = $build->getBuildPendingStatusObject();

      $item->setStatusIcon(
        $status->getIconIcon().' '.$status->getIconColor(),
        $status->getName());

      $targets = $build->getBuildTargets();

      if ($targets) {
        $target_list = id(new PHUIStatusListView());
        foreach ($targets as $target) {
          $status = $target->getTargetStatus();
          $icon = HarbormasterBuildTarget::getBuildTargetStatusIcon($status);
          $color = HarbormasterBuildTarget::getBuildTargetStatusColor($status);
          $status_name =
            HarbormasterBuildTarget::getBuildTargetStatusName($status);

          $name = $target->getName();

          $target_list->addItem(
            id(new PHUIStatusItemView())
              ->setIcon($icon, $color, $status_name)
              ->setTarget(pht('Target %d', $target->getID()))
              ->setNote($name));
        }

        $target_box = id(new PHUIBoxView())
          ->addPadding(PHUI::PADDING_SMALL)
          ->appendChild($target_list);

        $item->appendChild($target_box);
      }

      $build_list->addItem($item);
    }

    $build_list->setFlush(true);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Builds'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($build_list);

    return $box;
  }

  private function renderLintAndUnit(
    HarbormasterBuildable $buildable,
    array $builds) {

    $viewer = $this->getViewer();

    $targets = array();
    foreach ($builds as $build) {
      foreach ($build->getBuildTargets() as $target) {
        $targets[] = $target;
      }
    }

    if (!$targets) {
      return;
    }

    $target_phids = mpull($targets, 'getPHID');

    $lint_data = id(new HarbormasterBuildLintMessage())->loadAllWhere(
      'buildTargetPHID IN (%Ls)',
      $target_phids);

    $unit_data = id(new HarbormasterBuildUnitMessageQuery())
      ->setViewer($viewer)
      ->withBuildTargetPHIDs($target_phids)
      ->execute();

    if ($lint_data) {
      $lint_table = id(new HarbormasterLintPropertyView())
        ->setViewer($viewer)
        ->setLimit(10)
        ->setLintMessages($lint_data);

      $lint_href = $this->getApplicationURI('lint/'.$buildable->getID().'/');

      $lint_header = id(new PHUIHeaderView())
        ->setHeader(pht('Lint Messages'))
        ->addActionLink(
          id(new PHUIButtonView())
            ->setTag('a')
            ->setHref($lint_href)
            ->setIcon('fa-list-ul')
            ->setText('View All'));

      $lint = id(new PHUIObjectBoxView())
        ->setHeader($lint_header)
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->setTable($lint_table);
    } else {
      $lint = null;
    }

    if ($unit_data) {
      $unit = id(new HarbormasterUnitSummaryView())
        ->setViewer($viewer)
        ->setBuildable($buildable)
        ->setUnitMessages($unit_data)
        ->setShowViewAll(true)
        ->setLimit(5);
    } else {
      $unit = null;
    }

    return array($lint, $unit);
  }

}
