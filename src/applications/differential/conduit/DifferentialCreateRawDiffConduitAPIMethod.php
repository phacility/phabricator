<?php

final class DifferentialCreateRawDiffConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.createrawdiff';
  }

  public function getMethodDescription() {
    return pht('Create a new Differential diff from a raw diff source.');
  }

  public function defineParamTypes() {
    return array(
      'diff' => 'required string',
      'repositoryPHID' => 'optional string',
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
    $raw_diff = $request->getValue('diff');

    $repository_phid = $request->getValue('repositoryPHID');
    if ($repository_phid) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($repository_phid))
        ->executeOne();
      if (!$repository) {
        throw new Exception(
          pht('No such repository "%s"!', $repository_phid));
      }
    } else {
      $repository = null;
    }

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($raw_diff);
    $diff = DifferentialDiff::newFromRawChanges($changes);

    $diff->setLintStatus(DifferentialLintStatus::LINT_SKIP);
    $diff->setUnitStatus(DifferentialUnitStatus::UNIT_SKIP);

    $diff->setAuthorPHID($viewer->getPHID());
    $diff->setCreationMethod('web');

    if ($repository) {
      $diff->setRepositoryPHID($repository->getPHID());
    }

    $diff->save();

    return $this->buildDiffInfoDictionary($diff);
  }

}
