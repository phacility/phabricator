<?php

final class HarbormasterUIEventListener
  extends PhabricatorEventListener {

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhabricatorEventType::TYPE_UI_WILLRENDERPROPERTIES:
        $this->handlePropertyEvent($event);
        break;
    }
  }

  private function handlePropertyEvent($ui_event) {
    $user = $ui_event->getUser();
    $object = $ui_event->getValue('object');

    if (!$object || !$object->getPHID()) {
      // No object, or the object has no PHID yet..
      return;
    }

    $target = null;
    if ($object instanceof PhabricatorRepositoryCommit) {
      $target = $object;
    } elseif ($object instanceof DifferentialRevision) {
      $target = $object->loadActiveDiff();
    } else {
      return;
    }

    if (!$this->canUseApplication($ui_event->getUser())) {
      return;
    }

    $buildables = id(new HarbormasterBuildableQuery())
      ->setViewer($user)
      ->withBuildablePHIDs(array($target->getPHID()))
      ->execute();
    if (!$buildables) {
      return;
    }

    $builds = id(new HarbormasterBuildQuery())
      ->setViewer($user)
      ->withBuildablePHIDs(mpull($buildables, 'getPHID'))
      ->execute();
    if (!$builds) {
      return;
    }

    $build_handles = id(new PhabricatorHandleQuery())
      ->setViewer($user)
      ->withPHIDs(mpull($builds, 'getPHID'))
      ->execute();

    $status_view = new PHUIStatusListView();

    foreach ($builds as $build) {
      $item = new PHUIStatusItemView();
      $item->setTarget(
        $build_handles[$build->getPHID()]->renderLink());

      switch ($build->getBuildStatus()) {
        case HarbormasterBuild::STATUS_INACTIVE:
          $item->setIcon('open-dark', pht('Inactive'));
          break;
        case HarbormasterBuild::STATUS_PENDING:
          $item->setIcon('open-blue', pht('Pending'));
          break;
        case HarbormasterBuild::STATUS_WAITING:
          $item->setIcon('up-blue', pht('Waiting on Resource'));
          break;
        case HarbormasterBuild::STATUS_BUILDING:
          $item->setIcon('right-blue', pht('Building'));
          break;
        case HarbormasterBuild::STATUS_PASSED:
          $item->setIcon('accept-green', pht('Passed'));
          break;
        case HarbormasterBuild::STATUS_FAILED:
          $item->setIcon('reject-red', pht('Failed'));
          break;
        case HarbormasterBuild::STATUS_ERROR:
          $item->setIcon('minus-red', pht('Unexpected Error'));
          break;
        case HarbormasterBuild::STATUS_CANCELLED:
          $item->setIcon('minus-dark', pht('Cancelled'));
          break;
        default:
          $item->setIcon('question', pht('Unknown'));
          break;
      }


      $status_view->addItem($item);
    }

    $view = $ui_event->getValue('view');
    $view->addProperty(pht('Build Status'), $status_view);
  }

}
