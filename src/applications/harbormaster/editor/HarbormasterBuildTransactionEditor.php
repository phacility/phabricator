<?php

final class HarbormasterBuildTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorHarbormasterApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Harbormaster Builds');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = HarbormasterBuildTransaction::TYPE_CREATE;
    $types[] = HarbormasterBuildTransaction::TYPE_COMMAND;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildTransaction::TYPE_CREATE:
      case HarbormasterBuildTransaction::TYPE_COMMAND:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildTransaction::TYPE_CREATE:
        return true;
      case HarbormasterBuildTransaction::TYPE_COMMAND:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildTransaction::TYPE_CREATE:
        return;
      case HarbormasterBuildTransaction::TYPE_COMMAND:
        return $this->executeBuildCommand($object, $xaction);
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  private function executeBuildCommand(
    HarbormasterBuild $build,
    HarbormasterBuildTransaction $xaction) {

    $actor = $this->getActor();
    $message_type = $xaction->getNewValue();

    // TODO: Restore logic that tests if the command can issue without causing
    // anything to lapse into an invalid state. This should not be the same
    // as the logic which powers the web UI: for example, if an "abort" is
    // queued we want to disable "Abort" in the web UI, but should obviously
    // process it here.

    switch ($message_type) {
      case HarbormasterBuildCommand::COMMAND_ABORT:
        // TODO: This should move to external effects, perhaps.
        $build->releaseAllArtifacts($actor);
        $build->setBuildStatus(HarbormasterBuildStatus::STATUS_ABORTED);
        break;
      case HarbormasterBuildCommand::COMMAND_RESTART:
        $build->restartBuild($actor);
        $build->setBuildStatus(HarbormasterBuildStatus::STATUS_BUILDING);
        break;
      case HarbormasterBuildCommand::COMMAND_RESUME:
        $build->setBuildStatus(HarbormasterBuildStatus::STATUS_BUILDING);
        break;
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        $build->setBuildStatus(HarbormasterBuildStatus::STATUS_PAUSED);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case HarbormasterBuildTransaction::TYPE_CREATE:
      case HarbormasterBuildTransaction::TYPE_COMMAND:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

}
