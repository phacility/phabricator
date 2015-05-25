<?php

final class PhabricatorRepositoryManagementReparseWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('reparse')
      ->setExamples('**reparse** [options] __repository__')
      ->setSynopsis(
        pht(
          '**reparse** __what__ __which_parts__ [--trace] [--force]'."\n\n".
          'Rerun the Diffusion parser on specific commits and repositories. '.
          'Mostly useful for debugging changes to Diffusion.'."\n\n".
          'e.g. enqueue reparse owners in the TEST repo for all commits:'."\n".
          'repository reparse --all TEST --owners'."\n\n".
          'e.g. do same but exclude before yesterday (local time):'."\n".
          'repository reparse --all TEST --owners --min-date yesterday'."\n".
          'repository reparse --all TEST --owners --min-date "today -1 day".'.
          "\n\n".
          'e.g. do same but exclude before 03/31/2013 (local time):'."\n".
          'repository reparse --all TEST --owners --min-date "03/31/2013"'))
      ->setArguments(
        array(
          array(
            'name'     => 'revision',
            'wildcard' => true,
          ),
          array(
            'name'     => 'all',
            'param'    => 'callsign or phid',
            'help'     => pht(
              'Reparse all commits in the specified repository. This mode '.
              'queues parsers into the task queue; you must run taskmasters '.
              'to actually do the parses. Use with __%s__ to run '.
              'the tasks locally instead of with taskmasters.',
              '--force-local'),
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
            'help'     => pht('Reparse changes.'),
          ),
          array(
            'name'     => 'herald',
            'help'     => pht(
              'Reevaluate Herald rules (may send huge amounts of email!)'),
          ),
          array(
            'name'     => 'owners',
            'help'     => pht(
              'Reevaluate related commits for owners packages (may delete '.
              'existing relationship entries between your package and some '.
              'old commits!)'),
          ),
          array(
            'name'     => 'force',
            'short'    => 'f',
            'help'     => pht('Act noninteractively, without prompting.'),
          ),
          array(
            'name'     => 'force-local',
            'help'     => pht(
              'Only used with __%s__, use this to run the tasks locally '.
              'instead of deferring them to taskmaster daemons.',
              '--all'),
          ),
          array(
            'name'    => 'force-autoclose',
            'help'    => pht(
              'Only used with __%s, use this to make sure any '.
              'pertinent diffs are closed regardless of configuration.',
              '--message__'),
          ),
        ));

  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $all_from_repo = $args->getArg('all');
    $reparse_message = $args->getArg('message');
    $reparse_change = $args->getArg('change');
    $reparse_herald = $args->getArg('herald');
    $reparse_owners = $args->getArg('owners');
    $reparse_what = $args->getArg('revision');
    $force = $args->getArg('force');
    $force_local = $args->getArg('force-local');
    $min_date = $args->getArg('min-date');

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

    if (!$reparse_message && !$reparse_change && !$reparse_herald &&
      !$reparse_owners) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify what information to reparse with %s, %s, %s, and/or %s.',
          '--message',
          '--change',
          '--herald',
          '--owners'));
      }

    $min_timestamp = false;
    if ($min_date) {
      $min_timestamp = strtotime($min_date);

      if (!$all_from_repo) {
        throw new PhutilArgumentUsageException(
          pht(
            "You must use --all if you specify --min-date\n".
            "e.g.\n".
            "  repository reparse --all TEST --owners --min-date yesterday"));
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

    if ($reparse_owners && !$force) {
      $console->writeOut(
        "%s\n",
        pht(
          'You are about to recreate the relationship entries between the '.
          'commits and the packages they touch. This might delete some '.
          'existing relationship entries for some old commits.'));

      if (!phutil_console_confirm(pht('Are you ready to continue?'))) {
        throw new PhutilArgumentUsageException(pht('Cancelled.'));
      }
    }

    $commits = array();
    if ($all_from_repo) {
      $repository = id(new PhabricatorRepository())->loadOneWhere(
        'callsign = %s OR phid = %s',
        $all_from_repo,
        $all_from_repo);
      if (!$repository) {
        throw new PhutilArgumentUsageException(
          pht('Unknown repository %s!', $all_from_repo));
      }
      $constraint = '';
      if ($min_timestamp) {
        $console->writeOut("%s\n", pht(
          'Excluding entries before UNIX timestamp: %s',
          $min_timestamp));
        $table = new PhabricatorRepositoryCommit();
        $conn_r = $table->establishConnection('r');
        $constraint = qsprintf(
          $conn_r,
          'AND epoch >= %d',
          $min_timestamp);
      }
      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'repositoryID = %d %Q',
        $repository->getID(),
        $constraint);
      $callsign = $repository->getCallsign();
      if (!$commits) {
        throw new PhutilArgumentUsageException(pht(
          "No commits have been discovered in %s repository!\n",
          $callsign));
      }
    } else {
      $commits = array();
      foreach ($reparse_what as $identifier) {
        $matches = null;
        if (!preg_match('/r([A-Z]+)([a-z0-9]+)/', $identifier, $matches)) {
          throw new PhutilArgumentUsageException(pht(
            "Can't parse commit identifier: %s",
            $identifier));
        }
        $callsign = $matches[1];
        $commit_identifier = $matches[2];
        $repository = id(new PhabricatorRepository())->loadOneWhere(
          'callsign = %s',
          $callsign);
        if (!$repository) {
          throw new PhutilArgumentUsageException(pht(
            "No repository with callsign '%s'!",
            $callsign));
        }
        $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
          'repositoryID = %d AND commitIdentifier = %s',
          $repository->getID(),
          $commit_identifier);
        if (!$commit) {
          throw new PhutilArgumentUsageException(pht(
            "No matching commit '%s' in repository '%s'. ".
            "(For git and mercurial repositories, you must specify the entire ".
            "commit hash.)",
            $commit_identifier,
            $callsign));
        }
        $commits[] = $commit;
      }
    }

    if ($all_from_repo && !$force_local) {
      $console->writeOut("%s\n", pht(
        '**NOTE**: This script will queue tasks to reparse the data. Once the '.
        'tasks have been queued, you need to run Taskmaster daemons to '.
        'execute them.'."\n\n".
        "QUEUEING TASKS (%s Commits):",
        new PhutilNumber(count($commits))));
    }

    $progress = new PhutilConsoleProgressBar();
    $progress->setTotal(count($commits));

    $tasks = array();
    foreach ($commits as $commit) {
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

      if ($reparse_herald) {
        $classes[] = 'PhabricatorRepositoryCommitHeraldWorker';
      }

      if ($reparse_owners) {
        $classes[] = 'PhabricatorRepositoryCommitOwnersWorker';
      }

      $spec = array(
        'commitID'  => $commit->getID(),
        'only'      => true,
        'forceAutoclose' => $args->getArg('force-autoclose'),
      );

      if ($all_from_repo && !$force_local) {
        foreach ($classes as $class) {
          PhabricatorWorker::scheduleTask(
            $class,
            $spec,
            array(
              'priority' => PhabricatorWorker::PRIORITY_IMPORT,
            ));
        }
      } else {
        foreach ($classes as $class) {
          $worker = newv($class, array($spec));
          $worker->executeTask();
        }
      }

      $progress->update(1);
    }

    $progress->done();

    return 0;
  }

}
