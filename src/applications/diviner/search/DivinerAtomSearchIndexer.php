<?php

final class DivinerAtomSearchIndexer extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new DivinerLiveSymbol();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $atom = $this->loadDocumentByPHID($phid);
    $book = $atom->getBook();

    $doc = $this->newDocument($phid)
      ->setDocumentTitle($atom->getTitle())
      ->setDocumentCreated($book->getDateCreated())
      ->setDocumentModified($book->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $atom->getSummary());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_BOOK,
      $atom->getBookPHID(),
      DivinerBookPHIDType::TYPECONST,
      $book->getDateCreated());

    return $doc;
  }

}
