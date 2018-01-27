<?php

final class PhabricatorEdgeChangeRecord
  extends Phobject {

  private $xaction;

  public static function newFromTransaction(
    PhabricatorApplicationTransaction $xaction) {
    $record = new self();
    $record->xaction = $xaction;
    return $record;
  }

  public function getChangedPHIDs() {
    $add = $this->getAddedPHIDs();
    $rem = $this->getRemovedPHIDs();

    $add = array_fuse($add);
    $rem = array_fuse($rem);

    return array_keys($add + $rem);
  }

  public function getAddedPHIDs() {
    $old = $this->getOldDestinationPHIDs();
    $new = $this->getNewDestinationPHIDs();

    $old = array_fuse($old);
    $new = array_fuse($new);

    $add = array_diff_key($new, $old);
    return array_keys($add);
  }

  public function getRemovedPHIDs() {
    $old = $this->getOldDestinationPHIDs();
    $new = $this->getNewDestinationPHIDs();

    $old = array_fuse($old);
    $new = array_fuse($new);

    $rem = array_diff_key($old, $new);
    return array_keys($rem);
  }

  public function getModernOldEdgeTransactionData() {
    return $this->getRemovedPHIDs();
  }

  public function getModernNewEdgeTransactionData() {
    return $this->getAddedPHIDs();
  }

  private function getOldDestinationPHIDs() {
    if ($this->xaction) {
      $old = $this->xaction->getOldValue();
      return $this->getPHIDsFromTransactionValue($old);
    }

    throw new Exception(
      pht('Edge change record is not configured with any change data.'));
  }

  private function getNewDestinationPHIDs() {
    if ($this->xaction) {
      $new = $this->xaction->getNewValue();
      return $this->getPHIDsFromTransactionValue($new);
    }

    throw new Exception(
      pht('Edge change record is not configured with any change data.'));
  }

  private function getPHIDsFromTransactionValue($value) {
    if (!$value) {
      return array();
    }

    // If the list items are arrays, this is an older-style map of
    // dictionaries.
    $head = head($value);
    if (is_array($head)) {
      return ipull($value, 'dst');
    }

    // If the list items are not arrays, this is a newer-style list of PHIDs.
    return $value;
  }

}
