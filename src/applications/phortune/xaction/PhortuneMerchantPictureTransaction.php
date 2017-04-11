<?php

final class PhortuneMerchantPictureTransaction
  extends PhortuneMerchantTransactionType {

  const TRANSACTIONTYPE = 'merchant:picture';

  public function generateOldValue($object) {
    return $object->getProfileImagePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setProfileImagePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the picture.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the picture for merchant %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-camera-retro';
  }

}
