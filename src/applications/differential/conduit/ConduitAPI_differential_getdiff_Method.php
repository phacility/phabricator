<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_getdiff_Method
  extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method has been deprecated in favor of differential.querydiffs.');
  }


  public function getMethodDescription() {
    return pht('Load the content of a diff from Differential by revision id '.
               'or diff id.');
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'optional id',
      'diff_id'     => 'optional id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF'        => 'No such diff exists.',
    );
  }

  public function shouldRequireAuthentication() {
    return !PhabricatorEnv::getEnvConfig('differential.anonymous-access');
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = null;
    $revision_ids = array();
    $diff_ids = array();

    $revision_id = $request->getValue('revision_id');
    if ($revision_id) {
      $revision_ids = array($revision_id);
    }
    $diff_id = $request->getValue('diff_id');
    if ($diff_id) {
      $diff_ids = array($diff_id);
    }
    if ($diff_ids || $revision_ids) {
      $diff = id(new DifferentialDiffQuery())
        ->setViewer($request->getUser())
        ->withIDs($diff_ids)
        ->withRevisionIDs($revision_ids)
        ->needChangesets(true)
        ->needArcanistProjects(true)
        ->executeOne();
    }

    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    return $diff->getDiffDict();
  }

}
