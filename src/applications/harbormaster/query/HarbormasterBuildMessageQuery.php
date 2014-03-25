<?php

final class HarbormasterBuildMessageQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $buildTargetPHIDs;
  private $consumed;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withBuildTargetPHIDs(array $phids) {
    $this->buildTargetPHIDs = $phids;
    return $this;
  }

  public function withConsumed($consumed) {
    $this->consumed = $consumed;
    return $this;
  }

  protected function loadPage() {
    $table = new HarbormasterBuildMessage();
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

  protected function willFilterPage(array $page) {
    $build_target_phids = array_filter(mpull($page, 'getBuildTargetPHID'));
    if ($build_target_phids) {
      $build_targets = id(new PhabricatorObjectQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($build_target_phids)
        ->setParentQuery($this)
        ->execute();
      $build_targets = mpull($build_targets, null, 'getPHID');
    } else {
      $build_targets = array();
    }

    foreach ($page as $key => $message) {
      $build_target_phid = $message->getBuildTargetPHID();
      if (empty($build_targets[$build_target_phid])) {
        unset($page[$key]);
        continue;
      }
      $message->attachBuildTarget($build_targets[$build_target_phid]);
    }

    return $page;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->buildTargetPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'buildTargetPHID IN (%Ls)',
        $this->buildTargetPHIDs);
    }

    if ($this->consumed !== null) {
      $where[] = qsprintf(
        $conn_r,
        'isConsumed = %d',
        (int)$this->isConsumed);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationHarbormaster';
  }

}
