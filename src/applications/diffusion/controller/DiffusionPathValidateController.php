<?php

final class DiffusionPathValidateController extends DiffusionController {

  protected function getRepositoryIdentifierFromRequest(
    AphrontRequest $request) {
    return $request->getStr('repositoryPHID');
  }

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadDiffusionContext();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $path = $request->getStr('path');
    $path = ltrim($path, '/');

    $browse_results = DiffusionBrowseResultSet::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.browsequery',
        array(
          'path' => $path,
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

    return id(new AphrontAjaxResponse())->setContent($output);
  }
}
