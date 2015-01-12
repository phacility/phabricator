<?php

final class DiffusionPathCompleteController extends DiffusionController {

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

    $query_path = $request->getStr('q');
    if (preg_match('@/$@', $query_path)) {
      $query_dir = $query_path;
    } else {
      $query_dir = dirname($query_path).'/';
    }
    $query_dir = ltrim($query_dir, '/');

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => $request->getUser(),
        'repository' => $repository,
        'path' => $query_dir,
      ));
    $this->setDiffusionRequest($drequest);

    $browse_results = DiffusionBrowseResultSet::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.browsequery',
        array(
          'path' => $drequest->getPath(),
          'commit' => $drequest->getCommit(),
        )));
    $paths = $browse_results->getPaths();

    $output = array();
    foreach ($paths as $path) {
      $full_path = $query_dir.$path->getPath();
      if ($path->getFileType() == DifferentialChangeType::FILE_DIRECTORY) {
        $full_path .= '/';
      }
      $output[] = array('/'.$full_path, null, substr(md5($full_path), 0, 7));
    }

    return id(new AphrontAjaxResponse())->setContent($output);
  }
}
