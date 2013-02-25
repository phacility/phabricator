<?php

final class DiffusionLastModifiedController extends DiffusionController {

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();

    $modified_query = DiffusionLastModifiedQuery::newFromDiffusionRequest(
      $drequest);
    list($commit, $commit_data) = $modified_query->loadLastModification();

    $phids = array();
    if ($commit_data) {
      if ($commit_data->getCommitDetail('authorPHID')) {
        $phids[$commit_data->getCommitDetail('authorPHID')] = true;
      }
      if ($commit_data->getCommitDetail('committerPHID')) {
        $phids[$commit_data->getCommitDetail('committerPHID')] = true;
      }
    }

    $phids = array_keys($phids);
    $handles = $this->loadViewerHandles($phids);

    $view = new DiffusionBrowseTableView();
    $view->setUser($request->getUser());
    $output = $view->renderLastModifiedColumns(
      $drequest,
      $handles,
      $commit,
      $commit_data);

    return id(new AphrontAjaxResponse())
      ->setContent($output);
  }
}
