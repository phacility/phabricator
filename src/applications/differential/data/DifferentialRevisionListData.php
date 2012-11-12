<?php

final class DifferentialRevisionListData {

  const QUERY_OPEN_OWNED               = 'open';
  const QUERY_OPEN_REVIEWER            = 'reviewer';
  const QUERY_OWNED                    = 'owned';
  const QUERY_OWNED_OR_REVIEWER        = 'related';
  const QUERY_NEED_ACTION_FROM_OTHERS  = 'need-other-action';
  const QUERY_NEED_ACTION_FROM_SELF    = 'need-self-action';
  const QUERY_COMMITTABLE              = 'committable';
  const QUERY_REVISION_IDS             = 'revision-ids';
  const QUERY_PHIDS                    = 'phids';
  const QUERY_CC                       = 'cc';
  const QUERY_ALL_OPEN                 = 'all-open';

  private $ids;
  private $filter;
  private $handles;
  private $revisions;
  private $order;

  public function __construct($filter, array $ids) {
    $this->filter = $filter;
    $this->ids = $ids;
  }

  public function getRevisions() {
    return $this->revisions;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  public function loadRevisions() {
    switch ($this->filter) {
      case self::QUERY_CC:
        $this->revisions = $this->loadAllOpenWithCCs($this->ids);
        break;
      case self::QUERY_ALL_OPEN:
        $this->revisions = $this->loadAllOpen();
        break;
      case self::QUERY_OPEN_OWNED:
        $this->revisions = $this->loadAllWhere(
          'revision.status in (%Ld) AND revision.authorPHID in (%Ls)',
          $this->getOpenStatuses(),
          $this->ids);
        break;
      case self::QUERY_COMMITTABLE:
        $this->revisions = $this->loadAllWhere(
          'revision.status in (%Ld) AND revision.authorPHID in (%Ls)',
          array(
            ArcanistDifferentialRevisionStatus::ACCEPTED,
          ),
          $this->ids);
        break;
      case self::QUERY_REVISION_IDS:
        $this->revisions = $this->loadAllWhere(
          'id in (%Ld)',
          $this->ids);
        break;
      case self::QUERY_OPEN_REVIEWER:
        $this->revisions = $this->loadAllWhereJoinReview(
          'revision.status in (%Ld) AND relationship.objectPHID in (%Ls)',
          $this->getOpenStatuses(),
          $this->ids);
        break;
      case self::QUERY_OWNED:
        $this->revisions = $this->loadAllWhere(
          'revision.authorPHID in (%Ls)',
          $this->ids);
        break;
      case self::QUERY_OWNED_OR_REVIEWER:
        $rev = new DifferentialRevision();
        $data = queryfx_all(
          $rev->establishConnection('r'),
          'SELECT revs.* FROM (
            (
              SELECT revision.*
              FROM %T revision
              WHERE revision.authorPHID in (%Ls)
            )
            UNION
            (
              SELECT revision.*
              FROM %T revision, %T rel
              WHERE rel.revisionId = revision.Id
                    AND rel.relation = %s
                    AND rel.objectPHID in (%Ls)
            )
          ) as revs
          %Q',
          $rev->getTableName(),
          $this->ids,
          $rev->getTableName(),
          DifferentialRevision::RELATIONSHIP_TABLE,
          DifferentialRevision::RELATION_REVIEWER,
          $this->ids,
          $this->getOrderClause());
        $this->revisions = $rev->loadAllFromArray($data);
        break;
      case self::QUERY_NEED_ACTION_FROM_SELF:
        $rev = new DifferentialRevision();
        $data = queryfx_all(
          $rev->establishConnection('r'),
          'SELECT revision.* FROM %T revision
            WHERE revision.authorPHID in (%Ls)
            AND revision.status in (%Ld)

           UNION ALL

           SELECT revision.* FROM %T revision JOIN %T relationship
            ON relationship.revisionID = revision.id
              AND relationship.relation = %s
            WHERE relationship.objectPHID IN (%Ls)
              AND revision.status in (%Ld)

          %Q',
          $rev->getTableName(),
          $this->ids,
          array(
            ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
            ArcanistDifferentialRevisionStatus::ACCEPTED,
          ),
          $rev->getTableName(),
          DifferentialRevision::RELATIONSHIP_TABLE,
          DifferentialRevision::RELATION_REVIEWER,
          $this->ids,
          array(
            ArcanistDifferentialRevisionStatus::NEEDS_REVIEW,
          ),
          $this->getOrderClause());

        $data = ipull($data, null, 'id');
        $this->revisions = $rev->loadAllFromArray($data);
        break;
      case self::QUERY_NEED_ACTION_FROM_OTHERS:
        $rev = new DifferentialRevision();
        $data = queryfx_all(
          $rev->establishConnection('r'),
          'SELECT revision.* FROM %T revision
            WHERE revision.authorPHID in (%Ls)
            AND revision.status IN (%Ld)

          UNION ALL

          SELECT revision.* FROM %T revision JOIN %T relationship
           ON relationship.revisionID = revision.id
            AND relationship.relation = %s
          WHERE relationship.objectPHID IN (%Ls)
            AND revision.status in (%Ld)

          %Q',
          $rev->getTableName(),
          $this->ids,
          array(
            ArcanistDifferentialRevisionStatus::NEEDS_REVIEW,
          ),
          $rev->getTableName(),
          DifferentialRevision::RELATIONSHIP_TABLE,
          DifferentialRevision::RELATION_REVIEWER,
          $this->ids,
          array(
            ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
            ArcanistDifferentialRevisionStatus::ACCEPTED,
          ),
          $this->getOrderClause());

        $data = ipull($data, null, 'id');

        $this->revisions = $rev->loadAllFromArray($data);
        break;
      case self::QUERY_PHIDS:
        $this->revisions = $this->loadAllWhere(
          'revision.phid in (%Ls)',
          $this->ids);
        break;
    }

    return $this->revisions;
  }

