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

    if (!Filesystem::pathExists($path)) {
      return array();
    }

    try {
      $data = Filesystem::readFile($path);
    } catch (FilesystemException $ex) {
      throw new PhutilProxyException(
        pht(
          'Configuration file "%s" exists, but could not be read.',
          $path),
        $ex);
    }

    try {
      $result = phutil_json_decode($data);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          'Configuration file "%s" exists and is readable, but the content '.
          'is not valid JSON. You may have edited this file manually and '.
          'introduced a syntax error by mistake. Correct the file syntax '.
          'to continue.',
          $path),
        $ex);
    }

    return $result;
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
