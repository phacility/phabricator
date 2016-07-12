<?php

final class DivinerGenerateWorkflow extends DivinerWorkflow {

  private $atomCache;

  protected function didConstruct() {
    $this
      ->setName('generate')
      ->setSynopsis(pht('Generate documentation.'))
      ->setArguments(
        array(
          array(
            'name' => 'clean',
            'help' => pht('Clear the caches before generating documentation.'),
          ),
          array(
            'name' => 'book',
            'param' => 'path',
            'help' => pht('Path to a Diviner book configuration.'),
          ),
          array(
            'name' => 'publisher',
            'param' => 'class',
            'help' => pht('Specify a subclass of %s.', 'DivinerPublisher'),
            'default' => 'DivinerLivePublisher',
          ),
          array(
            'name' => 'repository',
            'param' => 'identifier',
            'help' => pht('Repository that the documentation belongs to.'),
          ),
        ));
  }

  protected function getAtomCache() {
    if (!$this->atomCache) {
      $book_root = $this->getConfig('root');
      $book_name = $this->getConfig('name');
      $cache_directory = $book_root.'/.divinercache/'.$book_name;
      $this->atomCache = new DivinerAtomCache($cache_directory);
    }
    return $this->atomCache;
  }

  protected function log($message) {
    $console = PhutilConsole::getConsole();
    $console->writeErr($message."\n");
  }

  public function execute(PhutilArgumentParser $args) {
    $book = $args->getArg('book');
    if ($book) {
      $books = array($book);
    } else {
      $cwd = getcwd();
      $this->log(pht('FINDING DOCUMENTATION BOOKS'));

      $books = id(new FileFinder($cwd))
        ->withType('f')
        ->withSuffix('book')
        ->find();

      if (!$books) {
        throw new PhutilArgumentUsageException(
          pht(
            "There are no Diviner '%s' files anywhere beneath the current ".
            "directory. Use '%s' to specify a documentation book to generate.",
            '.book',
            '--book <book>'));
      } else {
        $this->log(pht('Found %s book(s).', phutil_count($books)));
      }
    }

    foreach ($books as $book) {
      $short_name = basename($book);

      $this->log(pht('Generating book "%s"...', $short_name));
      $this->generateBook($book, $args);
      $this->log(pht('Completed generation of "%s".', $short_name)."\n");
    }
  }

