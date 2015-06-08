<?php

final class PhrictionSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PhrictionDocument();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $document = $this->loadDocumentByPHID($phid);

    $content = id(new PhrictionContent())->load($document->getContentID());
    $document->attachContent($content);

    $content = $document->getContent();

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($document->getPHID());
    $doc->setDocumentType(PhrictionDocumentPHIDType::TYPECONST);
    $doc->setDocumentTitle($content->getTitle());

    // TODO: This isn't precisely correct, denormalize into the Document table?
    $doc->setDocumentCreated($content->getDateCreated());
    $doc->setDocumentModified($content->getDateModified());

    $doc->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $content->getContent());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $content->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $content->getDateCreated());

    $doc->addRelationship(
      ($document->getStatus() == PhrictionDocumentStatus::STATUS_EXISTS)
        ? PhabricatorSearchRelationship::RELATIONSHIP_OPEN
        : PhabricatorSearchRelationship::RELATIONSHIP_CLOSED,
      $document->getPHID(),
      PhrictionDocumentPHIDType::TYPECONST,
      time());

    return $doc;
  }
}
