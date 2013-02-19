<?php

abstract class DivinerPublisher {

  private $atomCache;
  private $atomGraphHashToNodeHashMap;
  private $atomMap = array();
  private $renderer;
  private $config;
  private $symbolReverseMap;

  public function setRenderer(DivinerRenderer $renderer) {
    $renderer->setPublisher($this);
    $this->renderer = $renderer;
    return $this;
  }

  public function getRenderer() {
    return $this->renderer;
  }

  public function setConfig(array $config) {
    $this->config = $config;
    return $this;
  }

  public function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  public function setAtomCache(DivinerAtomCache $cache) {
    $this->atomCache = $cache;
    $graph_map = $this->atomCache->getGraphMap();
    $this->atomGraphHashToNodeHashMap = array_flip($graph_map);
  }

  protected function getAtomFromGraphHash($graph_hash) {
    if (empty($this->atomGraphHashToNodeHashMap[$graph_hash])) {
      throw new Exception("No such atom '{$graph_hash}'!");
    }

    return $this->getAtomFromNodeHash(
      $this->atomGraphHashToNodeHashMap[$graph_hash]);
  }

  protected function getAtomFromNodeHash($node_hash) {
    if (empty($this->atomMap[$node_hash])) {
      $dict = $this->atomCache->getAtom($node_hash);
      $this->atomMap[$node_hash] = DivinerAtom::newFromDictionary($dict);
    }
    return $this->atomMap[$node_hash];
  }

  protected function getSimilarAtoms(DivinerAtom $atom) {
    if ($this->symbolReverseMap === null) {
      $rmap = array();
      $smap = $this->atomCache->getSymbolMap();
      foreach ($smap as $nhash => $shash) {
        $rmap[$shash][$nhash] = true;
      }
      $this->symbolReverseMap = $rmap;
    }

    $shash = $atom->getRef()->toHash();

    if (empty($this->symbolReverseMap[$shash])) {
      throw new Exception("Atom has no symbol map entry!");
    }

    $hashes = $this->symbolReverseMap[$shash];

    $atoms = array();
    foreach ($hashes as $hash => $ignored) {
      $atoms[] = $this->getAtomFromNodeHash($hash);
    }

    $atoms = msort($atoms, 'getSortKey');
    return $atoms;
  }

  /**
   * If a book contains multiple definitions of some atom, like some function
   * "f()", we assign them an arbitrary (but fairly stable) order and publish
   * them as "function/f/1/", "function/f/2/", etc., or similar.
   */
  protected function getAtomSimilarIndex(DivinerAtom $atom) {
    $atoms = $this->getSimilarAtoms($atom);
    if (count($atoms) == 1) {
      return null;
    }

    $index = 1;
    foreach ($atoms as $similar_atom) {
      if ($atom === $similar_atom) {
        return $index;
      }
      $index++;
    }

    throw new Exception("Expected to find atom while disambiguating!");
  }


  abstract protected function loadAllPublishedHashes();
  abstract protected function deleteDocumentsByHash(array $hashes);
  abstract protected function createDocumentsByHash(array $hashes);
  abstract public function findAtomByRef(DivinerAtomRef $ref);

  final public function publishAtoms(array $hashes) {
    $existing = $this->loadAllPublishedHashes();

    $existing_map = array_fill_keys($existing, true);
    $hashes_map = array_fill_keys($hashes, true);

    $deleted = array_diff_key($existing_map, $hashes_map);
    $created = array_diff_key($hashes_map, $existing_map);

    echo pht('Deleting %d documents.', count($deleted))."\n";
    $this->deleteDocumentsByHash(array_keys($deleted));

    echo pht('Creating %d documents.', count($created))."\n";
    $this->createDocumentsByHash(array_keys($created));
  }

  protected function shouldGenerateDocumentForAtom(DivinerAtom $atom) {
    switch ($atom->getType()) {
      case DivinerAtom::TYPE_FILE:
        return false;
      case DivinerAtom::TYPE_ARTICLE:
      default:
        break;
    }

    return true;
  }

}
