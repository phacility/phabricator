<?php

final class PhrictionDocumentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $slugs;

  private $needContent;

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

  public function withSlugs(array $slugs) {
    $this->slugs = $slugs;
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

    $rows = queryfx_all(
      $conn_r,
      'SELECT * FROM %T %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
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

    foreach ($documents as $document) {
      $document->attachProject(null);
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
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->slugs) {
      $where[] = qsprintf(
        $conn,
        'slug IN (%Ls)',
        $this->slugs);
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


  public function getQueryApplicationClass() {
    return 'PhabricatorApplicationPhriction';
  }

}
