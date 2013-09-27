<?php

final class DiffusionLastModifiedController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $commit = null;
    $commit_data = null;

    $conduit_result = $this->callConduitWithDiffusionRequest(
      'diffusion.lastmodifiedquery',
      array(
        'commit' => $drequest->getCommit(),
        'path' => $drequest->getPath()
      ));
    $c_dict = $conduit_result['commit'];
    if ($c_dict) {
      $commit = PhabricatorRepositoryCommit::newFromDictionary($c_dict);
    }
    $c_d_dict = $conduit_result['commitData'];
    if ($c_d_dict) {
      $commit_data =
        PhabricatorRepositoryCommitData::newFromDictionary($c_d_dict);
    }

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
