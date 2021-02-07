<?php

final class PhabricatorRepositoryManagementReparseWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('reparse')
      ->setExamples('**reparse** [options] __commit__')
      ->setSynopsis(
        pht(
          '**reparse** __what__ __which_parts__ [--trace] [--force]'."\n\n".
          'Rerun the Diffusion parser on specific commits and repositories. '.
          'Mostly useful for debugging changes to Diffusion.'."\n\n".
          'e.g. do same but exclude before yesterday (local time):'."\n".
          'repository reparse --all TEST --change --min-date yesterday'."\n".
          'repository reparse --all TEST --change --min-date "today -1 day".'.
          "\n\n".
          'e.g. do same but exclude before 03/31/2013 (local time):'."\n".
          'repository reparse --all TEST --change --min-date "03/31/2013"'))
      ->setArguments(
        array(
          array(
            'name'     => 'revision',
            'wildcard' => true,
          ),
          array(
            'name'     => 'all',
            'param'    => 'repository',
            'help'     => pht(
              'Reparse all commits in the specified repository.'),
          ),
          array(
            'name'     => 'min-date',
            'param'    => 'date',
            'help'     => pht(
              "Must be used with __%s__, this will exclude commits which ".
              "are earlier than __date__.\n".
              "Valid examples:\n".
              "  'today', 'today 2pm', '-1 hour', '-2 hours', '-24 hours',\n".
              "  'yesterday', 'today -1 day', 'yesterday 2pm', '2pm -1 day',\n".
              "  'last Monday', 'last Monday 14:00', 'last Monday 2pm',\n".
              "  '31 March 2013', '31 Mar', '03/31', '03/31/2013',\n".
              "See __%s__ for more.",
              '--all',
              'http://www.php.net/manual/en/datetime.formats.php'),
          ),
          array(
            'name'     => 'message',
            'help'     => pht('Reparse commit messages.'),
          ),
          array(
            'name'     => 'change',
            'help'     => pht('Reparse source changes.'),
          ),
          array(
            'name'     => 'publish',
            'help'     => pht(
              'Publish changes: send email, publish Feed stories, run '.
              'Herald rules, etc.'),
          ),
          array(
            'name'     => 'force',
            'short'    => 'f',
            'help'     => pht('Act noninteractively, without prompting.'),
          ),
          array(
            'name'     => 'background',
            'help'     => pht(
              'Queue tasks for the daemons instead of running them in the '.
              'foreground.'),
          ),
          array(
            'name' => 'importing',
            'help' => pht('Reparse all steps which have not yet completed.'),
          ),
        ));

  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $all_from_repo = $args->getArg('all');
    $reparse_message = $args->getArg('message');
    $reparse_change = $args->getArg('change');
    $reparse_publish = $args->getArg('publish');
    $reparse_what = $args->getArg('revision');
    $force = $args->getArg('force');
    $background = $args->getArg('background');
    $min_date = $args->getArg('min-date');
    $importing = $args->getArg('importing');

    if (!$all_from_repo && !$reparse_what) {
      throw new PhutilArgumentUsageException(
        pht('Specify a commit or repository to reparse.'));
    }

    if ($all_from_repo && $reparse_what) {
      $commits = implode(', ', $reparse_what);
      throw new PhutilArgumentUsageException(
        pht(
          "Specify a commit or repository to reparse, not both:\n".
          "All from repo: %s\n".
          "Commit(s) to reparse: %s",
          $all_from_repo,
          $commits));
    }

    $any_step = ($reparse_message || $reparse_change || $reparse_publish);

    if ($any_step && $importing) {
      throw new PhutilArgumentUsageException(
        pht(
          'Choosing steps with "--importing" conflicts with flags which '.
          'select specific steps.'));
    } else if ($any_step) {
      // OK.
    } else if ($importing) {
      // OK.
    } else if (!$any_step && !$importing) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify which steps to reparse with "--message", "--change", '.
          'and/or "--publish"; or "--importing" to run all missing steps.'));
    }

    $min_timestamp = false;
    if ($min_date) {
      $min_timestamp = strtotime($min_date);

      if (!$all_from_repo) {
        throw new PhutilArgumentUsageException(
          pht(
            'You must use "--all" if you specify "--min-date".'));
      }

      // previous to PHP 5.1.0 you would compare with -1, instead of false
      if (false === $min_timestamp) {
        throw new PhutilArgumentUsageException(
          pht(
            "Supplied --min-date is not valid. See help for valid examples.\n".
            "Supplied value: '%s'\n",
            $min_date));
      }
    }

    $commits = array();
    if ($all_from_repo) {
      $repository = id(new PhabricatorRepositoryQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withIdentifiers(array($all_from_repo))
        ->executeOne();

      if (!$repository) {
        throw new PhutilArgumentUsageException(
          pht('Unknown repository "%s"!', $all_from_repo));
      }

      $query = id(new DiffusionCommitQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withRepository($repository);

      if ($min_timestamp) {
        $query->withEpochRange($min_timestamp, null);
      }

      if ($importing) {
        $query->withImporting(true);
      }

      $commits = $query->execute();

      if (!$commits) {
        throw new PhutilArgumentUsageException(
          pht(
            'No commits have been discovered in the "%s" repository!',
            $repository->getDisplayName()));
      }
    } else {
      $commits = $this->loadNamedCommits($reparse_what);
    }

    if (!$background) {
      PhabricatorWorker::setRunAllTasksInProcess(true);
    }

    $progress = new PhutilConsoleProgressBar();
    $progress->setTotal(count($commits));

    $tasks = array();
    foreach ($commits as $commit) {
      $repository = $commit->getRepository();

      if ($importing) {
        $status = $commit->getImportStatus();
        // Find the first missing import step and queue that up.
        $reparse_message = false;
        $reparse_change = false;
        $reparse_publish = false;
        if (!($status & PhabricatorRepositoryCommit::IMPORTED_MESSAGE)) {
          $reparse_message = true;
        } else if (!($status & PhabricatorRepositoryCommit::IMPORTED_CHANGE)) {
          $reparse_change = true;
        } else if (!($status & PhabricatorRepositoryCommit::IMPORTED_PUBLISH)) {
          $reparse_publish = true;
        } else {
          continue;
        }
      }

      $classes = array();
      switch ($repository->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        if ($reparse_message) {
          $classes[] = 'PhabricatorRepositoryGitCommitMessageParserWorker';
        }
        if ($reparse_change) {
          $classes[] = 'PhabricatorRepositoryGitCommitChangeParserWorker';
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        if ($reparse_message) {
          $classes[] =
            'PhabricatorRepositoryMercurialCommitMessageParserWorker';
        }
        if ($reparse_change) {
          $classes[] = 'PhabricatorRepositoryMercurialCommitChangeParserWorker';
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        if ($reparse_message) {
          $classes[] = 'PhabricatorRepositorySvnCommitMessageParserWorker';
        }
        if ($reparse_change) {
          $classes[] = 'PhabricatorRepositorySvnCommitChangeParserWorker';
        }
        break;
      }

      if ($reparse_publish) {
        $classes[] = 'PhabricatorRepositoryCommitPublishWorker';
      }

      // NOTE: With "--importing", we queue the first unparsed step and let
      // it queue the other ones normally. Without "--importing", we queue
      // all the requested steps explicitly.

      $spec = array(
        'commitPHID' => $commit->getPHID(),
        'only' => !$importing,
        'via' => 'reparse',
      );

      foreach ($classes as $class) {
        try {
          PhabricatorWorker::scheduleTask(
            $class,
            $spec,
            array(
              'priority' => PhabricatorWorker::PRIORITY_IMPORT,
              'objectPHID' => $commit->getPHID(),
              'containerPHID' => $repository->getPHID(),
            ));
        } catch (PhabricatorWorkerPermanentFailureException $ex) {
          // See T13315. We expect some reparse steps to occasionally raise
          // permanent failures: for example, because they are no longer
          // reachable. This is a routine condition, not a catastrophic
          // failure, so let the user know something happened but continue
          // reparsing any remaining commits.
          echo tsprintf(
            "<bg:yellow>** %s **</bg> %s\n",
            pht('WARN'),
            $ex->getMessage());
        }
      }

      $progress->update(1);
    }

    $progress->done();

    return 0;
  }

}
