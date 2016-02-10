<?php

final class FundInitiativeFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $initiative = $object;

    $document->setDocumentTitle($initiative->getName());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $initiative->getOwnerPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $initiative->getDateCreated());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
      $initiative->getOwnerPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $initiative->getDateCreated());

    $document->addRelationship(
      $initiative->isClosed()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $initiative->getPHID(),
      FundInitiativePHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }
}
