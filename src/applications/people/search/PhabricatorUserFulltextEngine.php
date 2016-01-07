<?php

final class PhabricatorUserFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $user = $object;

    $document->setDocumentTitle($user->getFullName());

    $document->addRelationship(
      $user->isUserActivated()
        ? PhabricatorSearchRelationship::RELATIONSHIP_OPEN
        : PhabricatorSearchRelationship::RELATIONSHIP_CLOSED,
      $user->getPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }
}
