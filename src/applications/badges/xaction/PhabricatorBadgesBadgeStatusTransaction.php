<?php

final class PhabricatorBadgesBadgeStatusTransaction
  extends PhabricatorBadgesBadgeTransactionType {

  const TRANSACTIONTYPE = 'badges.status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    if ($this->getNewValue() == PhabricatorBadgesBadge::STATUS_ARCHIVED) {
      return pht(
        '%s disabled this badge.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this badge.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue() == PhabricatorBadgesBadge::STATUS_ARCHIVED) {
      return pht(
        '%s disabled the badge %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s enabled the badge %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    if ($this->getNewValue() == PhabricatorBadgesBadge::STATUS_ARCHIVED) {
      return 'fa-ban';
    } else {
      return 'fa-check';
    }
  }

}
