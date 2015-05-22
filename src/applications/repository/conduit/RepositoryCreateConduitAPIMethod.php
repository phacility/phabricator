<?php

final class RepositoryCreateConduitAPIMethod
  extends RepositoryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'repository.create';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht('Repository methods are new and subject to change.');
  }

  public function getMethodDescription() {
    return pht('Create a new repository.');
  }

  protected function defineParamTypes() {
    $vcs_const = $this->formatStringConstants(array('git', 'hg', 'svn'));

    return array(
      'name'                => 'required string',
      'vcs'                 => 'required '.$vcs_const,
      'callsign'            => 'required string',
      'description'         => 'optional string',
      'encoding'            => 'optional string',
      'tracking'            => 'optional bool',
      'uri'                 => 'required string',
      'credentialPHID'      => 'optional string',
      'svnSubpath'          => 'optional string',
      'branchFilter'        => 'optional list<string>',
      'closeCommitsFilter'  => 'optional list<string>',
      'pullFrequency'       => 'optional int',
      'defaultBranch'       => 'optional string',
      'heraldEnabled'       => 'optional bool, default = true',
      'autocloseEnabled'    => 'optional bool, default = true',
      'svnUUID'             => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-DUPLICATE' => pht('Duplicate repository callsign.'),
      'ERR-BAD-CALLSIGN' => pht(
        'Callsign is required and must be ALL UPPERCASE LETTERS.'),
      'ERR-UNKNOWN-REPOSITORY-VCS' => pht('Unknown repository VCS type.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $application = id(new PhabricatorApplicationQuery())
      ->setViewer($request->getUser())
      ->withClasses(array('PhabricatorDiffusionApplication'))
      ->executeOne();

    PhabricatorPolicyFilter::requireCapability(
      $request->getUser(),
      $application,
      DiffusionCreateRepositoriesCapability::CAPABILITY);

    // TODO: This has some duplication with (and lacks some of the validation
    // of) the web workflow; refactor things so they can share more code as this
    // stabilizes. Specifically, this should move to transactions since they
    // work properly now.

    $repository = PhabricatorRepository::initializeNewRepository(
      $request->getUser());

    $repository->setName($request->getValue('name'));

    $callsign = $request->getValue('callsign');
    if (!preg_match('/^[A-Z]+\z/', $callsign)) {
      throw new ConduitException('ERR-BAD-CALLSIGN');
    }
    $repository->setCallsign($callsign);

    $local_path = PhabricatorEnv::getEnvConfig(
      'repository.default-local-path');

    $local_path = rtrim($local_path, '/');
    $local_path = $local_path.'/'.$callsign.'/';

    $vcs = $request->getValue('vcs');

    $map = array(
      'git' => PhabricatorRepositoryType::REPOSITORY_TYPE_GIT,
      'hg'  => PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL,
      'svn' => PhabricatorRepositoryType::REPOSITORY_TYPE_SVN,
    );
    if (empty($map[$vcs])) {
      throw new ConduitException('ERR-UNKNOWN-REPOSITORY-VCS');
    }
    $repository->setVersionControlSystem($map[$vcs]);

    $repository->setCredentialPHID($request->getValue('credentialPHID'));

    $remote_uri = $request->getValue('uri');
    PhabricatorRepository::assertValidRemoteURI($remote_uri);

    $details = array(
      'encoding'          => $request->getValue('encoding'),
      'description'       => $request->getValue('description'),
      'tracking-enabled'  => (bool)$request->getValue('tracking', true),
      'remote-uri'        => $remote_uri,
      'local-path'        => $local_path,
      'branch-filter'     => array_fill_keys(
        $request->getValue('branchFilter', array()),
        true),
      'close-commits-filter' => array_fill_keys(
        $request->getValue('closeCommitsFilter', array()),
        true),
      'pull-frequency'    => $request->getValue('pullFrequency'),
      'default-branch'    => $request->getValue('defaultBranch'),
      'herald-disabled'   => !$request->getValue('heraldEnabled', true),
      'svn-subpath'       => $request->getValue('svnSubpath'),
      'disable-autoclose' => !$request->getValue('autocloseEnabled', true),
    );

    foreach ($details as $key => $value) {
      $repository->setDetail($key, $value);
    }

    try {
      $repository->save();
    } catch (AphrontDuplicateKeyQueryException $ex) {
      throw new ConduitException('ERR-DUPLICATE');
    }

    return $repository->toDictionary();
  }

}
