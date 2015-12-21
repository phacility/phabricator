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
      // TODO: Load current indexed versions.
      $current_versions = array();
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

    // TODO: Save new index versions.

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

  public function indexDocumentByPHID($phid) {
    $indexers = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorSearchDocumentIndexer')
      ->execute();

    foreach ($indexers as $indexer) {
      if ($indexer->shouldIndexDocumentByPHID($phid)) {
        $indexer->indexDocumentByPHID($phid);
        break;
      }
    }

    return $this;
  }

}
