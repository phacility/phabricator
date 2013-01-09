<?php

/**
 * @group pholio
 */
final class PholioSearchIndexer extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PholioMock();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $mock = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($mock->getPHID());
    $doc->setDocumentType(phid_get_type($mock->getPHID()));
    $doc->setDocumentTitle($mock->getName());
    $doc->setDocumentCreated($mock->getDateCreated());
    $doc->setDocumentModified($mock->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $mock->getDescription());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $mock->getAuthorPHID(),
      PhabricatorPHIDConstants::PHID_TYPE_USER,
      $mock->getDateCreated());

    return $doc;
  }
}
