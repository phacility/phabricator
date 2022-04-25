<?php

final class PhabricatorMySQLSetupCheck extends PhabricatorSetupCheck {

  public function getDefaultGroup() {
    return self::GROUP_MYSQL;
  }

  protected function executeChecks() {
    $refs = PhabricatorDatabaseRef::getActiveDatabaseRefs();
    foreach ($refs as $ref) {
      try {
        $this->executeRefChecks($ref);
      } catch (AphrontConnectionQueryException $ex) {
        // If we're unable to connect to a host, just skip the checks for it.
        // This can happen if we're restarting during a cluster incident. See
        // T12966 for discussion.
      }
    }
  }

  private function executeRefChecks(PhabricatorDatabaseRef $ref) {
    $max_allowed_packet = $ref->loadRawMySQLConfigValue('max_allowed_packet');

    $host_name = $ref->getRefKey();

    // This primarily supports setting the filesize limit for MySQL to 8MB,
    // which may produce a >16MB packet after escaping.
    $recommended_minimum = (32 * 1024 * 1024);
    if ($max_allowed_packet < $recommended_minimum) {
      $message = pht(
        'On host "%s", MySQL is configured with a small "%s" (%d), which '.
        'may cause some large writes to fail. The recommended minimum value '.
        'for this setting is "%d".',
        $host_name,
        'max_allowed_packet',
        $max_allowed_packet,
        $recommended_minimum);

      $this->newIssue('mysql.max_allowed_packet')
        ->setName(pht('Small MySQL "%s"', 'max_allowed_packet'))
        ->setMessage($message)
        ->setDatabaseRef($ref)
        ->addMySQLConfig('max_allowed_packet');
    }

    $modes = $ref->loadRawMySQLConfigValue('sql_mode');
    $modes = explode(',', $modes);

    if (!in_array('STRICT_ALL_TABLES', $modes)) {
      $summary = pht(
        'MySQL is not in strict mode (on host "%s"), but using strict mode '.
        'is recommended.',
        $host_name);

      $message = pht(
        'On database host "%s", the global "sql_mode" setting does not '.
        'include the "STRICT_ALL_TABLES" mode. Enabling this mode is '.
        'recommended to generally improve how MySQL handles certain errors.'.
        "\n\n".
        'Without this mode enabled, MySQL will silently ignore some error '.
        'conditions, including inserts which attempt to store more data in '.
        'a column than actually fits. This behavior is usually undesirable '.
        'and can lead to data corruption (by truncating multibyte characters '.
        'in the middle), data loss (by discarding the data which does not '.
        'fit into the column), or security concerns (for example, by '.
        'truncating keys or credentials).'.
        "\n\n".
        'This software is developed and tested in "STRICT_ALL_TABLES" mode so '.
        'you should normally never encounter these situations, but may run '.
        'into them if you interact with the database directly, run '.
        'third-party code, develop extensions, or just encounter a bug in '.
        'the software.'.
        "\n\n".
        'Enabling "STRICT_ALL_TABLES" makes MySQL raise an explicit error '.
        'if one of these unusual situations does occur. This is a safer '.
        'behavior and prevents these situations from causing secret, subtle, '.
        'and potentially serious issues later on.'.
        "\n\n".
        'You can find more information about this mode (and how to configure '.
        'it) in the MySQL manual. Usually, it is sufficient to add this to '.
        'your "my.cnf" file (in the "[mysqld]" section) and then '.
        'restart "mysqld":'.
        "\n\n".
        '%s'.
        "\n".
        'Note that if you run other applications against the same database, '.
        'they may not work in strict mode.'.
        "\n\n".
        'If you can not or do not want to enable "STRICT_ALL_TABLES", you '.
        'can safely ignore this warning. This software will work correctly '.
        'with this mode enabled or disabled.',
        $host_name,
        phutil_tag('pre', array(), 'sql_mode=STRICT_ALL_TABLES'));

      $this->newIssue('sql_mode.strict')
        ->setName(pht('MySQL %s Mode Not Set', 'STRICT_ALL_TABLES'))
        ->setSummary($summary)
        ->setMessage($message)
        ->setDatabaseRef($ref)
        ->addMySQLConfig('sql_mode');
    }

    $is_innodb_fulltext = false;
    $is_myisam_fulltext = false;
    if ($this->shouldUseMySQLSearchEngine()) {
      if (PhabricatorSearchDocument::isInnoDBFulltextEngineAvailable()) {
        $is_innodb_fulltext = true;
      } else {
        $is_myisam_fulltext = true;
      }
    }

    if ($is_myisam_fulltext) {
      $stopword_file = $ref->loadRawMySQLConfigValue('ft_stopword_file');
      if ($stopword_file === null) {
        $summary = pht(
          'Your version of MySQL (on database host "%s") does not support '.
          'configuration of a stopword file. You will not be able to find '.
          'search results for common words.',
          $host_name);

        $message = pht(
          "Database host \"%s\" does not support the %s option. You will not ".
          "be able to find search results for common words. You can gain ".
          "access to this option by upgrading MySQL to a more recent ".
          "version.\n\n".
          "You can ignore this warning if you plan to configure Elasticsearch ".
          "later, or aren't concerned about searching for common words.",
          $host_name,
          phutil_tag('tt', array(), 'ft_stopword_file'));

        $this->newIssue('mysql.ft_stopword_file')
          ->setName(pht('MySQL %s Not Supported', 'ft_stopword_file'))
          ->setSummary($summary)
          ->setMessage($message)
          ->setDatabaseRef($ref)
          ->addMySQLConfig('ft_stopword_file');

      } else if ($stopword_file == '(built-in)') {
        $root = dirname(phutil_get_library_root('phabricator'));
        $stopword_path = $root.'/resources/sql/stopwords.txt';
        $stopword_path = Filesystem::resolvePath($stopword_path);

        $namespace = PhabricatorEnv::getEnvConfig('storage.default-namespace');

        $summary = pht(
          'MySQL (on host "%s") is using a default stopword file, which '.
          'will prevent searching for many common words.',
          $host_name);

        $message = pht(
          "Database host \"%s\" is using the builtin stopword file for ".
          "building search indexes. This can make the search ".
          "feature less useful.\n\n".
          "Stopwords are common words which are not indexed and thus can not ".
          "be searched for. The default stopword file has about 500 words, ".
          "including various words which you are likely to wish to search ".
          "for, such as 'various', 'likely', 'wish', and 'zero'.\n\n".
          "To make search more useful, you can use an alternate stopword ".
          "file with fewer words. Alternatively, if you aren't concerned ".
          "about searching for common words, you can ignore this warning. ".
          "If you later plan to configure Elasticsearch, you can also ignore ".
          "this warning: this stopword file only affects MySQL fulltext ".
          "indexes.\n\n".
          "To choose a different stopword file, add this to your %s file ".
          "(in the %s section) and then restart %s:\n\n".
          "%s\n".
          "(You can also use a different file if you prefer. The file ".
          "suggested above has about 50 of the most common English words.)\n\n".
          "Finally, run this command to rebuild indexes using the new ".
          "rules:\n\n".
          "%s",
          $host_name,
          phutil_tag('tt', array(), 'my.cnf'),
          phutil_tag('tt', array(), '[mysqld]'),
          phutil_tag('tt', array(), 'mysqld'),
          phutil_tag('pre', array(), 'ft_stopword_file='.$stopword_path),
          phutil_tag(
            'pre',
            array(),
            "mysql> REPAIR TABLE {$namespace}_search.search_documentfield;"));

        $this->newIssue('mysql.ft_stopword_file')
          ->setName(pht('MySQL is Using Default Stopword File'))
          ->setSummary($summary)
          ->setMessage($message)
          ->setDatabaseRef($ref)
          ->addMySQLConfig('ft_stopword_file');
      }
    }

    if ($is_myisam_fulltext) {
      $min_len = $ref->loadRawMySQLConfigValue('ft_min_word_len');
      if ($min_len >= 4) {
        $namespace = PhabricatorEnv::getEnvConfig('storage.default-namespace');

        $summary = pht(
          'MySQL is configured (on host "%s") to only index words with at '.
          'least %d characters.',
          $host_name,
          $min_len);

        $message = pht(
          "Database host \"%s\" is configured to use the default minimum word ".
          "length when building search indexes, which is 4. This means words ".
          "which are only 3 characters long will not be indexed and can not ".
          "be searched for.\n\n".
          "For example, you will not be able to find search results for words ".
          "like 'SMS', 'web', or 'DOS'.\n\n".
          "You can change this setting to 3 to allow these words to be ".
          "indexed. Alternatively, you can ignore this warning if you are ".
          "not concerned about searching for 3-letter words. If you later ".
          "plan to configure Elasticsearch, you can also ignore this warning: ".
          "only MySQL fulltext search is affected.\n\n".
          "To reduce the minimum word length to 3, add this to your %s file ".
          "(in the %s section) and then restart %s:\n\n".
          "%s\n".
          "Finally, run this command to rebuild indexes using the new ".
          "rules:\n\n".
          "%s",
          $host_name,
          phutil_tag('tt', array(), 'my.cnf'),
          phutil_tag('tt', array(), '[mysqld]'),
          phutil_tag('tt', array(), 'mysqld'),
          phutil_tag('pre', array(), 'ft_min_word_len=3'),
          phutil_tag(
            'pre',
            array(),
            "mysql> REPAIR TABLE {$namespace}_search.search_documentfield;"));

        $this->newIssue('mysql.ft_min_word_len')
          ->setName(pht('MySQL is Using Default Minimum Word Length'))
          ->setSummary($summary)
          ->setMessage($message)
          ->setDatabaseRef($ref)
          ->addMySQLConfig('ft_min_word_len');
      }
    }

    // NOTE: The default value of "innodb_ft_min_token_size" is 3, which is
    // a reasonable value, so we do not warn about it: if it is set to
    // something else, the user adjusted it on their own.

    // NOTE: We populate a stopwords table at "phabricator_search.stopwords",
    // but the default InnoDB stopword list is pretty reasonable (36 words,
    // versus 500+ in MyISAM). Just use the builtin list until we run into
    // concrete issues with it. Users can switch to our stopword table with:
    //
    // [mysqld]
    //   innodb_ft_server_stopword_table = phabricator_search/stopwords

    $innodb_pool = $ref->loadRawMySQLConfigValue('innodb_buffer_pool_size');
    $innodb_bytes = phutil_parse_bytes($innodb_pool);
    $innodb_readable = phutil_format_bytes($innodb_bytes);

    // This is arbitrary and just trying to detect values that the user
    // probably didn't set themselves. The Mac OS X default is 128MB and
    // 40% of an AWS EC2 Micro instance is 245MB, so keeping it somewhere
    // between those two values seems like a reasonable approximation.
    $minimum_readable = '225MB';

    $minimum_bytes = phutil_parse_bytes($minimum_readable);
    if ($innodb_bytes < $minimum_bytes) {
      $summary = pht(
        'MySQL (on host "%s") is configured with a very small '.
        'innodb_buffer_pool_size, which may impact performance.',
        $host_name);

      $message = pht(
        "Database host \"%s\" is configured with a very small %s (%s). ".
        "This may cause poor database performance and lock exhaustion.\n\n".
        "There are no hard-and-fast rules to setting an appropriate value, ".
        "but a reasonable starting point for a standard install is something ".
        "like 40%% of the total memory on the machine. For example, if you ".
        "have 4GB of RAM on the machine you have installed this software on, ".
        "you might set this value to %s.\n\n".
        "You can read more about this option in the MySQL documentation to ".
        "help you make a decision about how to configure it for your use ".
        "case. There are no concerns specific to this software which make it ".
        "different from normal workloads with respect to this setting.\n\n".
        "To adjust the setting, add something like this to your %s file (in ".
        "the %s section), replacing %s with an appropriate value for your ".
        "host and use case. Then restart %s:\n\n".
        "%s\n".
        "If you're satisfied with the current setting, you can safely ".
        "ignore this setup warning.",
        $host_name,
        phutil_tag('tt', array(), 'innodb_buffer_pool_size'),
        phutil_tag('tt', array(), $innodb_readable),
        phutil_tag('tt', array(), '1600M'),
        phutil_tag('tt', array(), 'my.cnf'),
        phutil_tag('tt', array(), '[mysqld]'),
        phutil_tag('tt', array(), '1600M'),
        phutil_tag('tt', array(), 'mysqld'),
        phutil_tag('pre', array(), 'innodb_buffer_pool_size=1600M'));

      $this->newIssue('mysql.innodb_buffer_pool_size')
        ->setName(pht('MySQL May Run Slowly'))
        ->setSummary($summary)
        ->setMessage($message)
        ->setDatabaseRef($ref)
        ->addMySQLConfig('innodb_buffer_pool_size');
    }

    $conn = $ref->newManagementConnection();

    $ok = PhabricatorStorageManagementAPI::isCharacterSetAvailableOnConnection(
      'utf8mb4',
      $conn);
    if (!$ok) {
      $summary = pht(
        'You are using an old version of MySQL (on host "%s"), and should '.
        'upgrade.',
        $host_name);

      $message = pht(
        'You are using an old version of MySQL (on host "%s") which has poor '.
        'unicode support (it does not support the "utf8mb4" collation set). '.
        'You will encounter limitations when working with some unicode data.'.
        "\n\n".
        'We strongly recommend you upgrade to MySQL 5.5 or newer.',
        $host_name);

      $this->newIssue('mysql.utf8mb4')
        ->setName(pht('Old MySQL Version'))
        ->setSummary($summary)
        ->setDatabaseRef($ref)
        ->setMessage($message);
    }

    $info = queryfx_one(
      $conn,
      'SELECT UNIX_TIMESTAMP() epoch');

    $epoch = (int)$info['epoch'];
    $local = PhabricatorTime::getNow();
    $delta = (int)abs($local - $epoch);
    if ($delta > 60) {
      $this->newIssue('mysql.clock')
        ->setName(pht('Major Web/Database Clock Skew'))
        ->setSummary(
          pht(
            'This web host ("%s") is set to a very different time than a '.
            'database host "%s".',
            php_uname('n'),
            $host_name))
        ->setMessage(
          pht(
            'A database host ("%s") and this web host ("%s") disagree on the '.
            'current time by more than 60 seconds (absolute skew is %s '.
            'seconds). Check that the current time is set correctly '.
            'everywhere.',
            $host_name,
            php_uname('n'),
            new PhutilNumber($delta)));
    }

    $local_infile = $ref->loadRawMySQLConfigValue('local_infile');
    if ($local_infile) {
      $summary = pht(
        'The MySQL "local_infile" option is enabled. This option is '.
        'unsafe.');

      $message = pht(
        'Your MySQL server is configured with the "local_infile" option '.
        'enabled. This option allows an attacker who finds an SQL injection '.
        'hole to escalate their attack by copying files from the webserver '.
        'into the database with "LOAD DATA LOCAL INFILE" queries, then '.
        'reading the file content with "SELECT" queries.'.
        "\n\n".
        'You should disable this option in your %s file, in the %s section:'.
        "\n\n".
        '%s',
        phutil_tag('tt', array(), 'my.cnf'),
        phutil_tag('tt', array(), '[mysqld]'),
        phutil_tag('pre', array(), 'local_infile=0'));

      $this->newIssue('mysql.local_infile')
        ->setName(pht('Unsafe MySQL "local_infile" Setting Enabled'))
        ->setSummary($summary)
        ->setMessage($message)
        ->setDatabaseRef($ref)
        ->addMySQLConfig('local_infile');
    }

  }

  protected function shouldUseMySQLSearchEngine() {
    $services = PhabricatorSearchService::getAllServices();
    foreach ($services as $service) {
      if ($service instanceof PhabricatorMySQLSearchHost) {
        return true;
      }
    }
    return false;
  }

}
