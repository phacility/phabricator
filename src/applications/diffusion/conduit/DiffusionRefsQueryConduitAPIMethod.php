<?php

final class DiffusionRefsQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.refsquery';
  }

  public function getMethodDescription() {
    return pht(
      'Query a git repository for ref information at a specific commit.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $request->getValue('commit');

    list($stdout) = $repository->execxLocalCommand(
      'log --format=%s -n 1 %s --',
      '%d',
      $commit);

    // %d, gives a weird output format
    // similar to (remote/one, remote/two, remote/three)
    $refs = trim($stdout, "() \n");
    if (!$refs) {
      return array();
    }
    $refs = explode(',', $refs);
    $refs = array_map('trim', $refs);

    $ref_links = array();
    foreach ($refs as $ref) {
      $ref_links[] = array(
        'ref' => $ref,
        'href' => $drequest->generateURI(
          array(
            'action'  => 'browse',
            'branch'  => $ref,
          )),
      );
    }

    return $ref_links;
  }

}
