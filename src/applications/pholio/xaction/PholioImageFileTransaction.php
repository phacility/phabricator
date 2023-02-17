<?php

final class PholioImageFileTransaction
  extends PholioImageTransactionType {

  const TRANSACTIONTYPE = 'image-file';

  public function generateOldValue($object) {
    $images = $object->getActiveImages();
    return array_values(mpull($images, 'getPHID'));
  }

  public function generateNewValue($object, $value) {
    $editor = $this->getEditor();

    $old_value = $this->getOldValue();
    $new_value = $value;

    return $editor->getPHIDList($old_value, $new_value);
  }

  public function applyExternalEffects($object, $value) {
    $old_map = array_fuse($this->getOldValue());
    $new_map = array_fuse($this->getNewValue());

    $add_map = array_diff_key($new_map, $old_map);
    $rem_map = array_diff_key($old_map, $new_map);

    $editor = $this->getEditor();

    foreach ($rem_map as $phid) {
      $editor->loadPholioImage($object, $phid)
        ->setIsObsolete(1)
        ->save();
    }

    foreach ($add_map as $phid) {
      $editor->loadPholioImage($object, $phid)
        ->setMockPHID($object->getPHID())
        ->save();
    }
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
    $editor = $this->getEditor();

    // NOTE: This method is a little weird (and includes ALL the file PHIDs,
    // including old file PHIDs) because we currently don't have a storage
    // object when called. This might change at some point.

    $image_changes = $value;

    $image_phids = array();
    foreach ($image_changes as $change_type => $phids) {
      foreach ($phids as $phid) {
        $image_phids[$phid] = $phid;
      }
    }

    $file_phids = array();
    foreach ($image_phids as $image_phid) {
      $file_phids[] = $editor->loadPholioImage($object, $image_phid)
        ->getFilePHID();
    }

    return $file_phids;
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {
    return $this->getEditor()->mergePHIDOrEdgeTransactions($u, $v);
  }

}
