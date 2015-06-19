<?php

final class DivinerAtomCache extends DivinerDiskCache {

  private $fileHashMap;
  private $atomMap;
  private $symbolMap;
  private $edgeSrcMap;
  private $edgeDstMap;
  private $graphMap;

  private $atoms = array();
  private $writeAtoms = array();

  public function __construct($cache_directory) {
    return parent::__construct($cache_directory, 'diviner-atom-cache');
  }

  public function delete() {
    parent::delete();

    $this->fileHashMap = null;
    $this->atomMap = null;
    $this->atoms = array();

    return $this;
  }

/* -(  File Hash Map  )------------------------------------------------------ */


  public function getFileHashMap() {
    if ($this->fileHashMap === null) {
      $this->fileHashMap = $this->getCache()->getKey('file', array());
    }
    return $this->fileHashMap;
  }

  public function addFileHash($file_hash, $atom_hash) {
    $this->getFileHashMap();
    $this->fileHashMap[$file_hash] = $atom_hash;
    return $this;
  }

  public function fileHashExists($file_hash) {
    $map = $this->getFileHashMap();
    return isset($map[$file_hash]);
  }

  public function deleteFileHash($file_hash) {
    if ($this->fileHashExists($file_hash)) {
      $map = $this->getFileHashMap();
      $atom_hash = $map[$file_hash];
      unset($this->fileHashMap[$file_hash]);

      $this->deleteAtomHash($atom_hash);
    }

    return $this;
  }


/* -(  Atom Map  )----------------------------------------------------------- */


  public function getAtomMap() {
    if ($this->atomMap === null) {
      $this->atomMap = $this->getCache()->getKey('atom', array());
    }
    return $this->atomMap;
  }

  public function getAtom($atom_hash) {
    if (!array_key_exists($atom_hash, $this->atoms)) {
      $key = 'atom/'.$this->getHashKey($atom_hash);
      $this->atoms[$atom_hash] = $this->getCache()->getKey($key);
    }
    return $this->atoms[$atom_hash];
  }

  public function addAtom(array $atom) {
    $hash = $atom['hash'];
    $this->atoms[$hash] = $atom;

    $this->getAtomMap();
    $this->atomMap[$hash] = true;

    $this->writeAtoms['atom/'.$this->getHashKey($hash)] = $atom;

    return $this;
  }

  public function deleteAtomHash($atom_hash) {
    $atom = $this->getAtom($atom_hash);
    if ($atom) {
      foreach ($atom['childHashes'] as $child_hash) {
        $this->deleteAtomHash($child_hash);
      }
    }

    $this->getAtomMap();
    unset($this->atomMap[$atom_hash]);
    unset($this->writeAtoms[$atom_hash]);

    $this->getCache()->deleteKey('atom/'.$this->getHashKey($atom_hash));

    return $this;
  }

  public function saveAtoms() {
    $this->getCache()->setKeys(
      array(
        'file'  => $this->getFileHashMap(),
        'atom'  => $this->getAtomMap(),
      ) + $this->writeAtoms);
    $this->writeAtoms = array();
    return $this;
  }


/* -(  Symbol Hash Map  )---------------------------------------------------- */


  public function getSymbolMap() {
    if ($this->symbolMap === null) {
      $this->symbolMap = $this->getCache()->getKey('symbol', array());
    }
    return $this->symbolMap;
  }

  public function addSymbol($atom_hash, $symbol_hash) {
    $this->getSymbolMap();
    $this->symbolMap[$atom_hash] = $symbol_hash;
    return $this;
  }

  public function deleteSymbol($atom_hash) {
    $this->getSymbolMap();
    unset($this->symbolMap[$atom_hash]);

    return $this;
  }

  public function saveSymbols() {
    $this->getCache()->setKeys(
      array(
        'symbol' => $this->getSymbolMap(),
      ));
    return $this;
  }

/* -(  Edge Map  )----------------------------------------------------------- */


  public function getEdgeMap() {
    if ($this->edgeDstMap === null) {
      $this->edgeDstMap = $this->getCache()->getKey('edge', array());
      $this->edgeSrcMap = array();
      foreach ($this->edgeDstMap as $dst => $srcs) {
        foreach ($srcs as $src => $ignored) {
          $this->edgeSrcMap[$src][$dst] = true;
        }
      }
    }
    return $this->edgeDstMap;
  }

  public function getEdgesWithDestination($symbol_hash) {
    $this->getEdgeMap();
    return array_keys(idx($this->edgeDstMap, $symbol_hash, array()));
  }

  public function addEdges($node_hash, array $symbol_hash_list) {
    $this->getEdgeMap();
    $this->edgeSrcMap[$node_hash] = array_fill_keys($symbol_hash_list, true);
    foreach ($symbol_hash_list as $symbol_hash) {
      $this->edgeDstMap[$symbol_hash][$node_hash] = true;
    }
    return $this;
  }

  public function deleteEdges($node_hash) {
    $this->getEdgeMap();
    foreach (idx($this->edgeSrcMap, $node_hash, array()) as $dst => $ignored) {
      unset($this->edgeDstMap[$dst][$node_hash]);
      if (empty($this->edgeDstMap[$dst])) {
        unset($this->edgeDstMap[$dst]);
      }
    }
    unset($this->edgeSrcMap[$node_hash]);
    return $this;
  }

  public function saveEdges() {
    $this->getCache()->setKeys(
      array(
        'edge' => $this->getEdgeMap(),
      ));
    return $this;
  }


/* -(  Graph Map  )---------------------------------------------------------- */


  public function getGraphMap() {
    if ($this->graphMap === null) {
      $this->graphMap = $this->getCache()->getKey('graph', array());
    }
    return $this->graphMap;
  }

  public function deleteGraph($node_hash) {
    $this->getGraphMap();
    unset($this->graphMap[$node_hash]);
    return $this;
  }

  public function addGraph($node_hash, $graph_hash) {
    $this->getGraphMap();
    $this->graphMap[$node_hash] = $graph_hash;
    return $this;
  }

  public function saveGraph() {
    $this->getCache()->setKeys(
      array(
        'graph' => $this->getGraphMap(),
      ));
    return $this;
  }

}
