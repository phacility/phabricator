<?php

final class PhrictionDocumentPublishTransaction
  extends PhrictionDocumentTransactionType {

  const TRANSACTIONTYPE = 'publish';

  public function generateOldValue($object) {
    return $object->getContentPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setContentPHID($value);
  }

  public function getActionName() {
    return pht('Published');
  }

  public function getTitle() {
    return pht(
      '%s published a new version of this document.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s published a new version of %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    foreach ($xactions as $xaction) {
      $content_phid = $xaction->getNewValue();

      // If this isn't changing anything, skip it.
      if ($content_phid === $object->getContentPHID()) {
        continue;
      }

      $content = id(new PhrictionContentQuery())
        ->setViewer($actor)
        ->withPHIDs(array($content_phid))
        ->executeOne();
      if (!$content) {
        $errors[] = $this->newInvalidError(
          pht(
            'Unable to load Content object with PHID "%s".',
            $content_phid),
          $xaction);
        continue;
      }

      if ($content->getDocumentPHID() !== $object->getPHID()) {
        $errors[] = $this->newInvalidError(
          pht(
            'Content object "%s" can not be published because it belongs '.
            'to a different document.',
            $content_phid));
        continue;
      }
    }

    return $errors;
  }

}
