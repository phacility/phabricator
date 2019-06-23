<?php

abstract class PhrictionDocumentEditTransaction
  extends PhrictionDocumentVersionTransaction {

  public function generateOldValue($object) {
    if ($this->getEditor()->getIsNewObject()) {
      return null;
    }

    // NOTE: We want to get the newest version of the content here, regardless
    // of whether it's published or not.

    $actor = $this->getActor();

    return id(new PhrictionContentQuery())
      ->setViewer($actor)
      ->withDocumentPHIDs(array($object->getPHID()))
      ->setOrder('newest')
      ->setLimit(1)
      ->executeOne()
      ->getContent();
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function applyInternalEffects($object, $value) {
    $content = $this->getNewDocumentContent($object);
    $content->setContent($value);
  }

  public function getActionStrength() {
    return 130;
  }

  public function getActionName() {
    return pht('Edited');
  }

  public function getTitle() {
    return pht(
      '%s edited the content of this document.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s edited the content of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getMailDiffSectionHeader() {
    return pht('CHANGES TO DOCUMENT CONTENT');
  }

  public function hasChangeDetailView() {
    return true;
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

}
