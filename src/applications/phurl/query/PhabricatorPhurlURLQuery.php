<?php

final class PhabricatorPhurlURLQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $names;
  private $longURLs;
  private $aliases;
  private $authorPHIDs;

  public function newResultObject() {
    return new PhabricatorPhurlURL();
  }

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      id(new PhabricatorPhurlURLNameNgrams()),
      $ngrams);
  }

  public function withLongURLs(array $long_urls) {
    $this->longURLs = $long_urls;
    return $this;
  }

  public function withAliases(array $aliases) {
    $this->aliases = $aliases;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $url = $this->loadCursorObject($cursor);
    return array(
      'id' => $url->getID(),
    );
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'url.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'url.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'url.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'url.name IN (%Ls)',
        $this->names);
    }

    if ($this->longURLs !== null) {
      $where[] = qsprintf(
        $conn,
        'url.longURL IN (%Ls)',
        $this->longURLs);
    }

    if ($this->aliases !== null) {
      $where[] = qsprintf(
        $conn,
        'url.alias IN (%Ls)',
        $this->aliases);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'url';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhurlApplication';
  }
}
