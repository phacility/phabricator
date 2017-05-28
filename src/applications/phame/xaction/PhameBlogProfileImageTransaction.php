<?php

final class PhameBlogProfileImageTransaction
  extends PhameBlogTransactionType {

  // TODO: Migrate these transactions ha ha .... ha
  const TRANSACTIONTYPE = 'phame.blog.header.image';

  public function generateOldValue($object) {
    return $object->getProfileImagePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setProfileImagePHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the profile image for this blog.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the profile image for blog %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-file-image-o';
  }

}
