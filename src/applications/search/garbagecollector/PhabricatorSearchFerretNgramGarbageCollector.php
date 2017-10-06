<?php

final class PhabricatorSearchFerretNgramGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'search.ferret.ngram';

  public function getCollectorName() {
    return pht('Ferret Engine Ngrams');
  }

  public function hasAutomaticPolicy() {
    return true;
  }

  protected function collectGarbage() {
    $all_objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFerretInterface')
      ->execute();

    $did_collect = false;
    foreach ($all_objects as $object) {
      $engine = $object->newFerretEngine();
      $conn = $object->establishConnection('w');

      $ngram_row = queryfx_one(
        $conn,
        'SELECT ngram FROM %T WHERE needsCollection = 1 LIMIT 1',
        $engine->getCommonNgramsTableName());
      if (!$ngram_row) {
        continue;
      }

      $ngram = $ngram_row['ngram'];

      queryfx(
        $conn,
        'DELETE FROM %T WHERE ngram = %s',
        $engine->getNgramsTableName(),
        $ngram);

      queryfx(
        $conn,
        'UPDATE %T SET needsCollection = 0 WHERE ngram = %s',
        $engine->getCommonNgramsTableName(),
        $ngram);

      $did_collect = true;
      break;
    }

    return $did_collect;
  }

}
