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

    $query
      ->setViewer($this->getViewer())
      ->withObjectPHIDs(array($object->getPHID()))
      ->withComments(true)
      ->needComments(true);

    // See PHI719. Users occasionally create objects with huge numbers of
    // comments, which can be slow to index. We handle this with reasonable
    // grace: at time of writing, we can index a task with 100K comments in
    // about 30 seconds. However, we do need to hold all the comments in
    // memory in the AbstractDocument, so there's some practical limit to what
    // we can realistically index.

    // Since objects with more than 1,000 comments are not likely to be
    // legitimate objects with actual discussion, index only the first
    // thousand comments.

    $query
      ->setOrderVector(array('-id'))
      ->setLimit(1000);

    $xactions = $query->execute();

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
