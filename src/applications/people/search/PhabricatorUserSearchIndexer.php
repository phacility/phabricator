<?php

final class PhabricatorUserSearchIndexer
  extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PhabricatorUser();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $user = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($user->getPHID());
    $doc->setDocumentType(PhabricatorPeopleUserPHIDType::TYPECONST);
    $doc->setDocumentTitle($user->getFullName());
    $doc->setDocumentCreated($user->getDateCreated());
    $doc->setDocumentModified($user->getDateModified());

    $doc->addRelationship(
      $user->isUserActivated()
        ? PhabricatorSearchRelationship::RELATIONSHIP_OPEN
        : PhabricatorSearchRelationship::RELATIONSHIP_CLOSED,
      $user->getPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      time());

    return $doc;
  }
}
