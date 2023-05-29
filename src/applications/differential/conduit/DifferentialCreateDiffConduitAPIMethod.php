<?php

final class DifferentialCreateDiffConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.creatediff';
  }

  public function getMethodDescription() {
    return pht('Create a new Differential diff.');
  }

  protected function defineParamTypes() {
    $vcs_const = $this->formatStringConstants(
      array(
        'svn',
        'git',
        'hg',
      ));

    $status_const = $this->formatStringConstants(
      array(
        'none',
        'skip',
        'okay',
        'warn',
        'fail',
      ));

    return array(
      'changes'                   => 'required list<dict>',
      'sourceMachine'             => 'required string',
      'sourcePath'                => 'required string',
      'branch'                    => 'required string',
      'bookmark'                  => 'optional string',
      'sourceControlSystem'       => 'required '.$vcs_const,
      'sourceControlPath'         => 'required string',
      'sourceControlBaseRevision' => 'required string',
      'creationMethod'            => 'optional string',
      'lintStatus'                => 'required '.$status_const,
      'unitStatus'                => 'required '.$status_const,
      'repositoryPHID'            => 'optional phid',

      'parentRevisionID'          => 'deprecated',
      'authorPHID'                => 'deprecated',
      'repositoryUUID'            => 'deprecated',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();
    $change_data = $request->getValue('changes');
    if ($change_data === null) {
      throw new Exception(pht('Field "changes" must be non-empty.'));
    }

    $changes = array();
    foreach ($change_data as $dict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($dict);
    }

    $diff = DifferentialDiff::newFromRawChanges($viewer, $changes);

    // TODO: Remove repository UUID eventually; for now continue writing
    // the UUID. Note that we'll overwrite it below if we identify a
    // repository, and `arc` no longer sends it. This stuff is retained for
    // backward compatibility.

    $repository_uuid = $request->getValue('repositoryUUID');
    $repository_phid = $request->getValue('repositoryPHID');
    if ($repository_phid) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($repository_phid))
        ->executeOne();
      if ($repository) {
        $repository_phid = $repository->getPHID();
        $repository_uuid = $repository->getUUID();
      }
    }

    switch ($request->getValue('lintStatus')) {
      case 'skip':
        $lint_status = DifferentialLintStatus::LINT_SKIP;
        break;
      case 'okay':
        $lint_status = DifferentialLintStatus::LINT_OKAY;
        break;
      case 'warn':
        $lint_status = DifferentialLintStatus::LINT_WARN;
        break;
      case 'fail':
        $lint_status = DifferentialLintStatus::LINT_FAIL;
        break;
      case 'none':
      default:
        $lint_status = DifferentialLintStatus::LINT_NONE;
        break;
    }

    switch ($request->getValue('unitStatus')) {
      case 'skip':
        $unit_status = DifferentialUnitStatus::UNIT_SKIP;
        break;
      case 'okay':
        $unit_status = DifferentialUnitStatus::UNIT_OKAY;
        break;
      case 'warn':
        $unit_status = DifferentialUnitStatus::UNIT_WARN;
        break;
      case 'fail':
        $unit_status = DifferentialUnitStatus::UNIT_FAIL;
        break;
      case 'none':
      default:
        $unit_status = DifferentialUnitStatus::UNIT_NONE;
        break;
    }

    $source_path = $request->getValue('sourcePath');
    $source_path = $this->normalizeSourcePath($source_path);

    $diff_data_dict = array(
      'sourcePath' => $source_path,
      'sourceMachine' => $request->getValue('sourceMachine'),
      'branch' => $request->getValue('branch'),
      'creationMethod' => $request->getValue('creationMethod'),
      'authorPHID' => $viewer->getPHID(),
      'bookmark' => $request->getValue('bookmark'),
      'repositoryUUID' => $repository_uuid,
      'repositoryPHID' => $repository_phid,
      'sourceControlSystem' => $request->getValue('sourceControlSystem'),
      'sourceControlPath' => $request->getValue('sourceControlPath'),
      'sourceControlBaseRevision' =>
        $request->getValue('sourceControlBaseRevision'),
      'lintStatus' => $lint_status,
      'unitStatus' => $unit_status,
    );

    $xactions = array(
      id(new DifferentialDiffTransaction())
        ->setTransactionType(DifferentialDiffTransaction::TYPE_DIFF_CREATE)
        ->setNewValue($diff_data_dict),
    );

    id(new DifferentialDiffEditor())
      ->setActor($viewer)
      ->setContentSource($request->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($diff, $xactions);

    $path = '/differential/diff/'.$diff->getID().'/';
    $uri = PhabricatorEnv::getURI($path);

    return array(
      'diffid' => $diff->getID(),
      'phid' => $diff->getPHID(),
      'uri' => $uri,
    );
  }

  private function normalizeSourcePath($source_path) {
    // See T13385. This property is probably headed for deletion. Until we get
    // there, stop errors arising from running "arc diff" in a working copy
    // with too many characters.

    $max_size = id(new DifferentialDiff())
      ->getColumnMaximumByteLength('sourcePath');

    return id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes($max_size)
      ->setTerminator('')
      ->truncateString($source_path);
  }

}
