<?php

final class DiffusionCacheEngineExtension
  extends PhabricatorCacheEngineExtension {

  const EXTENSIONKEY = 'diffusion';

  public function getExtensionName() {
    return pht('Diffusion Repositories');
  }

  public function discoverLinkedObjects(
    PhabricatorCacheEngine $engine,
    array $objects) {
    $viewer = $engine->getViewer();
    $results = array();

    // When an Almanac Service changes, update linked repositories.

    $services = $this->selectObjects($objects, 'AlmanacService');
    if ($services) {
      $repositories = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withAlmanacServicePHIDs(mpull($services, 'getPHID'))
        ->execute();
      foreach ($repositories as $repository) {
        $results[] = $repository;
      }
    }

    return $results;
  }

  public function deleteCaches(
    PhabricatorCacheEngine $engine,
    array $objects) {

    $keys = array();
    $repositories = $this->selectObjects($objects, 'PhabricatorRepository');
    foreach ($repositories as $repository) {
      $keys[] = $repository->getAlmanacServiceCacheKey();
    }

    $keys = array_filter($keys);

    if ($keys) {
      $cache = PhabricatorCaches::getMutableStructureCache();
      $cache->deleteKeys($keys);
    }
  }

}
