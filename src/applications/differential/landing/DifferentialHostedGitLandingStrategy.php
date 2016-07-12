<?php

final class DifferentialHostedGitLandingStrategy
  extends DifferentialLandingStrategy {

  public function processLandRequest(
    AphrontRequest $request,
    DifferentialRevision $revision,
    PhabricatorRepository $repository) {

    $viewer = $request->getUser();
    $workspace = $this->getGitWorkspace($repository);

    try {
      $this->commitRevisionToWorkspace($revision, $workspace, $viewer);
    } catch (Exception $e) {
      throw new PhutilProxyException(
        pht('Failed to commit patch.'),
        $e);
    }

    try {
      $this->pushWorkspaceRepository($repository, $workspace, $viewer);
    } catch (Exception $e) {
      throw new PhutilProxyException(
        pht('Failed to push changes upstream.'),
        $e);
    }
  }

  public function commitRevisionToWorkspace(
    DifferentialRevision $revision,
    ArcanistRepositoryAPI $workspace,
    PhabricatorUser $user) {

    $diff_id = $revision->loadActiveDiff()->getID();

    $call = new ConduitCall(
      'differential.getrawdiff',
      array(
        'diffID'   => $diff_id,
      ));

    $call->setUser($user);
    $raw_diff = $call->execute();

    $missing_binary =
      "\nindex "
      ."0000000000000000000000000000000000000000.."
      ."0000000000000000000000000000000000000000\n";
    if (strpos($raw_diff, $missing_binary) !== false) {
      throw new Exception(pht('Patch is missing content for a binary file'));
    }

    $future = $workspace->execFutureLocal('apply --index -');
    $future->write($raw_diff);
    $future->resolvex();

    $workspace->reloadWorkingCopy();

    $call = new ConduitCall(
      'differential.getcommitmessage',
      array(
        'revision_id'   => $revision->getID(),
      ));

    $call->setUser($user);
    $message = $call->execute();

    $author = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $revision->getAuthorPHID());

    $author_string = sprintf(
      '%s <%s>',
      $author->getRealName(),
      $author->loadPrimaryEmailAddress());
    $author_date = $revision->getDateCreated();

    $workspace->execxLocal(
      '-c user.name=%s -c user.email=%s '.
      'commit --date=%s --author=%s '.
      '--message=%s',
      // -c will set the 'committer'
      $user->getRealName(),
      $user->loadPrimaryEmailAddress(),
      $author_date,
      $author_string,
      $message);
  }

  public function pushWorkspaceRepository(
    PhabricatorRepository $repository,
    ArcanistRepositoryAPI $workspace,
    PhabricatorUser $user) {

    $workspace->execxLocal('push origin HEAD:master');
  }

  public function createMenuItem(
    PhabricatorUser $viewer,
    DifferentialRevision $revision,
    PhabricatorRepository $repository) {

    $vcs = $repository->getVersionControlSystem();
    if ($vcs !== PhabricatorRepositoryType::REPOSITORY_TYPE_GIT) {
      return;
    }

    if (!$repository->isHosted()) {
      return;
    }

    if (!$repository->isWorkingCopyBare()) {
      return;
    }

    // TODO: This temporarily disables this action, because it doesn't work
    // and is confusing to users. If you want to use it, comment out this line
    // for now and we'll provide real support eventually.
    return;

    return $this->createActionView(
      $revision,
      pht('Land to Hosted Repository'));
  }
}
