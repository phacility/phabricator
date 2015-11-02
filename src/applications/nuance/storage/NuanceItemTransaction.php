<?php

final class NuanceItemTransaction
  extends NuanceTransaction {

  const PROPERTY_KEY = 'property.key';

  const TYPE_OWNER = 'nuance.item.owner';
  const TYPE_REQUESTOR = 'nuance.item.requestor';
  const TYPE_SOURCE = 'nuance.item.source';
  const TYPE_PROPERTY = 'nuance.item.property';
  const TYPE_QUEUE = 'nuance.item.queue';

  public function getApplicationTransactionType() {
    return NuanceItemPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new NuanceItemTransactionComment();
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $type = $this->getTransactionType();

    switch ($type) {
      case self::TYPE_REQUESTOR:
      case self::TYPE_SOURCE:
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
      case self::TYPE_QUEUE:
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
      case self::TYPE_QUEUE:
        return pht(
          '%s routed this item to the %s queue.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($new));
    }

    return parent::getTitle();
  }

}
