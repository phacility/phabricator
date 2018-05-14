<?php

final class DiffusionInternalAncestorsConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function isInternalAPI() {
    return true;
  }

  public function getAPIMethodName() {
    return 'diffusion.internal.ancestors';
  }

  public function getMethodDescription() {
    return pht('Internal method for filtering ref ancestors.');
  }

  protected function defineReturnType() {
    return 'list<string>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'ref' => 'required string',
      'commits' => 'required list<string>',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $commits = $request->getValue('commits');
    $ref = $request->getValue('ref');

    $graph = new PhabricatorGitGraphStream($repository, $ref);

    $keep = array();
    foreach ($commits as $identifier) {
      try {
        $graph->getCommitDate($identifier);
        $keep[] = $identifier;
      } catch (Exception $ex) {
        // Not an ancestor.
      }
    }

    return $keep;
  }

}
