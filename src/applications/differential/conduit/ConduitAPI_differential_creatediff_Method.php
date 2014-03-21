<?php

final class ConduitAPI_differential_creatediff_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Create a new Differential diff.";
  }

  public function defineParamTypes() {
    return array(
      'changes'                   => 'required list<dict>',
      'sourceMachine'             => 'required string',
      'sourcePath'                => 'required string',
      'branch'                    => 'required string',
      'bookmark'                  => 'optional string',
      'sourceControlSystem'       => 'required enum<svn, git, hg>',
      'sourceControlPath'         => 'required string',
      'sourceControlBaseRevision' => 'required string',
      'creationMethod'            => 'optional string',
      'arcanistProject'           => 'optional string',
      'lintStatus'                =>
        'required enum<none, skip, okay, warn, fail, postponed>',
      'unitStatus'                =>
        'required enum<none, skip, okay, warn, fail, postponed>',
      'repositoryPHID'            => 'optional phid',

      'parentRevisionID'          => 'deprecated',
      'authorPHID'                => 'deprecated',
      'repositoryUUID'            => 'deprecated',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $change_data = $request->getValue('changes');

    $changes = array();
    foreach ($change_data as $dict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($dict);
    }

    $diff = DifferentialDiff::newFromRawChanges($changes);
    $diff->setSourcePath($request->getValue('sourcePath'));
    $diff->setSourceMachine($request->getValue('sourceMachine'));

    $diff->setBranch($request->getValue('branch'));
    $diff->setCreationMethod($request->getValue('creationMethod'));
    $diff->setAuthorPHID($viewer->getPHID());
    $diff->setBookmark($request->getValue('bookmark'));

    // TODO: Remove this eventually; for now continue writing the UUID. Note
    // that we'll overwrite it below if we identify a repository, and `arc`
    // no longer sends it. This stuff is retained for backward compatibility.
    $diff->setRepositoryUUID($request->getValue('repositoryUUID'));

    $repository_phid = $request->getValue('repositoryPHID');
    if ($repository_phid) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($repository_phid))
        ->executeOne();
      if ($repository) {
        $diff->setRepositoryPHID($repository->getPHID());
        $diff->setRepositoryUUID($repository->getUUID());
      }
    }

    $system = $request->getValue('sourceControlSystem');
    $diff->setSourceControlSystem($system);
    $diff->setSourceControlPath($request->getValue('sourceControlPath'));
    $diff->setSourceControlBaseRevision(
      $request->getValue('sourceControlBaseRevision'));

    $project_name = $request->getValue('arcanistProject');
    $project_phid = null;
    if ($project_name) {
      $arcanist_project = id(new PhabricatorRepositoryArcanistProject())
        ->loadOneWhere(
          'name = %s',
          $project_name);
      if (!$arcanist_project) {
        $arcanist_project = new PhabricatorRepositoryArcanistProject();
        $arcanist_project->setName($project_name);
        $arcanist_project->save();
      }
      $project_phid = $arcanist_project->getPHID();
    }

    $diff->setArcanistProjectPHID($project_phid);

    switch ($request->getValue('lintStatus')) {
      case 'skip':
        $diff->setLintStatus(DifferentialLintStatus::LINT_SKIP);
        break;
      case 'okay':
        $diff->setLintStatus(DifferentialLintStatus::LINT_OKAY);
        break;
      case 'warn':
        $diff->setLintStatus(DifferentialLintStatus::LINT_WARN);
        break;
      case 'fail':
        $diff->setLintStatus(DifferentialLintStatus::LINT_FAIL);
        break;
      case 'postponed':
        $diff->setLintStatus(DifferentialLintStatus::LINT_POSTPONED);
        break;
      case 'none':
      default:
        $diff->setLintStatus(DifferentialLintStatus::LINT_NONE);
        break;
    }

    switch ($request->getValue('unitStatus')) {
      case 'skip':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_SKIP);
        break;
      case 'okay':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_OKAY);
        break;
      case 'warn':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_WARN);
        break;
      case 'fail':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_FAIL);
        break;
      case 'postponed':
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_POSTPONED);
        break;
      case 'none':
      default:
        $diff->setUnitStatus(DifferentialUnitStatus::UNIT_NONE);
        break;
    }

    $diff->save();

    // If we didn't get an explicit `repositoryPHID` (which means the client is
    // old, or couldn't figure out which repository the working copy belongs
    // to), apply heuristics to try to figure it out.

    if (!$repository_phid) {
      $repository = id(new DifferentialRepositoryLookup())
        ->setDiff($diff)
        ->setViewer($viewer)
        ->lookupRepository();
      if ($repository) {
        $diff->setRepositoryPHID($repository->getPHID());
        $diff->setRepositoryUUID($repository->getUUID());
        $diff->save();
      }
    }

    $path = '/differential/diff/'.$diff->getID().'/';
    $uri = PhabricatorEnv::getURI($path);

    return array(
      'diffid' => $diff->getID(),
      'uri'    => $uri,
    );
  }

}
