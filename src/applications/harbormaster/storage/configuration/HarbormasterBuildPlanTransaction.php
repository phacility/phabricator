<?php

final class HarbormasterBuildPlanTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'harbormaster:name';

  public function getApplicationName() {
    return 'harbormaster';
  }

  public function getApplicationTransactionType() {
    return HarbormasterPHIDTypeBuildPlan::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new HarbormasterBuildPlanTransactionComment();
  }

  public function getIcon() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return 'create';
        }
        break;
    }

    return parent::getIcon();
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return 'green';
        }
        break;
    }

    return parent::getIcon();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this build plan.',
            $author_handle);
        } else {
          return pht(
            '%s renamed this build plan from "%s" to "%s".',
            $author_handle,
            $old,
            $new);
        }
        break;
    }

    return parent::getTitle();
  }

}
