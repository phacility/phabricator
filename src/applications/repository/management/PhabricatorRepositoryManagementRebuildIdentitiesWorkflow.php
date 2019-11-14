<?php

final class PhabricatorRepositoryManagementRebuildIdentitiesWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  private $identityCache = array();

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
      $needs_update = false;

      $data = $commit->getCommitData();
      $author_name = $data->getAuthorName();

      $author_identity = $this->getIdentityForCommit(
        $commit,
        $author_name);

      $author_phid = $commit->getAuthorIdentityPHID();
      $identity_phid = $author_identity->getPHID();
      if ($author_phid !== $identity_phid) {
        $commit->setAuthorIdentityPHID($identity_phid);
        $data->setCommitDetail('authorIdentityPHID', $identity_phid);
        $needs_update = true;
      }

      $committer_name = $data->getCommitDetail('committer', null);
      $committer_phid = $commit->getCommitterIdentityPHID();
      if (strlen($committer_name)) {
        $committer_identity = $this->getIdentityForCommit(
          $commit,
          $committer_name);
        $identity_phid = $committer_identity->getPHID();
      } else {
        $identity_phid = null;
      }

      if ($committer_phid !== $identity_phid) {
        $commit->setCommitterIdentityPHID($identity_phid);
        $data->setCommitDetail('committerIdentityPHID', $identity_phid);
        $needs_update = true;
      }

      if ($needs_update) {
        $commit->save();
        $data->save();
        echo tsprintf(
          "Rebuilt identities for %s.\n",
          $commit->getDisplayName());
      } else {
        echo tsprintf(
          "No changes for %s.\n",
          $commit->getDisplayName());
      }
    }

  }

  private function getIdentityForCommit(
    PhabricatorRepositoryCommit $commit,
    $raw_identity) {

    if (!isset($this->identityCache[$raw_identity])) {
      $viewer = $this->getViewer();

      $identity = id(new DiffusionRepositoryIdentityEngine())
        ->setViewer($viewer)
        ->setSourcePHID($commit->getPHID())
        ->newResolvedIdentity($raw_identity);

      $this->identityCache[$raw_identity] = $identity;
    }

    return $this->identityCache[$raw_identity];
  }

}
