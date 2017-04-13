<?php

final class ConpherenceThreadPictureTransaction
  extends ConpherenceThreadTransactionType {

  const TRANSACTIONTYPE = 'picture';

  public function generateOldValue($object) {
    return $object->getProfileImagePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setProfileImagePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the room image.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the room image for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
