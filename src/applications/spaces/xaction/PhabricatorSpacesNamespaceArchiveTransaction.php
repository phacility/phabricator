<?php

final class PhabricatorSpacesNamespaceArchiveTransaction
  extends PhabricatorSpacesNamespaceTransactionType {

  const TRANSACTIONTYPE = 'spaces:archive';

  public function generateOldValue($object) {
    return $object->getIsArchived();
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsArchived((int)$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s archived this space.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s activated this space.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s archived space %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s activated space %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    if ($new) {
      return 'fa-ban';
    } else {
      return 'fa-check';
    }
  }

  public function getColor() {
    $new = $this->getNewValue();
    if ($new) {
      return 'indigo';
    }
  }

}
