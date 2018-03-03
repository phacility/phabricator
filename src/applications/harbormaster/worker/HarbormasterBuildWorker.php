<?php

/**
 * Start a build.
 */
final class HarbormasterBuildWorker extends HarbormasterWorker {

  public function renderForDisplay(PhabricatorUser $viewer) {
    try {
      $build = $this->loadBuild();
    } catch (Exception $ex) {
      return null;
    }

    return $viewer->renderHandle($build->getPHID());
  }

  protected function doWork() {
    $viewer = $this->getViewer();

    $engine = id(new HarbormasterBuildEngine())
      ->setViewer($viewer);

    $data = $this->getTaskData();
    $build_id = idx($data, 'buildID');

    if ($build_id) {
      $build = $this->loadBuild();
      $engine->setBuild($build);
      $engine->continueBuild();
    } else {
      $buildable = $this->loadBuildable();
      $engine->updateBuildable($buildable);
    }
  }

  private function loadBuild() {
    $data = $this->getTaskData();
    $id = idx($data, 'buildID');

    $viewer = $this->getViewer();
    $build = id(new HarbormasterBuildQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$build) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Invalid build ID "%s".', $id));
    }

    return $build;
  }

  private function loadBuildable() {
    $data = $this->getTaskData();
    $phid = idx($data, 'buildablePHID');

    $viewer = $this->getViewer();
    $buildable = id(new HarbormasterBuildableQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$buildable) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht('Invalid buildable PHID "%s".', $phid));
    }

    return $buildable;
  }

}
