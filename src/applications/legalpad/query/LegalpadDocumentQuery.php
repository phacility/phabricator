<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $creatorPHIDs;
  private $contributorPHIDs; // TODO - T3479
  private $dateCreatedAfter;
  private $dateCreatedBefore;

  private $needDocumentBodies;
  private $needContributors;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withCreatorPHIDs(array $phids) {
    $this->creatorPHIDs = $phids;
    return $this;
  }

  public function withContributorPHIDs(array $phids) {
    $this->contributorPHIDs = $phids;
    return $this;
  }

  public function needDocumentBodies($need_bodies) {
    $this->needDocumentBodies = $need_bodies;
    return $this;
  }

  public function needContributors($need_contributors) {
    $this->needContributors = $need_contributors;
    return $this;
  }

  public function withDateCreatedBefore($date_created_before) {
    $this->dateCreatedBefore = $date_created_before;
    return $this;
  }

  public function withDateCreatedAfter($date_created_after) {
    $this->dateCreatedAfter = $date_created_after;
    return $this;
  }

  protected function loadPage() {
    $table = new LegalpadDocument();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT legalpad_document.* FROM %T legalpad_document %Q %Q %Q',
      $table->getTableName(),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    $documents = $table->loadAllFromArray($data);

    return $documents;
  }

  protected function willFilterPage(array $documents) {
    if (!$documents) {
      return $documents;
    }

    if ($this->needDocumentBodies) {
      $documents = $this->loadDocumentBodies($documents);
    }

    if ($this->needContributors) {
      $documents = $this->loadContributors($documents);
    }

    return $documents;
  }

  protected function buildWhereClause($conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

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

    if ($this->creatorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'creatorPHID IN (%Ls)',
        $this->creatorPHIDs);
    }

    if ($this->dateCreatedAfter) {
      $where[] = qsprintf(
        $conn_r,
        'dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore) {
      $where[] = qsprintf(
        $conn_r,
        'dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    return $this->formatWhereClause($where);
  }

  private function loadDocumentBodies(array $documents) {
    $body_phids = mpull($documents, 'getDocumentBodyPHID');
    $bodies = id(new LegalpadDocumentBody())->loadAllWhere(
      'phid IN (%Ls)',
      $body_phids);
    $bodies = mpull($bodies, null, 'getPHID');

    foreach ($documents as $document) {
      $body = idx($bodies, $document->getDocumentBodyPHID());
      $document->attachDocumentBody($body);
    }

    return $documents;
  }

  private function loadContributors(array $documents) {
    $document_map = mpull($documents, null, 'getPHID');
    $edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_CONTRIBUTOR;
    $contributor_data = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs(array_keys($document_map))
      ->withEdgeTypes(array($edge_type))
      ->execute();

    foreach ($document_map as $document_phid => $document) {
      $data = $contributor_data[$document_phid];
      $contributors = array_keys(idx($data, $edge_type, array()));
      $document->attachContributors($contributors);
    }

    return $documents;
  }


}