  private function generateBook($book, PhutilArgumentParser $args) {
    $this->atomCache = null;

    $this->readBookConfiguration($book);

    if ($args->getArg('clean')) {
      $this->log(pht('CLEARING CACHES'));
      $this->getAtomCache()->delete();
      $this->log(pht('Done.')."\n");
    }

    // The major challenge of documentation generation is one of dependency
    // management. When regenerating documentation, we want to do the smallest
    // amount of work we can, so that regenerating documentation after minor
    // changes is quick.
    //
    // = Atom Cache =
    //
    // In the first stage, we find all the direct changes to source code since
    // the last run. This stage relies on two data structures:
    //
    //  - File Hash Map: `map<file_hash, node_hash>`
    //  - Atom Map: `map<node_hash, true>`
    //
    // First, we hash all the source files in the project to detect any which
    // have changed since the previous run (i.e., their hash is not present in
    // the File Hash Map). If a file's content hash appears in the map, it has
    // not changed, so we don't need to reparse it.
    //
    // We break the contents of each file into "atoms", which represent a unit
    // of source code (like a function, method, class or file). Each atom has a
    // "node hash" based on the content of the atom: if a function definition
    // changes, the node hash of the atom changes too. The primary output of
    // the atom cache is a list of node hashes which exist in the project. This
    // is the Atom Map. The node hash depends only on the definition of the atom
    // and the atomizer implementation. It ends with an "N", for "node".
    //
    // (We need the Atom Map in addition to the File Hash Map because each file
    // may have several atoms in it (e.g., multiple functions, or a class and
    // its methods). The File Hash Map contains an exhaustive list of all atoms
    // with type "file", but not child atoms of those top-level atoms.)
    //
    // = Graph Cache =
    //
    // We now know which atoms exist, and can compare the Atom Map to some
    // existing cache to figure out what has changed. However, this isn't
    // sufficient to figure out which documentation actually needs to be
    // regenerated, because atoms depend on other atoms. For example, if `B
    // extends A` and the definition for `A` changes, we need to regenerate the
    // documentation in `B`. Similarly, if `X` links to `Y` and `Y` changes, we
    // should regenerate `X`. (In both these cases, the documentation for the
    // connected atom may not actually change, but in some cases it will, and
    // the extra work we need to do is generally very small compared to the
    // size of the project.)
    //
    // To figure out which other nodes have changed, we compute a "graph hash"
    // for each node. This hash combines the "node hash" with the node hashes
    // of connected nodes. Our primary output is a list of graph hashes, which
    // a documentation generator can use to easily determine what work needs
    // to be done by comparing the list with a list of cached graph hashes,
    // then generating documentation for new hashes and deleting documentation
    // for missing hashes. The graph hash ends with a "G", for "graph".
    //
    // In this stage, we rely on three data structures:
    //
    //  - Symbol Map: `map<node_hash, symbol_hash>`
    //  - Edge Map: `map<node_hash, list<symbol_hash>>`
    //  - Graph Map: `map<node_hash, graph_hash>`
    //
    // Calculating the graph hash requires several steps, because we need to
    // figure out which nodes an atom is attached to. The atom contains symbolic
    // references to other nodes by name (e.g., `extends SomeClass`) in the form
    // of @{class:DivinerAtomRefs}. We can also build a symbolic reference for
    // any atom from the atom itself. Each @{class:DivinerAtomRef} generates a
    // symbol hash, which ends with an "S", for "symbol".
    //
    // First, we update the symbol map. We remove (and mark dirty) any symbols
    // associated with node hashes which no longer exist (e.g., old/dead nodes).
    // Second, we add (and mark dirty) any symbols associated with new nodes.
    // We also add edges defined by new nodes to the graph.
    //
    // We initialize a list of dirty nodes to the list of new nodes, then find
    // all nodes connected to dirty symbols and add them to the dirty node list.
    // This list now contains every node with a new or changed graph hash.
    //
    // We walk the dirty list and compute the new graph hashes, adding them
    // to the graph hash map. This Graph Map can then be passed to an actual
    // documentation generator, which can compare the graph hashes to a list
    // of already-generated graph hashes and easily assess which documents need
    // to be regenerated and which can be deleted.

    $this->buildAtomCache();
    $this->buildGraphCache();

    $publisher_class = $args->getArg('publisher');
    $symbols = id(new PhutilSymbolLoader())
      ->setName($publisher_class)
      ->setConcreteOnly(true)
      ->setAncestorClass('DivinerPublisher')
      ->selectAndLoadSymbols();

    if (!$symbols) {
      throw new PhutilArgumentUsageException(
        pht(
          "Publisher class '%s' must be a concrete subclass of %s.",
          $publisher_class,
          'DivinerPublisher'));
    }
    $publisher = newv($publisher_class, array());

    $identifier = $args->getArg('repository');
    $repository = null;
    if (strlen($identifier)) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withIdentifiers(array($identifier))
        ->executeOne();

      if (!$repository) {
        throw new PhutilArgumentUsageException(
          pht(
            'Repository "%s" does not exist.',
            $identifier));
      }

      $publisher->setRepositoryPHID($repository->getPHID());
    }

    $this->publishDocumentation($args->getArg('clean'), $publisher);
  }


