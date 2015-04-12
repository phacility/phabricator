<?php

final class DiffusionExistsQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.existsquery';
  }

  public function getMethodDescription() {
    return 'Determine if code exists in a version control system.';
  }

  protected function defineReturnType() {
    return 'bool';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'required string',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $repository = $this->getDiffusionRequest()->getRepository();
    $commit = $request->getValue('commit');
    list($err, $merge_base) = $repository->execLocalCommand(
      'cat-file -t %s',
      $commit);
    return !$err;
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    $repository = $this->getDiffusionRequest()->getRepository();
    $commit = $request->getValue('commit');
    list($info) = $repository->execxRemoteCommand(
      'info %s',
      $repository->getRemoteURI());
    $exists = false;
    $matches = null;
    if (preg_match('/^Revision: (\d+)$/m', $info, $matches)) {
      $base_revision = $matches[1];
      $exists = $base_revision >= $commit;
    }
    return $exists;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $repository = $this->getDiffusionRequest()->getRepository();
    $commit = $request->getValue('commit');
    list($err, $stdout) = $repository->execLocalCommand(
      'id --rev %s',
      $commit);
    return  !$err;
  }

}
