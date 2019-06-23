<?php

final class PhrictionDocumentTitleTransaction
  extends PhrictionDocumentVersionTransaction {

  const TRANSACTIONTYPE = 'title';

  public function generateOldValue($object) {
    if ($this->isNewObject()) {
      return null;
    }
    return $this->getEditor()->getOldContent()->getTitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus(PhrictionDocumentStatus::STATUS_EXISTS);

    $content = $this->getNewDocumentContent($object);

    $content->setTitle($value);
  }

  public function getActionStrength() {
    return 140;
  }

  public function getActionName() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      if ($this->getMetadataValue('stub:create:phid')) {
        return pht('Stubbed');
      } else {
        return pht('Created');
      }
    }
    return pht('Retitled');
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      if ($this->getMetadataValue('stub:create:phid')) {
        return pht(
          '%s stubbed out this document when creating %s.',
          $this->renderAuthor(),
          $this->renderHandleLink(
            $this->getMetadataValue('stub:create:phid')));
      } else {
        return pht(
          '%s created this document.',
          $this->renderAuthor());
      }
    }

    return pht(
      '%s changed the title from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if ($old === null) {
      return pht(
        '%s created %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }

    return pht(
      '%s renamed %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $title = $object->getContent()->getTitle();
    if ($this->isEmptyTextTransaction($title, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Documents must have a title.'));
    }

    if ($this->isNewObject()) {
      // No ancestral slugs is "/". No ancestry checks apply when creating the
      // root document.
      $ancestral_slugs = PhabricatorSlug::getAncestry($object->getSlug());
      if ($ancestral_slugs) {
        // You must be able to view and edit the parent document to create a new
        // child.
        $parent_document = id(new PhrictionDocumentQuery())
          ->setViewer($this->getActor())
          ->withSlugs(array(last($ancestral_slugs)))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->executeOne();
        if (!$parent_document) {
          $errors[] = $this->newInvalidError(
            pht('You can not create a document which does not have a parent.'));
        }
      }
    }

    return $errors;
  }

}
