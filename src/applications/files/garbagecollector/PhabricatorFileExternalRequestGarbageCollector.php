<?php

final class PhabricatorFileExternalRequestGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'files.externalttl';

  public function getCollectorName() {
    return pht('External Requests (TTL)');
  }

  public function hasAutomaticPolicy() {
    return true;
  }

  protected function collectGarbage() {
    $file_requests = id(new PhabricatorFileExternalRequest())->loadAllWhere(
      'ttl < %d LIMIT 100',
      PhabricatorTime::getNow());
    $engine = new PhabricatorDestructionEngine();
    foreach ($file_requests as $request) {
      $engine->destroyObject($request);
    }

    return (count($file_requests) == 100);
  }

}
