<?php

final class DifferentialQueryDiffsConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.querydiffs';
  }

  public function getMethodDescription() {
    return pht('Query differential diffs which match certain criteria.');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'optional list<uint>',
      'revisionIDs' => 'optional list<uint>',
    );
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $ids = $request->getValue('ids', array());
    $revision_ids = $request->getValue('revisionIDs', array());

    if (!$ids && !$revision_ids) {
      // This method just returns nothing if you pass no constraints because
      // pagination hadn't been invented yet in 2008 when this method was
      // written.
      return array();
    }

    $query = id(new DifferentialDiffQuery())
      ->setViewer($request->getUser())
      ->needChangesets(true);

    if ($ids) {
      $query->withIDs($ids);
    }

    if ($revision_ids) {
      $query->withRevisionIDs($revision_ids);
    }

    $diffs = $query->execute();

    return mpull($diffs, 'getDiffDict', 'getID');
  }

}
