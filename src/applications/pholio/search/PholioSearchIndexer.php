<?php

final class PholioSearchIndexer extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PholioMock();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $mock = $this->loadDocumentByPHID($phid);

    $doc = $this->newDocument($phid)
      ->setDocumentTitle($mock->getName())
      ->setDocumentCreated($mock->getDateCreated())
      ->setDocumentModified($mock->getDateModified());

    $doc->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $mock->getDescription());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $mock->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $mock->getDateCreated());

    $this->indexTransactions(
      $doc,
      new PholioTransactionQuery(),
      array($phid));

    return $doc;
  }

}
