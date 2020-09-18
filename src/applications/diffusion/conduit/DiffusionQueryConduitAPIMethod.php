<?php

abstract class DiffusionQueryConduitAPIMethod
  extends DiffusionConduitAPIMethod {

  public function shouldAllowPublic() {
    return true;
  }

  private $diffusionRequest;
  private $repository;

  protected function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }

  protected function getDiffusionRequest() {
    return $this->diffusionRequest;
  }

  protected function getRepository(ConduitAPIRequest $request) {
    return $this->getDiffusionRequest()->getRepository();
  }

  final protected function defineErrorTypes() {
    return $this->defineCustomErrorTypes() +
      array(
        'ERR-UNKNOWN-REPOSITORY' =>
          pht('There is no matching repository.'),
        'ERR-UNKNOWN-VCS-TYPE' =>
          pht('Unknown repository VCS type.'),
        'ERR-UNSUPPORTED-VCS' =>
          pht('VCS is not supported for this method.'),
      );
  }

  /**
   * Subclasses should override this to specify custom error types.
   */
  protected function defineCustomErrorTypes() {
    return array();
  }

  final protected function defineParamTypes() {
    return $this->defineCustomParamTypes() +
      array(
        'callsign' => 'optional string (deprecated)',
        'repository' => 'optional string',
        'branch' => 'optional string',
      );
  }

  /**
   * Subclasses should override this to specify custom param types.
   */
  protected function defineCustomParamTypes() {
    return array();
  }

  /**
   * Subclasses should override these methods with the proper result for the
   * pertinent version control system, e.g. getGitResult for Git.
   *
   * If the result is not supported for that VCS, do not implement it. e.g.
   * Subversion (SVN) does not support branches.
   */
  protected function getGitResult(ConduitAPIRequest $request) {
    throw new ConduitException('ERR-UNSUPPORTED-VCS');
  }
  protected function getSVNResult(ConduitAPIRequest $request) {
    throw new ConduitException('ERR-UNSUPPORTED-VCS');
  }
  protected function getMercurialResult(ConduitAPIRequest $request) {
    throw new ConduitException('ERR-UNSUPPORTED-VCS');
  }

  /**
   * This method is final because most queries will need to construct a
   * @{class:DiffusionRequest} and use it. Consolidating this codepath and
   * enforcing @{method:getDiffusionRequest} works when we need it is good.
   *
   * @{method:getResult} should be overridden by subclasses as necessary, e.g.
   * there is a common operation across all version control systems that
   * should occur after @{method:getResult}, like formatting a timestamp.
   */
  final protected function execute(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    // We pass this flag on to prevent proxying of any other Conduit calls
    // which we need to make in order to respond to this one. Although we
    // could safely proxy them, we take a big performance hit in the common
    // case, and doing more proxying wouldn't exercise any additional code so
    // we wouldn't gain a testability/predictability benefit.
    $is_cluster_request = $request->getIsClusterRequest();
    $drequest->setIsClusterRequest($is_cluster_request);

    $viewer = $request->getViewer();
    $repository = $drequest->getRepository();

    // TODO: Allow web UI queries opt out of this if they don't care about
    // fetching the most up-to-date data? Synchronization can be slow, and a
    // lot of web reads are probably fine if they're a few seconds out of
    // date.
    id(new DiffusionRepositoryClusterEngine())
      ->setViewer($viewer)
      ->setRepository($repository)
      ->synchronizeWorkingCopyBeforeRead();

    return $this->getResult($request);
  }


  protected function newConduitCallProxyClient(ConduitAPIRequest $request) {
    $viewer = $request->getViewer();

    $identifier = $request->getValue('repository');
    if ($identifier === null) {
      $identifier = $request->getValue('callsign');
    }

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $viewer,
        'repository' => $identifier,
        'branch' => $request->getValue('branch'),
        'path' => $request->getValue('path'),
        'commit' => $request->getValue('commit'),
      ));

    if (!$drequest) {
      throw new Exception(
        pht(
          'Repository "%s" is not a valid repository.',
          $identifier));
    }

    $repository = $drequest->getRepository();

    $client = $repository->newConduitClientForRequest($request);
    if ($client) {
      return $client;
    }

    $this->setDiffusionRequest($drequest);

    return null;
  }

  protected function getResult(ConduitAPIRequest $request) {
    $repository = $this->getRepository($request);
    $result = null;
    switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $result = $this->getGitResult($request);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $result = $this->getMercurialResult($request);
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $result = $this->getSVNResult($request);
        break;
      default:
        throw new ConduitException('ERR-UNKNOWN-VCS-TYPE');
        break;
    }
    return $result;
  }

}
