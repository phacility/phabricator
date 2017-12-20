<?php

final class PhabricatorBuiltinFileCachePurger
  extends PhabricatorCachePurger {

  const PURGERKEY = 'builtin-file';

  public function purgeCache() {
    $viewer = $this->getViewer();

    $files = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIsBuiltin(true)
      ->execute();

    $engine = new PhabricatorDestructionEngine();
    foreach ($files as $file) {
      $engine->destroyObject($file);
    }
  }

}
