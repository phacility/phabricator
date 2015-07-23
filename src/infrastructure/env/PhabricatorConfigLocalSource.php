<?php

final class PhabricatorConfigLocalSource extends PhabricatorConfigProxySource {

  public function __construct() {
    $config = $this->loadConfig();
    $this->setSource(new PhabricatorConfigDictionarySource($config));
  }

  public function setKeys(array $keys) {
    $result = parent::setKeys($keys);
    $this->saveConfig();
    return $result;
  }

  public function deleteKeys(array $keys) {
    $result = parent::deleteKeys($keys);
    $this->saveConfig();
    return parent::deleteKeys($keys);
  }

  private function loadConfig() {
    $path = $this->getConfigPath();
    if (@file_exists($path)) {
      $data = @file_get_contents($path);
      if ($data) {
        $data = json_decode($data, true);
        if (is_array($data)) {
          return $data;
        }
      }
    }

    return array();
  }

  private function saveConfig() {
    $config = $this->getSource()->getAllKeys();
    $json = new PhutilJSON();
    $data = $json->encodeFormatted($config);
    Filesystem::writeFile($this->getConfigPath(), $data);
  }

  private function getConfigPath() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $path = $root.'/conf/local/local.json';
    return $path;
  }

}
