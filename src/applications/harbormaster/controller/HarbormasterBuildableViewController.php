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
      ->needBuilds(true)
      ->executeOne();
    if (!$buildable) {
      return new Aphront404Response();
    }

    $build_list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($buildable->getBuilds() as $build) {
      $view_uri = $this->getApplicationURI('/build/'.$build->getID().'/');
      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Build %d', $build->getID()))
        ->setHeader($build->getName())
        ->setHref($view_uri);

      switch ($build->getBuildStatus()) {
        case HarbormasterBuild::STATUS_INACTIVE:
          $item->setBarColor('grey');
          $item->addAttribute(pht('Inactive'));
          break;
        case HarbormasterBuild::STATUS_PENDING:
          $item->setBarColor('blue');
          $item->addAttribute(pht('Pending'));
          break;
        case HarbormasterBuild::STATUS_WAITING:
          $item->setBarColor('violet');
          $item->addAttribute(pht('Waiting'));
          break;
        case HarbormasterBuild::STATUS_BUILDING:
          $item->setBarColor('yellow');
          $item->addAttribute(pht('Building'));
          break;
        case HarbormasterBuild::STATUS_PASSED:
          $item->setBarColor('green');
          $item->addAttribute(pht('Passed'));
          break;
        case HarbormasterBuild::STATUS_FAILED:
          $item->setBarColor('red');
          $item->addAttribute(pht('Failed'));
          break;
        case HarbormasterBuild::STATUS_ERROR:
          $item->setBarColor('red');
          $item->addAttribute(pht('Unexpected Error'));
          break;
        case HarbormasterBuild::STATUS_STOPPED:
          $item->setBarColor('black');
          $item->addAttribute(pht('Stopped'));
          break;
      }

      if ($build->isRestarting()) {
        $item->addIcon('backward', pht('Restarting'));
      } else if ($build->isStopping()) {
        $item->addIcon('stop', pht('Stopping'));
      } else if ($build->isResuming()) {
        $item->addIcon('play', pht('Resuming'));
      }

      $build_id = $build->getID();

      $restart_uri = "build/restart/{$build_id}/buildable/";
      $resume_uri = "build/resume/{$build_id}/buildable/";
      $stop_uri = "build/stop/{$build_id}/buildable/";

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon('backward')
          ->setName(pht('Restart'))
          ->setHref($this->getApplicationURI($restart_uri))
          ->setWorkflow(true)
          ->setDisabled(!$build->canRestartBuild()));

      if ($build->canResumeBuild()) {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('play')
            ->setName(pht('Resume'))
            ->setHref($this->getApplicationURI($resume_uri))
            ->setWorkflow(true));
      } else {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('stop')
            ->setName(pht('Stop'))
            ->setHref($this->getApplicationURI($stop_uri))
            ->setWorkflow(true)
            ->setDisabled(!$build->canStopBuild()));
      }

      $build_list->addItem($item);
    }

    $title = pht("Buildable %d", $id);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($buildable);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    $actions = $this->buildActionList($buildable);
    $this->buildPropertyLists($box, $buildable, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("B{$id}");

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $build_list,
      ),
      array(
        'title' => $title,
        'device' => true,
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
        ->setIcon('backward')
        ->setName(pht('Restart All Builds'))
        ->setHref($this->getApplicationURI($restart_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_restart || !$can_edit));

    $list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('stop')
        ->setName(pht('Stop All Builds'))
        ->setHref($this->getApplicationURI($stop_uri))
        ->setWorkflow(true)
        ->setDisabled(!$can_stop || !$can_edit));

    $list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('play')
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

}
