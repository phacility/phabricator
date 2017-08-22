<?php

final class DifferentialGetRevisionConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.getrevision';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht("Replaced by '%s'.", 'differential.query');
  }

  public function getMethodDescription() {
    return pht('Load the content of a revision from Differential.');
  }

  protected function defineParamTypes() {
    return array(
      'revision_id' => 'required id',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION' => pht('No such revision exists.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = null;

    $revision_id = $request->getValue('revision_id');
    $revision = id(new DifferentialRevisionQuery())
      ->withIDs(array($revision_id))
      ->setViewer($request->getUser())
      ->needReviewers(true)
      ->executeOne();

    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    $reviewer_phids = $revision->getReviewerPHIDs();

    $diffs = id(new DifferentialDiffQuery())
      ->setViewer($request->getUser())
      ->withRevisionIDs(array($revision_id))
      ->needChangesets(true)
      ->execute();
    $diff_dicts = mpull($diffs, 'getDiffDict');

    $commit_dicts = array();
    $commit_phids = $revision->loadCommitPHIDs();
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($request->getUser())
      ->withPHIDs($commit_phids)
      ->execute();

    foreach ($commit_phids as $commit_phid) {
      $commit_dicts[] = array(
        'fullname'      => $handles[$commit_phid]->getFullName(),
        'dateCommitted' => $handles[$commit_phid]->getTimestamp(),
      );
    }

    $field_data = $this->loadCustomFieldsForRevisions(
      $request->getUser(),
      array($revision));

    $dict = array(
      'id' => $revision->getID(),
      'phid' => $revision->getPHID(),
      'authorPHID' => $revision->getAuthorPHID(),
      'uri' => PhabricatorEnv::getURI('/D'.$revision->getID()),
      'title' => $revision->getTitle(),
      'status' => $revision->getLegacyRevisionStatus(),
      'statusName'  => $revision->getStatusDisplayName(),
      'summary' => $revision->getSummary(),
      'testPlan' => $revision->getTestPlan(),
      'lineCount' => $revision->getLineCount(),
      'reviewerPHIDs' => $reviewer_phids,
      'diffs' => $diff_dicts,
      'commits' => $commit_dicts,
      'auxiliary' => idx($field_data, $revision->getPHID(), array()),
    );

    return $dict;
  }

}
