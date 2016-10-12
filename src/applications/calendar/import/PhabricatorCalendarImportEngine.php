<?php

abstract class PhabricatorCalendarImportEngine
  extends Phobject {

  final public function getImportEngineType() {
    return $this->getPhobjectClassConstant('ENGINETYPE', 64);
  }


  abstract public function getImportEngineName();
  abstract public function getImportEngineHint();

  abstract public function newEditEngineFields(
    PhabricatorEditEngine $engine,
    PhabricatorCalendarImport $import);

  abstract public function getDisplayName(PhabricatorCalendarImport $import);

  abstract public function didCreateImport(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import);

  final public static function getAllImportEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getImportEngineType')
      ->setSortMethod('getImportEngineName')
      ->execute();
  }

  final protected function importEventDocument(
    PhabricatorUser $viewer,
    PhabricatorCalendarImport $import,
    PhutilCalendarRootNode $root) {

    $event_type = PhutilCalendarEventNode::NODETYPE;

    $events = array();
    foreach ($root->getChildren() as $document) {
      foreach ($document->getChildren() as $node) {
        if ($node->getNodeType() != $event_type) {
          // TODO: Warn that we ignored this.
          continue;
        }

        $event = PhabricatorCalendarEvent::newFromDocumentNode($viewer, $node);

        $event
          ->setImportAuthorPHID($viewer->getPHID())
          ->setImportSourcePHID($import->getPHID())
          ->attachImportSource($import);

        $events[] = $event;
      }
    }

    // TODO: Use transactions.
    // TODO: Update existing events instead of fataling.
    foreach ($events as $event) {
      $event->save();
    }

  }



}
