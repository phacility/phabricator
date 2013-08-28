<?php

final class ReleephRequestQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $requestedCommitPHIDs;
  private $commitToRevMap;
  private $ids;
  private $phids;
  private $severities;
  private $requestorPHIDs;
  private $branchIDs;

  const STATUS_ALL          = 'status-all';
  const STATUS_OPEN         = 'status-open';
  const STATUS_REQUESTED    = 'status-requested';
  const STATUS_NEEDS_PULL   = 'status-needs-pull';
  const STATUS_REJECTED     = 'status-rejected';
  const STATUS_ABANDONED    = 'status-abandoned';
  const STATUS_PULLED       = 'status-pulled';
  const STATUS_NEEDS_REVERT = 'status-needs-revert';
  const STATUS_REVERTED     = 'status-reverted';

  private $status = self::STATUS_ALL;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withBranchIDs(array $branch_ids) {
    $this->branchIDs = $branch_ids;
    return $this;
  }

  public function getRevisionPHID($commit_phid) {
    if ($this->commitToRevMap) {
      return idx($this->commitToRevMap, $commit_phid, null);
    }

    return null;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withRequestedCommitPHIDs(array $requested_commit_phids) {
    $this->requestedCommitPHIDs = $requested_commit_phids;
    return $this;
  }

  public function withRequestorPHIDs(array $phids) {
    $this->requestorPHIDs = $phids;
    return $this;
  }

  public function withSeverities(array $severities) {
    $this->severities = $severities;
    return $this;
  }

  public function withRevisionPHIDs(array $revision_phids) {
    $type = PhabricatorEdgeConfig::TYPE_DREV_HAS_COMMIT;

    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($revision_phids)
      ->withEdgeTypes(array($type))
      ->execute();

    $this->commitToRevMap = array();

    foreach ($edges as $revision_phid => $edge) {
      foreach ($edge[$type] as $commitPHID => $item) {
        $this->commitToRevMap[$commitPHID] = $revision_phid;
      }
    }

    $this->requestedCommitPHIDs = array_keys($this->commitToRevMap);
  }

  public function loadPage() {
    $table = new ReleephRequest();
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

  public function willFilterPage(array $requests) {

    // TODO: These should be serviced by the query, but are not currently
    // denormalized anywhere. For now, filter them here instead.

    $keep_status = array_fuse($this->getKeepStatusConstants());
    if ($keep_status) {
      foreach ($requests as $key => $request) {
        if (empty($keep_status[$request->getStatus()])) {
          unset($requests[$key]);
        }
      }
    }

    if ($this->severities) {
      $severities = array_fuse($this->severities);
      foreach ($requests as $key => $request) {

        // NOTE: Facebook uses a custom field here.
        if (ReleephDefaultFieldSelector::isFacebook()) {
          $severity = $request->getDetail('severity');
        } else {
          $severity = $request->getDetail('releeph:severity');
        }

        if (empty($severities[$severity])) {
          unset($requests[$key]);
        }
      }
    }

    return $requests;
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

    if ($this->branchIDs) {
      $where[] = qsprintf(
        $conn_r,
        'branchID IN (%Ld)',
        $this->branchIDs);
    }

    if ($this->requestedCommitPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'requestCommitPHID IN (%Ls)',
        $this->requestedCommitPHIDs);
    }

    if ($this->requestorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'requestUserPHID IN (%Ls)',
        $this->requestorPHIDs);
    }

    $where[] = $this->buildPagingClause($conn_r);

    return $this->formatWhereClause($where);
  }

  private function getKeepStatusConstants() {
    switch ($this->status) {
      case self::STATUS_ALL:
        return array();
      case self::STATUS_OPEN:
        return array(
          ReleephRequestStatus::STATUS_REQUESTED,
          ReleephRequestStatus::STATUS_NEEDS_PICK,
          ReleephRequestStatus::STATUS_NEEDS_REVERT,
        );
      case self::STATUS_REQUESTED:
        return array(
          ReleephRequestStatus::STATUS_REQUESTED,
        );
      case self::STATUS_NEEDS_PULL:
        return array(
          ReleephRequestStatus::STATUS_NEEDS_PICK,
        );
      case self::STATUS_REJECTED:
        return array(
          ReleephRequestStatus::STATUS_REJECTED,
        );
      case self::STATUS_ABANDONED:
        return array(
          ReleephRequestStatus::STATUS_ABANDONED,
        );
      case self::STATUS_PULLED:
        return array(
          ReleephRequestStatus::STATUS_PICKED,
        );
      case self::STATUS_NEEDS_REVERT:
        return array(
          ReleephRequestStatus::NEEDS_REVERT,
        );
      case self::STATUS_REVERTED:
        return array(
          ReleephRequestStatus::REVERTED,
        );
      default:
        throw new Exception("Unknown status '{$this->status}'!");
    }
  }

}
