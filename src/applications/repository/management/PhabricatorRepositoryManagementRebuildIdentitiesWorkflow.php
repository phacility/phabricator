<?php

final class PhabricatorRepositoryManagementRebuildIdentitiesWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  private $identityCache = array();
  private $phidCache = array();
  private $dryRun;

  protected function didConstruct() {
    $this
      ->setName('rebuild-identities')
      ->setExamples(
        '**rebuild-identities** [__options__] __repository__')
      ->setSynopsis(pht('Rebuild repository identities from commits.'))
      ->setArguments(
        array(
          array(
            'name' => 'all-repositories',
            'help' => pht('Rebuild identities across all repositories.'),
          ),
          array(
            'name' => 'all-identities',
            'help' => pht('Rebuild all currently-known identities.'),
          ),
          array(
            'name' => 'repository',
            'param' => 'repository',
            'repeat' => true,
            'help' => pht('Rebuild identities in a repository.'),
          ),
          array(
            'name' => 'commit',
            'param' => 'commit',
            'repeat' => true,
            'help' => pht('Rebuild identities for a commit.'),
          ),
          array(
            'name' => 'user',
            'param' => 'user',
            'repeat' => true,
            'help' => pht('Rebuild identities for a user.'),
          ),
          array(
            'name' => 'email',
            'param' => 'email',
            'repeat' => true,
            'help' => pht('Rebuild identities for an email address.'),
          ),
          array(
            'name' => 'raw',
            'param' => 'raw',
            'repeat' => true,
            'help' => pht('Rebuild identities for a raw commit string.'),
          ),
          array(
            'name' => 'dry-run',
            'help' => pht('Show changes, but do not make any changes.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $rebuilt_anything = false;

    $all_repositories = $args->getArg('all-repositories');
    $repositories = $args->getArg('repository');

    if ($all_repositories && $repositories) {
      throw new PhutilArgumentUsageException(
        pht(
          'Flags "--all-repositories" and "--repository" are not '.
          'compatible.'));
    }


    $all_identities = $args->getArg('all-identities');
    $raw = $args->getArg('raw');

    if ($all_identities && $raw) {
      throw new PhutilArgumentUsageException(
        pht(
          'Flags "--all-identities" and "--raw" are not '.
          'compatible.'));
    }

    $dry_run = $args->getArg('dry-run');
    $this->dryRun = $dry_run;

    if ($this->dryRun) {
      $this->logWarn(
        pht('DRY RUN'),
        pht('This is a dry run, so no changes will be written.'));
    }

    if ($all_repositories || $repositories) {
      $rebuilt_anything = true;

      if ($repositories) {
        $repository_list = $this->loadRepositories($args, 'repository');
      } else {
        $repository_query = id(new PhabricatorRepositoryQuery())
          ->setViewer($viewer);
        $repository_list = new PhabricatorQueryIterator($repository_query);
      }

      foreach ($repository_list as $repository) {
        $commit_query = id(new DiffusionCommitQuery())
          ->setViewer($viewer)
          ->needCommitData(true)
          ->withRepositoryIDs(array($repository->getID()));

        // See T13457. Adjust ordering to hit keys better and tweak page size
        // to improve performance slightly, since these records are small.
        $commit_query->setOrderVector(array('-epoch', '-id'));

        $commit_iterator = id(new PhabricatorQueryIterator($commit_query))
          ->setPageSize(1000);

        $this->rebuildCommits($commit_iterator);
      }
    }

    $commits = $args->getArg('commit');
    if ($commits) {
      $rebuilt_anything = true;
      $commit_list = $this->loadCommits($args, 'commit');

      // Reload commits to get commit data.
      $commit_list = id(new DiffusionCommitQuery())
        ->setViewer($viewer)
        ->needCommitData(true)
        ->withIDs(mpull($commit_list, 'getID'))
        ->execute();

      $this->rebuildCommits($commit_list);
    }

    $users = $args->getArg('user');
    if ($users) {
      $rebuilt_anything = true;

      $user_list = $this->loadUsersFromArguments($users);
      $this->rebuildUsers($user_list);
    }

    $emails = $args->getArg('email');
    if ($emails) {
      $rebuilt_anything = true;
      $this->rebuildEmails($emails);
    }

    if ($all_identities || $raw) {
      $rebuilt_anything = true;

      if ($raw) {
        $identities = id(new PhabricatorRepositoryIdentityQuery())
          ->setViewer($viewer)
          ->withIdentityNames($raw)
          ->execute();

        $identities = mpull($identities, null, 'getIdentityNameRaw');
        foreach ($raw as $raw_identity) {
          if (!isset($identities[$raw_identity])) {
            throw new PhutilArgumentUsageException(
              pht(
                'No identity "%s" exists. When selecting identities with '.
                '"--raw", the entire identity must match exactly.',
                $raw_identity));
          }
        }

        $identity_list = $identities;
      } else {
        $identity_query = id(new PhabricatorRepositoryIdentityQuery())
          ->setViewer($viewer);

        $identity_list = new PhabricatorQueryIterator($identity_query);

        $this->logInfo(
          pht('REBUILD'),
          pht('Rebuilding all existing identities.'));
      }

      $this->rebuildIdentities($identity_list);
    }

    if (!$rebuilt_anything) {
      throw new PhutilArgumentUsageException(
        pht(
          'Nothing specified to rebuild. Use flags to choose which '.
          'identities to rebuild, or "--help" for help.'));
    }

    return 0;
  }

  private function rebuildCommits($commits) {
    foreach ($commits as $commit) {
      $needs_update = false;

      $data = $commit->getCommitData();
      $author = $data->getAuthorString();

      $author_identity = $this->getIdentityForCommit(
        $commit,
        $author);

      $author_phid = $commit->getAuthorIdentityPHID();
      $identity_phid = $author_identity->getPHID();

      $aidentity_phid = $identity_phid;
      if ($author_phid !== $identity_phid) {
        $commit->setAuthorIdentityPHID($identity_phid);
        $data->setCommitDetail('authorIdentityPHID', $identity_phid);
        $needs_update = true;
      }

      $committer_name = $data->getCommitterString();
      $committer_phid = $commit->getCommitterIdentityPHID();
      if (phutil_nonempty_string($committer_name)) {
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

        $this->logInfo(
          pht('COMMIT'),
          pht(
            'Rebuilt identities for "%s".',
            $commit->getDisplayName()));
      } else {
        $this->logInfo(
          pht('SKIP'),
          pht(
            'No changes for commit "%s".',
            $commit->getDisplayName()));
      }
    }
  }

  private function getIdentityForCommit(
    PhabricatorRepositoryCommit $commit,
    $raw_identity) {

    if (!isset($this->identityCache[$raw_identity])) {
      $identity = $this->newIdentityEngine()
        ->setSourcePHID($commit->getPHID())
        ->newResolvedIdentity($raw_identity);

      $this->identityCache[$raw_identity] = $identity;
    }

    return $this->identityCache[$raw_identity];
  }


  private function rebuildUsers($users) {
    $viewer = $this->getViewer();

    foreach ($users as $user) {
      $this->logInfo(
        pht('USER'),
        pht(
          'Rebuilding identities for user "%s".',
          $user->getMonogram()));

      $emails = id(new PhabricatorUserEmail())->loadAllWhere(
        'userPHID = %s',
        $user->getPHID());
      if ($emails) {
        $this->rebuildEmails(mpull($emails, 'getAddress'));
      }

      $identities = id(new PhabricatorRepositoryIdentityQuery())
        ->setViewer($viewer)
        ->withRelatedPHIDs(array($user->getPHID()))
        ->execute();

      if (!$identities) {
        $this->logWarn(
          pht('NO IDENTITIES'),
          pht('Found no identities directly related to user.'));
        continue;
      }

      $this->rebuildIdentities($identities);
    }
  }

  private function rebuildEmails($emails) {
    $viewer = $this->getViewer();

    foreach ($emails as $email) {
      $this->logInfo(
        pht('EMAIL'),
        pht('Rebuilding identities for email address "%s".', $email));

      $identities = id(new PhabricatorRepositoryIdentityQuery())
        ->setViewer($viewer)
        ->withEmailAddresses(array($email))
        ->execute();

      if (!$identities) {
        $this->logWarn(
          pht('NO IDENTITIES'),
          pht('Found no identities for email address "%s".', $email));
        continue;
      }

      $this->rebuildIdentities($identities);
    }
  }

  private function rebuildIdentities($identities) {
    $dry_run = $this->dryRun;

    foreach ($identities as $identity) {
      $raw_identity = $identity->getIdentityName();

      if (isset($this->identityCache[$raw_identity])) {
        $this->logInfo(
          pht('SKIP'),
          pht(
            'Identity "%s" has already been rebuilt.',
            $raw_identity));
        continue;
      }

      $this->logInfo(
        pht('IDENTITY'),
        pht(
          'Rebuilding identity "%s".',
          $raw_identity));

      $old_auto = $identity->getAutomaticGuessedUserPHID();
      $old_assign = $identity->getManuallySetUserPHID();

      $identity = $this->newIdentityEngine()
        ->newUpdatedIdentity($identity);

      $this->identityCache[$raw_identity] = $identity;

      $new_auto = $identity->getAutomaticGuessedUserPHID();
      $new_assign = $identity->getManuallySetUserPHID();

      $same_auto = ($old_auto === $new_auto);
      $same_assign = ($old_assign === $new_assign);

      if ($same_auto && $same_assign) {
        $this->logInfo(
          pht('UNCHANGED'),
          pht('No changes to identity.'));
      } else {
        if (!$same_auto) {
          if ($dry_run) {
            $this->logWarn(
              pht('DETECTED PHID'),
              pht(
                '(Dry Run) Would update detected user from "%s" to "%s".',
                $this->renderPHID($old_auto),
                $this->renderPHID($new_auto)));
          } else {
            $this->logWarn(
              pht('DETECTED PHID'),
              pht(
                'Detected user updated from "%s" to "%s".',
                $this->renderPHID($old_auto),
                $this->renderPHID($new_auto)));
          }
        }
        if (!$same_assign) {
          if ($dry_run) {
            $this->logWarn(
              pht('ASSIGNED PHID'),
              pht(
                '(Dry Run) Would update assigned user from "%s" to "%s".',
                $this->renderPHID($old_assign),
                $this->renderPHID($new_assign)));
          } else {
            $this->logWarn(
              pht('ASSIGNED PHID'),
              pht(
                'Assigned user updated from "%s" to "%s".',
                $this->renderPHID($old_assign),
                $this->renderPHID($new_assign)));
          }
        }
      }
    }
  }

  private function renderPHID($phid) {
    if ($phid == null) {
      return pht('NULL');
    }

    if (!isset($this->phidCache[$phid])) {
      $viewer = $this->getViewer();
      $handles = $viewer->loadHandles(array($phid));
      $this->phidCache[$phid] = pht(
        '%s <%s>',
        $handles[$phid]->getFullName(),
        $phid);
    }

    return $this->phidCache[$phid];
  }

  private function newIdentityEngine() {
    $viewer = $this->getViewer();

    return id(new DiffusionRepositoryIdentityEngine())
      ->setViewer($viewer)
      ->setDryRun($this->dryRun);
  }

}
