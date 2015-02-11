<?php

/**
 * Execute a build target.
 */
final class HarbormasterTargetWorker extends HarbormasterWorker {

  public function getRequiredLeaseTime() {
    // This worker performs actual build work, which may involve a long wait
    // on external systems.
    return phutil_units('24 hours in seconds');
  }

  private function loadBuildTarget() {
    $data = $this->getTaskData();
    $id = idx($data, 'targetID');

    $target = id(new HarbormasterBuildTargetQuery())
      ->withIDs(array($id))
      ->setViewer($this->getViewer())
      ->executeOne();

    if (!$target) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Bad build target ID "%d".',
          $id));
    }

    return $target;
  }

  protected function doWork() {
    $target = $this->loadBuildTarget();
    $build = $target->getBuild();
    $viewer = $this->getViewer();

    $target->setDateStarted(time());

    try {
      if ($target->getBuildGeneration() !== $build->getBuildGeneration()) {
        throw new HarbormasterBuildAbortedException();
      }

      $status_pending = HarbormasterBuildTarget::STATUS_PENDING;
      if ($target->getTargetStatus() == $status_pending) {
        $target->setTargetStatus(HarbormasterBuildTarget::STATUS_BUILDING);
        $target->save();
      }

      $implementation = $target->getImplementation();
      $implementation->execute($build, $target);

      $next_status = HarbormasterBuildTarget::STATUS_PASSED;
      if ($implementation->shouldWaitForMessage($target)) {
        $next_status = HarbormasterBuildTarget::STATUS_WAITING;
      }

      $target->setTargetStatus($next_status);

      if ($target->isComplete()) {
        $target->setDateCompleted(time());
      }

      $target->save();
    } catch (PhabricatorWorkerYieldException $ex) {
      // If the target wants to yield, let that escape without further
      // processing. We'll resume after the task retries.
      throw $ex;
    } catch (HarbormasterBuildFailureException $ex) {
      // A build step wants to fail explicitly.
      $target->setTargetStatus(HarbormasterBuildTarget::STATUS_FAILED);
      $target->setDateCompleted(time());
      $target->save();
    } catch (HarbormasterBuildAbortedException $ex) {
      // A build step is aborting because the build has been restarted.
      $target->setTargetStatus(HarbormasterBuildTarget::STATUS_ABORTED);
      $target->setDateCompleted(time());
      $target->save();
    } catch (Exception $ex) {
      phlog($ex);

      try {
        $log = $build->createLog($target, 'core', 'exception');
        $start = $log->start();
        $log->append((string)$ex);
        $log->finalize($start);
      } catch (Exception $log_ex) {
        phlog($log_ex);
      }

      $target->setTargetStatus(HarbormasterBuildTarget::STATUS_FAILED);
      $target->setDateCompleted(time());
      $target->save();
    }

    id(new HarbormasterBuildEngine())
      ->setViewer($viewer)
      ->setBuild($build)
      ->continueBuild();
  }

}
