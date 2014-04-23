<?php

final class HarbormasterBuildStepTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_CREATE = 'harbormaster:step:create';

  public function getApplicationName() {
    return 'harbormaster';
  }

  public function getApplicationTransactionType() {
    return HarbormasterPHIDTypeBuildStep::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_CREATE:
        return pht(
          '%s created this build step.',
          $this->renderHandleLink($author_phid));
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
    }

    return parent::getColor();
  }

}
