<?php

/**
 * Start a build.
 */
final class HarbormasterBuildWorker extends HarbormasterWorker {

  protected function doWork() {
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

    id(new HarbormasterBuildEngine())
      ->setViewer($viewer)
      ->setBuild($build)
      ->continueBuild();
  }

}
