<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_expandshortcommitquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function __construct() {
    $this->setShouldCreateDiffusionRequest(false);
  }

  public function getMethodDescription() {
    return
      'Expands a short commit name to its full glory.';
  }

  public function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
    );
  }

  protected function defineCustomErrorTypes() {
    return array(
      'ERR-MISSING-COMMIT' => pht(
        'Bad commit.'),
      'ERR-INVALID-COMMIT' => pht(
        'Invalid object name.'),
      'ERR-UNPARSEABLE-OUTPUT' => pht(
        'Unparseable output from cat-file.')
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    return $this->getGitOrMercurialResult($request);
  }
  protected function getMercurialResult(ConduitAPIRequest $request) {
    return $this->getGitOrMercurialResult($request);
  }

  private function getGitOrMercurialResult(ConduitAPIRequest $request) {
    $repository = $this->getRepository($request);
    $query = DiffusionExpandShortNameQuery::newFromRepository($repository);
    $query->setCommit($request->getValue('commit'));
    try {
      $result = $query->expand();
      return $result;
    } catch (DiffusionExpandCommitQueryException $e) {
      switch ($e->getStatusCode()) {
        case DiffusionExpandCommitQueryException::CODE_INVALID:
          throw id(new ConduitException('ERR-INVALID-COMMIT'))
            ->setErrorDescription($e->getMessage());
          break;
        case DiffusionExpandCommitQueryException::CODE_MISSING:
          throw id(new ConduitException('ERR-MISSING-COMMIT'))
            ->setErrorDescription($e->getMessage());
          break;
        case DiffusionExpandCommitQueryException::CODE_UNPARSEABLE:
          throw id(new ConduitException('ERR-UNPARSEABLE-OUTPUT'))
            ->setErrorDescription($e->getMessage());
         break;
      }
    }
  }
}
