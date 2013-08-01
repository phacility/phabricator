<?php

/**
 * @group phriction
 */
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
    $doc->setDocumentType(PhrictionPHIDTypeDocument::TYPECONST);
    $doc->setDocumentTitle($content->getTitle());

    // TODO: This isn't precisely correct, denormalize into the Document table?
    $doc->setDocumentCreated($content->getDateCreated());
    $doc->setDocumentModified($content->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $content->getContent());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $content->getAuthorPHID(),
      PhabricatorPeoplePHIDTypeUser::TYPECONST,
      $content->getDateCreated());

    if ($document->getStatus() == PhrictionDocumentStatus::STATUS_EXISTS) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
        $document->getPHID(),
        PhrictionPHIDTypeDocument::TYPECONST,
        time());
    }

    return $doc;
  }
}
