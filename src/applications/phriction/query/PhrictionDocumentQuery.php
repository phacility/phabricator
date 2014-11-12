<?php

final class PhrictionDocumentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $slugs;
  private $depths;
  private $slugPrefix;
  private $statuses;

  private $needContent;

  private $status       = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_NONSTUB  = 'status-nonstub';

  private $order        = 'order-created';
  const ORDER_CREATED   = 'order-created';
  const ORDER_UPDATED   = 'order-updated';
  const ORDER_HIERARCHY = 'order-hierarchy';

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withSlugs(array $slugs) {
    $this->slugs = $slugs;
    return $this;
  }

  public function withDepths(array $depths) {
    $this->depths = $depths;
    return $this;
  }

  public function  withSlugPrefix($slug_prefix) {
    $this->slugPrefix = $slug_prefix;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function needContent($need_content) {
    $this->needContent = $need_content;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  protected function loadPage() {
    $table = new PhrictionDocument();
    $conn_r = $table->establishConnection('r');

    if ($this->order == self::ORDER_HIERARCHY) {
      $order_clause = $this->buildHierarchicalOrderClause($conn_r);
    } else {
      $order_clause = $this->buildOrderClause($conn_r);
    }

    $rows = queryfx_all(
      $conn_r,
      'SELECT d.* FROM %T d %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $order_clause,
      $this->buildLimitClause($conn_r));

    $documents = $table->loadAllFromArray($rows);

    if ($documents) {
      $ancestor_slugs = array();
      foreach ($documents as $key => $document) {
        $document_slug = $document->getSlug();
        foreach (PhabricatorSlug::getAncestry($document_slug) as $ancestor) {
          $ancestor_slugs[$ancestor][] = $key;
        }
      }

      if ($ancestor_slugs) {
        $ancestors = queryfx_all(
          $conn_r,
          'SELECT * FROM %T WHERE slug IN (%Ls)',
          $document->getTableName(),
          array_keys($ancestor_slugs));
        $ancestors = $table->loadAllFromArray($ancestors);
        $ancestors = mpull($ancestors, null, 'getSlug');

        foreach ($ancestor_slugs as $ancestor_slug => $document_keys) {
          $ancestor = idx($ancestors, $ancestor_slug);
          foreach ($document_keys as $document_key) {
            $documents[$document_key]->attachAncestor(
              $ancestor_slug,
              $ancestor);
          }
        }
      }
    }

    return $documents;
  }

  protected function willFilterPage(array $documents) {
    // To view a Phriction document, you must also be able to view all of the
    // ancestor documents. Filter out documents which have ancestors that are
    // not visible.

    $document_map = array();
    foreach ($documents as $document) {
      $document_map[$document->getSlug()] = $document;
      foreach ($document->getAncestors() as $key => $ancestor) {
        if ($ancestor) {
          $document_map[$key] = $ancestor;
        }
      }
    }

    $filtered_map = $this->applyPolicyFilter(
      $document_map,
      array(PhabricatorPolicyCapability::CAN_VIEW));

    // Filter all of the documents where a parent is not visible.
    foreach ($documents as $document_key => $document) {
      // If the document itself is not visible, filter it.
      if (!isset($filtered_map[$document->getSlug()])) {
        $this->didRejectResult($documents[$document_key]);
        unset($documents[$document_key]);
        continue;
      }

      // If an ancestor exists but is not visible, filter the document.
      foreach ($document->getAncestors() as $ancestor_key => $ancestor) {
        if (!$ancestor) {
          continue;
        }

        if (!isset($filtered_map[$ancestor_key])) {
          $this->didRejectResult($documents[$document_key]);
          unset($documents[$document_key]);
          break;
        }
      }
    }

    if (!$documents) {
      return $documents;
    }

    if ($this->needContent) {
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
    }

    return $documents;
  }

  private function buildJoinClause(AphrontDatabaseConnection $conn) {
    $join = '';
    if ($this->order == self::ORDER_HIERARCHY) {
      $content_dao = new PhrictionContent();
      $join = qsprintf(
        $conn,
        'JOIN %T c ON d.contentID = c.id',
        $content_dao->getTableName());
    }
    return $join;
  }
  protected function buildWhereClause(AphrontDatabaseConnection $conn) {
    $where = array();

    if ($this->ids) {
      $where[] = qsprintf(
        $conn,
        'd.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn,
        'd.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->slugs) {
      $where[] = qsprintf(
        $conn,
        'd.slug IN (%Ls)',
        $this->slugs);
    }

    if ($this->statuses) {
      $where[] = qsprintf(
        $conn,
        'd.status IN (%Ld)',
        $this->statuses);
    }

    if ($this->slugPrefix) {
      $where[] = qsprintf(
        $conn,
        'd.slug LIKE %>',
        $this->slugPrefix);
    }

    if ($this->depths) {
      $where[] = qsprintf(
        $conn,
        'd.depth IN (%Ld)',
        $this->depths);
    }

    switch ($this->status) {
      case self::STATUS_OPEN:
        $where[] = qsprintf(
          $conn,
          'd.status NOT IN (%Ld)',
          array(
            PhrictionDocumentStatus::STATUS_DELETED,
            PhrictionDocumentStatus::STATUS_MOVED,
            PhrictionDocumentStatus::STATUS_STUB,
          ));
        break;
      case self::STATUS_NONSTUB:
        $where[] = qsprintf(
          $conn,
          'd.status NOT IN (%Ld)',
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

  private function buildHierarchicalOrderClause(
    AphrontDatabaseConnection $conn_r) {

    if ($this->getBeforeID()) {
      return qsprintf(
        $conn_r,
        'ORDER BY d.depth, c.title, %Q %Q',
        $this->getPagingColumn(),
        $this->getReversePaging() ? 'DESC' : 'ASC');
    } else {
      return qsprintf(
        $conn_r,
        'ORDER BY d.depth, c.title, %Q %Q',
        $this->getPagingColumn(),
        $this->getReversePaging() ? 'ASC' : 'DESC');
    }
  }

  protected function getPagingColumn() {
    switch ($this->order) {
      case self::ORDER_CREATED:
      case self::ORDER_HIERARCHY:
        return 'd.id';
      case self::ORDER_UPDATED:
        return 'd.contentID';
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

  protected function getPagingValue($result) {
    switch ($this->order) {
      case self::ORDER_CREATED:
      case self::ORDER_HIERARCHY:
        return $result->getID();
      case self::ORDER_UPDATED:
        return $result->getContentID();
      default:
        throw new Exception("Unknown order '{$this->order}'!");
    }
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhrictionApplication';
  }

}
