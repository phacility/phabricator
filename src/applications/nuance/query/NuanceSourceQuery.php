<?php

final class NuanceSourceQuery
  extends NuanceQuery {

  private $ids;
  private $phids;
  private $types;
  private $isDisabled;
  private $hasCursors;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withTypes($types) {
    $this->types = $types;
    return $this;
  }

  public function withIsDisabled($disabled) {
    $this->isDisabled = $disabled;
    return $this;
  }

  public function withHasImportCursors($has_cursors) {
    $this->hasCursors = $has_cursors;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      new NuanceSourceNameNgrams(),
      $ngrams);
  }

  public function newResultObject() {
    return new NuanceSource();
  }

  protected function getPrimaryTableAlias() {
    return 'source';
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $sources) {
    $all_types = NuanceSourceDefinition::getAllDefinitions();

    foreach ($sources as $key => $source) {
      $definition = idx($all_types, $source->getType());
      if (!$definition) {
        $this->didRejectResult($source);
        unset($sources[$key]);
        continue;
      }
      $source->attachDefinition($definition);
    }

    return $sources;
  }


  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->types !== null) {
      $where[] = qsprintf(
        $conn,
        'source.type IN (%Ls)',
        $this->types);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'source.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'source.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->isDisabled !== null) {
      $where[] = qsprintf(
        $conn,
        'source.isDisabled = %d',
        (int)$this->isDisabled);
    }

    if ($this->hasCursors !== null) {
      $cursor_types = array();

      $definitions = NuanceSourceDefinition::getAllDefinitions();
      foreach ($definitions as $key => $definition) {
        if ($definition->hasImportCursors()) {
          $cursor_types[] = $key;
        }
      }

      if ($this->hasCursors) {
        if (!$cursor_types) {
          throw new PhabricatorEmptyQueryException();
        } else {
          $where[] = qsprintf(
            $conn,
            'source.type IN (%Ls)',
            $cursor_types);
        }
      } else {
        if (!$cursor_types) {
          // Apply no constraint.
        } else {
          $where[] = qsprintf(
            $conn,
            'source.type NOT IN (%Ls)',
            $cursor_types);
        }
      }
    }

    return $where;
  }

}
