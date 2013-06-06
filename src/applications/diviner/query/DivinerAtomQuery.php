<?php

final class DivinerAtomQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $bookPHIDs;
  private $names;
  private $types;
  private $contexts;
  private $indexes;
  private $includeUndocumentable;

  private $needAtoms;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBookPHIDs(array $phids) {
    $this->bookPHIDs = $phids;
    return $this;
  }

  public function withTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withContexts(array $contexts) {
    $this->contexts = $contexts;
    return $this;
  }

  public function withIndexes(array $indexes) {
    $this->indexes = $indexes;
    return $this;
  }

  public function needAtoms($need) {
    $this->needAtoms = $need;
    return $this;
  }

  public function withIncludeUndocumentable($include) {
    $this->includeUndocumentable = $include;
    return $this;
  }

  protected function loadPage() {
    $table = new DivinerLiveSymbol();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  protected function willFilterPage(array $atoms) {
    if (!$atoms) {
      return $atoms;
    }

    $books = array_unique(mpull($atoms, 'getBookPHID'));

    $books = id(new DivinerBookQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($books)
      ->execute();
    $books = mpull($books, null, 'getPHID');

    foreach ($atoms as $key => $atom) {
      $book = idx($books, $atom->getBookPHID());
      if (!$book) {
        unset($atoms[$key]);
        continue;
      }
      $atom->attachBook($book);
    }

    if ($this->needAtoms) {
      $atom_data = id(new DivinerLiveAtom())->loadAllWhere(
        'symbolPHID IN (%Ls)',
        mpull($atoms, 'getPHID'));
      $atom_data = mpull($atom_data, null, 'getSymbolPHID');

      foreach ($atoms as $key => $atom) {
        $data = idx($atom_data, $atom->getPHID());
        if (!$data) {
          unset($atoms[$key]);
          continue;
        }
        $atom->attachAtom($data);
      }
    }

    return $atoms;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->bookPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'bookPHID IN (%Ls)',
        $this->bookPHIDs);
    }

    if ($this->types) {
      $where[] = qsprintf(
        $conn_r,
        'type IN (%Ls)',
        $this->types);
    }

    if ($this->names) {
      $where[] = qsprintf(
        $conn_r,
        'name IN (%Ls)',
        $this->names);
    }

    if ($this->contexts) {
      $with_null = false;
      $contexts = $this->contexts;
      foreach ($contexts as $key => $value) {
        if ($value === null) {
          unset($contexts[$key]);
          $with_null = true;
          continue;
        }
      }

      if ($contexts && $with_null) {
        $where[] = qsprintf(
          $conn_r,
          'context IN (%Ls) OR context IS NULL',
          $contexts);
      } else if ($contexts) {
        $where[] = qsprintf(
          $conn_r,
          'context IN (%Ls)',
          $contexts);
      } else if ($with_null) {
        $where[] = qsprintf(
          $conn_r,
          'context IS NULL');
      }
    }

    if ($this->indexes) {
      $where[] = qsprintf(
        $conn_r,
        'atomIndex IN (%Ld)',
        $this->indexes);
    }

    if (!$this->includeUndocumentable) {
      $where[] = qsprintf(
        $conn_r,
        'isDocumentable = 1');
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

}
