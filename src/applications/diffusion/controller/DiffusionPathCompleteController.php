<?php

final class DiffusionPathCompleteController extends DiffusionController {

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

    $query_path = $request->getStr('q');
    if (preg_match('@/$@', $query_path)) {
      $query_dir = $query_path;
    } else {
      $query_dir = dirname($query_path).'/';
    }
    $query_dir = ltrim($query_dir, '/');

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $repository,
        'path'        => $query_dir,
      ));

    $browse_query = DiffusionBrowseQuery::newFromDiffusionRequest($drequest);
    $browse_query->setViewer($request->getUser());
    $paths = $browse_query->loadPaths();

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
