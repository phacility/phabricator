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
    $build = $this->loadBuild();

    id(new HarbormasterBuildEngine())
      ->setViewer($viewer)
      ->setBuild($build)
      ->continueBuild();
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

}
