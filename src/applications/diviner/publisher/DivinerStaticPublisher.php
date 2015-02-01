<?php

final class DivinerStaticPublisher extends DivinerPublisher {

  private $publishCache;
  private $atomNameMap;

  private function getPublishCache() {
    if (!$this->publishCache) {
      $dir = implode(
        DIRECTORY_SEPARATOR,
        array(
          $this->getConfig('root'),
          '.divinercache',
          $this->getConfig('name'),
          'static',
        ));
      $this->publishCache = new DivinerPublishCache($dir);
    }

    return $this->publishCache;
  }

  protected function loadAllPublishedHashes() {
    return array_keys($this->getPublishCache()->getPathMap());
  }

  protected function deleteDocumentsByHash(array $hashes) {
    $root = $this->getConfig('root');
    $cache = $this->getPublishCache();

    foreach ($hashes as $hash) {
      $paths = $cache->getAtomPathsFromCache($hash);
      foreach ($paths as $path) {
        $abs = $root.DIRECTORY_SEPARATOR.$path;
        Filesystem::remove($abs);

        // If the parent directory is now empty, clean it up.
        $dir = dirname($abs);
        while (true) {
          if (!Filesystem::isDescendant($dir, $root)) {
            // Directory is outside of the root.
            break;
          }
          if (Filesystem::listDirectory($dir)) {
            // Directory is not empty.
            break;
          }

          Filesystem::remove($dir);
          $dir = dirname($dir);
        }
      }

      $cache->removeAtomPathsFromCache($hash);
      $cache->deleteAtomFromIndex($hash);
    }
  }

  protected function createDocumentsByHash(array $hashes) {
    $indexes = array();
    $cache = $this->getPublishCache();

    foreach ($hashes as $hash) {
      $atom = $this->getAtomFromGraphHash($hash);

      $paths = array();
      if ($this->shouldGenerateDocumentForAtom($atom)) {
        $content = $this->getRenderer()->renderAtom($atom);

        $this->writeDocument($atom, $content);

        $paths[] = $this->getAtomRelativePath($atom);
        if ($this->getAtomSimilarIndex($atom) !== null) {
          $index = dirname($this->getAtomRelativePath($atom)).'index.html';
          $indexes[$index] = $atom;
          $paths[] = $index;
        }

        $this->addAtomToIndex($hash, $atom);
      }

      $cache->addAtomPathsToCache($hash, $paths);
    }

    foreach ($indexes as $index => $atoms) {
      // TODO: Publish disambiguation pages.
    }

    $this->publishIndex();
    $cache->writePathMap();
    $cache->writeIndex();
  }

  private function publishIndex() {
    $index = $this->getPublishCache()->getIndex();
    $refs = array();

    foreach ($index as $hash => $dictionary) {
      $refs[$hash] = DivinerAtomRef::newFromDictionary($dictionary);
    }

    $content = $this->getRenderer()->renderAtomIndex($refs);

    $path = implode(
      DIRECTORY_SEPARATOR,
      array(
        $this->getConfig('root'),
        'docs',
        $this->getConfig('name'),
        'index.html',
      ));

    Filesystem::writeFile($path, $content);
  }

  public function findAtomByRef(DivinerAtomRef $ref) {
    if ($ref->getBook() != $this->getConfig('name')) {
      return null;
    }

    if ($this->atomNameMap === null) {
      $name_map = array();
      foreach ($this->getPublishCache()->getIndex() as $hash => $dict) {
        $name_map[$dict['name']][$hash] = $dict;
      }
      $this->atomNameMap = $name_map;
    }

    $name = $ref->getName();
    if (empty($this->atomNameMap[$name])) {
      return null;
    }

    $candidates = $this->atomNameMap[$name];
    foreach ($candidates as $key => $dict) {
      $candidates[$key] = DivinerAtomRef::newFromDictionary($dict);
      if ($ref->getType()) {
        if ($candidates[$key]->getType() != $ref->getType()) {
          unset($candidates[$key]);
        }
      }

      if ($ref->getContext()) {
        if ($candidates[$key]->getContext() != $ref->getContext()) {
          unset($candidates[$key]);
        }
      }
    }

    // If we have exactly one uniquely identifiable atom, return it.
    if (count($candidates) == 1) {
      return $this->getAtomFromNodeHash(last_key($candidates));
    }

    return null;
  }

  private function addAtomToIndex($hash, DivinerAtom $atom) {
    $ref = $atom->getRef();
    $ref->setIndex($this->getAtomSimilarIndex($atom));
    $ref->setSummary($this->getRenderer()->renderAtomSummary($atom));

    $this->getPublishCache()->addAtomToIndex($hash, $ref->toDictionary());
  }

  private function writeDocument(DivinerAtom $atom, $content) {
    $root = $this->getConfig('root');
    $path = $root.DIRECTORY_SEPARATOR.$this->getAtomRelativePath($atom);

    if (!Filesystem::pathExists($path)) {
      Filesystem::createDirectory($path, $umask = 0755, $recursive = true);
    }

    Filesystem::writeFile($path.'index.html', $content);

    return $this;
  }

  private function getAtomRelativePath(DivinerAtom $atom) {
    $ref = $atom->getRef();

    $book = $ref->getBook();
    $type = $ref->getType();
    $context = $ref->getContext();
    $name = $ref->getName();

    $path = array(
      'docs',
      $book,
      $type,
    );
    if ($context !== null) {
      $path[] = $context;
    }
    $path[] = $name;

    $index = $this->getAtomSimilarIndex($atom);
    if ($index !== null) {
      $path[] = '@'.$index;
    }

    $path[] = null;

    return implode(DIRECTORY_SEPARATOR, $path);
  }

}
