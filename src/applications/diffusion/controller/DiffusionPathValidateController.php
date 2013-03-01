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

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $browse_query->setViewer($request->getUser());
    $browse_query->needValidityOnly(true);
    $valid = $browse_query->loadPaths();

    if (!$valid) {
      switch ($browse_query->getReasonForEmptyResultSet()) {
        case DiffusionBrowseQuery::REASON_IS_FILE:
          $valid = true;
          break;
        case DiffusionBrowseQuery::REASON_IS_EMPTY:
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
