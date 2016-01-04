<?php

final class ManiphestProjectNameFulltextEngineExtension
  extends PhabricatorFulltextEngineExtension {

  const EXTENSIONKEY = 'maniphest.project.name';

  public function getExtensionName() {
    return pht('Maniphest Project Name Cache');
  }

  public function shouldIndexFulltextObject($object) {
    return ($object instanceof PhabricatorProject);
  }

  public function indexFulltextObject(
    $object,
    PhabricatorSearchAbstractDocument $document) {

    ManiphestNameIndex::updateIndex(
      $object->getPHID(),
      $object->getName());
  }

}
