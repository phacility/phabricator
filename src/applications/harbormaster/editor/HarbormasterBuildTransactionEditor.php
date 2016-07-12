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

    $command = $xaction->getNewValue();

    switch ($command) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        $issuable = $build->canRestartBuild();
        break;
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        $issuable = $build->canPauseBuild();
        break;
      case HarbormasterBuildCommand::COMMAND_RESUME:
        $issuable = $build->canResumeBuild();
        break;
      case HarbormasterBuildCommand::COMMAND_ABORT:
        $issuable = $build->canAbortBuild();
        break;
      default:
        throw new Exception(pht('Unknown command %s', $command));
    }

    if (!$issuable) {
      return;
    }

    $actor = $this->getActor();
    if (!$build->canIssueCommand($actor, $command)) {
      return;
    }

    id(new HarbormasterBuildCommand())
      ->setAuthorPHID($xaction->getAuthorPHID())
      ->setTargetPHID($build->getPHID())
      ->setCommand($command)
      ->save();

    PhabricatorWorker::scheduleTask(
      'HarbormasterBuildWorker',
      array(
        'buildID' => $build->getID(),
      ),
      array(
        'objectPHID' => $build->getPHID(),
      ));
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
