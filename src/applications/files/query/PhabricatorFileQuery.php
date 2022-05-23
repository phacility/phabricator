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
  private $isDeleted;
  private $needTransforms;
  private $builtinKeys;
  private $isBuiltin;
  private $storageEngines;
  private $attachedObjectPHIDs;

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

  public function withBuiltinKeys(array $keys) {
    $this->builtinKeys = $keys;
    return $this;
  }

  public function withIsBuiltin($is_builtin) {
    $this->isBuiltin = $is_builtin;
    return $this;
  }

  public function withAttachedObjectPHIDs(array $phids) {
    $this->attachedObjectPHIDs = $phids;
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

  public function withIsDeleted($deleted) {
    $this->isDeleted = $deleted;
    return $this;
  }

  public function withNameNgrams($ngrams) {
    return $this->withNgramsConstraint(
      id(new PhabricatorFileNameNgrams()),
      $ngrams);
  }

  public function withStorageEngines(array $engines) {
    $this->storageEngines = $engines;
    return $this;
  }

  public function showOnlyExplicitUploads($explicit_uploads) {
    $this->explicitUploads = $explicit_uploads;
    return $this;
  }

  public function needTransforms(array $transforms) {
    $this->needTransforms = $transforms;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorFile();
  }

  protected function loadPage() {
    $files = $this->loadStandardPage($this->newResultObject());

    if (!$files) {
      return $files;
    }

    // Figure out which files we need to load attached objects for. In most
    // cases, we need to load attached objects to perform policy checks for
    // files.

    // However, in some special cases where we know files will always be
    // visible, we skip this. See T8478 and T13106.
    $need_objects = array();
    $need_xforms = array();
    foreach ($files as $file) {
      $always_visible = false;

      if ($file->getIsProfileImage()) {
        $always_visible = true;
      }

      if ($file->isBuiltin()) {
        $always_visible = true;
      }

      if ($always_visible) {
        // We just treat these files as though they aren't attached to
        // anything. This saves a query in common cases when we're loading
        // profile images or builtins. We could be slightly more nuanced
        // about this and distinguish between "not attached to anything" and
        // "might be attached but policy checks don't need to care".
        $file->attachObjectPHIDs(array());
        continue;
      }

      $need_objects[] = $file;
      $need_xforms[] = $file;
    }

    $viewer = $this->getViewer();
    $is_omnipotent = $viewer->isOmnipotent();

    // If we have any files left which do need objects, load the edges now.
    $object_phids = array();
    if ($need_objects) {
      $attachments_map = $this->newAttachmentsMap($need_objects);

      foreach ($need_objects as $file) {
        $file_phid = $file->getPHID();
        $phids = $attachments_map[$file_phid];

        $file->attachObjectPHIDs($phids);

        if ($is_omnipotent) {
          // If the viewer is omnipotent, we don't need to load the associated
          // objects either since the viewer can certainly see the object.
          // Skipping this can improve performance and prevent cycles. This
          // could possibly become part of the profile/builtin code above which
          // short circuits attacment policy checks in cases where we know them
          // to be unnecessary.
          continue;
        }

        foreach ($phids as $phid) {
          $object_phids[$phid] = true;
        }
      }
    }

    // If this file is a transform of another file, load that file too. If you
    // can see the original file, you can see the thumbnail.

    // TODO: It might be nice to put this directly on PhabricatorFile and
    // remove the PhabricatorTransformedFile table, which would be a little
    // simpler.

    if ($need_xforms) {
      $xforms = id(new PhabricatorTransformedFile())->loadAllWhere(
        'transformedPHID IN (%Ls)',
        mpull($need_xforms, 'getPHID'));
      $xform_phids = mpull($xforms, 'getOriginalPHID', 'getTransformedPHID');
      foreach ($xform_phids as $derived_phid => $original_phid) {
        $object_phids[$original_phid] = true;
      }
    } else {
      $xform_phids = array();
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

  private function newAttachmentsMap(array $files) {
    $file_phids = mpull($files, 'getPHID');

    $attachments_table = new PhabricatorFileAttachment();
    $attachments_conn = $attachments_table->establishConnection('r');

    $attachments = queryfx_all(
      $attachments_conn,
      'SELECT filePHID, objectPHID FROM %R WHERE filePHID IN (%Ls)
        AND attachmentMode IN (%Ls)',
      $attachments_table,
      $file_phids,
      array(
        PhabricatorFileAttachment::MODE_ATTACH,
      ));

    $attachments_map = array_fill_keys($file_phids, array());
    foreach ($attachments as $row) {
      $file_phid = $row['filePHID'];
      $object_phid = $row['objectPHID'];
      $attachments_map[$file_phid][] = $object_phid;
    }

    return $attachments_map;
  }

  protected function didFilterPage(array $files) {
    $xform_keys = $this->needTransforms;
    if ($xform_keys !== null) {
      $xforms = id(new PhabricatorTransformedFile())->loadAllWhere(
        'originalPHID IN (%Ls) AND transform IN (%Ls)',
        mpull($files, 'getPHID'),
        $xform_keys);

      if ($xforms) {
        $xfiles = id(new PhabricatorFile())->loadAllWhere(
          'phid IN (%Ls)',
          mpull($xforms, 'getTransformedPHID'));
        $xfiles = mpull($xfiles, null, 'getPHID');
      }

      $xform_map = array();
      foreach ($xforms as $xform) {
        $xfile = idx($xfiles, $xform->getTransformedPHID());
        if (!$xfile) {
          continue;
        }
        $original_phid = $xform->getOriginalPHID();
        $xform_key = $xform->getTransform();
        $xform_map[$original_phid][$xform_key] = $xfile;
      }

      $default_xforms = array_fill_keys($xform_keys, null);

      foreach ($files as $file) {
        $file_xforms = idx($xform_map, $file->getPHID(), array());
        $file_xforms += $default_xforms;
        $file->attachTransforms($file_xforms);
      }
    }

    return $files;
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->transforms) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T t ON t.transformedPHID = f.phid',
        id(new PhabricatorTransformedFile())->getTableName());
    }

    if ($this->shouldJoinAttachmentsTable()) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %R attachments ON attachments.filePHID = f.phid
          AND attachmentMode IN (%Ls)',
        new PhabricatorFileAttachment(),
        array(
          PhabricatorFileAttachment::MODE_ATTACH,
        ));
    }

    return $joins;
  }

  private function shouldJoinAttachmentsTable() {
    return ($this->attachedObjectPHIDs !== null);
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'f.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'f.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'f.authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->explicitUploads !== null) {
      $where[] = qsprintf(
        $conn,
        'f.isExplicitUpload = %d',
        (int)$this->explicitUploads);
    }

    if ($this->transforms !== null) {
      $clauses = array();
      foreach ($this->transforms as $transform) {
        if ($transform['transform'] === true) {
          $clauses[] = qsprintf(
            $conn,
            '(t.originalPHID = %s)',
            $transform['originalPHID']);
        } else {
          $clauses[] = qsprintf(
            $conn,
            '(t.originalPHID = %s AND t.transform = %s)',
            $transform['originalPHID'],
            $transform['transform']);
        }
      }
      $where[] = qsprintf($conn, '%LO', $clauses);
    }

    if ($this->dateCreatedAfter !== null) {
      $where[] = qsprintf(
        $conn,
        'f.dateCreated >= %d',
        $this->dateCreatedAfter);
    }

    if ($this->dateCreatedBefore !== null) {
      $where[] = qsprintf(
        $conn,
        'f.dateCreated <= %d',
        $this->dateCreatedBefore);
    }

    if ($this->contentHashes !== null) {
      $where[] = qsprintf(
        $conn,
        'f.contentHash IN (%Ls)',
        $this->contentHashes);
    }

    if ($this->minLength !== null) {
      $where[] = qsprintf(
        $conn,
        'byteSize >= %d',
        $this->minLength);
    }

    if ($this->maxLength !== null) {
      $where[] = qsprintf(
        $conn,
        'byteSize <= %d',
        $this->maxLength);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'name in (%Ls)',
        $this->names);
    }

    if ($this->isPartial !== null) {
      $where[] = qsprintf(
        $conn,
        'isPartial = %d',
        (int)$this->isPartial);
    }

    if ($this->isDeleted !== null) {
      $where[] = qsprintf(
        $conn,
        'isDeleted = %d',
        (int)$this->isDeleted);
    }

    if ($this->builtinKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'builtinKey IN (%Ls)',
        $this->builtinKeys);
    }

    if ($this->isBuiltin !== null) {
      if ($this->isBuiltin) {
        $where[] = qsprintf(
          $conn,
          'builtinKey IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn,
          'builtinKey IS NULL');
      }
    }

    if ($this->storageEngines !== null) {
      $where[] = qsprintf(
        $conn,
        'storageEngine IN (%Ls)',
        $this->storageEngines);
    }

    if ($this->attachedObjectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'attachments.objectPHID IN (%Ls)',
        $this->attachedObjectPHIDs);
    }

    return $where;
  }

  protected function getPrimaryTableAlias() {
    return 'f';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorFilesApplication';
  }

}
