<?php

final class PholioImageSequenceTransaction
  extends PholioImageTransactionType {

  const TRANSACTIONTYPE = 'image-sequence';

  public function generateOldValue($object) {
    $sequence = null;
    $phid = null;
    $image = $this->getImageForXaction($object);
    if ($image) {
      $sequence = $image->getSequence();
      $phid = $image->getPHID();
    }
    return array($phid => $sequence);
  }

  public function applyInternalEffects($object, $value) {
    $image = $this->getImageForXaction($object);
    $value = (int)head($this->getNewValue());
    $image->setSequence($value);
    $image->save();
  }

  public function getTitle() {
    $new = $this->getNewValue();

    return pht(
      '%s updated an image\'s (%s) sequence.',
      $this->renderAuthor(),
      $this->renderHandleLink(key($new)));
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated image sequence of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function shouldHide() {
    // this is boring / silly to surface; changing sequence is NBD
    return true;
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {
    $raw_new_value_u = $u->getNewValue();
    $raw_new_value_v = $v->getNewValue();
    $phid_u = key($raw_new_value_u);
    $phid_v = key($raw_new_value_v);
    if ($phid_u == $phid_v) {
      return $v;
    }
  }

}
