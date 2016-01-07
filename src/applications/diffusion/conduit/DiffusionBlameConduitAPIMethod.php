<?php

final class DiffusionBlameConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.blame';
  }

  public function getMethodDescription() {
    return pht('Get blame information for a list of paths.');
  }

  protected function defineReturnType() {
    return 'map<string, wild>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'paths' => 'required list<string>',
      'commit' => 'required string',
      'timeout' => 'optional int',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    $paths = $request->getValue('paths');

    $blame_query = DiffusionBlameQuery::newFromDiffusionRequest($drequest)
      ->setPaths($paths);

    $timeout = $request->getValue('timeout');
    if ($timeout) {
      $blame_query->setTimeout($timeout);
    }

    $blame = $blame_query->execute();

    return $blame;
  }

}