/* -(  Atom Cache  )--------------------------------------------------------- */


  private function buildAtomCache() {
    $this->log(pht('BUILDING ATOM CACHE'));

    $file_hashes = $this->findFilesInProject();
    $this->log(
      pht(
        'Found %s file(s) in project.',
        phutil_count($file_hashes)));

    $this->deleteDeadAtoms($file_hashes);
    $atomize = $this->getFilesToAtomize($file_hashes);
    $this->log(
      pht(
        'Found %s unatomized, uncached file(s).',
        phutil_count($atomize)));

    $file_atomizers = $this->getAtomizersForFiles($atomize);
    $this->log(
      pht(
        'Found %s file(s) to atomize.',
        phutil_count($file_atomizers)));

    $futures = $this->buildAtomizerFutures($file_atomizers);
    $this->log(
      pht(
        'Atomizing %s file(s).',
        phutil_count($file_atomizers)));

    if ($futures) {
      $this->resolveAtomizerFutures($futures, $file_hashes);
      $this->log(pht('Atomization complete.'));
    } else {
      $this->log(pht('Atom cache is up to date, no files to atomize.'));
    }

    $this->log(pht('Writing atom cache.'));
    $this->getAtomCache()->saveAtoms();
    $this->log(pht('Done.')."\n");
  }

  private function getAtomizersForFiles(array $files) {
    $rules = $this->getRules();
    $exclude = $this->getExclude();
    $atomizers = array();

    foreach ($files as $file) {
      foreach ($exclude as $pattern) {
        if (preg_match($pattern, $file)) {
          continue 2;
        }
      }

      foreach ($rules as $rule => $atomizer) {
        $ok = preg_match($rule, $file);
        if ($ok === false) {
          throw new Exception(
            pht("Rule '%s' is not a valid regular expression.", $rule));
        }
        if ($ok) {
          $atomizers[$file] = $atomizer;
          continue;
        }
      }
    }

    return $atomizers;
  }

  private function getRules() {
    return $this->getConfig('rules', array(
      '/\\.diviner$/' => 'DivinerArticleAtomizer',
      '/\\.php$/' => 'DivinerPHPAtomizer',
    ));
  }

  private function getExclude() {
    $exclude = (array)$this->getConfig('exclude', array());
    return $exclude;
  }

  private function findFilesInProject() {
    $raw_hashes = id(new FileFinder($this->getConfig('root')))
      ->excludePath('*/.*')
      ->withType('f')
      ->setGenerateChecksums(true)
      ->find();

    $version = $this->getDivinerAtomWorldVersion();

    $file_hashes = array();
    foreach ($raw_hashes as $file => $md5_hash) {
      $rel_file = Filesystem::readablePath($file, $this->getConfig('root'));
      // We want the hash to change if the file moves or Diviner gets updated,
      // not just if the file content changes. Derive a hash from everything
      // we care about.
      $file_hashes[$rel_file] = md5("{$rel_file}\0{$md5_hash}\0{$version}").'F';
    }

    return $file_hashes;
  }

  private function deleteDeadAtoms(array $file_hashes) {
    $atom_cache = $this->getAtomCache();

    $hash_to_file = array_flip($file_hashes);
    foreach ($atom_cache->getFileHashMap() as $hash => $atom) {
      if (empty($hash_to_file[$hash])) {
        $atom_cache->deleteFileHash($hash);
      }
    }
  }

  private function getFilesToAtomize(array $file_hashes) {
    $atom_cache = $this->getAtomCache();

    $atomize = array();
    foreach ($file_hashes as $file => $hash) {
      if (!$atom_cache->fileHashExists($hash)) {
        $atomize[] = $file;
      }
    }

    return $atomize;
  }

  private function buildAtomizerFutures(array $file_atomizers) {
    $atomizers = array();
    foreach ($file_atomizers as $file => $atomizer) {
      $atomizers[$atomizer][] = $file;
    }

    $root = dirname(phutil_get_library_root('phabricator'));
    $config_root = $this->getConfig('root');

    $bar = id(new PhutilConsoleProgressBar())
      ->setTotal(count($file_atomizers));

    $futures = array();
    foreach ($atomizers as $class => $files) {
      foreach (array_chunk($files, 32) as $chunk) {
        $future = new ExecFuture(
          '%s atomize --ugly --book %s --atomizer %s -- %Ls',
          $root.'/bin/diviner',
          $this->getBookConfigPath(),
          $class,
          $chunk);
        $future->setCWD($config_root);

        $futures[] = $future;

        $bar->update(count($chunk));
      }
    }

    $bar->done();

    return $futures;
  }

  private function resolveAtomizerFutures(array $futures, array $file_hashes) {
    assert_instances_of($futures, 'Future');

    $atom_cache = $this->getAtomCache();
    $bar = id(new PhutilConsoleProgressBar())
      ->setTotal(count($futures));
    $futures = id(new FutureIterator($futures))
      ->limit(4);

    foreach ($futures as $key => $future) {
      try {
        $atoms = $future->resolveJSON();

        foreach ($atoms as $atom) {
          if ($atom['type'] == DivinerAtom::TYPE_FILE) {
            $file_hash = $file_hashes[$atom['file']];
            $atom_cache->addFileHash($file_hash, $atom['hash']);
          }
          $atom_cache->addAtom($atom);
        }
      } catch (Exception $e) {
        phlog($e);
      }

      $bar->update(1);
    }
    $bar->done();
  }

  /**
   * Get a global version number, which changes whenever any atom or atomizer
   * implementation changes in a way which is not backward-compatible.
   */
  private function getDivinerAtomWorldVersion() {
    $version = array();
    $version['atom'] = DivinerAtom::getAtomSerializationVersion();
    $version['rules'] = $this->getRules();

    $atomizers = id(new PhutilClassMapQuery())
      ->setAncestorClass('DivinerAtomizer')
      ->execute();

    $atomizer_versions = array();
    foreach ($atomizers as $atomizer) {
      $name = get_class($atomizer);
      $atomizer_versions[$name] = call_user_func(
        array(
          $name,
          'getAtomizerVersion',
        ));
    }

    ksort($atomizer_versions);
    $version['atomizers'] = $atomizer_versions;

    return md5(serialize($version));
  }


