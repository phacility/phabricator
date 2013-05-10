<?php

final class DiffusionPathValidateController extends DiffusionController {

  public function willProcessRequest(array $data) {
    // Don't build a DiffusionRequest.
  }

  public function processRequest() {
    $request = $this->getRequest();

    $repository_phid = $request->getStr('repositoryPHID');
    $repository = id(new PhabricatorRepository())->loadOneWhere(
      'phid = %s',
      $repository_phid);
    if (!$repository) {
      return new Aphront400Response();
    }

    $path = $request->getStr('path');
    $path = ltrim($path, '/');

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $repository,
        'path'        => $path,
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
        $message = 'Not found in '.$branch;
      } else {
        $message = 'Not found at HEAD';
      }
    } else {
      $message = 'OK';
    }

    $output['message'] = $message;

    return id(new AphrontAjaxResponse())->setContent($output);
  }
}
