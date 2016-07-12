<?php

final class DiffusionPathCompleteController extends DiffusionController {

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

    $query_path = $request->getStr('q');
    if (preg_match('@/$@', $query_path)) {
      $query_dir = $query_path;
    } else {
      $query_dir = dirname($query_path).'/';
    }
    $query_dir = ltrim($query_dir, '/');

    $browse_results = DiffusionBrowseResultSet::newFromConduit(
      $this->callConduitWithDiffusionRequest(
        'diffusion.browsequery',
        array(
          'path' => $query_dir,
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
