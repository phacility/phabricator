<?php

final class PholioImageDescriptionTransaction
  extends PholioImageTransactionType {

  const TRANSACTIONTYPE = 'image-description';

  public function generateOldValue($object) {
    $description = null;
    $phid = null;
    $image = $this->getImageForXaction($object);
    if ($image) {
      $description = $image->getDescription();
      $phid = $image->getPHID();
    }
    return array($phid => $description);
  }

  public function applyInternalEffects($object, $value) {
    $image = $this->getImageForXaction($object);
    $value = (string)head($this->getNewValue());
    $image->setDescription($value);
    $image->save();
  }

  public function getTitle() {
    $new = $this->getNewValue();

    return pht(
      '%s updated an image\'s (%s) description.',
      $this->renderAuthor(),
      $this->renderHandle(head_key($new)));
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated image descriptions of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {

    $raw_new_value_u = $u->getNewValue();
    $raw_new_value_v = $v->getNewValue();
    $phid_u = head_key($raw_new_value_u);
    $phid_v = head_key($raw_new_value_v);
    if ($phid_u == $phid_v) {
      return $v;
    }

    return null;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    return ($old === array(null => null));
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText(head($this->getOldValue()))
      ->setNewText(head($this->getNewValue()));
  }

}
