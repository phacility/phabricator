<?php

final class DivinerLivePublisher extends DivinerPublisher {

  private $book;

  private function loadBook() {
    if (!$this->book) {
      $book_name = $this->getConfig('name');

      $book = id(new DivinerLiveBook())->loadOneWhere(
        'name = %s',
        $book_name);
      if (!$book) {
        $book = id(new DivinerLiveBook())
          ->setName($book_name)
          ->setViewPolicy(PhabricatorPolicies::POLICY_USER)
          ->save();
      }

      $book->setConfigurationData($this->getConfigurationData())->save();

      $this->book = $book;
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
      ->withIncludeUndocumentable(true)
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
    $symbols = id(new DivinerLiveSymbol())->loadAllWhere(
      'bookPHID = %s AND graphHash IS NOT NULL',
      $this->loadBook()->getPHID());

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
        'UPDATE %T SET graphHash = NULL WHERE graphHash IN (%Q)',
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
        ->setGroupName($ref->getGroup());

      if ($is_documentable) {
        $renderer = $this->getRenderer();
        $summary = $renderer->renderAtomSummary($atom);
        $summary = (string)phutil_safe_html($summary);
        $symbol->setSummary($summary);
      }

      $symbol->save();

      if ($is_documentable) {
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
