<?php

final class DiffusionBrowseMainController extends DiffusionBrowseController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $drequest = $this->diffusionRequest;

    // Figure out if we're browsing a directory, a file, or a search result
    // list. Then delegate to the appropriate controller.

    $grep = $request->getStr('grep');
    $find = $request->getStr('find');
    if (strlen($grep) || strlen($find)) {
      $controller = new DiffusionBrowseSearchController();
    } else {
      $results = DiffusionBrowseResultSet::newFromConduit(
        $this->callConduitWithDiffusionRequest(
          'diffusion.browsequery',
          array(
            'path' => $drequest->getPath(),
            'commit' => $drequest->getStableCommit(),
          )));
      $reason = $results->getReasonForEmptyResultSet();
      $is_file = ($reason == DiffusionBrowseResultSet::REASON_IS_FILE);

      if ($is_file) {
        $controller = new DiffusionBrowseFileController($request);
      } else {
        $controller = new DiffusionBrowseDirectoryController($request);
        $controller->setBrowseQueryResults($results);
      }
    }

    $controller->setDiffusionRequest($drequest);
    $controller->setCurrentApplication($this->getCurrentApplication());
    return $this->delegateToController($controller);
  }

}
