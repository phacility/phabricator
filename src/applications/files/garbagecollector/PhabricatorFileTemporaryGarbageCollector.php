<?php

final class PhabricatorFileTemporaryGarbageCollector
  extends PhabricatorGarbageCollector {

  public function collectGarbage() {
    $files = id(new PhabricatorFile())->loadAllWhere(
      'ttl < %d LIMIT 100',
      time());

    foreach ($files as $file) {
      $file->delete();
    }

    return (count($files) == 100);
  }

}
