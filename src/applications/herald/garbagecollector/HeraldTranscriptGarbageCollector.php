<?php

final class HeraldTranscriptGarbageCollector
  extends PhabricatorGarbageCollector {

  const COLLECTORCONST = 'herald.transcripts';

  public function getCollectorName() {
    return pht('Herald Transcripts');
  }

  public function getDefaultRetentionPolicy() {
    return phutil_units('30 days in seconds');
  }

  public function collectGarbage() {
    $ttl = PhabricatorEnv::getEnvConfig('gcdaemon.ttl.herald-transcripts');
    if ($ttl <= 0) {
      return false;
    }

    $table = new HeraldTranscript();
    $conn_w = $table->establishConnection('w');

    queryfx(
      $conn_w,
      'UPDATE %T SET
          objectTranscript     = "",
          ruleTranscripts      = "",
          conditionTranscripts = "",
          applyTranscripts     = "",
          garbageCollected     = 1
        WHERE garbageCollected = 0 AND time < %d
        LIMIT 100',
      $table->getTableName(),
      time() - $ttl);

    return ($conn_w->getAffectedRows() == 100);
  }

}