  private function getOpenStatuses() {
    return array(
      ArcanistDifferentialRevisionStatus::NEEDS_REVIEW,
      ArcanistDifferentialRevisionStatus::NEEDS_REVISION,
      ArcanistDifferentialRevisionStatus::ACCEPTED,
    );
  }

  private function loadAllOpen() {
    return $this->loadAllWhere('status in (%Ld)', $this->getOpenStatuses());
  }

  private function loadAllWhereJoinReview($pattern) {
    $reviewer = DifferentialRevision::RELATION_REVIEWER;

    $argv = func_get_args();

    $rev = new DifferentialRevision();

    $pattern = array_shift($argv);
    $pattern =
      'SELECT revision.*
        FROM %T revision LEFT JOIN %T relationship
        ON revision.id = relationship.revisionID
        AND relationship.relation = %s
        WHERE '.$pattern.'
        GROUP BY revision.id '.$this->getOrderClause();

    array_unshift(
      $argv,
      $rev->getTableName(),
      DifferentialRevision::RELATIONSHIP_TABLE,
      DifferentialRevision::RELATION_REVIEWER);

    $data = vqueryfx_all(
      $rev->establishConnection('r'),
      $pattern,
      $argv);

    return $rev->loadAllFromArray($data);
  }

  private function loadAllWhere($pattern) {
    $rev = new DifferentialRevision();

    $argv = func_get_args();
    array_shift($argv);
    array_unshift($argv, $rev->getTableName());

    $data = vqueryfx_all(
      $rev->establishConnection('r'),
      'SELECT * FROM %T revision WHERE '.$pattern.' '.$this->getOrderClause(),
      $argv);

    return $rev->loadAllFromArray($data);
  }

  private function loadAllOpenWithCCs(array $ccphids) {
    $rev = new DifferentialRevision();

    $revision = new DifferentialRevision();
    $data = queryfx_all(
      $rev->establishConnection('r'),
      'SELECT revision.* FROM %T revision
        JOIN %T relationship ON relationship.revisionID = revision.id
          AND relationship.relation = %s
          AND relationship.objectPHID in (%Ls)
        WHERE revision.status in (%Ld) %Q',
      $revision->getTableName(),
      DifferentialRevision::RELATIONSHIP_TABLE,
      DifferentialRevision::RELATION_SUBSCRIBED,
      $ccphids,
      $this->getOpenStatuses(),
      $this->getOrderClause());
    return $revision->loadAllFromArray($data);
  }

  private function getOrderClause() {
    $reverse = false;
    $order = $this->order;

    if (strlen($order) && $order[0] == '-') {
      $reverse = true;
      $order = substr($order, 1);
    }

    $asc = $reverse ? 'DESC' : 'ASC';

    switch ($order) {
      case 'ID':
        $clause = 'id';
        break;
      case 'Revision':
        $clause = 'name';
        break;
      case 'Status':
        $clause = 'status';
        break;
      case 'Lines':
        $clause = 'lineCount';
        break;
      case 'Created':
        $clause = 'dateCreated';
        $asc = $reverse ? 'ASC' : 'DESC';
        break;
      case '':
      case 'Modified':
        $clause = 'dateModified';
        $asc = $reverse ? 'ASC' : 'DESC';
        break;
      default:
        throw new Exception("Invalid order '{$order}'.");
    }

    return "ORDER BY {$clause} {$asc}";
  }

}
