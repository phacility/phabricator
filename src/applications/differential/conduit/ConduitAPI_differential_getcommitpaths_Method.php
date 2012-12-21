<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_getcommitpaths_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Query which paths should be included when committing a ".
           "Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required int',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<string>';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'No such revision exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('revision_id');

    $revision = id(new DifferentialRevision())->load($id);
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
