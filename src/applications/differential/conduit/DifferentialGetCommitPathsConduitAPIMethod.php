<?php

final class DifferentialGetCommitPathsConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.getcommitpaths';
  }

  public function getMethodDescription() {
    return pht(
      'Query which paths should be included when committing a '.
      'Differential revision.');
  }

  protected function defineParamTypes() {
    return array(
      'revision_id' => 'required int',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<string>';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => pht('No such revision exists.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('revision_id');

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($id))
      ->executeOne();
    if (!$revision) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    $paths = array();
    $diff = id(new DifferentialDiff())->loadOneWhere(
      'revisionID = %d ORDER BY id DESC limit 1',
      $revision->getID());

    $diff->attachChangesets($diff->loadChangesets());

    foreach ($diff->getChangesets() as $changeset) {
      $paths[] = $changeset->getFilename();
    }

    return $paths;
  }

}
