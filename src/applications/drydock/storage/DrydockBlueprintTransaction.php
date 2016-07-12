<?php

final class DrydockBlueprintTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'drydock:blueprint:name';
  const TYPE_DISABLED = 'drydock:blueprint:disabled';

  public function getApplicationName() {
    return 'drydock';
  }

  public function getApplicationTransactionType() {
    return DrydockBlueprintPHIDType::TYPECONST;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if (!strlen($old)) {
          return pht(
            '%s created this blueprint.',
            $author_handle);
        } else {
          return pht(
            '%s renamed this blueprint from "%s" to "%s".',
            $author_handle,
            $old,
            $new);
        }
      case self::TYPE_DISABLED:
        if ($new) {
          return pht(
            '%s disabled this blueprint.',
            $author_handle);
        } else {
          return pht(
            '%s enabled this blueprint.',
            $author_handle);
        }
    }

    return parent::getTitle();
  }

}
