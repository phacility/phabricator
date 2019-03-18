<?php

/**
 * This is a more formal version of @{class:PhabricatorEdgeQuery} that is used
 * to expose edges to Conduit.
 */
final class PhabricatorEdgeObjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $sourcePHIDs;
  private $sourcePHIDType;
  private $edgeTypes;
  private $destinationPHIDs;

  public function withSourcePHIDs(array $source_phids) {
    $this->sourcePHIDs = $source_phids;
    return $this;
  }

  public function withEdgeTypes(array $types) {
    $this->edgeTypes = $types;
    return $this;
  }

  public function withDestinationPHIDs(array $destination_phids) {
    $this->destinationPHIDs = $destination_phids;
    return $this;
  }

  protected function willExecute() {
    $source_phids = $this->sourcePHIDs;

    if (!$source_phids) {
      throw new Exception(
        pht(
          'Edge object query must be executed with a nonempty list of '.
          'source PHIDs.'));
    }

    $phid_item = null;
    $phid_type = null;
    foreach ($source_phids as $phid) {
      $this_type = phid_get_type($phid);
      if ($this_type == PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
        throw new Exception(
          pht(
            'Source PHID "%s" in edge object query has unknown PHID type.',
            $phid));
      }

      if ($phid_type === null) {
        $phid_type = $this_type;
        $phid_item = $phid;
        continue;
      }

      if ($phid_type !== $this_type) {
        throw new Exception(
          pht(
            'Two source PHIDs ("%s" and "%s") have different PHID types '.
            '("%s" and "%s"). All PHIDs must be of the same type to execute '.
            'an edge object query.',
            $phid_item,
            $phid,
            $phid_type,
            $this_type));
      }
    }

    $this->sourcePHIDType = $phid_type;
  }

  protected function loadPage() {
    $type = $this->sourcePHIDType;
    $conn = PhabricatorEdgeConfig::establishConnection($type, 'r');
    $table = PhabricatorEdgeConfig::TABLE_NAME_EDGE;
    $rows = $this->loadStandardPageRowsWithConnection($conn, $table);

    $result = array();
    foreach ($rows as $row) {
      $result[] = PhabricatorEdgeObject::newFromRow($row);
    }

    return $result;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $parts = parent::buildWhereClauseParts($conn);

    $parts[] = qsprintf(
      $conn,
      'src IN (%Ls)',
      $this->sourcePHIDs);

    $parts[] = qsprintf(
      $conn,
      'type IN (%Ls)',
      $this->edgeTypes);

    if ($this->destinationPHIDs !== null) {
      $parts[] = qsprintf(
        $conn,
        'dst IN (%Ls)',
        $this->destinationPHIDs);
    }

    return $parts;
  }

  public function getQueryApplicationClass() {
    return null;
  }

  protected function getPrimaryTableAlias() {
    return 'edge';
  }

  public function getOrderableColumns() {
    return array(
      'dateCreated' => array(
        'table' => 'edge',
        'column' => 'dateCreated',
        'type' => 'int',
      ),
      'sequence' => array(
        'table' => 'edge',
        'column' => 'seq',
        'type' => 'int',

        // TODO: This is not actually unique, but we're just doing our best
        // here.
        'unique' => true,
      ),
    );
  }

  protected function getDefaultOrderVector() {
    return array('dateCreated', 'sequence');
  }

  protected function newInternalCursorFromExternalCursor($cursor) {
    list($epoch, $sequence) = $this->parseCursor($cursor);

    // Instead of actually loading an edge, we're just making a fake edge
    // with the properties the cursor describes.

    $edge_object = PhabricatorEdgeObject::newFromRow(
      array(
        'dateCreated' => $epoch,
        'seq' => $sequence,
      ));

    return id(new PhabricatorQueryCursor())
      ->setObject($edge_object);
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'dateCreated' => $object->getDateCreated(),
      'sequence' => $object->getSequence(),
    );
  }

  protected function newExternalCursorStringForResult($object) {
    return sprintf(
      '%d_%d',
      $object->getDateCreated(),
      $object->getSequence());
  }

  private function parseCursor($cursor) {
    if (!preg_match('/^\d+_\d+\z/', $cursor)) {
      $this->throwCursorException(
        pht(
          'Expected edge cursor in the form "0123_6789", got "%s".',
          $cursor));
    }

    return explode('_', $cursor);
  }

}
