<?php

final class PhabricatorIndexEngine extends Phobject {

  private $object;
  private $extensions;
  private $versions;
  private $parameters;

  public function setParameters(array $parameters) {
    $this->parameters = $parameters;
    return $this;
  }

  public function getParameters() {
    return $this->parameters;
  }

  public function setObject($object) {
    $this->object = $object;
    return $this;
  }

  public function getObject() {
    return $this->object;
  }

  public function shouldIndexObject() {
    $extensions = $this->newExtensions();

    $parameters = $this->getParameters();
    foreach ($extensions as $extension) {
      $extension->setParameters($parameters);
    }

    $object = $this->getObject();
    $versions = array();
    foreach ($extensions as $key => $extension) {
      $version = $extension->getIndexVersion($object);
      if ($version !== null) {
        $versions[$key] = (string)$version;
      }
    }

    if (idx($parameters, 'force')) {
      $current_versions = array();
    } else {
      $keys = array_keys($versions);
      $current_versions = $this->loadIndexVersions($keys);
    }

    foreach ($versions as $key => $version) {
      $current_version = idx($current_versions, $key);

      if ($current_version === null) {
        continue;
      }

      // If nothing has changed since we built the current index, we do not
      // need to rebuild the index.
      if ($current_version === $version) {
        unset($extensions[$key]);
      }
    }

    $this->extensions = $extensions;
    $this->versions = $versions;

    // We should index the object only if there is any work to be done.
    return (bool)$this->extensions;
  }

  public function indexObject() {
    $extensions = $this->extensions;
    $object = $this->getObject();

    foreach ($extensions as $key => $extension) {
      $extension->indexObject($this, $object);
    }

    $this->saveIndexVersions($this->versions);

    return $this;
  }

  private function newExtensions() {
    $object = $this->getObject();

    $extensions = PhabricatorIndexEngineExtension::getAllExtensions();
    foreach ($extensions as $key => $extension) {
      if (!$extension->shouldIndexObject($object)) {
        unset($extensions[$key]);
      }
    }

    return $extensions;
  }

  private function loadIndexVersions(array $extension_keys) {
    if (!$extension_keys) {
      return array();
    }

    $object = $this->getObject();
    $object_phid = $object->getPHID();

    $table = new PhabricatorSearchIndexVersion();
    $conn_r = $table->establishConnection('w');

    $rows = queryfx_all(
      $conn_r,
      'SELECT version, extensionKey
        FROM %R
        WHERE objectPHID = %s AND extensionKey IN (%Ls)',
      $table,
      $object_phid,
      $extension_keys);

    return ipull($rows, 'version', 'extensionKey');
  }

  private function saveIndexVersions(array $versions) {
    if (!$versions) {
      return;
    }

    $object = $this->getObject();
    $object_phid = $object->getPHID();

    $table = new PhabricatorSearchIndexVersion();
    $conn_w = $table->establishConnection('w');

    $now = PhabricatorTime::getNow();

    // See T13587. For now, this is just a marker to make it easy to reindex
    // documents if some version of the indexing code is later discovered to
    // be questionable.
    $index_version = '2021-02-16-A';

    $sql = array();
    foreach ($versions as $key => $version) {
      $sql[] = qsprintf(
        $conn_w,
        '(%s, %s, %s, %s, %d)',
        $object_phid,
        $key,
        $version,
        $index_version,
        $now);
    }

    queryfx(
      $conn_w,
      'INSERT INTO %R (objectPHID, extensionKey, version,
          indexVersion, indexEpoch)
        VALUES %LQ
        ON DUPLICATE KEY UPDATE
          version = VALUES(version),
          indexVersion = VALUES(indexVersion),
          indexEpoch = VALUES(indexEpoch)',
      $table,
      $sql);
  }

}
