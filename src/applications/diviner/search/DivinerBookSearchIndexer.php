<?php

final class DivinerBookSearchIndexer extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new DivinerLiveBook();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $book = $this->loadDocumentByPHID($phid);

    $doc = $this->newDocument($phid)
      ->setDocumentTitle($book->getTitle())
      ->setDocumentCreated($book->getDateCreated())
      ->setDocumentModified($book->getDateModified());

    $doc->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $book->getPreface());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
      $book->getRepositoryPHID(),
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      $book->getDateCreated());

    $this->indexTransactions(
      $doc,
      new DivinerLiveBookTransactionQuery(),
      array($phid));

    return $doc;
  }


}
