<?php

final class DivinerAtomSearchIndexer extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new DivinerLiveSymbol();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $atom = $this->loadDocumentByPHID($phid);
    $book = $atom->getBook();

    if (!$atom->getIsDocumentable()) {
      return null;
    }

    $doc = $this->newDocument($phid)
      ->setDocumentTitle($atom->getTitle())
      ->setDocumentCreated($book->getDateCreated())
      ->setDocumentModified($book->getDateModified());

    $doc->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $atom->getSummary());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_BOOK,
      $atom->getBookPHID(),
      DivinerBookPHIDType::TYPECONST,
      PhabricatorTime::getNow());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_REPOSITORY,
      $atom->getRepositoryPHID(),
      PhabricatorRepositoryRepositoryPHIDType::TYPECONST,
      PhabricatorTime::getNow());

    $doc->addRelationship(
      $atom->getGraphHash()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $atom->getBookPHID(),
      DivinerBookPHIDType::TYPECONST,
      PhabricatorTime::getNow());

    return $doc;
  }

}
