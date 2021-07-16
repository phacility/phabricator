<?php

final class HarbormasterBuildableMessageTransaction
  extends HarbormasterBuildableTransactionType {

  const TRANSACTIONTYPE = 'harbormaster:buildable:command';

  public function generateOldValue($object) {
    return null;
  }

  public function getTitle() {
    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildMessageRestartTransaction::MESSAGETYPE:
        return pht(
          '%s restarted this buildable.',
          $this->renderAuthor());
      case HarbormasterBuildMessageResumeTransaction::MESSAGETYPE:
        return pht(
          '%s resumed this buildable.',
          $this->renderAuthor());
      case HarbormasterBuildMessagePauseTransaction::MESSAGETYPE:
        return pht(
          '%s paused this buildable.',
          $this->renderAuthor());
      case HarbormasterBuildMessageAbortTransaction::MESSAGETYPE:
        return pht(
          '%s aborted this buildable.',
          $this->renderAuthor());
    }

    return parent::getTitle();
  }

  public function getIcon() {
    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildMessageRestartTransaction::MESSAGETYPE:
        return 'fa-backward';
      case HarbormasterBuildMessageResumeTransaction::MESSAGETYPE:
        return 'fa-play';
      case HarbormasterBuildMessagePauseTransaction::MESSAGETYPE:
        return 'fa-pause';
      case HarbormasterBuildMessageAbortTransaction::MESSAGETYPE:
        return 'fa-exclamation-triangle';
    }

    return parent::getIcon();
  }

  public function getColor() {
    $new = $this->getNewValue();

    switch ($new) {
      case HarbormasterBuildMessagePauseTransaction::MESSAGETYPE:
        return 'red';
    }

    return parent::getColor();
  }

}
