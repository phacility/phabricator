<?php

final class DifferentialGetAllDiffsConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.getalldiffs';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method has been deprecated in favor of %s.',
      'differential.querydiffs');
  }

  public function getMethodDescription() {
    return pht('Load all diffs for given revisions from Differential.');
  }

  protected function defineParamTypes() {
    return array(
      'revision_ids' => 'required list<int>',
    );
  }

  protected function defineReturnType() {
    return 'dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();
    $revision_ids = $request->getValue('revision_ids');

    if (!$revision_ids) {
      return $results;
    }

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($request->getUser())
      ->withRevisionIDs($revision_ids)
      ->execute();

    foreach ($diffs as $diff) {
      $results[] = array(
        'revision_id' => $diff->getRevisionID(),
        'diff_id' => $diff->getID(),
      );
    }

    return $results;
  }

}
