<?php

final class DiffusionInternalCommitSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'internal.commit.search';
  }

  public function newSearchEngine() {
    return new DiffusionInternalCommitSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read raw information about commits.');
  }

  protected function newConduitCallProxyClient(ConduitAPIRequest $request) {
    $viewer = $request->getViewer();

    $constraints = $request->getValue('constraints');
    if (is_array($constraints)) {
      $repository_phids = idx($constraints, 'repositoryPHIDs');
    } else {
      $repository_phids = array();
    }

    $repository_phid = null;
    if (is_array($repository_phids)) {
      if (phutil_is_natural_list($repository_phids)) {
        if (count($repository_phids) === 1) {
          $value = head($repository_phids);
          if (is_string($value)) {
            $repository_phid = $value;
          }
        }
      }
    }

    if ($repository_phid === null) {
      throw new Exception(
        pht(
          'This internal method must be invoked with a "repositoryPHIDs" '.
          'constraint with exactly one value.'));
    }

    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($repository_phid))
      ->executeOne();
    if (!$repository) {
      return array();
    }

    return $repository->newConduitClientForRequest($request);
  }

}
