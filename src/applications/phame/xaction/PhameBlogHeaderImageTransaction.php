<?php

final class PhameBlogHeaderImageTransaction
  extends PhameBlogTransactionType {

  // TODO: Migrate these transactions ha ha .... ha
  const TRANSACTIONTYPE = 'phame.blog.profile.image';

  public function generateOldValue($object) {
    return $object->getHeaderImagePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setHeaderImagePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the header image for this blog.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the header image for blog %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-camera';
  }

}
