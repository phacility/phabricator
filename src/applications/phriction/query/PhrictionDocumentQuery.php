<?php

/**
 * @group phriction
 */
final class PhrictionDocumentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;

  private $status       = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_NONSTUB  = 'status-nonstub';

  private $order        = 'order-created';
  const ORDER_CREATED   = 'order-created';
  const ORDER_UPDATED   = 'order-updated';

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

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  protected function loadPage() {
    $document = new PhrictionDocument();
    $conn_r = $document->establishConnection('r');

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $document->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $document->loadAllFromArray($rows);
  }

  protected function willFilterPage(array $documents) {
    if (!$documents) {
      return array();
    }

    $contents = id(new PhrictionContent())->loadAllWhere(
      'id IN (%Ld)',
      mpull($documents, 'getContentID'));

    foreach ($documents as $key => $document) {
      $content_id = $document->getContentID();
      if (empty($contents[$content_id])) {
        unset($documents[$key]);
        continue;
      }
      $document->attachContent($contents[$content_id]);
    }

    $project_slugs = array();
    foreach ($documents as $key => $document) {
      $slug = $document->getSlug();
      if (!PhrictionDocument::isProjectSlug($slug)) {
        continue;
      }
      $project_slugs[$key] = PhrictionDocument::getProjectSlugIdentifier($slug);
    }

    if ($project_slugs) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->getViewer())
        ->withPhrictionSlugs($project_slugs)
        ->execute();
      $projects = mpull($projects, null, 'getPhrictionSlug');
      foreach ($documents as $key => $document) {
        $slug = idx($project_slugs, $key);
        if ($slug) {
          $project = idx($projects, $slug);
          if (!$project) {
            unset($documents[$key]);
            continue;
          }
          $document->attachProject($project);
        }
      }
    }

    return $documents;
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->phids);
    }

    switch ($this->status) {
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn,
          'status NOT IN (%Ld)',
          array(
            PhrictionDocumentStatus::STATUS_DELETED,
            PhrictionDocumentStatus::STATUS_MOVED,
            PhrictionDocumentStatus::STATUS_STUB,
          ));
        break;
      case self::STATUS_NONSTUB:
        $where[] = qsprintf(
          $conn,
          'status NOT IN (%Ld)',
          array(
            PhrictionDocumentStatus::STATUS_MOVED,
            PhrictionDocumentStatus::STATUS_STUB,
          ));
        break;
      case self::STATUS_ANY:
        break;
      default:
        throw new Exception("Unknown status '{$this->status}'!");
    }

    $where[] = $this->buildPagingClause($conn);

    return $this->formatWhereClause($where);
  }

  protected function getPagingColumn() {
    switch ($this->order) {
      case self::ORDER_CREATED:
        return 'id';
      case self::ORDER_UPDATED:
        return 'contentID';
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

  protected function getPagingValue($result) {
    switch ($this->order) {
      case self::ORDER_CREATED:
        return $result->getID();
      case self::ORDER_UPDATED:
        return $result->getContentID();
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

}
