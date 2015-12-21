<?php

final class ManiphestTaskFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $task = $object;

    $document->setDocumentTitle($task->getTitle());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $task->getDescription());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $task->getAuthorPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $task->getDateCreated());

    $document->addRelationship(
      $task->isClosed()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $task->getPHID(),
      ManiphestTaskPHIDType::TYPECONST,
      PhabricatorTime::getNow());

    $owner = $task->getOwnerPHID();
    if ($owner) {
      $document->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
        $owner,
        PhabricatorPeopleUserPHIDType::TYPECONST,
        time());
    } else {
      $document->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_UNOWNED,
        $task->getPHID(),
        PhabricatorPHIDConstants::PHID_TYPE_VOID,
        $task->getDateCreated());
    }
  }

}
