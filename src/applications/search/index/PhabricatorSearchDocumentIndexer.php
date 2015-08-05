<?php

abstract class PhabricatorSearchDocumentIndexer extends Phobject {

  private $context;

  protected function setContext($context) {
    $this->context = $context;
    return $this;
  }

  protected function getContext() {
    return $this->context;
  }

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
      throw new Exception(pht("Unable to load object by PHID '%s'!", $phid));
    }
    return $object;
  }

  public function indexDocumentByPHID($phid, $context) {
    try {
      $this->setContext($context);

      $document = $this->buildAbstractDocumentByPHID($phid);
      if ($document === null) {
        // This indexer doesn't build a document index, so we're done.
        return $this;
      }

      $object = $this->loadDocumentByPHID($phid);

      // Automatically rebuild CustomField indexes if the object uses custom
      // fields.
      if ($object instanceof PhabricatorCustomFieldInterface) {
        $this->indexCustomFields($document, $object);
      }

      // Automatically rebuild subscriber indexes if the object is subscribable.
      if ($object instanceof PhabricatorSubscribableInterface) {
        $this->indexSubscribers($document);
      }

      // Automatically build project relationships
      if ($object instanceof PhabricatorProjectInterface) {
        $this->indexProjects($document, $object);
      }

      $engine = PhabricatorSearchEngine::loadEngine();
      try {
        $engine->reindexAbstractDocument($document);
      } catch (Exception $ex) {
        phlog(
          pht(
            'Unable to index document %s with engine %s.',
            $document->getPHID(),
            get_class($engine)));
        phlog($ex);
      }

      $this->dispatchDidUpdateIndexEvent($phid, $document);
    } catch (Exception $ex) {
      phlog(
        pht(
          'Unable to build document %s with indexer %s.',
          $phid,
          get_class($this)));
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

  protected function indexProjects(
    PhabricatorSearchAbstractDocument $doc,
    PhabricatorProjectInterface $object) {

    $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorProjectObjectHasProjectEdgeType::EDGECONST);
    if ($project_phids) {
      foreach ($project_phids as $project_phid) {
        $doc->addRelationship(
          PhabricatorSearchRelationship::RELATIONSHIP_PROJECT,
          $project_phid,
          PhabricatorProjectProjectPHIDType::TYPECONST,
          $doc->getDocumentModified()); // Bogus timestamp.
      }
    }
  }

  protected function indexTransactions(
    PhabricatorSearchAbstractDocument $doc,
    PhabricatorApplicationTransactionQuery $query,
    array $phids) {

    $xactions = id(clone $query)
      ->setViewer($this->getViewer())
      ->withObjectPHIDs($phids)
      ->execute();

    foreach ($xactions as $xaction) {
      if (!$xaction->hasComment()) {
        continue;
      }

      $comment = $xaction->getComment();
      $doc->addField(
        PhabricatorSearchDocumentFieldType::FIELD_COMMENT,
        $comment->getContent());
    }
  }

  protected function indexCustomFields(
    PhabricatorSearchAbstractDocument $document,
    PhabricatorCustomFieldInterface $object) {

    // Rebuild the ApplicationSearch indexes. These are internal and not part of
    // the fulltext search, but putting them in this workflow allows users to
    // use the same tools to rebuild the indexes, which is easy to understand.

    $field_list = PhabricatorCustomField::getObjectFields(
      $object,
      PhabricatorCustomField::ROLE_DEFAULT);

    $field_list->setViewer($this->getViewer());
    $field_list->readFieldsFromStorage($object);

    // Rebuild ApplicationSearch indexes.
    $field_list->rebuildIndexes($object);

    // Rebuild global search indexes.
    $field_list->updateAbstractDocument($document);
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
