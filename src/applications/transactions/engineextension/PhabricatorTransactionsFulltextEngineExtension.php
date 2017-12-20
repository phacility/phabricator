<?php

final class PhabricatorTransactionsFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'transactions';

  public function getExtensionName() {
    return pht('Comments');
  }

  public function shouldEnrichFulltextObject($object) {
    return ($object instanceof PhabricatorApplicationTransactionInterface);
  }

  public function enrichFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    $query = PhabricatorApplicationTransactionQuery::newQueryForObject($object);
    if (!$query) {
      return;
    }

    $xactions = $query
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array($object->getPHID()))
      ->withComments(true)
      ->needComments(true)
      ->execute();

    foreach ($xactions as $xaction) {
      if (!$xaction->hasComment()) {
        continue;
      }

      $comment = $xaction->getComment();

      $document->addField(
        PhabricatorSearchDocumentFieldType::FIELD_COMMENT,
        $comment->getContent());
    }
  }

}
