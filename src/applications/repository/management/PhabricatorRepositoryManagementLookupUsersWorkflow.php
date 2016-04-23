<?php

final class PhabricatorRepositoryManagementLookupUsersWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('lookup-users')
      ->setExamples('**lookup-users** __commit__ ...')
      ->setSynopsis(
        pht('Resolve user accounts for users attached to __commit__.'))
      ->setArguments(
        array(
          array(
            'name'        => 'commits',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $commits = $this->loadCommits($args, 'commits');
    if (!$commits) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more commits to resolve users for.'));
    }

    $console = PhutilConsole::getConsole();
    foreach ($commits as $commit) {
      $repo = $commit->getRepository();
      $name =  $repo->formatCommitName($commit->getCommitIdentifier());

      $console->writeOut(
        "%s\n",
        pht('Examining commit %s...', $name));

      $refs_raw = DiffusionQuery::callConduitWithDiffusionRequest(
        $this->getViewer(),
        DiffusionRequest::newFromDictionary(
          array(
            'repository' => $repo,
            'user' => $this->getViewer(),
          )),
        'diffusion.querycommits',
        array(
          'repositoryPHID' => $repo->getPHID(),
          'phids' => array($commit->getPHID()),
          'bypassCache' => true,
        ));

      if (empty($refs_raw['data'])) {
        throw new Exception(
          pht(
            'Unable to retrieve details for commit "%s"!',
            $commit->getPHID()));
      }

      $ref = DiffusionCommitRef::newFromConduitResult(head($refs_raw['data']));

      $author = $ref->getAuthor();
      $console->writeOut(
        "%s\n",
        pht('Raw author string: %s', coalesce($author, 'null')));

      if ($author !== null) {
        $handle = $this->resolveUser($commit, $author);
        if ($handle) {
          $console->writeOut(
            "%s\n",
            pht('Phabricator user: %s', $handle->getFullName()));
        } else {
          $console->writeOut(
            "%s\n",
            pht('Unable to resolve a corresponding Phabricator user.'));
        }
      }

      $committer = $ref->getCommitter();
      $console->writeOut(
        "%s\n",
        pht('Raw committer string: %s', coalesce($committer, 'null')));

      if ($committer !== null) {
        $handle = $this->resolveUser($commit, $committer);
        if ($handle) {
          $console->writeOut(
            "%s\n",
            pht('Phabricator user: %s', $handle->getFullName()));
        } else {
          $console->writeOut(
            "%s\n",
            pht('Unable to resolve a corresponding Phabricator user.'));
        }
      }
    }

    return 0;
  }

  private function resolveUser(PhabricatorRepositoryCommit $commit, $name) {
    $phid = id(new DiffusionResolveUserQuery())
      ->withCommit($commit)
      ->withName($name)
      ->execute();

    if (!$phid) {
      return null;
    }

    return id(new PhabricatorHandleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs(array($phid))
      ->executeOne();
  }

}
