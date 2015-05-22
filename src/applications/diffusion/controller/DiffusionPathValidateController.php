<?php

final class DiffusionPathValidateController extends DiffusionController {

  protected function shouldLoadDiffusionRequest() {
    return false;
  }

  protected function processDiffusionRequest(AphrontRequest $request) {

    $repository_phid = $request->getStr('repositoryPHID');
    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer($request->getUser())
      ->withPHIDs(array($repository_phid))
      ->executeOne();
    if (!$repository) {
      return new Aphront400Response();
    }

    $path = $request->getStr('path');
    $path = ltrim($path, '/');

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $request->getUser(),
        'repository' => $repository,
        'path' => $path,
      ));
    $this->setDiffusionRequest($drequest);

    $browse_results = DiffusionBrowseResultSet::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.browsequery',
        array(
          'path' => $drequest->getPath(),
          'commit' => $drequest->getCommit(),
          'needValidityOnly' => true,
        )));
    $valid = $browse_results->isValidResults();

    if (!$valid) {
      switch ($browse_results->getReasonForEmptyResultSet()) {
        case DiffusionBrowseResultSet::REASON_IS_FILE:
          $valid = true;
          break;
        case DiffusionBrowseResultSet::REASON_IS_EMPTY:
          $valid = true;
          break;
      }
    }

    $output = array(
      'valid' => (bool)$valid,
    );

    if (!$valid) {
      $branch = $drequest->getBranch();
      if ($branch) {
        $message = pht('Not found in %s', $branch);
      } else {
        $message = pht('Not found at %s', 'HEAD');
      }
    } else {
      $message = pht('OK');
    }

    $output['message'] = $message;

    return id(new AphrontAjaxResponse())->setContent($output);
  }
}
