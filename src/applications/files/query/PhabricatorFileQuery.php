<?php

final class PhabricatorFileQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $explicitUploads;
  private $transforms;
  private $dateCreatedAfter;
  private $dateCreatedBefore;
  private $contentHashes;
  private $minLength;
  private $maxLength;
  private $names;
  private $isPartial;

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

  public function withDateCreatedBefore($date_created_before) {
    $this->dateCreatedBefore = $date_created_before;
    return $this;
  }

  public function withDateCreatedAfter($date_created_after) {
    $this->dateCreatedAfter = $date_created_after;
    return $this;
  }

  public function withContentHashes(array $content_hashes) {
    $this->contentHashes = $content_hashes;
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
          pht(
            "Transform specification must be a dictionary with keys ".
            "'%s' and '%s'!",
            'originalPHID',
            'transform'));
      }
    }

    $this->transforms = $specs;
    return $this;
  }

  public function withLengthBetween($min, $max) {
    $this->minLength = $min;
    $this->maxLength = $max;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withIsPartial($partial) {
    $this->isPartial = $partial;
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

    $files = $table->loadAllFromArray($data);

    if (!$files) {
      return $files;
    }

    // We need to load attached objects to perform policy checks for files.
    // First, load the edges.

    $edge_type = PhabricatorFileHasObjectEdgeType::EDGECONST;
    $file_phids = mpull($files, 'getPHID');
    $edges = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($file_phids)
      ->withEdgeTypes(array($edge_type))
      ->execute();

    $object_phids = array();
    foreach ($files as $file) {
      $phids = array_keys($edges[$file->getPHID()][$edge_type]);
      $file->attachObjectPHIDs($phids);
      foreach ($phids as $phid) {
        $object_phids[$phid] = true;
      }
    }

    // If this file is a transform of another file, load that file too. If you
    // can see the original file, you can see the thumbnail.

    // TODO: It might be nice to put this directly on PhabricatorFile and remove
    // the PhabricatorTransformedFile table, which would be a little simpler.

    $xforms = id(new PhabricatorTransformedFile())->loadAllWhere(
      'transformedPHID IN (%Ls)',
      $file_phids);
    $xform_phids = mpull($xforms, 'getOriginalPHID', 'getTransformedPHID');
    foreach ($xform_phids as $derived_phid => $original_phid) {
      $object_phids[$original_phid] = true;
    }

    $object_phids = array_keys($object_phids);

    // Now, load the objects.

    $objects = array();
    if ($object_phids) {
      // NOTE: We're explicitly turning policy exceptions off, since the rule
      // here is "you can see the file if you can see ANY associated object".
      // Without this explicit flag, we'll incorrectly throw unless you can
      // see ALL associated objects.

      $objects = id(new PhabricatorObjectQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($object_phids)
        ->setRaisePolicyExceptions(false)
        ->execute();
      $objects = mpull($objects, null, 'getPHID');
    }

    foreach ($files as $file) {
      $file_objects = array_select_keys($objects, $file->getObjectPHIDs());
      $file->attachObjects($file_objects);
    }

    foreach ($files as $key => $file) {
      $original_phid = idx($xform_phids, $file->getPHID());
      if ($original_phid == PhabricatorPHIDConstants::PHID_VOID) {
        // This is a special case for builtin files, which are handled
        // oddly.
        $original = null;
      } else if ($original_phid) {
        $original = idx($objects, $original_phid);
        if (!$original) {
          // If the viewer can't see the original file, also prevent them from
          // seeing the transformed file.
          $this->didRejectResult($file);
          unset($files[$key]);
          continue;
        }
      } else {
        $original = null;
      }
      $file->attachOriginalFile($original);
    }

    return $files;
  }

  protected function buildJoinClause(AphrontDatabaseConnection $conn_r) {
    $joins = array();

    if ($this->transforms) {
      $joins[] = qsprintf(
        $conn_r,
        'JOIN %T t ON t.transformedPHID = f.phid',
        id(new PhabricatorTransformedFile())->getTableName());
    }

    return implode(' ', $joins);
  }

  protected function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    $where[] = $this->buildPagingClause($conn_r);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'f.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn_r,
        'f.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn_r,
        'f.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->explicitUploads !== null) {
      $where[] = qsprintf(
        $conn_r,
        'f.isExplicitUpload = true');
    }

    if ($this->transforms !== null) {
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

    if ($this->dateCreatedAfter !== null) {
      $where[] = qsprintf(
        $conn_r,
        'f.dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore !== null) {
      $where[] = qsprintf(
        $conn_r,
        'f.dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    if ($this->contentHashes !== null) {
      $where[] = qsprintf(
        $conn_r,
        'f.contentHash IN (%Ls)',
        $this->contentHashes);
    }

    if ($this->minLength !== null) {
      $where[] = qsprintf(
        $conn_r,
        'byteSize >= %d',
        $this->minLength);
    }

    if ($this->maxLength !== null) {
      $where[] = qsprintf(
        $conn_r,
        'byteSize <= %d',
        $this->maxLength);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn_r,
        'name in (%Ls)',
        $this->names);
    }

    if ($this->isPartial !== null) {
      $where[] = qsprintf(
        $conn_r,
        'isPartial = %d',
        (int)$this->isPartial);
    }

    return $this->formatWhereClause($where);
  }

  protected function getPrimaryTableAlias() {
    return 'f';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFilesApplication';
  }

}
