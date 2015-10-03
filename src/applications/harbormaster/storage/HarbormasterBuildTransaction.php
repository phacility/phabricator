<?php

final class HarbormasterBuildTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CREATE = 'harbormaster:build:create';
  const TYPE_COMMAND = 'harbormaster:build:command';

  public function getApplicationName() {
    return 'harbormaster';
  }

  public function getApplicationTransactionType() {
    return HarbormasterBuildPHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        return pht(
          '%s created this build.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_COMMAND:
        switch ($new) {
          case HarbormasterBuildCommand::COMMAND_RESTART:
            return pht(
              '%s restarted this build.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildCommand::COMMAND_ABORT:
            return pht(
              '%s aborted this build.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildCommand::COMMAND_RESUME:
            return pht(
              '%s resumed this build.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildCommand::COMMAND_PAUSE:
            return pht(
              '%s paused this build.',
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
          case HarbormasterBuildCommand::COMMAND_RESTART:
            return 'fa-backward';
          case HarbormasterBuildCommand::COMMAND_RESUME:
            return 'fa-play';
          case HarbormasterBuildCommand::COMMAND_PAUSE:
            return 'fa-pause';
          case HarbormasterBuildCommand::COMMAND_ABORT:
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
          case HarbormasterBuildCommand::COMMAND_PAUSE:
          case HarbormasterBuildCommand::COMMAND_ABORT:
            return 'red';
        }
    }
    return parent::getColor();
  }
}
