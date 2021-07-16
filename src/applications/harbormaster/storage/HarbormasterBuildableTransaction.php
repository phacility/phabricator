<?php

final class HarbormasterBuildableTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CREATE = 'harbormaster:buildable:create';
  const TYPE_COMMAND = 'harbormaster:buildable:command';

  public function getApplicationName() {
    return 'harbormaster';
  }

  public function getApplicationTransactionType() {
    return HarbormasterBuildablePHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        return pht(
          '%s created this buildable.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_COMMAND:
        switch ($new) {
          case HarbormasterBuildMessageRestartTransaction::MESSAGETYPE:
            return pht(
              '%s restarted this buildable.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildMessageResumeTransaction::MESSAGETYPE:
            return pht(
              '%s resumed this buildable.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildMessagePauseTransaction::MESSAGETYPE:
            return pht(
              '%s paused this buildable.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildMessageAbortTransaction::MESSAGETYPE:
            return pht(
              '%s aborted this buildable.',
              $this->renderHandleLink($author_phid));
        }
    }
    return parent::getTitle();
  }

  public function getIcon() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        return 'fa-plus';
      case self::TYPE_COMMAND:
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
    }

    return parent::getIcon();
  }

  public function getColor() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        return 'green';
      case self::TYPE_COMMAND:
        switch ($new) {
          case HarbormasterBuildMessagePauseTransaction::MESSAGETYPE:
            return 'red';
        }
    }
    return parent::getColor();
  }
}
