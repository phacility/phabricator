<?php

final class PhabricatorCalendarEventFulltextEngine
  extends PhabricatorFulltextEngine {

  protected function buildAbstractDocument(
    PhabricatorSearchAbstractDocument $document,
    $object) {

    $event = $object;

    $document->setDocumentTitle($event->getName());

    $document->addField(
      PhabricatorSearchDocumentFieldType::FIELD_BODY,
      $event->getDescription());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $event->getHostPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $event->getDateCreated());

    $document->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
      $event->getHostPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $event->getDateCreated());

    $document->addRelationship(
      $event->getIsCancelled()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $event->getPHID(),
      PhabricatorCalendarEventPHIDType::TYPECONST,
      PhabricatorTime::getNow());
  }

}
