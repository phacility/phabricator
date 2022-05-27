<?php

final class PhabricatorCountdownQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $upcoming;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withUpcoming() {
    $this->upcoming = true;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorCountdown();
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'authorPHID in (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->upcoming !== null) {
      $where[] = qsprintf(
        $conn,
        'epoch >= %d',
        PhabricatorTime::getNow());
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorCountdownApplication';
  }

  public function getBuiltinOrders() {
    return array(
      'ending' => array(
        'vector' => array('-epoch', '-id'),
        'name' => pht('End Date (Past to Future)'),
      ),
      'unending' => array(
        'vector' => array('epoch', 'id'),
        'name' => pht('End Date (Future to Past)'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return array(
      'epoch' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'epoch',
        'type' => 'int',
      ),
    ) + parent::getOrderableColumns();
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'epoch' => (int)$object->getEpoch(),
    );
  }

}
