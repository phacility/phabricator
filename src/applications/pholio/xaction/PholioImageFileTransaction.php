<?php

final class PholioImageFileTransaction
  extends PholioImageTransactionType {

  const TRANSACTIONTYPE = 'image-file';

  public function generateOldValue($object) {
    $images = $object->getImages();
    return array_values(mpull($images, 'getPHID'));
  }

  public function generateNewValue($object, $value) {
    $new_value = array();
    foreach ($value as $key => $images) {
      $new_value[$key] = mpull($images, 'getPHID');
    }
    $old = array_fuse($this->getOldValue());
    return $this->getEditor()->getPHIDList($old, $new_value);
  }

  public function applyInternalEffects($object, $value) {
    $old_map = array_fuse($this->getOldValue());
    $new_map = array_fuse($this->getNewValue());

    $obsolete_map = array_diff_key($old_map, $new_map);
    $images = $object->getImages();
    foreach ($images as $seq => $image) {
      if (isset($obsolete_map[$image->getPHID()])) {
        $image->setIsObsolete(1);
        $image->save();
        unset($images[$seq]);
      }
    }
    $object->attachImages($images);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);

    if ($add && $rem) {
      return pht(
        '%s edited image(s), added %d: %s; removed %d: %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderHandleList($add),
        count($rem),
        $this->renderHandleList($rem));
    } else if ($add) {
      return pht(
        '%s added %d image(s): %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderHandleList($add));
    } else {
      return pht(
        '%s removed %d image(s): %s.',
        $this->renderAuthor(),
        count($rem),
        $this->renderHandleList($rem));
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s updated images of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-picture-o';
  }

  public function getColor() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);
    if ($add && $rem) {
      return PhabricatorTransactions::COLOR_YELLOW;
    } else if ($add) {
      return PhabricatorTransactions::COLOR_GREEN;
    } else {
      return PhabricatorTransactions::COLOR_RED;
    }
  }

  public function extractFilePHIDs($object, $value) {
    $images = $this->getEditor()->getNewImages();
    $images = mpull($images, null, 'getPHID');


    $file_phids = array();
    foreach ($value as $image_phid) {
      $image = idx($images, $image_phid);
      if (!$image) {
        continue;
      }
      $file_phids[] = $image->getFilePHID();
    }
    return $file_phids;
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {
    return  $this->getEditor()->mergePHIDOrEdgeTransactions($u, $v);
  }

}
