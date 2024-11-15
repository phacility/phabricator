<?php

final class PhabricatorRoleBoardColumnsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Workboard Columns');
  }

  public function getAttachmentDescription() {
    return pht('Get the workboard columns where an object appears.');
  }

  public function loadAttachmentData(array $objects, $spec) {
    $viewer = $this->getViewer();

    $objects = mpull($objects, null, 'getPHID');
    $object_phids = array_keys($objects);

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($object_phids)
      ->withEdgeTypes(
        array(
          PhabricatorRoleObjectHasRoleEdgeType::EDGECONST,
        ));
    $edge_query->execute();

    $role_phids = $edge_query->getDestinationPHIDs();

    $engine = id(new PhabricatorRoleBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs($role_phids)
      ->setObjectPHIDs($object_phids)
      ->executeLayout();

    $results = array();
    foreach ($objects as $phid => $object) {
      $board_phids = $edge_query->getDestinationPHIDs(array($phid));

      $boards = array();
      foreach ($board_phids as $board_phid) {
        $columns = array();
        foreach ($engine->getObjectColumns($board_phid, $phid) as $column) {
          if ($column->getProxyPHID()) {
            // When an object is in a proxy column, don't return it on this
            // attachment. This information can be reconstructed from other
            // queries, is something of an implementation detail, and seems
            // unlikely to be interesting to API consumers.
            continue 2;
          }

          $columns[] = $column->getRefForConduit();
        }

        // If a role has no workboard, the object won't appear on any
        // columns. Just omit it from the result set.
        if (!$columns) {
          continue;
        }

        $boards[$board_phid] = array(
          'columns' => $columns,
        );
      }

      $results[$phid] = $boards;
    }

    return $results;
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $boards = idx($data, $object->getPHID(), array());

    return array(
      'boards' => $boards,
    );
  }

}
