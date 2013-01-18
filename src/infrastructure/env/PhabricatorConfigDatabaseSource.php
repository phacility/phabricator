<?php

final class PhabricatorConfigDatabaseSource
  extends PhabricatorConfigProxySource {

  public function __construct($namespace) {
    $config = $this->loadConfig($namespace);
    $this->setSource(new PhabricatorConfigDictionarySource($config));
  }

  public function isWritable() {
    // While this is writable, writes occur through the Config application.
    return false;
  }

  private function loadConfig($namespace) {
    $objects = id(new PhabricatorConfigEntry())->loadAllWhere(
      'namespace = %s AND isDeleted = 0',
      $namespace);
    return mpull($objects, 'getValue', 'getConfigKey');
  }

}
