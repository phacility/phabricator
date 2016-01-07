<?php

abstract class DiffusionBlameQuery extends DiffusionQuery {

  private $timeout;
  private $paths;

  public function setTimeout($timeout) {
    $this->timeout = $timeout;
    return $this;
  }

  public function getTimeout() {
    return $this->timeout;
  }

  public function setPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  public function getPaths() {
    return $this->paths;
  }

  abstract protected function newBlameFuture(DiffusionRequest $request, $path);

  abstract protected function resolveBlameFuture(ExecFuture $future);

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  final protected function executeQuery() {
    $paths = $this->getPaths();

    $blame = array();

    // Load cache keys: these are the commits at which each path was last
    // touched.
    $keys = $this->loadCacheKeys($paths);

    // Try to read blame data from cache.
    $cache = $this->readCacheData($keys);
    foreach ($paths as $key => $path) {
      if (!isset($cache[$path])) {
        continue;
      }

      $blame[$path] = $cache[$path];
      unset($paths[$key]);
    }

    // If we have no paths left, we filled everything from cache and can
    // bail out early.
    if (!$paths) {
      return $blame;
    }

    $request = $this->getRequest();
    $timeout = $this->getTimeout();

    // We're still missing at least some data, so we need to run VCS commands
    // to pull it.
    $futures = array();
    foreach ($paths as $path) {
      $future = $this->newBlameFuture($request, $path);

      if ($timeout) {
        $future->setTimeout($timeout);
      }

      $futures[$path] = $future;
    }

    $futures = id(new FutureIterator($futures))
      ->limit(4);

    foreach ($futures as $path => $future) {
      $path_blame = $this->resolveBlameFuture($future);
      if ($path_blame !== null) {
        $blame[$path] = $path_blame;
      }
    }

    // Fill the cache with anything we generated.
    $this->writeCacheData(
      array_select_keys($keys, $paths),
      $blame);

    return $blame;
  }

  private function loadCacheKeys(array $paths) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $repository = $request->getRepository();
    $repository_id = $repository->getID();

    $last_modified = parent::callConduitWithDiffusionRequest(
      $viewer,
      $request,
      'diffusion.lastmodifiedquery',
      array(
        'paths' => array_fill_keys($paths, $request->getCommit()),
      ));

    $map = array();
    foreach ($paths as $path) {
      $identifier = idx($last_modified, $path);
      if ($identifier === null) {
        continue;
      }

      $path_hash = PhabricatorHash::digestForIndex($path);

      $map[$path] = "blame({$repository_id}, {$identifier}, {$path_hash}, raw)";
    }

    return $map;
  }

  private function readCacheData(array $keys) {
    $cache = PhabricatorCaches::getImmutableCache();
    $data = $cache->getKeys($keys);

    $results = array();
    foreach ($keys as $path => $key) {
      if (!isset($data[$key])) {
        continue;
      }
      $results[$path] = $data[$key];
    }

    // Decode the cache storage format.
    foreach ($results as $path => $cache) {
      list($head, $body) = explode("\n", $cache, 2);
      switch ($head) {
        case 'raw':
          $body = explode("\n", $body);
          break;
        default:
          $body = null;
          break;
      }

      if ($body === null) {
        unset($results[$path]);
      } else {
        $results[$path] = $body;
      }
    }

    return $results;
  }

  private function writeCacheData(array $keys, array $blame) {
    $writes = array();
    foreach ($keys as $path => $key) {
      $value = idx($blame, $path);
      if ($value === null) {
        continue;
      }

      // For now, just store the entire value with a "raw" header. In the
      // future, we could compress this or use IDs instead.
      $value = "raw\n".implode("\n", $value);

      $writes[$key] = $value;
    }

    if (!$writes) {
      return;
    }

    $cache = PhabricatorCaches::getImmutableCache();
    $data = $cache->setKeys($writes, phutil_units('14 days in seconds'));
  }

}
