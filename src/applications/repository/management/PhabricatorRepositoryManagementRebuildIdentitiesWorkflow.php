<?php

final class PhabricatorRepositoryManagementRebuildIdentitiesWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('rebuild-identities')
      ->setExamples(
        '**rebuild-identities** [__options__] __repository__')
      ->setSynopsis(pht('Rebuild repository identities from commits.'))
      ->setArguments(
        array(
          array(
            'name'    => 'repositories',
            'wildcard' => true,
          ),
          array(
            'name'     => 'all',
            'help'     => pht('Rebuild identities across all repositories.'),
        ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $all = $args->getArg('all');
    $repositories = $args->getArg('repositories');

    if ($all xor empty($repositories)) {
      throw new PhutilArgumentUsageException(
        pht('Specify --all or a list of repositories, but not both.'));
    }

    $query = id(new DiffusionCommitQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->needCommitData(true);

    if ($repositories) {
      $repos = $this->loadRepositories($args, 'repositories');
      $query->withRepositoryIDs(mpull($repos, 'getID'));
    }

    $iterator = new PhabricatorQueryIterator($query);
    foreach ($iterator as $commit) {
      $data = $commit->getCommitData();
      $author_name = $data->getAuthorName();
      $author_identity = $this->getIdentityForCommit(
        $commit, $author_name);

      $commit->setAuthorIdentityPHID($author_identity->getPHID());
      $data->setCommitDetail(
        'authorIdentityPHID', $author_identity->getPHID());

      $committer_name = $data->getCommitDetail('committer', null);
      if ($committer_name) {
        $committer_identity = $this->getIdentityForCommit(
          $commit, $committer_name);

        $commit->setCommitterIdentityPHID($committer_identity->getPHID());
        $data->setCommitDetail(
          'committerIdentityPHID', $committer_identity->getPHID());
      }

      $commit->save();
      $data->save();
    }

  }

  private function getIdentityForCommit(
    PhabricatorRepositoryCommit $commit, $identity_name) {

    static $seen = array();
    $identity_key = PhabricatorHash::digestForIndex($identity_name);
    if (empty($seen[$identity_key])) {
      try {
        $user_phid = id(new DiffusionResolveUserQuery())
          ->withCommit($commit)
          ->withName($identity_name)
          ->execute();

        $identity = id(new PhabricatorRepositoryIdentity())
          ->setAuthorPHID($commit->getPHID())
          ->setIdentityName($identity_name)
          ->setAutomaticGuessedUserPHID($user_phid)
          ->save();
      } catch (AphrontDuplicateKeyQueryException $ex) {
          // Somehow this identity already exists?
        $identity = id(new PhabricatorRepositoryIdentityQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withIdentityNames(array($identity_name))
          ->executeOne();
      }
      $seen[$identity_key] = $identity;
    }

    return $seen[$identity_key];
  }
}
