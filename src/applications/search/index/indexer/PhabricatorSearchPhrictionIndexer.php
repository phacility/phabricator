<?php

/**
 * @group search
 */
final class PhabricatorSearchPhrictionIndexer
  extends PhabricatorSearchDocumentIndexer {

  public static function indexDocument(PhrictionDocument $document) {
    $content = $document->getContent();

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($document->getPHID());
    $doc->setDocumentType(PhabricatorPHIDConstants::PHID_TYPE_WIKI);
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
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $content->getDateCreated());

    self::reindexAbstractDocument($doc);
  }
}