/* -(  Graph Cache  )-------------------------------------------------------- */


  private function buildGraphCache() {
    $this->log(pht('BUILDING GRAPH CACHE'));

    $atom_cache = $this->getAtomCache();
    $symbol_map = $atom_cache->getSymbolMap();
    $atoms = $atom_cache->getAtomMap();

    $dirty_symbols = array();
    $dirty_nhashes = array();

    $del_atoms = array_diff_key($symbol_map, $atoms);
    $this->log(
      pht(
        'Found %s obsolete atom(s) in graph.',
        phutil_count($del_atoms)));

    foreach ($del_atoms as $nhash => $shash) {
      $atom_cache->deleteSymbol($nhash);
      $dirty_symbols[$shash] = true;

      $atom_cache->deleteEdges($nhash);
      $atom_cache->deleteGraph($nhash);
    }

    $new_atoms = array_diff_key($atoms, $symbol_map);
    $this->log(
      pht(
        'Found %s new atom(s) in graph.',
        phutil_count($new_atoms)));

    foreach ($new_atoms as $nhash => $ignored) {
      $shash = $this->computeSymbolHash($nhash);
      $atom_cache->addSymbol($nhash, $shash);
      $dirty_symbols[$shash] = true;

      $atom_cache->addEdges($nhash, $this->getEdges($nhash));

      $dirty_nhashes[$nhash] = true;
    }

    $this->log(pht('Propagating changes through the graph.'));

    // Find all the nodes which point at a dirty node, and dirty them. Then
    // find all the nodes which point at those nodes and dirty them, and so
    // on. (This is slightly overkill since we probably don't need to propagate
    // dirtiness across documentation "links" between symbols, but we do want
    // to propagate it across "extends", and we suffer only a little bit of
    // collateral damage by over-dirtying as long as the documentation isn't
    // too well-connected.)

    $symbol_stack = array_keys($dirty_symbols);
    while ($symbol_stack) {
      $symbol_hash = array_pop($symbol_stack);

      foreach ($atom_cache->getEdgesWithDestination($symbol_hash) as $edge) {
        $dirty_nhashes[$edge] = true;
        $src_hash = $this->computeSymbolHash($edge);
        if (empty($dirty_symbols[$src_hash])) {
          $dirty_symbols[$src_hash] = true;
          $symbol_stack[] = $src_hash;
        }
      }
    }

    $this->log(
      pht(
        'Found %s affected atoms.',
        phutil_count($dirty_nhashes)));

    foreach ($dirty_nhashes as $nhash => $ignored) {
      $atom_cache->addGraph($nhash, $this->computeGraphHash($nhash));
    }

    $this->log(pht('Writing graph cache.'));

    $atom_cache->saveGraph();
    $atom_cache->saveEdges();
    $atom_cache->saveSymbols();

    $this->log(pht('Done.')."\n");
  }

  private function computeSymbolHash($node_hash) {
    $atom_cache = $this->getAtomCache();
    $atom = $atom_cache->getAtom($node_hash);

    if (!$atom) {
      throw new Exception(
        pht("No such atom with node hash '%s'!", $node_hash));
    }

    $ref = DivinerAtomRef::newFromDictionary($atom['ref']);
    return $ref->toHash();
  }

  private function getEdges($node_hash) {
    $atom_cache = $this->getAtomCache();
    $atom = $atom_cache->getAtom($node_hash);

    $refs = array();

    // Make the atom depend on its own symbol, so that all atoms with the same
    // symbol are dirtied (e.g., if a codebase defines the function `f()`
    // several times, all of them should be dirtied when one is dirtied).
    $refs[DivinerAtomRef::newFromDictionary($atom)->toHash()] = true;

    foreach (array_merge($atom['extends'], $atom['links']) as $ref_dict) {
      $ref = DivinerAtomRef::newFromDictionary($ref_dict);
      if ($ref->getBook() == $atom['book']) {
        $refs[$ref->toHash()] = true;
      }
    }

    return array_keys($refs);
  }

  private function computeGraphHash($node_hash) {
    $atom_cache = $this->getAtomCache();
    $atom = $atom_cache->getAtom($node_hash);

    $edges = $this->getEdges($node_hash);
    sort($edges);

    $inputs = array(
      'atomHash' => $atom['hash'],
      'edges' => $edges,
    );

    return md5(serialize($inputs)).'G';
  }

  private function publishDocumentation($clean, DivinerPublisher $publisher) {
    $atom_cache = $this->getAtomCache();
    $graph_map = $atom_cache->getGraphMap();

    $this->log(pht('PUBLISHING DOCUMENTATION'));

    $publisher
      ->setDropCaches($clean)
      ->setConfig($this->getAllConfig())
      ->setAtomCache($atom_cache)
      ->setRenderer(new DivinerDefaultRenderer())
      ->publishAtoms(array_values($graph_map));

    $this->log(pht('Done.'));
  }

}
