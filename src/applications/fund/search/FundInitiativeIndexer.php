<?php

final class FundInitiativeIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new FundInitiative();
  }

  protected function loadDocumentByPHID($phid) {
    $object = id(new FundInitiativeQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$object) {
      throw new Exception(
        pht(
          "Unable to load object by PHID '%s'!",
          $phid));
    }
    return $object;
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $initiative = $this->loadDocumentByPHID($phid);

    $doc = id(new PhabricatorSearchAbstractDocument())
      ->setPHID($initiative->getPHID())
      ->setDocumentType(FundInitiativePHIDType::TYPECONST)
      ->setDocumentTitle($initiative->getName())
      ->setDocumentCreated($initiative->getDateCreated())
      ->setDocumentModified($initiative->getDateModified());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $initiative->getOwnerPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $initiative->getDateCreated());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
      $initiative->getOwnerPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $initiative->getDateCreated());

    $doc->addRelationship(
      $initiative->isClosed()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $initiative->getPHID(),
      FundInitiativePHIDType::TYPECONST,
      time());

    $this->indexTransactions(
      $doc,
      new FundInitiativeTransactionQuery(),
      array($initiative->getPHID()));

    return $doc;
  }
}
