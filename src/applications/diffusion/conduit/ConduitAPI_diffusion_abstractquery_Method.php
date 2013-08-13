<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_diffusion_abstractquery_Method
  extends ConduitAPI_diffusion_Method {

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
  private $shouldCreateDiffusionRequest = true;

  protected function setDiffusionRequest(DiffusionRequest $request) {
    $this->diffusionRequest = $request;
    return $this;
  }
  protected function getDiffusionRequest() {
    return $this->diffusionRequest;
  }

  /**
   * A wee bit of magic here. If @{method:shouldCreateDiffusionRequest}
   * returns false, this function grabs a repository object based on the
   * callsign directly. Otherwise, the repository was loaded when we created a
   * @{class:DiffusionRequest}, so this function just pulls it out of the
   * @{class:DiffusionRequest}.
   *
   * @return @{class:PhabricatorRepository} $repository
   */
  protected function getRepository(ConduitAPIRequest $request) {
    if (!$this->repository) {
      if ($this->shouldCreateDiffusionRequest()) {
        $this->repository = $this->getDiffusionRequest()->getRepository();
      } else {
        $callsign = $request->getValue('callsign');
        $repository = id(new PhabricatorRepository())->loadOneWhere(
          'callsign = %s',
          $callsign);
        if (!$repository) {
          throw new ConduitException('ERR-UNKNOWN-REPOSITORY');
        }
        $this->repository = $repository;
      }
    }
    return $this->repository;
  }

  /**
   * You should probably not mess with this unless your conduit method is
   * involved with the creation / validation / etc. of
   * @{class:DiffusionRequest}s. If you are dealing with
   * @{class:DiffusionRequest}, setting this to false should help avoid
   * infinite loops.
   */
  protected function setShouldCreateDiffusionRequest($should) {
    $this->shouldCreateDiffusionRequest = $should;
    return $this;
  }
  private function shouldCreateDiffusionRequest() {
    return $this->shouldCreateDiffusionRequest;
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
   *
   * In the rare cases where one does not want to create a
   * @{class:DiffusionRequest} - suppose to avoid infinite loops in the
   * creation of a @{class:DiffusionRequest} - make sure to call
   *
   *   $this->setShouldCreateDiffusionRequest(false);
   *
   * in the constructor of the pertinent Conduit method.
   */
  final protected function execute(ConduitAPIRequest $request) {
    if ($this->shouldCreateDiffusionRequest()) {
      $drequest = DiffusionRequest::newFromDictionary(
        array(
          'user' => $request->getUser(),
          'callsign' => $request->getValue('callsign'),
          'branch' => $request->getValue('branch'),
          'path' => $request->getValue('path'),
          'commit' => $request->getValue('commit'),
        ));
      $this->setDiffusionRequest($drequest);
    }

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
