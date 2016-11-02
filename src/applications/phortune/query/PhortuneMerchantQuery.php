<?php

final class PhortuneMerchantQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;
  private $needProfileImage;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
    return $this;
  }

  public function needProfileImage($need) {
    $this->needProfileImage = $need;
    return $this;
  }

  protected function loadPage() {
    $table = new PhortuneMerchant();
    $conn = $table->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT m.* FROM %T m %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn),
      $this->buildWhereClause($conn),
      $this->buildOrderClause($conn),
      $this->buildLimitClause($conn));

    return $table->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $merchants) {
    $query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(mpull($merchants, 'getPHID'))
      ->withEdgeTypes(array(PhortuneMerchantHasMemberEdgeType::EDGECONST));
    $query->execute();

    foreach ($merchants as $merchant) {
      $member_phids = $query->getDestinationPHIDs(array($merchant->getPHID()));
      $member_phids = array_reverse($member_phids);
      $merchant->attachMemberPHIDs($member_phids);
    }

    if ($this->needProfileImage) {
      $default = null;
      $file_phids = mpull($merchants, 'getProfileImagePHID');
      $file_phids = array_filter($file_phids);
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setParentQuery($this)
          ->setViewer($this->getViewer())
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
      } else {
        $files = array();
      }

      foreach ($merchants as $merchant) {
        $file = idx($files, $merchant->getProfileImagePHID());
        if (!$file) {
          if (!$default) {
            $default = PhabricatorFile::loadBuiltin(
              $this->getViewer(),
              'merchant.png');
          }
          $file = $default;
        }
        $merchant->attachProfileImageFile($file);
      }
    }

    return $merchants;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

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

    if ($this->memberPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'e.dst IN (%Ls)',
        $this->memberPHIDs);
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn) {
    $joins = array();

    if ($this->memberPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T e ON m.phid = e.src AND e.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhortuneMerchantHasMemberEdgeType::EDGECONST);
    }

    return implode(' ', $joins);
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

}
