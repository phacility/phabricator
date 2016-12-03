<?php

abstract class PhabricatorStorageManagementWorkflow
  extends PhabricatorManagementWorkflow {

  private $apis = array();
  private $dryRun;
  private $force;
  private $patches;

  private $didInitialize;

  final public function setAPIs(array $apis) {
    $this->apis = $apis;
    return $this;
  }

  final public function getAnyAPI() {
    return head($this->getAPIs());
  }

  final public function getMasterAPIs() {
    $apis = $this->getAPIs();

    $results = array();
    foreach ($apis as $api) {
      if ($api->getRef()->getIsMaster()) {
        $results[] = $api;
      }
    }

    if (!$results) {
      throw new PhutilArgumentUsageException(
        pht(
          'This command only operates on database masters, but the selected '.
          'database hosts do not include any masters.'));
    }

    return $results;
  }

  final public function getSingleAPI() {
    $apis = $this->getAPIs();
    if (count($apis) == 1) {
      return head($apis);
    }

    throw new PhutilArgumentUsageException(
      pht(
        'Phabricator is configured in cluster mode, with multiple database '.
        'hosts. Use "--host" to specify which host you want to operate on.'));
  }

  final public function getAPIs() {
    return $this->apis;
  }

  final protected function isDryRun() {
    return $this->dryRun;
  }

  final protected function setDryRun($dry_run) {
    $this->dryRun = $dry_run;
    return $this;
  }

  final protected function isForce() {
    return $this->force;
  }

  final protected function setForce($force) {
    $this->force = $force;
    return $this;
  }

  public function getPatches() {
    return $this->patches;
  }

  public function setPatches(array $patches) {
    assert_instances_of($patches, 'PhabricatorStoragePatch');
    $this->patches = $patches;
    return $this;
  }

  protected function isReadOnlyWorkflow() {
    return false;
  }

  public function execute(PhutilArgumentParser $args) {
    $this->setDryRun($args->getArg('dryrun'));
    $this->setForce($args->getArg('force'));

    if (!$this->isReadOnlyWorkflow()) {
      if (PhabricatorEnv::isReadOnly()) {
        if ($this->isForce()) {
          PhabricatorEnv::setReadOnly(false, null);
        } else {
          throw new PhutilArgumentUsageException(
            pht(
              'Phabricator is currently in read-only mode. Use --force to '.
              'override this mode.'));
        }
      }
    }

    return $this->didExecute($args);
  }

  public function didExecute(PhutilArgumentParser $args) {}

  private function loadSchemata(PhabricatorStorageManagementAPI $api) {
    $query = id(new PhabricatorConfigSchemaQuery());

    $ref = $api->getRef();
    $ref_key = $ref->getRefKey();

    $query->setAPIs(array($api));
    $query->setRefs(array($ref));

    $actual = $query->loadActualSchemata();
    $expect = $query->loadExpectedSchemata();
    $comp = $query->buildComparisonSchemata($expect, $actual);

    return array(
      $comp[$ref_key],
      $expect[$ref_key],
      $actual[$ref_key],
    );
  }

  final protected function adjustSchemata(
    PhabricatorStorageManagementAPI $api,
    $unsafe) {

    $lock = $this->lock($api);

    try {
      $err = $this->doAdjustSchemata($api, $unsafe);
    } catch (Exception $ex) {
      $lock->unlock();
      throw $ex;
    }

    $lock->unlock();

    return $err;
  }

  final private function doAdjustSchemata(
    PhabricatorStorageManagementAPI $api,
    $unsafe) {

    $console = PhutilConsole::getConsole();

    $console->writeOut(
      "%s\n",
      pht(
        'Verifying database schemata on "%s"...',
        $api->getRef()->getRefKey()));

    list($adjustments, $errors) = $this->findAdjustments($api);

    if (!$adjustments) {
      $console->writeOut(
        "%s\n",
        pht('Found no adjustments for schemata.'));

      return $this->printErrors($errors, 0);
    }

    if (!$this->force && !$api->isCharacterSetAvailable('utf8mb4')) {
      $message = pht(
        "You have an old version of MySQL (older than 5.5) which does not ".
        "support the utf8mb4 character set. We strongly recomend upgrading to ".
        "5.5 or newer.\n\n".
        "If you apply adjustments now and later update MySQL to 5.5 or newer, ".
        "you'll need to apply adjustments again (and they will take a long ".
        "time).\n\n".
        "You can exit this workflow, update MySQL now, and then run this ".
        "workflow again. This is recommended, but may cause a lot of downtime ".
        "right now.\n\n".
        "You can exit this workflow, continue using Phabricator without ".
        "applying adjustments, update MySQL at a later date, and then run ".
        "this workflow again. This is also a good approach, and will let you ".
        "delay downtime until later.\n\n".
        "You can proceed with this workflow, and then optionally update ".
        "MySQL at a later date. After you do, you'll need to apply ".
        "adjustments again.\n\n".
        "For more information, see \"Managing Storage Adjustments\" in ".
        "the documentation.");

      $console->writeOut(
        "\n**<bg:yellow> %s </bg>**\n\n%s\n",
        pht('OLD MySQL VERSION'),
        phutil_console_wrap($message));

      $prompt = pht('Continue with old MySQL version?');
      if (!phutil_console_confirm($prompt, $default_no = true)) {
        return;
      }
    }

    $table = id(new PhutilConsoleTable())
      ->addColumn('database', array('title' => pht('Database')))
      ->addColumn('table', array('title' => pht('Table')))
      ->addColumn('name', array('title' => pht('Name')))
      ->addColumn('info', array('title' => pht('Issues')));

    foreach ($adjustments as $adjust) {
      $info = array();
      foreach ($adjust['issues'] as $issue) {
        $info[] = PhabricatorConfigStorageSchema::getIssueName($issue);
      }

      $table->addRow(array(
        'database' => $adjust['database'],
        'table' => idx($adjust, 'table'),
        'name' => idx($adjust, 'name'),
        'info' => implode(', ', $info),
      ));
    }

    $console->writeOut("\n\n");

    $table->draw();

    if ($this->dryRun) {
      $console->writeOut(
        "%s\n",
        pht('DRYRUN: Would apply adjustments.'));
      return 0;
    } else if ($this->didInitialize) {
      // If we just initialized the database, continue without prompting. This
      // is nicer for first-time setup and there's no reasonable reason any
      // user would ever answer "no" to the prompt against an empty schema.
    } else if (!$this->force) {
      $console->writeOut(
        "\n%s\n",
        pht(
          "Found %s adjustment(s) to apply, detailed above.\n\n".
          "You can review adjustments in more detail from the web interface, ".
          "in Config > Database Status. To better understand the adjustment ".
          "workflow, see \"Managing Storage Adjustments\" in the ".
          "documentation.\n\n".
          "MySQL needs to copy table data to make some adjustments, so these ".
          "migrations may take some time.",
          phutil_count($adjustments)));

      $prompt = pht('Apply these schema adjustments?');
      if (!phutil_console_confirm($prompt, $default_no = true)) {
        return 1;
      }
    }

    $console->writeOut(
      "%s\n",
      pht('Applying schema adjustments...'));

    $conn = $api->getConn(null);

    if ($unsafe) {
      queryfx($conn, 'SET SESSION sql_mode = %s', '');
    } else {
      queryfx($conn, 'SET SESSION sql_mode = %s', 'STRICT_ALL_TABLES');
    }

    $failed = array();

    // We make changes in several phases.
    $phases = array(
      // Drop surplus autoincrements. This allows us to drop primary keys on
      // autoincrement columns.
      'drop_auto',

      // Drop all keys we're going to adjust. This prevents them from
      // interfering with column changes.
      'drop_keys',

      // Apply all database, table, and column changes.
      'main',

      // Restore adjusted keys.
      'add_keys',

      // Add missing autoincrements.
      'add_auto',
    );

    $bar = id(new PhutilConsoleProgressBar())
      ->setTotal(count($adjustments) * count($phases));

    foreach ($phases as $phase) {
      foreach ($adjustments as $adjust) {
        try {
          switch ($adjust['kind']) {
            case 'database':
              if ($phase == 'main') {
                queryfx(
                  $conn,
                  'ALTER DATABASE %T CHARACTER SET = %s COLLATE = %s',
                  $adjust['database'],
                  $adjust['charset'],
                  $adjust['collation']);
              }
              break;
            case 'table':
              if ($phase == 'main') {
                queryfx(
                  $conn,
                  'ALTER TABLE %T.%T COLLATE = %s, ENGINE = %s',
                  $adjust['database'],
                  $adjust['table'],
                  $adjust['collation'],
                  $adjust['engine']);
              }
              break;
            case 'column':
              $apply = false;
              $auto = false;
              $new_auto = idx($adjust, 'auto');
              if ($phase == 'drop_auto') {
                if ($new_auto === false) {
                  $apply = true;
                  $auto = false;
                }
              } else if ($phase == 'main') {
                $apply = true;
                if ($new_auto === false) {
                  $auto = false;
                } else {
                  $auto = $adjust['is_auto'];
                }
              } else if ($phase == 'add_auto') {
                if ($new_auto === true) {
                  $apply = true;
                  $auto = true;
                }
              }

              if ($apply) {
                $parts = array();

                if ($auto) {
                  $parts[] = qsprintf(
                    $conn,
                    'AUTO_INCREMENT');
                }

                if ($adjust['charset']) {
                  $parts[] = qsprintf(
                    $conn,
                    'CHARACTER SET %Q COLLATE %Q',
                    $adjust['charset'],
                    $adjust['collation']);
                }

                queryfx(
                  $conn,
                  'ALTER TABLE %T.%T MODIFY %T %Q %Q %Q',
                  $adjust['database'],
                  $adjust['table'],
                  $adjust['name'],
                  $adjust['type'],
                  implode(' ', $parts),
                  $adjust['nullable'] ? 'NULL' : 'NOT NULL');
              }
              break;
            case 'key':
              if (($phase == 'drop_keys') && $adjust['exists']) {
                if ($adjust['name'] == 'PRIMARY') {
                  $key_name = 'PRIMARY KEY';
                } else {
                  $key_name = qsprintf($conn, 'KEY %T', $adjust['name']);
                }

                queryfx(
                  $conn,
                  'ALTER TABLE %T.%T DROP %Q',
                  $adjust['database'],
                  $adjust['table'],
                  $key_name);
              }

              if (($phase == 'add_keys') && $adjust['keep']) {
                // Different keys need different creation syntax. Notable
                // special cases are primary keys and fulltext keys.
                if ($adjust['name'] == 'PRIMARY') {
                  $key_name = 'PRIMARY KEY';
                } else if ($adjust['indexType'] == 'FULLTEXT') {
                  $key_name = qsprintf($conn, 'FULLTEXT %T', $adjust['name']);
                } else {
                  if ($adjust['unique']) {
                    $key_name = qsprintf(
                      $conn,
                      'UNIQUE KEY %T',
                      $adjust['name']);
                  } else {
                    $key_name = qsprintf(
                      $conn,
                      '/* NONUNIQUE */ KEY %T',
                      $adjust['name']);
                  }
                }

                queryfx(
                  $conn,
                  'ALTER TABLE %T.%T ADD %Q (%Q)',
                  $adjust['database'],
                  $adjust['table'],
                  $key_name,
                  implode(', ', $adjust['columns']));
              }
              break;
            default:
              throw new Exception(
                pht('Unknown schema adjustment kind "%s"!', $adjust['kind']));
          }
        } catch (AphrontQueryException $ex) {
          $failed[] = array($adjust, $ex);
        }
        $bar->update(1);
      }
    }
    $bar->done();

    if (!$failed) {
      $console->writeOut(
        "%s\n",
        pht('Completed applying all schema adjustments.'));

      $err = 0;
    } else {
      $table = id(new PhutilConsoleTable())
        ->addColumn('target', array('title' => pht('Target')))
        ->addColumn('error', array('title' => pht('Error')));

      foreach ($failed as $failure) {
        list($adjust, $ex) = $failure;

        $pieces = array_select_keys(
          $adjust,
          array('database', 'table', 'name'));
        $pieces = array_filter($pieces);
        $target = implode('.', $pieces);

        $table->addRow(
          array(
            'target' => $target,
            'error' => $ex->getMessage(),
          ));
      }

      $console->writeOut("\n");
      $table->draw();
      $console->writeOut(
        "\n%s\n",
        pht('Failed to make some schema adjustments, detailed above.'));
      $console->writeOut(
        "%s\n",
        pht(
          'For help troubleshooting adjustments, see "Managing Storage '.
          'Adjustments" in the documentation.'));

      $err = 1;
    }

    return $this->printErrors($errors, $err);
  }

  private function findAdjustments(
    PhabricatorStorageManagementAPI $api) {
    list($comp, $expect, $actual) = $this->loadSchemata($api);

    $issue_charset = PhabricatorConfigStorageSchema::ISSUE_CHARSET;
    $issue_collation = PhabricatorConfigStorageSchema::ISSUE_COLLATION;
    $issue_columntype = PhabricatorConfigStorageSchema::ISSUE_COLUMNTYPE;
    $issue_surpluskey = PhabricatorConfigStorageSchema::ISSUE_SURPLUSKEY;
    $issue_missingkey = PhabricatorConfigStorageSchema::ISSUE_MISSINGKEY;
    $issue_columns = PhabricatorConfigStorageSchema::ISSUE_KEYCOLUMNS;
    $issue_unique = PhabricatorConfigStorageSchema::ISSUE_UNIQUE;
    $issue_longkey = PhabricatorConfigStorageSchema::ISSUE_LONGKEY;
    $issue_auto = PhabricatorConfigStorageSchema::ISSUE_AUTOINCREMENT;
    $issue_engine = PhabricatorConfigStorageSchema::ISSUE_ENGINE;

    $adjustments = array();
    $errors = array();
    foreach ($comp->getDatabases() as $database_name => $database) {
      foreach ($this->findErrors($database) as $issue) {
        $errors[] = array(
          'database' => $database_name,
          'issue' => $issue,
        );
      }

      $expect_database = $expect->getDatabase($database_name);
      $actual_database = $actual->getDatabase($database_name);

      if (!$expect_database || !$actual_database) {
        // If there's a real issue here, skip this stuff.
        continue;
      }

      if ($actual_database->getAccessDenied()) {
        // If we can't access the database, we can't access the tables either.
        continue;
      }

      $issues = array();
      if ($database->hasIssue($issue_charset)) {
        $issues[] = $issue_charset;
      }
      if ($database->hasIssue($issue_collation)) {
        $issues[] = $issue_collation;
      }

      if ($issues) {
        $adjustments[] = array(
          'kind' => 'database',
          'database' => $database_name,
          'issues' => $issues,
          'charset' => $expect_database->getCharacterSet(),
          'collation' => $expect_database->getCollation(),
        );
      }

      foreach ($database->getTables() as $table_name => $table) {
        foreach ($this->findErrors($table) as $issue) {
          $errors[] = array(
            'database' => $database_name,
            'table' => $table_name,
            'issue' => $issue,
          );
        }

        $expect_table = $expect_database->getTable($table_name);
        $actual_table = $actual_database->getTable($table_name);

        if (!$expect_table || !$actual_table) {
          continue;
        }

        $issues = array();
        if ($table->hasIssue($issue_collation)) {
          $issues[] = $issue_collation;
        }

        if ($table->hasIssue($issue_engine)) {
          $issues[] = $issue_engine;
        }

        if ($issues) {
          $adjustments[] = array(
            'kind' => 'table',
            'database' => $database_name,
            'table' => $table_name,
            'issues' => $issues,
            'collation' => $expect_table->getCollation(),
            'engine' => $expect_table->getEngine(),
          );
        }

        foreach ($table->getColumns() as $column_name => $column) {
          foreach ($this->findErrors($column) as $issue) {
            $errors[] = array(
              'database' => $database_name,
              'table' => $table_name,
              'name' => $column_name,
              'issue' => $issue,
            );
          }

          $expect_column = $expect_table->getColumn($column_name);
          $actual_column = $actual_table->getColumn($column_name);

          if (!$expect_column || !$actual_column) {
            continue;
          }

          $issues = array();
          if ($column->hasIssue($issue_collation)) {
            $issues[] = $issue_collation;
          }
          if ($column->hasIssue($issue_charset)) {
            $issues[] = $issue_charset;
          }
          if ($column->hasIssue($issue_columntype)) {
            $issues[] = $issue_columntype;
          }
          if ($column->hasIssue($issue_auto)) {
            $issues[] = $issue_auto;
          }

          if ($issues) {
            if ($expect_column->getCharacterSet() === null) {
              // For non-text columns, we won't be specifying a collation or
              // character set.
              $charset = null;
              $collation = null;
            } else {
              $charset = $expect_column->getCharacterSet();
              $collation = $expect_column->getCollation();
            }

            $adjustment = array(
              'kind' => 'column',
              'database' => $database_name,
              'table' => $table_name,
              'name' => $column_name,
              'issues' => $issues,
              'collation' => $collation,
              'charset' => $charset,
              'type' => $expect_column->getColumnType(),

              // NOTE: We don't adjust column nullability because it is
              // dangerous, so always use the current nullability.
              'nullable' => $actual_column->getNullable(),

              // NOTE: This always stores the current value, because we have
              // to make these updates separately.
              'is_auto' => $actual_column->getAutoIncrement(),
            );

            if ($column->hasIssue($issue_auto)) {
              $adjustment['auto'] = $expect_column->getAutoIncrement();
            }

            $adjustments[] = $adjustment;
          }
        }

        foreach ($table->getKeys() as $key_name => $key) {
          foreach ($this->findErrors($key) as $issue) {
            $errors[] = array(
              'database' => $database_name,
              'table' => $table_name,
              'name' => $key_name,
              'issue' => $issue,
            );
          }

          $expect_key = $expect_table->getKey($key_name);
          $actual_key = $actual_table->getKey($key_name);

          $issues = array();
          $keep_key = true;
          if ($key->hasIssue($issue_surpluskey)) {
            $issues[] = $issue_surpluskey;
            $keep_key = false;
          }

          if ($key->hasIssue($issue_missingkey)) {
            $issues[] = $issue_missingkey;
          }

          if ($key->hasIssue($issue_columns)) {
            $issues[] = $issue_columns;
          }

          if ($key->hasIssue($issue_unique)) {
            $issues[] = $issue_unique;
          }

          // NOTE: We can't really fix this, per se, but we may need to remove
          // the key to change the column type. In the best case, the new
          // column type won't be overlong and recreating the key really will
          // fix the issue. In the worst case, we get the right column type and
          // lose the key, which is still better than retaining the key having
          // the wrong column type.
          if ($key->hasIssue($issue_longkey)) {
            $issues[] = $issue_longkey;
          }

          if ($issues) {
            $adjustment = array(
              'kind' => 'key',
              'database' => $database_name,
              'table' => $table_name,
              'name' => $key_name,
              'issues' => $issues,
              'exists' => (bool)$actual_key,
              'keep' => $keep_key,
            );

            if ($keep_key) {
              $adjustment += array(
                'columns' => $expect_key->getColumnNames(),
                'unique' => $expect_key->getUnique(),
                'indexType' => $expect_key->getIndexType(),
              );
            }

            $adjustments[] = $adjustment;
          }
        }
      }
    }

    return array($adjustments, $errors);
  }

  private function findErrors(PhabricatorConfigStorageSchema $schema) {
    $result = array();
    foreach ($schema->getLocalIssues() as $issue) {
      $status = PhabricatorConfigStorageSchema::getIssueStatus($issue);
      if ($status == PhabricatorConfigStorageSchema::STATUS_FAIL) {
        $result[] = $issue;
      }
    }
    return $result;
  }

  private function printErrors(array $errors, $default_return) {
    if (!$errors) {
      return $default_return;
    }

    $console = PhutilConsole::getConsole();

    $table = id(new PhutilConsoleTable())
      ->addColumn('target', array('title' => pht('Target')))
      ->addColumn('error', array('title' => pht('Error')));

    $any_surplus = false;
    $all_surplus = true;
    $any_access = false;
    $all_access = true;
    foreach ($errors as $error) {
      $pieces = array_select_keys(
        $error,
        array('database', 'table', 'name'));
      $pieces = array_filter($pieces);
      $target = implode('.', $pieces);

      $name = PhabricatorConfigStorageSchema::getIssueName($error['issue']);

      $issue = $error['issue'];

      if ($issue === PhabricatorConfigStorageSchema::ISSUE_SURPLUS) {
        $any_surplus = true;
      } else {
        $all_surplus = false;
      }

      if ($issue === PhabricatorConfigStorageSchema::ISSUE_ACCESSDENIED) {
        $any_access = true;
      } else {
        $all_access = false;
      }

      $table->addRow(
        array(
          'target' => $target,
          'error' => $name,
        ));
    }

    $console->writeOut("\n");
    $table->draw();
    $console->writeOut("\n");


    $message = array();
    if ($all_surplus) {
      $message[] = pht(
        'You have surplus schemata (extra tables or columns which Phabricator '.
        'does not expect). For information on resolving these '.
        'issues, see the "Surplus Schemata" section in the "Managing Storage '.
        'Adjustments" article in the documentation.');
    } else if ($all_access) {
      $message[] = pht(
        'The user you are connecting to MySQL with does not have the correct '.
        'permissions, and can not access some databases or tables that it '.
        'needs to be able to access. GRANT the user additional permissions.');
    } else {
      $message[] = pht(
        'The schemata have errors (detailed above) which the adjustment '.
        'workflow can not fix.');

      if ($any_access) {
        $message[] = pht(
          'Some of these errors are caused by access control problems. '.
          'The user you are connecting with does not have permission to see '.
          'all of the database or tables that Phabricator uses. You need to '.
          'GRANT the user more permission, or use a different user.');
      }

      if ($any_surplus) {
        $message[] = pht(
          'Some of these errors are caused by surplus schemata (extra '.
          'tables or columns which Phabricator does not expect). These are '.
          'not serious. For information on resolving these issues, see the '.
          '"Surplus Schemata" section in the "Managing Storage Adjustments" '.
          'article in the documentation.');
      }

      $message[] = pht(
        'If you are not developing Phabricator itself, report this issue to '.
        'the upstream.');

      $message[] = pht(
        'If you are developing Phabricator, these errors usually indicate '.
        'that your schema specifications do not agree with the schemata your '.
        'code actually builds.');
    }
    $message = implode("\n\n", $message);

    if ($all_surplus) {
      $console->writeOut(
        "**<bg:yellow> %s </bg>**\n\n%s\n",
        pht('SURPLUS SCHEMATA'),
        phutil_console_wrap($message));
    } else if ($all_access) {
      $console->writeOut(
        "**<bg:yellow> %s </bg>**\n\n%s\n",
        pht('ACCESS DENIED'),
        phutil_console_wrap($message));
    } else {
      $console->writeOut(
        "**<bg:red> %s </bg>**\n\n%s\n",
        pht('SCHEMATA ERRORS'),
        phutil_console_wrap($message));
    }

    return 2;
  }

  final protected function upgradeSchemata(
    array $apis,
    $apply_only = null,
    $no_quickstart = false,
    $init_only = false) {

    $locks = array();
    foreach ($apis as $api) {
      $locks[] = $this->lock($api);
    }

    try {
      $this->doUpgradeSchemata($apis, $apply_only, $no_quickstart, $init_only);
    } catch (Exception $ex) {
      foreach ($locks as $lock) {
        $lock->unlock();
      }
      throw $ex;
    }

    foreach ($locks as $lock) {
      $lock->unlock();
    }
  }

  final private function doUpgradeSchemata(
    array $apis,
    $apply_only,
    $no_quickstart,
    $init_only) {

    $patches = $this->patches;
    $is_dryrun = $this->dryRun;

    $api_map = array();
    foreach ($apis as $api) {
      $api_map[$api->getRef()->getRefKey()] = $api;
    }

    foreach ($api_map as $ref_key => $api) {
      $applied = $api->getAppliedPatches();

      $needs_init = ($applied === null);
      if (!$needs_init) {
        continue;
      }

      if ($is_dryrun) {
        echo tsprintf(
          "%s\n",
          pht(
            'DRYRUN: Storage on host "%s" does not exist yet, so it '.
            'would be created.',
            $ref_key));
        continue;
      }

      if ($apply_only) {
        throw new PhutilArgumentUsageException(
          pht(
            'Storage on host "%s" has not been initialized yet. You must '.
            'initialize storage before selectively applying patches.',
            $ref_key));
      }

      // If we're initializing storage for the first time on any host, track
      // it so that we can give the user a nicer experience during the
      // subsequent adjustment phase.
      $this->didInitialize = true;

      $legacy = $api->getLegacyPatches($patches);
      if ($legacy || $no_quickstart || $init_only) {
        // If we have legacy patches, we can't quickstart.
        $api->createDatabase('meta_data');
        $api->createTable(
          'meta_data',
          'patch_status',
          array(
            'patch VARCHAR(255) NOT NULL PRIMARY KEY COLLATE utf8_general_ci',
            'applied INT UNSIGNED NOT NULL',
          ));

        foreach ($legacy as $patch) {
          $api->markPatchApplied($patch);
        }
      } else {
        echo tsprintf(
          "%s\n",
          pht(
            'Loading quickstart template onto "%s"...',
            $ref_key));

        $root = dirname(phutil_get_library_root('phabricator'));
        $sql  = $root.'/resources/sql/quickstart.sql';
        $api->applyPatchSQL($sql);
      }
    }

    if ($init_only) {
      echo pht('Storage initialized.')."\n";
      return 0;
    }

    $applied_map = array();
    $state_map = array();
    foreach ($api_map as $ref_key => $api) {
      $applied = $api->getAppliedPatches();

      // If we still have nothing applied, this is a dry run and we didn't
      // actually initialize storage. Here, just do nothing.
      if ($applied === null) {
        if ($is_dryrun) {
          continue;
        } else {
          throw new Exception(
            pht(
              'Database initialization on host "%s" applied no patches!',
              $ref_key));
        }
      }

      $applied = array_fuse($applied);
      $state_map[$ref_key] = $applied;

      if ($apply_only) {
        if (isset($applied[$apply_only])) {
          if (!$this->force && !$is_dryrun) {
            echo phutil_console_wrap(
              pht(
                'Patch "%s" has already been applied on host "%s". Are you '.
                'sure you want to apply it again? This may put your storage '.
                'in a state that the upgrade scripts can not automatically '.
                'manage.',
                $apply_only,
                $ref_key));
            if (!phutil_console_confirm(pht('Apply patch again?'))) {
              echo pht('Cancelled.')."\n";
              return 1;
            }
          }

          // Mark this patch as not yet applied on this host.
          unset($applied[$apply_only]);
        }
      }

      $applied_map[$ref_key] = $applied;
    }

    // If we're applying only a specific patch, select just that patch.
    if ($apply_only) {
      $patches = array_select_keys($patches, array($apply_only));
    }

    // Apply each patch to each database. We apply patches patch-by-patch,
    // not database-by-database: for each patch we apply it to every database,
    // then move to the next patch.

    // We must do this because ".php" patches may depend on ".sql" patches
    // being up to date on all masters, and that will work fine if we put each
    // patch on every host before moving on. If we try to bring database hosts
    // up to date one at a time we can end up in a big mess.

    $duration_map = array();

    // First, find any global patches which have been applied to ANY database.
    // We are just going to mark these as applied without actually running
    // them. Otherwise, adding new empty masters to an existing cluster will
    // try to apply them against invalid states.
    foreach ($patches as $key => $patch) {
      if ($patch->getIsGlobalPatch()) {
        foreach ($applied_map as $ref_key => $applied) {
          if (isset($applied[$key])) {
            $duration_map[$key] = 1;
          }
        }
      }
    }

    while (true) {
      $applied_something = false;
      foreach ($patches as $key => $patch) {
        // First, check if any databases need this patch. We can just skip it
        // if it has already been applied everywhere.
        $need_patch = array();
        foreach ($applied_map as $ref_key => $applied) {
          if (isset($applied[$key])) {
            continue;
          }
          $need_patch[] = $ref_key;
        }

        if (!$need_patch) {
          unset($patches[$key]);
          continue;
        }

        // Check if we can apply this patch yet. Before we can apply a patch,
        // all of the dependencies for the patch must have been applied on all
        // databases. Requiring that all databases stay in sync prevents one
        // database from racing ahead if it happens to get a patch that nothing
        // else has yet.
        $missing_patch = null;
        foreach ($patch->getAfter() as $after) {
          foreach ($applied_map as $ref_key => $applied) {
            if (isset($applied[$after])) {
              // This database already has the patch. We can apply it to
              // other databases but don't need to apply it here.
              continue;
            }

            $missing_patch = $after;
            break 2;
          }
        }

        if ($missing_patch) {
          if ($apply_only) {
            echo tsprintf(
              "%s\n",
              pht(
                'Unable to apply patch "%s" because it depends on patch '.
                '"%s", which has not been applied on some hosts: %s.',
                $apply_only,
                $missing_patch,
                implode(', ', $need_patch)));
            return 1;
          } else {
            // Some databases are missing the dependencies, so keep trying
            // other patches instead. If everything goes right, we'll apply the
            // dependencies and then come back and apply this patch later.
            continue;
          }
        }

        $is_global = $patch->getIsGlobalPatch();
        $patch_apis = array_select_keys($api_map, $need_patch);
        foreach ($patch_apis as $ref_key => $api) {
          if ($is_global) {
            // If this is a global patch which we previously applied, just
            // read the duration from the map without actually applying
            // the patch.
            $duration = idx($duration_map, $key);
          } else {
            $duration = null;
          }

          if ($duration === null) {
            if ($is_dryrun) {
              echo tsprintf(
                "%s\n",
                pht(
                  'DRYRUN: Would apply patch "%s" to host "%s".',
                  $key,
                  $ref_key));
            } else {
              echo tsprintf(
                "%s\n",
                pht(
                  'Applying patch "%s" to host "%s"...',
                  $key,
                  $ref_key));
            }

            $t_begin = microtime(true);
            $api->applyPatch($patch);
            $t_end = microtime(true);

            $duration = ($t_end - $t_begin);
            $duration_map[$key] = $duration;
          }

          // If we're explicitly reapplying this patch, we don't need to
          // mark it as applied.
          if (!isset($state_map[$ref_key][$key])) {
            $api->markPatchApplied($key, ($t_end - $t_begin));
            $applied_map[$ref_key][$key] = true;
          }
        }

        // We applied this everywhere, so we're done with the patch.
        unset($patches[$key]);
        $applied_something = true;
      }

      if (!$applied_something) {
        if ($patches) {
          throw new Exception(
            pht(
              'Some patches could not be applied: %s',
              implode(', ', array_keys($patches))));
        } else if (!$is_dryrun && !$apply_only) {
          echo pht(
            'Storage is up to date. Use "%s" for details.',
            'storage status')."\n";
        }
        break;
      }
    }
  }

  final protected function getBareHostAndPort($host) {
    // Split out port information, since the command-line client requires a
    // separate flag for the port.
    $uri = new PhutilURI('mysql://'.$host);
    if ($uri->getPort()) {
      $port = $uri->getPort();
      $bare_hostname = $uri->getDomain();
    } else {
      $port = null;
      $bare_hostname = $host;
    }

    return array($bare_hostname, $port);
  }

  /**
   * Acquires a @{class:PhabricatorGlobalLock}.
   *
   * @return PhabricatorGlobalLock
   */
  final protected function lock(PhabricatorStorageManagementAPI $api) {
    // Although we're holding this lock on different databases so it could
    // have the same name on each as far as the database is concerned, the
    // locks would be the same within this process.
    $ref_key = $api->getRef()->getRefKey();
    $ref_hash = PhabricatorHash::digestForIndex($ref_key);
    $lock_name = 'adjust('.$ref_hash.')';

    return PhabricatorGlobalLock::newLock($lock_name)
      ->useSpecificConnection($api->getConn(null))
      ->lock();
  }

}
