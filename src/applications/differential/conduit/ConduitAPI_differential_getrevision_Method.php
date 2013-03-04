<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_getrevision_Method
  extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Replaced by 'differential.query'.";
  }

  public function getMethodDescription() {
    return "Load the content of a revision from Differential.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required id',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION'    => 'No such revision exists.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = null;

    $revision_id = $request->getValue('revision_id');
    $revision = id(new DifferentialRevision())->load($revision_id);
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    $revision->loadRelationships();
    $reviewer_phids = array_values($revision->getReviewers());

    $diffs = $revision->loadDiffs();

    $diff_dicts = array();
    foreach ($diffs as $diff) {
      $diff->attachChangesets($diff->loadChangesets());
      // TODO: We could batch this to improve performance.
      foreach ($diff->getChangesets() as $changeset) {
        $changeset->attachHunks($changeset->loadHunks());
      }
      $diff_dicts[] = $diff->getDiffDict();
    }

    $commit_dicts = array();
    $commit_phids = $revision->loadCommitPHIDs();
    $handles = id(new PhabricatorObjectHandleData($commit_phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    foreach ($commit_phids as $commit_phid) {
      $commit_dicts[] = array(
        'fullname'      => $handles[$commit_phid]->getFullName(),
        'dateCommitted' => $handles[$commit_phid]->getTimestamp(),
      );
    }

    $auxiliary_fields = $this->loadAuxiliaryFields(
      $revision,
      $request->getUser());

    $dict = array(
      'id' => $revision->getID(),
      'phid' => $revision->getPHID(),
      'authorPHID' => $revision->getAuthorPHID(),
      'uri' => PhabricatorEnv::getURI('/D'.$revision->getID()),
      'title' => $revision->getTitle(),
      'status' => $revision->getStatus(),
      'statusName'  =>
        ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
          $revision->getStatus()),
      'summary' => $revision->getSummary(),
      'testPlan' => $revision->getTestPlan(),
      'lineCount' => $revision->getLineCount(),
      'reviewerPHIDs' => $reviewer_phids,
      'diffs' => $diff_dicts,
      'commits' => $commit_dicts,
      'auxiliary' => $auxiliary_fields,
    );

    return $dict;
  }

  private function loadAuxiliaryFields(
    DifferentialRevision $revision,
    PhabricatorUser $user) {
    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();
    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setUser($user);
      if (!$aux_field->shouldAppearOnConduitView()) {
        unset($aux_fields[$key]);
      }
    }

    $aux_fields = DifferentialAuxiliaryField::loadFromStorage(
      $revision,
      $aux_fields);

    return mpull($aux_fields, 'getValueForConduit', 'getKeyForConduit');
  }

}
