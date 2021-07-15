<?php

final class HarbormasterBuildMessageTransaction
  extends HarbormasterBuildTransactionType {

  const TRANSACTIONTYPE = 'harbormaster:build:command';

  public function generateOldValue($object) {
    return null;
  }

  public function getTitle() {
    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        return pht(
          '%s restarted this build.',
          $this->renderAuthor());
      case HarbormasterBuildCommand::COMMAND_ABORT:
        return pht(
          '%s aborted this build.',
          $this->renderAuthor());
      case HarbormasterBuildCommand::COMMAND_RESUME:
        return pht(
          '%s resumed this build.',
          $this->renderAuthor());
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        return pht(
          '%s paused this build.',
          $this->renderAuthor());
    }

    return pht(
      '%s issued an unknown command ("%s") to this build.',
      $this->renderAuthor(),
      $this->renderValue($new));
  }

  public function getIcon() {
    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildCommand::COMMAND_RESTART:
        return 'fa-backward';
      case HarbormasterBuildCommand::COMMAND_RESUME:
        return 'fa-play';
      case HarbormasterBuildCommand::COMMAND_PAUSE:
        return 'fa-pause';
      case HarbormasterBuildCommand::COMMAND_ABORT:
        return 'fa-exclamation-triangle';
      default:
        return 'fa-question';
    }
  }

  public function getColor() {
    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildCommand::COMMAND_PAUSE:
      case HarbormasterBuildCommand::COMMAND_ABORT:
        return 'red';
    }

    return parent::getColor();
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'message';
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array(
      'type' => $xaction->getNewValue(),
    );
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    // TODO: Restore logic that tests if the command can issue without causing
    // anything to lapse into an invalid state. This should not be the same
    // as the logic which powers the web UI: for example, if an "abort" is
    // queued we want to disable "Abort" in the web UI, but should obviously
    // process it here.

    return $errors;
  }

  public function applyInternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildCommand::COMMAND_ABORT:
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

  public function applyExternalEffects($object, $value) {
    $actor = $this->getActor();
    $build = $object;

    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildCommand::COMMAND_ABORT:
        $build->releaseAllArtifacts($actor);
        break;
    }
  }


}
