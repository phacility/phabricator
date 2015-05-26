<?php

final class DifferentialHostedMercurialLandingStrategy
  extends DifferentialLandingStrategy {

  public function processLandRequest(
    AphrontRequest $request,
    DifferentialRevision $revision,
    PhabricatorRepository $repository) {

    $viewer = $request->getUser();

    $workspace = $this->getMercurialWorkspace($repository);

    try {
      $this->commitRevisionToWorkspace($revision, $workspace, $viewer);
    } catch (Exception $e) {
      throw new PhutilProxyException(pht('Failed to commit patch.'), $e);
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

    $future = $workspace->execFutureLocal('patch --no-commit -');
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
      'commit --date=%s --user=%s '.
      '--message=%s',
      $author_date.' 0',
      $author_string,
      $message);
  }


  public function pushWorkspaceRepository(
    PhabricatorRepository $repository,
    ArcanistRepositoryAPI $workspace,
    PhabricatorUser $user) {

    $workspace->execxLocal('push -b default');
  }

  public function createMenuItem(
    PhabricatorUser $viewer,
    DifferentialRevision $revision,
    PhabricatorRepository $repository) {

    $vcs = $repository->getVersionControlSystem();
    if ($vcs !== PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL) {
      return;
    }

    if (!$repository->isHosted()) {
      return;
    }

    return $this->createActionView(
      $revision,
      pht('Land to Hosted Repository'));
  }
}
