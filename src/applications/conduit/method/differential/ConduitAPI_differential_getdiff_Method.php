<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_getdiff_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Load the content of a diff from Differential.";
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
      'ERR_BAD_REVISION'    => 'No such revision exists.',
      'ERR_BAD_DIFF'        => 'No such diff exists.',
    );
  }

  public function shouldRequireAuthentication() {
    return !PhabricatorEnv::getEnvConfig('differential.anonymous-access');
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = null;

    $revision_id = $request->getValue('revision_id');
    if ($revision_id) {
      $revision = id(new DifferentialRevision())->load($revision_id);
      if (!$revision) {
        throw new ConduitException('ERR_BAD_REVISION');
      }
      $diff = id(new DifferentialDiff())->loadOneWhere(
        'revisionID = %d ORDER BY id DESC LIMIT 1',
        $revision->getID());
    } else {
      $diff_id = $request->getValue('diff_id');
      if ($diff_id) {
        $diff = id(new DifferentialDiff())->load($diff_id);
      }
    }

    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $diff->attachChangesets(
      $diff->loadRelatives(new DifferentialChangeset(), 'diffID'));
    foreach ($diff->getChangesets() as $changeset) {
      $changeset->attachHunks(
        $changeset->loadRelatives(new DifferentialHunk(), 'changesetID'));
    }

    $basic_dict = $diff->getDiffDict();

    // for conduit calls, the basic dict is not enough
    // we also need to include the arcanist project
    $project = $diff->loadArcanistProject();
    if ($project) {
      $project_name = $project->getName();
    } else {
      $project_name = null;
    }
    $basic_dict['projectName'] = $project_name;

    return $basic_dict;
  }

}
