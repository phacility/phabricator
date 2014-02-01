<?php

final class ConduitAPI_diffusion_querypaths_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return pht('Filename search on a repository.');
  }

  public function defineReturnType() {
    return 'list<string>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'path' => 'required string',
      'commit' => 'required string',
      'pattern' => 'required string',
      'limit' => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $results = parent::getResult($request);
    $offset = $request->getValue('offset');
    return array_slice($results, $offset);
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $path = $drequest->getPath();
    $commit = $request->getValue('commit');
    $repository = $drequest->getRepository();

    // http://comments.gmane.org/gmane.comp.version-control.git/197735

    $future = $repository->getLocalCommandFuture(
      'ls-tree --name-only -r -z %s -- %s',
      $commit,
      $path);

    $lines = id(new LinesOfALargeExecFuture($future))->setDelimiter("\0");
    return $this->filterResults($lines, $request);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $path = $request->getValue('path');
    $commit = $request->getValue('commit');

    // Adapted from diffusion.browsequery.
    list($entire_manifest) = $repository->execxLocalCommand(
      'manifest --rev %s',
      hgsprintf('%s', $commit));
    $entire_manifest = explode("\n", $entire_manifest);

    $match_against = trim($path, '/');
    $match_len = strlen($match_against);

    $lines = array();
    foreach ($entire_manifest as $path) {
      if (strlen($path) && !strncmp($path, $match_against, $match_len)) {
        $lines[] = $path;
      }
    }
    return $this->filterResults($lines, $request);
  }

  protected function filterResults($lines, ConduitAPIRequest $request) {
    $pattern = $request->getValue('pattern');
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    $results = array();
    foreach ($lines as $line) {
      if (preg_match('#'.str_replace('#', '\#', $pattern).'#', $line)) {
        $results[] = $line;
        if (count($results) >= $offset + $limit) {
          break;
        }
      }
    }
    return $results;
  }
}
