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
          case HarbormasterBuildCommand::COMMAND_RESTART:
            return pht(
              '%s restarted this buildable.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildCommand::COMMAND_RESUME:
            return pht(
              '%s resumed this buildable.',
              $this->renderHandleLink($author_phid));
          case HarbormasterBuildCommand::COMMAND_PAUSE:
            return pht(
              '%s paused this buildable.',
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
            return 'red';
        }
    }
    return parent::getColor();
  }
}
