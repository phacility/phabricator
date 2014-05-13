<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_diffusion_abstractquery_Method
  extends ConduitAPI_diffusion_Method {

  public function shouldAllowPublic() {
    return true;
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht(
      'See T2784 - migrating diffusion working copy calls to conduit methods. '.
      'Until that task is completed (and possibly after) these methods are '.
      'unstable.');
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

  final public function defineErrorTypes() {
    return $this->defineCustomErrorTypes() +
      array(
        'ERR-UNKNOWN-REPOSITORY' =>
          pht('There is no repository with that callsign.'),
        'ERR-UNKNOWN-VCS-TYPE' =>
          pht('Unknown repository VCS type.'),
        'ERR-UNSUPPORTED-VCS' =>
          pht('VCS is not supported for this method.'));
  }
  /**
   * Subclasses should override this to specify custom error types.
   */
  protected function defineCustomErrorTypes() {
    return array();
  }

  final public function defineParamTypes() {
    return $this->defineCustomParamTypes() +
      array(
        'callsign' => 'required string',
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
    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $request->getUser(),
        'callsign' => $request->getValue('callsign'),
        'branch' => $request->getValue('branch'),
        'path' => $request->getValue('path'),
        'commit' => $request->getValue('commit'),
        'initFromConduit' => false,
      ));

    $this->setDiffusionRequest($drequest);

    return $this->getResult($request);
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
