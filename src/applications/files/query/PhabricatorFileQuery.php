<?php

final class PhabricatorFileQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $explicitUploads;
  private $transforms;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $phids) {
    $this->authorPHIDs = $phids;
    return $this;
  }

  /**
   * Select files which are transformations of some other file. For example,
   * you can use this query to find previously generated thumbnails of an image
   * file.
   *
   * As a parameter, provide a list of transformation specifications. Each
   * specification is a dictionary with the keys `originalPHID` and `transform`.
   * The `originalPHID` is the PHID of the original file (the file which was
   * transformed) and the `transform` is the name of the transform to query
   * for. If you pass `true` as the `transform`, all transformations of the
   * file will be selected.
   *
   * For example:
   *
   *   array(
   *     array(
   *       'originalPHID' => 'PHID-FILE-aaaa',
   *       'transform'    => 'sepia',
   *     ),
   *     array(
   *       'originalPHID' => 'PHID-FILE-bbbb',
   *       'transform'    => true,
   *     ),
   *   )
   *
   * This selects the `"sepia"` transformation of the file with PHID
   * `PHID-FILE-aaaa` and all transformations of the file with PHID
   * `PHID-FILE-bbbb`.
   *
   * @param list<dict>  List of transform specifications, described above.
   * @return this
   */
  public function withTransforms(array $specs) {
    foreach ($specs as $spec) {
      if (!is_array($spec) ||
          empty($spec['originalPHID']) ||
          empty($spec['transform'])) {
        throw new Exception(
          "Transform specification must be a dictionary with keys ".
          "'originalPHID' and 'transform'!");
      }
    }

    $this->transforms = $specs;
    return $this;
  }

  public function showOnlyExplicitUploads($explicit_uploads) {
    $this->explicitUploads = $explicit_uploads;
    return $this;
  }

  protected function loadPage() {
    $table = new PhabricatorFile();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT f.* FROM %T f %Q %Q %Q %Q',
      $table->getTableName(),
      $this->buildJoinClause($conn_r),
      $this->buildWhereClause($conn_r),
      $this->buildOrderClause($conn_r),
      $this->buildLimitClause($conn_r));

    return $table->loadAllFromArray($data);
  }

  private function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->transforms) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T t ON t.transformedPHID = f.phid',
        id(new PhabricatorTransformedFile())->getTableName());
    }

    return implode(' ', $joins);
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids) {
      $where[] = qsprintf(
        $conn_r,
        'f.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids) {
      $where[] = qsprintf(
        $conn_r,
        'f.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs) {
      $where[] = qsprintf(
        $conn_r,
        'f.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->explicitUploads) {
      $where[] = qsprintf(
        $conn_r,
        'f.isExplicitUpload = true');
    }

    if ($this->transforms) {
      $clauses = array();
      foreach ($this->transforms as $transform) {
        if ($transform['transform'] === true) {
          $clauses[] = qsprintf(
            $conn_r,
            '(t.originalPHID = %s)',
            $transform['originalPHID']);
        } else {
          $clauses[] = qsprintf(
            $conn_r,
            '(t.originalPHID = %s AND t.transform = %s)',
            $transform['originalPHID'],
            $transform['transform']);
        }
      }
      $where[] = qsprintf($conn_r, '(%Q)', implode(') OR (', $clauses));
    }

    return $this->formatWhereClause($where);
  }

  protected function getPagingColumn() {
    return 'f.id';
  }

}
