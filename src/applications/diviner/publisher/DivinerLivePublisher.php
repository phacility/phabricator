<?php

final class DivinerLivePublisher extends DivinerPublisher {

  private $book;

  private function loadBook() {
    if (!$this->book) {
      $book_name = $this->getConfig('name');

      $book = id(new DivinerLiveBook())->loadOneWhere('name = %s', $book_name);
      if (!$book) {
        $book = id(new DivinerLiveBook())
          ->setName($book_name)
          ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
          ->save();
      }

      $book->setConfigurationData($this->getConfigurationData())->save();
      $this->book = $book;

      id(new PhabricatorSearchIndexer())
        ->queueDocumentForIndexing($book->getPHID());
    }

    return $this->book;
  }

  private function loadSymbolForAtom(DivinerAtom $atom) {
    $symbol = id(new DivinerAtomQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withBookPHIDs(array($this->loadBook()->getPHID()))
      ->withTypes(array($atom->getType()))
      ->withNames(array($atom->getName()))
      ->withContexts(array($atom->getContext()))
      ->withIndexes(array($this->getAtomSimilarIndex($atom)))
      ->executeOne();

    if ($symbol) {
      return $symbol;
    }

    return id(new DivinerLiveSymbol())
      ->setBookPHID($this->loadBook()->getPHID())
      ->setType($atom->getType())
      ->setName($atom->getName())
      ->setContext($atom->getContext())
      ->setAtomIndex($this->getAtomSimilarIndex($atom));
  }

  private function loadAtomStorageForSymbol(DivinerLiveSymbol $symbol) {
    $storage = id(new DivinerLiveAtom())->loadOneWhere(
      'symbolPHID = %s',
      $symbol->getPHID());

    if ($storage) {
      return $storage;
    }

    return id(new DivinerLiveAtom())
      ->setSymbolPHID($symbol->getPHID());
  }

  protected function loadAllPublishedHashes() {
    $symbols = id(new DivinerAtomQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withBookPHIDs(array($this->loadBook()->getPHID()))
      ->withGhosts(false)
      ->execute();

    return mpull($symbols, 'getGraphHash');
  }

  protected function deleteDocumentsByHash(array $hashes) {
    $atom_table = new DivinerLiveAtom();
    $symbol_table = new DivinerLiveSymbol();
    $conn_w = $symbol_table->establishConnection('w');

    $strings = array();
    foreach ($hashes as $hash) {
      $strings[] = qsprintf($conn_w, '%s', $hash);
    }

    foreach (PhabricatorLiskDAO::chunkSQL($strings, ', ') as $chunk) {
      queryfx(
        $conn_w,
        'UPDATE %T SET graphHash = NULL, nodeHash = NULL
          WHERE graphHash IN (%Q)',
        $symbol_table->getTableName(),
        $chunk);
    }

    queryfx(
      $conn_w,
      'DELETE a FROM %T a LEFT JOIN %T s
        ON a.symbolPHID = s.phid
        WHERE s.graphHash IS NULL',
      $atom_table->getTableName(),
      $symbol_table->getTableName());
  }

  protected function createDocumentsByHash(array $hashes) {
    foreach ($hashes as $hash) {
      $atom = $this->getAtomFromGraphHash($hash);
      $ref = $atom->getRef();

      $symbol = $this->loadSymbolForAtom($atom);

      $is_documentable = $this->shouldGenerateDocumentForAtom($atom);

      $symbol
        ->setGraphHash($hash)
        ->setIsDocumentable((int)$is_documentable)
        ->setTitle($ref->getTitle())
        ->setGroupName($ref->getGroup())
        ->setNodeHash($atom->getHash());

      if ($atom->getType() !== DivinerAtom::TYPE_FILE) {
        $renderer = $this->getRenderer();
        $summary = $renderer->getAtomSummary($atom);
        $symbol->setSummary($summary);
      } else {
        $symbol->setSummary('');
      }

      $symbol->save();

      id(new PhabricatorSearchIndexer())
        ->queueDocumentForIndexing($symbol->getPHID());

      // TODO: We probably need a finer-grained sense of what "documentable"
      // atoms are. Neither files nor methods are currently considered
      // documentable, but for different reasons: files appear nowhere, while
      // methods just don't appear at the top level. These are probably
      // separate concepts. Since we need atoms in order to build method
      // documentation, we insert them here. This also means we insert files,
      // which are unnecessary and unused. Make sure this makes sense, but then
      // probably introduce separate "isTopLevel" and "isDocumentable" flags?
      // TODO: Yeah do that soon ^^^

      if ($atom->getType() !== DivinerAtom::TYPE_FILE) {
        $storage = $this->loadAtomStorageForSymbol($symbol)
          ->setAtomData($atom->toDictionary())
          ->setContent(null)
          ->save();
      }

    }
  }

  public function findAtomByRef(DivinerAtomRef $ref) {
    // TODO: Actually implement this.
    return null;
  }

}
