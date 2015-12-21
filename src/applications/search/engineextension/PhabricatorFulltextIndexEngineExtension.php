<?php

final class PhabricatorFulltextIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'fulltext';

  public function getExtensionName() {
    return pht('Fulltext Engine');
  }

  public function shouldIndexObject($object) {
    return ($object instanceof PhabricatorFulltextInterface);
  }

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {

    $engine = $object->newFulltextEngine();
    if (!$engine) {
      return;
    }

    $engine->setObject($object);

    $engine->buildFulltextIndexes();
  }

}
