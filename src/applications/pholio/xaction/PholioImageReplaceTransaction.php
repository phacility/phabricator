<?php

final class PholioImageReplaceTransaction
  extends PholioImageTransactionType {

  const TRANSACTIONTYPE = 'image-replace';

  public function generateOldValue($object) {
    $new_image = $this->getNewValue();
    return $new_image->getReplacesImagePHID();
  }

  public function generateNewValue($object, $value) {
    return $value->getPHID();
  }

  public function applyInternalEffects($object, $value) {
    $old = $this->getOldValue();
    $images = $object->getImages();
    foreach ($images as $seq => $image) {
      if ($image->getPHID() == $old) {
        $image->setIsObsolete(1);
        $image->save();
        unset($images[$seq]);
      }
    }
    $object->attachImages($images);
  }

  public function getTitle() {
    return pht(
      '%s replaced %s with %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated images of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-picture-o';
  }

  public function getColor() {
    return PhabricatorTransactions::COLOR_YELLOW;
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {
    $u_img = $u->getNewValue();
    $v_img = $v->getNewValue();
    if ($u_img->getReplacesImagePHID() == $v_img->getReplacesImagePHID()) {
      return $v;
    }
  }

  public function extractFilePHIDs($object, $value) {
    $file_phids = array();

    $editor = $this->getEditor();
    $images = $editor->getNewImages();
    foreach ($images as $image) {
      if ($image->getPHID() !== $value) {
        continue;
      }

      $file_phids[] = $image->getFilePHID();
    }

    return $file_phids;
  }

}
