<?php

final class PhragmentQueryFragmentsConduitAPIMethod
  extends PhragmentConduitAPIMethod {

  public function getAPIMethodName() {
    return 'phragment.queryfragments';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Query fragments based on their paths.');
  }

  protected function defineParamTypes() {
    return array(
      'paths' => 'required list<string>',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_FRAGMENT' => 'No such fragment exists',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $paths = $request->getValue('paths');

    $fragments = id(new PhragmentFragmentQuery())
      ->setViewer($request->getUser())
      ->withPaths($paths)
      ->execute();
    $fragments = mpull($fragments, null, 'getPath');
    foreach ($paths as $path) {
      if (!array_key_exists($path, $fragments)) {
        throw new ConduitException('ERR_BAD_FRAGMENT');
      }
    }

    $results = array();
    foreach ($fragments as $path => $fragment) {
      $mappings = $fragment->getFragmentMappings(
        $request->getUser(),
        $fragment->getPath());

      $file_phids = mpull(mpull($mappings, 'getLatestVersion'), 'getFilePHID');
      $files = id(new PhabricatorFileQuery())
        ->setViewer($request->getUser())
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');

      $result = array();
      foreach ($mappings as $cpath => $child) {
        $file_phid = $child->getLatestVersion()->getFilePHID();
        if (!isset($files[$file_phid])) {
          // Skip any files we don't have permission to access.
          continue;
        }

        $file = $files[$file_phid];
        $cpath = substr($child->getPath(), strlen($fragment->getPath()) + 1);
        $result[] = array(
          'phid' => $child->getPHID(),
          'phidVersion' => $child->getLatestVersionPHID(),
          'path' => $cpath,
          'hash' => $file->getContentHash(),
          'version' => $child->getLatestVersion()->getSequence(),
          'uri' => $file->getViewURI(),
        );
      }
      $results[$path] = $result;
    }
    return $results;
  }

}
