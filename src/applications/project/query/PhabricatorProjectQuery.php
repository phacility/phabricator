<?php

final class PhabricatorProjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;

  private $status       = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_CLOSED   = 'status-closed';
  const STATUS_ACTIVE   = 'status-active';
  const STATUS_ARCHIVED = 'status-archived';

  private $needMembers;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
    return $this;
  }

  public function needMembers($need_members) {
    $this->needMembers = $need_members;
    return $this;
  }

  protected function getPagingColumn() {
    return 'name';
  }

  protected function getPagingValue($result) {
    return $result->getName();
  }

  protected function getReversePaging() {
    return true;
  }

  public function loadPage() {
    $table = new PhabricatorProject();
    $conn_r = $table->establishConnection('r');

    // NOTE: Because visibility checks for projects depend on whether or not
    // the user is a project member, we always load their membership. If we're
    // loading all members anyway we can piggyback on that; otherwise we
    // do an explicit join.

    $select_clause = '';
    if (!$this->needMembers) {
      $select_clause = ', vm.dst viewerIsMember';
    }

    $data = queryfx_all(
      $conn_r,
      'SELECT p.* %Q FROM %T p %Q %Q %Q %Q %Q',
      $select_clause,
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildGroupClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $projects = $table->loadAllFromArray($data);

    if ($projects) {
      $viewer_phid = $this->getViewer()->getPHID();
      if ($this->needMembers) {
        $etype = PhabricatorEdgeConfig::TYPE_PROJ_MEMBER;
        $members = id(new PhabricatorEdgeQuery())
          ->withSourcePHIDs(mpull($projects, 'getPHID'))
          ->withEdgeTypes(array($etype))
          ->execute();
        foreach ($projects as $project) {
          $phid = $project->getPHID();
          $project->attachMemberPHIDs(array_keys($members[$phid][$etype]));
          $project->setIsUserMember(
            $viewer_phid,
            isset($members[$phid][$etype][$viewer_phid]));
        }
      } else {
        foreach ($data as $row) {
          $projects[$row['id']]->setIsUserMember(
            $viewer_phid,
            ($row['viewerIsMember'] !== null));
        }
      }
    }

    return $projects;
  }

  private function buildWhereClause($conn_r) {
    $where = array();

    if ($this->status != self::STATUS_ANY) {
      switch ($this->status) {
        case self::STATUS_OPEN:
        case self::STATUS_ACTIVE:
          $filter = array(
            PhabricatorProjectStatus::STATUS_ACTIVE,
          );
          break;
        case self::STATUS_CLOSED:
        case self::STATUS_ARCHIVED:
          $filter = array(
            PhabricatorProjectStatus::STATUS_ARCHIVED,
          );
          break;
        default:
          throw new Exception(
            "Unknown project status '{$this->status}'!");
      }
      $where[] = qsprintf(
        $conn_r,
        'status IN (%Ld)',
        $filter);
    }

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

    if ($this->memberPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'e.dst IN (%Ls)',
        $this->memberPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  private function buildGroupClause($conn_r) {
    if ($this->memberPHIDs) {
      return 'GROUP BY p.id';
    } else {
      return '';
    }
  }

  private function buildJoinClause($conn_r) {
    $joins = array();

    if (!$this->needMembers) {
      $joins[] = qsprintf(
        $conn_r,
        'LEFT JOIN %T vm ON vm.src = p.phid AND vm.type = %d AND vm.dst = %s',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorEdgeConfig::TYPE_PROJ_MEMBER,
        $this->getViewer()->getPHID());
    }

    if ($this->memberPHIDs) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T e ON e.src = p.phid AND e.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorEdgeConfig::TYPE_PROJ_MEMBER);
    }

    return implode(' ', $joins);
  }

}
