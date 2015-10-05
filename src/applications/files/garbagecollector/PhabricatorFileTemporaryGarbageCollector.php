<?php

final class PhabricatorFileTemporaryGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'files.ttl';

  public function getCollectorName() {
    return pht('Files (TTL)');
  }

  public function hasAutomaticPolicy() {
    return true;
  }

  protected function collectGarbage() {
    $files = id(new PhabricatorFile())->loadAllWhere(
      'ttl < %d LIMIT 100',
      PhabricatorTime::getNow());

    foreach ($files as $file) {
      $file->delete();
    }

    return (count($files) == 100);
  }

}
