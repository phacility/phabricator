<?php

final class PhabricatorCalendarEventSearchIndexer
    extends PhabricatorSearchDocumentIndexer {

  public function getIndexableObject() {
    return new PhabricatorCalendarEvent();
  }

  protected function buildAbstractDocumentByPHID($phid) {
    $event = $this->loadDocumentByPHID($phid);

    $doc = new PhabricatorSearchAbstractDocument();
    $doc->setPHID($event->getPHID());
    $doc->setDocumentType(PhabricatorCalendarEventPHIDType::TYPECONST);
    $doc->setDocumentTitle($event->getName());
    $doc->setDocumentCreated($event->getDateCreated());
    $doc->setDocumentModified($event->getDateModified());

    $doc->addField(
      PhabricatorSearchField::FIELD_BODY,
      $event->getDescription());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_AUTHOR,
      $event->getUserPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $event->getDateCreated());

    $doc->addRelationship(
      PhabricatorSearchRelationship::RELATIONSHIP_OWNER,
      $event->getUserPHID(),
      PhabricatorPeopleUserPHIDType::TYPECONST,
      $event->getDateCreated());

    $doc->addRelationship(
      $event->getIsCancelled()
        ? PhabricatorSearchRelationship::RELATIONSHIP_CLOSED
        : PhabricatorSearchRelationship::RELATIONSHIP_OPEN,
      $event->getPHID(),
      PhabricatorCalendarEventPHIDType::TYPECONST,
      time());

    $this->indexTransactions(
      $doc,
      new PhabricatorCalendarEventTransactionQuery(),
      array($phid));

    return $doc;
  }

}
