<?php

final class ReleephRequestQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $requestedCommitPHIDs;
  private $ids;
  private $phids;
  private $severities;
  private $requestorPHIDs;
  private $branchIDs;
  private $requestedObjectPHIDs;

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

  public function withRequestedObjectPHIDs(array $phids) {
    $this->requestedObjectPHIDs = $phids;
    return $this;
  }

  protected function loadPage() {
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

  protected function willFilterPage(array $requests) {
    // Load requested objects: you must be able to see an object to see
    // requests for it.
    $object_phids = mpull($requests, 'getRequestedObjectPHID');
    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($object_phids)
      ->execute();

    foreach ($requests as $key => $request) {
      $object_phid = $request->getRequestedObjectPHID();
      $object = idx($objects, $object_phid);
      if (!$object) {
        unset($requests[$key]);
        continue;
      }
      $request->attachRequestedObject($object);
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

    $branch_ids = array_unique(mpull($requests, 'getBranchID'));
    $branches = id(new ReleephBranchQuery())
      ->withIDs($branch_ids)
      ->setViewer($this->getViewer())
      ->execute();
    $branches = mpull($branches, null, 'getID');
    foreach ($requests as $key => $request) {
      $branch = idx($branches, $request->getBranchID());
      if (!$branch) {
        unset($requests[$key]);
        continue;
      }
      $request->attachBranch($branch);
    }

    // TODO: These should be serviced by the query, but are not currently
    // denormalized anywhere. For now, filter them here instead. Note that
    // we must perform this filtering *after* querying and attaching branches,
    // because request status depends on the product.

    $keep_status = array_fuse($this->getKeepStatusConstants());
    if ($keep_status) {
      foreach ($requests as $key => $request) {
        if (empty($keep_status[$request->getStatus()])) {
          unset($requests[$key]);
        }
      }
    }

    return $requests;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->branchIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'branchID IN (%Ld)',
        $this->branchIDs);
    }

    if ($this->requestedCommitPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'requestCommitPHID IN (%Ls)',
        $this->requestedCommitPHIDs);
    }

    if ($this->requestorPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'requestUserPHID IN (%Ls)',
        $this->requestorPHIDs);
    }

    if ($this->requestedObjectPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'requestedObjectPHID IN (%Ls)',
        $this->requestedObjectPHIDs);
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
        throw new Exception(pht("Unknown status '%s'!", $this->status));
    }
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorReleephApplication';
  }

}
