<?php

final class HarbormasterBuildableViewController
  extends HarbormasterController {

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

    $can_restart = false;
    $can_resume = false;
    $can_pause = false;
    $can_abort = false;

    $command_restart = HarbormasterBuildCommand::COMMAND_RESTART;
    $command_resume = HarbormasterBuildCommand::COMMAND_RESUME;
    $command_pause = HarbormasterBuildCommand::COMMAND_PAUSE;
    $command_abort = HarbormasterBuildCommand::COMMAND_ABORT;

    foreach ($buildable->getBuilds() as $build) {
      if ($build->canRestartBuild()) {
        if ($build->canIssueCommand($viewer, $command_restart)) {
          $can_restart = true;
        }
      }
      if ($build->canResumeBuild()) {
        if ($build->canIssueCommand($viewer, $command_resume)) {
          $can_resume = true;
        }
      }
      if ($build->canPauseBuild()) {
        if ($build->canIssueCommand($viewer, $command_pause)) {
          $can_pause = true;
        }
      }
      if ($build->canAbortBuild()) {
        if ($build->canIssueCommand($viewer, $command_abort)) {
          $can_abort = true;
        }
      }
    }

    $restart_uri = "buildable/{$id}/restart/";
    $pause_uri = "buildable/{$id}/pause/";
    $resume_uri = "buildable/{$id}/resume/";
    $abort_uri = "buildable/{$id}/abort/";

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-repeat')
        ->setName(pht('Restart All Builds'))
        ->setHref($this->getApplicationURI($restart_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_restart || !$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pause')
        ->setName(pht('Pause All Builds'))
        ->setHref($this->getApplicationURI($pause_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_pause || !$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-play')
        ->setName(pht('Resume All Builds'))
        ->setHref($this->getApplicationURI($resume_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_resume || !$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-exclamation-triangle')
        ->setName(pht('Abort All Builds'))
        ->setHref($this->getApplicationURI($abort_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_abort || !$can_edit));

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

      $status = $build->getBuildStatus();
      $item->setStatusIcon(
        'fa-dot-circle-o '.HarbormasterBuild::getBuildStatusColor($status),
        HarbormasterBuild::getBuildStatusName($status));

      $item->addAttribute(HarbormasterBuild::getBuildStatusName($status));

      if ($build->isRestarting()) {
        $item->addIcon('fa-repeat', pht('Restarting'));
      } else if ($build->isPausing()) {
        $item->addIcon('fa-pause', pht('Pausing'));
      } else if ($build->isResuming()) {
        $item->addIcon('fa-play', pht('Resuming'));
      }

      $build_id = $build->getID();

      $restart_uri = "build/restart/{$build_id}/buildable/";
      $resume_uri = "build/resume/{$build_id}/buildable/";
      $pause_uri = "build/pause/{$build_id}/buildable/";
      $abort_uri = "build/abort/{$build_id}/buildable/";

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('fa-repeat')
          ->setName(pht('Restart'))
          ->setHref($this->getApplicationURI($restart_uri))
          ->setWorkflow(true)
          ->setDisabled(!$build->canRestartBuild()));

      if ($build->canResumeBuild()) {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-play')
            ->setName(pht('Resume'))
            ->setHref($this->getApplicationURI($resume_uri))
            ->setWorkflow(true));
      } else {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-pause')
            ->setName(pht('Pause'))
            ->setHref($this->getApplicationURI($pause_uri))
            ->setWorkflow(true)
            ->setDisabled(!$build->canPauseBuild()));
      }

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

    $unit_data = id(new HarbormasterBuildUnitMessage())->loadAllWhere(
      'buildTargetPHID IN (%Ls)',
      $target_phids);

    if ($lint_data) {
      $lint_table = id(new HarbormasterLintPropertyView())
        ->setUser($viewer)
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
