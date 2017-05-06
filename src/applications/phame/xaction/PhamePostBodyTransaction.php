<?php

final class PhamePostBodyTransaction
  extends PhamePostTransactionType {

  const TRANSACTIONTYPE = 'phame.post.body';

  public function generateOldValue($object) {
    return $object->getBody();
  }

  public function applyInternalEffects($object, $value) {
    $object->setBody($value);
  }

  public function getTitle() {
    return pht(
      '%s updated the post content.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s updated the post content for %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO POST CONTENT');
  }

  public function newChangeDetailView() {
    $viewer = $this->getViewer();

    return id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setViewer($viewer)
      ->setOldText($this->getOldValue())
      ->setNewText($this->getNewValue());
  }

  public function newRemarkupChanges() {
    $changes = array();

    $changes[] = $this->newRemarkupChange()
      ->setOldValue($this->getOldValue())
      ->setNewValue($this->getNewValue());

    return $changes;
  }

  public function getIcon() {
    return 'fa-file-text-o';
  }

}
