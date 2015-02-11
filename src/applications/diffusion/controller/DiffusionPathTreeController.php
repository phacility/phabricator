<?php

final class DiffusionPathTreeController extends DiffusionController {

  protected function processDiffusionRequest(AphrontRequest $request) {
    $drequest = $this->getDiffusionRequest();

    if (!$drequest->getRepository()->canUsePathTree()) {
      return new Aphront404Response();
    }

    $paths = $this->callConduitWithDiffusionRequest(
      'diffusion.querypaths',
      array(
        'path' => $drequest->getPath(),
        'commit' => $drequest->getCommit(),
      ));

    $tree = array();
    foreach ($paths as $path) {
      $parts = preg_split('((?<=/))', $path);
      $cursor = &$tree;
      foreach ($parts as $part) {
        if (!is_array($cursor)) {
          $cursor = array();
        }
        if (!isset($cursor[$part])) {
          $cursor[$part] = 1;
        }
        $cursor = &$cursor[$part];
      }
    }

    return id(new AphrontAjaxResponse())->setContent(array('tree' => $tree));
  }
}
