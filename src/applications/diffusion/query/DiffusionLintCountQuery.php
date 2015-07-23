<?php

final class DiffusionLintCountQuery extends PhabricatorQuery {

  private $branchIDs;
  private $paths;
  private $codes;

  public function withBranchIDs(array $branch_ids) {
    $this->branchIDs = $branch_ids;
    return $this;
  }

  public function withPaths(array $paths) {
    $this->paths = $paths;
    return $this;
  }

  public function withCodes(array $codes) {
    $this->codes = $codes;
    return $this;
  }

  public function execute() {
    if (!$this->paths) {
      throw new PhutilInvalidStateException('withPaths');
    }

    if (!$this->branchIDs) {
      throw new PhutilInvalidStateException('withBranchIDs');
    }

    $conn_r = id(new PhabricatorRepositoryCommit())->establishConnection('r');

    $this->paths = array_unique($this->paths);
    list($dirs, $paths) = $this->processPaths();

    $parts = array();
    foreach ($dirs as $dir) {
      $parts[$dir] = qsprintf(
        $conn_r,
        'path LIKE %>',
        $dir);
    }
    foreach ($paths as $path) {
      $parts[$path] = qsprintf(
        $conn_r,
        'path = %s',
        $path);
    }

    $queries = array();
    foreach ($parts as $key => $part) {
      $queries[] = qsprintf(
        $conn_r,
        'SELECT %s path_prefix, COUNT(*) N FROM %T %Q',
        $key,
        PhabricatorRepository::TABLE_LINTMESSAGE,
        $this->buildCustomWhereClause($conn_r, $part));
    }

    $huge_union_query = '('.implode(') UNION ALL (', $queries).')';

    $data = queryfx_all(
      $conn_r,
      '%Q',
      $huge_union_query);

    return $this->processResults($data);
  }

  protected function buildCustomWhereClause(
    AphrontDatabaseConnection $conn_r,
    $part) {

    $where = array();

    $where[] = $part;

    if ($this->codes !== null) {
      $where[] = qsprintf(
        $conn_r,
        'code IN (%Ls)',
        $this->codes);
    }

    if ($this->branchIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'branchID IN (%Ld)',
        $this->branchIDs);
    }

    return $this->formatWhereClause($where);
  }

  private function processPaths() {
    $dirs = array();
    $paths = array();
    foreach ($this->paths as $path) {
      $path = '/'.$path;
      if (substr($path, -1) == '/') {
        $dirs[] = $path;
      } else {
        $paths[] = $path;
      }
    }
    return array($dirs, $paths);
  }

  private function processResults(array $data) {
    $data = ipull($data, 'N', 'path_prefix');

    // Strip the leading "/" back off each path.
    $output = array();
    foreach ($data as $path => $count) {
      $output[substr($path, 1)] = $count;
    }

    return $output;
  }

}
