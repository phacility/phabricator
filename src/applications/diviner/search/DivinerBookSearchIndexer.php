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

    return $doc;
  }


}
