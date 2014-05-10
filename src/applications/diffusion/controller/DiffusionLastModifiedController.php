<?php

final class DiffusionLastModifiedController extends DiffusionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();

    $paths = $request->getStr('paths');
    $paths = json_decode($paths, true);

    $output = array();
    foreach ($paths as $path) {
      $prequest = clone $drequest;
      $prequest->setPath($path);

      $conduit_result = $this->callConduitWithDiffusionRequest(
        'diffusion.lastmodifiedquery',
        array(
          'commit' => $prequest->getCommit(),
          'path' => $prequest->getPath(),
        ));

      $commit = PhabricatorRepositoryCommit::newFromDictionary(
        $conduit_result['commit']);

      $commit_data = PhabricatorRepositoryCommitData::newFromDictionary(
        $conduit_result['commitData']);

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
      $output[$path] = $view->renderLastModifiedColumns(
        $prequest,
        $handles,
        $commit,
        $commit_data);
    }

    return id(new AphrontAjaxResponse())->setContent($output);
  }
}
