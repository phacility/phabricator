<?php

final class NuanceSourceTransaction
  extends NuanceTransaction {

  const TYPE_NAME = 'source.name';
  const TYPE_DEFAULT_QUEUE = 'source.queue.default';

  public function getApplicationTransactionType() {
    return NuanceSourcePHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceSourceTransactionComment();
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $type = $this->getTransactionType();

    switch ($type) {
      case self::TYPE_DEFAULT_QUEUE:
        return !$old;
      case self::TYPE_NAME:
        return ($old === null);
    }

    return parent::shouldHide();
  }

  public function getRequiredHandlePHIDs() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $type = $this->getTransactionType();

    $phids = parent::getRequiredHandlePHIDs();
    switch ($type) {
      case self::TYPE_DEFAULT_QUEUE:
        if ($old) {
          $phids[] = $old;
        }
        if ($new) {
          $phids[] = $new;
        }
        break;
    }

    return $phids;
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $type = $this->getTransactionType();
    $author_phid = $this->getAuthorPHID();

    switch ($type) {
      case self::TYPE_DEFAULT_QUEUE:
        return pht(
          '%s changed the default queue from %s to %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($old),
          $this->renderHandleLink($new));
      case self::TYPE_NAME:
        return pht(
          '%s renamed this source from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
    }

    return parent::getTitle();
  }

}
