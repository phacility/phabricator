<?php

final class EdgeSearchConduitAPIMethod
  extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'edge.search';
  }

  public function getMethodDescription() {
    return pht('Read edge relationships between objects.');
  }

  public function getMethodDocumentation() {
    $viewer = $this->getViewer();

    $rows = array();
    foreach ($this->getConduitEdgeTypeMap() as $key => $type) {
      $inverse_constant = $type->getInverseEdgeConstant();
      if ($inverse_constant) {
        $inverse_type = PhabricatorEdgeType::getByConstant($inverse_constant);
        $inverse = $inverse_type->getConduitKey();
      } else {
        $inverse = null;
      }

      $rows[] = array(
        $key,
        $type->getConduitName(),
        $inverse,
        new PHUIRemarkupView($viewer, $type->getConduitDescription()),
      );
    }

    $types_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Constant'),
          pht('Name'),
          pht('Inverse'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          'mono',
          'pri',
          'mono',
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edge Types'))
      ->setTable($types_table);
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('This method is new and experimental.');
  }

  protected function defineParamTypes() {
    return array(
      'sourcePHIDs' => 'list<phid>',
      'types' => 'list<const>',
      'destinationPHIDs' => 'optional list<phid>',
    ) + $this->getPagerParamTypes();
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $pager = $this->newPager($request);

    $source_phids = $request->getValue('sourcePHIDs', array());
    $edge_types = $request->getValue('types', array());
    $destination_phids = $request->getValue('destinationPHIDs', array());

    $object_query = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames($source_phids);

    $object_query->execute();
    $objects = $object_query->getNamedResults();
    foreach ($source_phids as $phid) {
      if (empty($objects[$phid])) {
        throw new Exception(
          pht(
            'Source PHID "%s" does not identify a valid object, or you do '.
            'not have permission to view it.',
            $phid));
      }
    }
    $source_phids = mpull($objects, 'getPHID');

    if (!$edge_types) {
      throw new Exception(
        pht(
          'Edge search must specify a nonempty list of edge types.'));
    }

    $edge_map = $this->getConduitEdgeTypeMap();

    $constant_map = array();
    $edge_constants = array();
    foreach ($edge_types as $edge_type) {
      if (!isset($edge_map[$edge_type])) {
        throw new Exception(
          pht(
            'Edge type "%s" is not a recognized edge type.',
            $edge_type));
      }

      $constant = $edge_map[$edge_type]->getEdgeConstant();

      $edge_constants[] = $constant;
      $constant_map[$constant] = $edge_type;
    }

    $edge_query = id(new PhabricatorEdgeObjectQuery())
      ->setViewer($viewer)
      ->withSourcePHIDs($source_phids)
      ->withEdgeTypes($edge_constants);

    if ($destination_phids) {
      $edge_query->withDestinationPHIDs($destination_phids);
    }

    $edge_objects = $edge_query->executeWithCursorPager($pager);

    $edges = array();
    foreach ($edge_objects as $edge_object) {
      $edges[] = array(
        'sourcePHID' => $edge_object->getSourcePHID(),
        'edgeType' => $constant_map[$edge_object->getEdgeType()],
        'destinationPHID' => $edge_object->getDestinationPHID(),
      );
    }

    $results = array(
      'data' => $edges,
    );

    return $this->addPagerResults($results, $pager);
  }

  private function getConduitEdgeTypeMap() {
    $types = PhabricatorEdgeType::getAllTypes();

    $map = array();
    foreach ($types as $type) {
      $key = $type->getConduitKey();
      if ($key === null) {
        continue;
      }

      $map[$key] = $type;
    }

    ksort($map);

    return $map;
  }
}
