<?php

final class PhabricatorSearchNgramsDestructionEngineExtension
  extends PhabricatorDestructionEngineExtension {

  const EXTENSIONKEY = 'search.ngrams';

  public function getExtensionName() {
    return pht('Search Ngram');
  }

  public function canDestroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {
    return ($object instanceof PhabricatorNgramsInterface);
  }

  public function destroyObject(
    PhabricatorDestructionEngine $engine,
    $object) {

    foreach ($object->newNgrams() as $ngram) {
      queryfx(
        $ngram->establishConnection('w'),
        'DELETE FROM %T WHERE objectID = %d',
        $ngram->getTableName(),
        $object->getID());
    }
  }

}
