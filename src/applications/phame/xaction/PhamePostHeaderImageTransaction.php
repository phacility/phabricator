<?php

final class PhamePostHeaderImageTransaction
  extends PhamePostTransactionType {

  const TRANSACTIONTYPE = 'phame.post.headerimage';

  public function generateOldValue($object) {
    return $object->getHeaderImagePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setHeaderImagePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the header image for this post.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the header image for post %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-camera';
  }

}
