<?php

final class HarbormasterBuildableViewController
  extends HarbormasterController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $this->id;

    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needBuildableHandles(true)
      ->needContainerHandles(true)
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    // Pull builds and build targets.
    $builds = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withBuildablePHIDs(array($buildable->getPHID()))
      ->needBuildTargets(true)
      ->execute();

    $buildable->attachBuilds($builds);

    $build_list = $this->buildBuildList($buildable);

    $title = pht('Buildable %d', $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($buildable);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $timeline = $this->buildTransactionTimeline(
      $buildable,
      new HarbormasterBuildableTransactionQuery());
    $timeline->setShouldTerminate(true);

    $actions = $this->buildActionList($buildable);
    $this->buildPropertyLists($box, $buildable, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("B{$id}");

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $build_list,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildActionList(HarbormasterBuildable $buildable) {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $id = $buildable->getID();

    $list = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($buildable)
      ->setObjectURI($buildable->getMonogram());

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $buildable,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_restart = false;
    $can_resume = false;
    $can_stop = false;

    foreach ($buildable->getBuilds() as $build) {
      if ($build->canRestartBuild()) {
        $can_restart = true;
      }
      if ($build->canResumeBuild()) {
        $can_resume = true;
      }
      if ($build->canStopBuild()) {
        $can_stop = true;
      }
    }

    $restart_uri = "buildable/{$id}/restart/";
    $stop_uri = "buildable/{$id}/stop/";
    $resume_uri = "buildable/{$id}/resume/";

    $list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-repeat')
        ->setName(pht('Restart All Builds'))
        ->setHref($this->getApplicationURI($restart_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_restart || !$can_edit));

    $list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pause')
        ->setName(pht('Pause All Builds'))
        ->setHref($this->getApplicationURI($stop_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_stop || !$can_edit));

    $list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-play')
        ->setName(pht('Resume All Builds'))
        ->setHref($this->getApplicationURI($resume_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_resume || !$can_edit));

    return $list;
  }

  private function buildPropertyLists(
    PHUIObjectBoxView $box,
    HarbormasterBuildable $buildable,
    PhabricatorActionListView $actions) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($buildable)
      ->setActionList($actions);
    $box->addPropertyList($properties);

    $properties->addProperty(
      pht('Buildable'),
      $buildable->getBuildableHandle()->renderLink());

    if ($buildable->getContainerHandle() !== null) {
      $properties->addProperty(
        pht('Container'),
        $buildable->getContainerHandle()->renderLink());
    }

    $properties->addProperty(
      pht('Origin'),
      $buildable->getIsManualBuildable()
        ? pht('Manual Buildable')
        : pht('Automatic Buildable'));

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
      $item->setBarColor(HarbormasterBuild::getBuildStatusColor($status));

      $item->addAttribute(HarbormasterBuild::getBuildStatusName($status));

      if ($build->isRestarting()) {
        $item->addIcon('fa-repeat', pht('Restarting'));
      } else if ($build->isStopping()) {
        $item->addIcon('fa-pause', pht('Pausing'));
      } else if ($build->isResuming()) {
        $item->addIcon('fa-play', pht('Resuming'));
      }

      $build_id = $build->getID();

      $restart_uri = "build/restart/{$build_id}/buildable/";
      $resume_uri = "build/resume/{$build_id}/buildable/";
      $stop_uri = "build/stop/{$build_id}/buildable/";

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
            ->setHref($this->getApplicationURI($stop_uri))
            ->setWorkflow(true)
            ->setDisabled(!$build->canStopBuild()));
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

    return $build_list;
  }

}
