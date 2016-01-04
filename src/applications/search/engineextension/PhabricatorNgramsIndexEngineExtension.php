<?php

final class PhabricatorNgramsIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'ngrams';

  public function getExtensionName() {
    return pht('Ngrams Engine');
  }

  public function getIndexVersion($object) {
    $ngrams = $object->newNgrams();
    $map = mpull($ngrams, 'getValue', 'getNgramKey');
    ksort($map);
    $serialized = serialize($map);

    return PhabricatorHash::digestForIndex($serialized);
  }

  public function shouldIndexObject($object) {
    return ($object instanceof PhabricatorNgramsInterface);
  }

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {

    foreach ($object->newNgrams() as $ngram) {
      $ngram->writeNgram($object->getID());
    }
  }

}
