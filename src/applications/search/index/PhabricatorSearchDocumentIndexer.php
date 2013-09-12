<?php

/**
 * @group search
 */
abstract class PhabricatorSearchDocumentIndexer {

  abstract public function getIndexableObject();
  abstract protected function buildAbstractDocumentByPHID($phid);

  protected function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  public function shouldIndexDocumentByPHID($phid) {
    $object = $this->getIndexableObject();
    return (phid_get_type($phid) == phid_get_type($object->generatePHID()));
  }

  public function getIndexIterator() {
    $object = $this->getIndexableObject();
    return new LiskMigrationIterator($object);
  }

  protected function loadDocumentByPHID($phid) {
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->executeOne();
    if (!$object) {
      throw new Exception("Unable to load object by phid '{$phid}'!");
    }
    return $object;
  }

  public function indexDocumentByPHID($phid) {
    try {
      $document = $this->buildAbstractDocumentByPHID($phid);

      $engine = PhabricatorSearchEngineSelector::newSelector()->newEngine();
      try {
        $engine->reindexAbstractDocument($document);
      } catch (Exception $ex) {
        $phid = $document->getPHID();
        $class = get_class($engine);

        phlog("Unable to index document {$phid} with engine {$class}.");
        phlog($ex);
      }

      $this->dispatchDidUpdateIndexEvent($phid, $document);
    } catch (Exception $ex) {
      $class = get_class($this);
      phlog("Unable to build document {$phid} with indexer {$class}.");
      phlog($ex);
    }

    return $this;
  }

  protected function newDocument($phid) {
    return id(new PhabricatorSearchAbstractDocument())
      ->setPHID($phid)
      ->setDocumentType(phid_get_type($phid));
  }

  protected function indexSubscribers(
    PhabricatorSearchAbstractDocument $doc) {

    $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $doc->getPHID());
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($subscribers)
      ->execute();

    foreach ($handles as $phid => $handle) {
      $doc->addRelationship(
        PhabricatorSearchRelationship::RELATIONSHIP_SUBSCRIBER,
        $phid,
        $handle->getType(),
        $doc->getDocumentModified()); // Bogus timestamp.
    }
  }

  protected function indexTransactions(
    PhabricatorSearchAbstractDocument $doc,
    PhabricatorApplicationTransactionQuery $query,
    array $phids) {

    $xactions = id(clone $query)
      ->setViewer($this->getViewer())
      ->withObjectPHIDs($phids)
      ->withTransactionTypes(array(PhabricatorTransactions::TYPE_COMMENT))
      ->execute();

    foreach ($xactions as $xaction) {
      if (!$xaction->hasComment()) {
        continue;
      }

      $comment = $xaction->getComment();
      $doc->addField(
        PhabricatorSearchField::FIELD_COMMENT,
        $comment->getContent());
    }
  }

  private function dispatchDidUpdateIndexEvent(
    $phid,
    PhabricatorSearchAbstractDocument $document) {

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_SEARCH_DIDUPDATEINDEX,
      array(
        'phid'      => $phid,
        'object'    => $this->loadDocumentByPHID($phid),
        'document'  => $document,
      ));
    $event->setUser($this->getViewer());
    PhutilEventEngine::dispatchEvent($event);
  }

}
