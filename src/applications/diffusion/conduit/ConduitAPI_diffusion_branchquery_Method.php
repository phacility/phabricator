<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_branchquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return 'Determine what branches exist for a repository.';
  }

  public function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'limit' => 'optional int',
      'offset' => 'optional int'
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    // We need to add 1 in case we pick up HEAD.
    $count = $offset + $limit + 1;

    list($stdout) = $repository->execxLocalCommand(
      'for-each-ref %C --sort=-creatordate --format=%s refs/remotes',
      $count ? '--count='.(int)$count : null,
      '%(refname:short) %(objectname)');

    $branch_list = DiffusionGitBranch::parseRemoteBranchOutput(
      $stdout,
      $only_this_remote = DiffusionBranchInformation::DEFAULT_GIT_REMOTE);

    $branches = array();
    foreach ($branch_list as $name => $head) {
      if (!$repository->shouldTrackBranch($name)) {
        continue;
      }

      $branch = new DiffusionBranchInformation();
      $branch->setName($name);
      $branch->setHeadCommitIdentifier($head);
      $branches[] = $branch->toDictionary();
    }

    if ($offset) {
      $branches = array_slice($branches, $offset);
    }

    // We might have too many even after offset slicing, if there was no HEAD
    // for some reason.
    if ($limit) {
      $branches = array_slice($branches, 0, $limit);
    }

    return $branches;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');

    list($stdout) = $repository->execxLocalCommand(
      '--debug branches');
    $branch_info = ArcanistMercurialParser::parseMercurialBranches($stdout);

    $branches = array();
    foreach ($branch_info as $name => $info) {
      $branch = new DiffusionBranchInformation();
      $branch->setName($name);
      $branch->setHeadCommitIdentifier($info['rev']);
      $branches[] = $branch->toDictionary();
    }

    if ($offset) {
      $branches = array_slice($branches, $offset);
    }

    if ($limit) {
      $branches = array_slice($branches, 0, $limit);
    }

    return $branches;
  }
}
