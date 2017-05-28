<?php

final class PhabricatorBadgesQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $qualities;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withQualities(array $qualities) {
    $this->qualities = $qualities;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      id(new PhabricatorBadgesBadgeNameNgrams()),
      $ngrams);
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function getPrimaryTableAlias() {
    return 'badges';
  }

  public function newResultObject() {
    return new PhabricatorBadgesBadge();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'badges.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'badges.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->qualities !== null) {
      $where[] = qsprintf(
        $conn,
        'badges.quality IN (%Ls)',
        $this->qualities);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'badges.status IN (%Ls)',
        $this->statuses);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorBadgesApplication';
  }

  public function getBuiltinOrders() {
    return array(
      'quality' => array(
        'vector' => array('quality', 'id'),
        'name' => pht('Rarity (Rarest First)'),
      ),
      'shoddiness' => array(
        'vector' => array('-quality', '-id'),
        'name' => pht('Rarity (Most Common First)'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return array(
      'quality' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'quality',
        'reverse' => true,
        'type' => 'int',
      ),
    ) + parent::getOrderableColumns();
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $badge = $this->loadCursorObject($cursor);
    return array(
      'quality' => $badge->getQuality(),
      'id' => $badge->getID(),
    );
  }

}
