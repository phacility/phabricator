<?php

final class PhabricatorStorageManagementAdjustWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('adjust')
      ->setExamples('**adjust** [__options__]')
      ->setSynopsis(
        pht(
          'Make schemata adjustments to correct issues with characters sets, '.
          'collations, and keys.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $force = $args->getArg('force');

    $this->requireAllPatchesApplied();
    return $this->adjustSchemata($force);
  }

  private function requireAllPatchesApplied() {
    $api = $this->getAPI();
    $applied = $api->getAppliedPatches();

    if ($applied === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'You have not initialized the database yet. You must initialize '.
          'the database before you can adjust schemata. Run `storage upgrade` '.
          'to initialize the database.'));
    }

    $applied = array_fuse($applied);

    $patches = $this->getPatches();
    $patches = mpull($patches, null, 'getFullKey');
    $missing = array_diff_key($patches, $applied);

    if ($missing) {
      throw new PhutilArgumentUsageException(
        pht(
          'You have not applied all available storage patches yet. You must '.
          'apply all available patches before you can adjust schemata. '.
          'Run `storage status` to show patch status, and `storage upgrade` '.
          'to apply missing patches.'));
    }
  }

  private function loadSchemata() {
    $query = id(new PhabricatorConfigSchemaQuery())
      ->setAPI($this->getAPI());

    $actual = $query->loadActualSchema();
    $expect = $query->loadExpectedSchema();
    $comp = $query->buildComparisonSchema($expect, $actual);

    return array($comp, $expect, $actual);
  }

  private function adjustSchemata($force) {
    $console = PhutilConsole::getConsole();

    $console->writeOut(
      "%s\n",
      pht('Verifying database schemata...'));

    $adjustments = $this->findAdjustments();

    if (!$adjustments) {
      $console->writeOut(
        "%s\n",
        pht('Found no issues with schemata.'));
      return;
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

    if (!$force) {
      $console->writeOut(
        "\n%s\n",
        pht(
          "Found %s issues(s) with schemata, detailed above.\n\n".
          "You can review issues in more detail from the web interface, ".
          "in Config > Database Status.\n\n".
          "MySQL needs to copy table data to make some adjustments, so these ".
          "migrations may take some time.".

          // TODO: Remove warning once this stabilizes.
          "\n\n".
          "WARNING: This workflow is new and unstable. If you continue, you ".
          "may unrecoverably destory data. Make sure you have a backup before ".
          "you proceed.",

          new PhutilNumber(count($adjustments))));

      $prompt = pht('Fix these schema issues?');
      if (!phutil_console_confirm($prompt, $default_no = true)) {
        return;
      }
    }

    $console->writeOut(
      "%s\n",
      pht('Dropping caches, for faster migrations...'));

    $root = dirname(phutil_get_library_root('phabricator'));
    $bin = $root.'/bin/cache';
    phutil_passthru('%s purge --purge-all', $bin);

    $console->writeOut(
      "%s\n",
      pht('Fixing schema issues...'));

    $api = $this->getAPI();
    $conn = $api->getConn(null);

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
                  'ALTER TABLE %T.%T COLLATE = %s',
                  $adjust['database'],
                  $adjust['table'],
                  $adjust['collation']);
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
        pht('Completed fixing all schema issues.'));
      return 0;
    }

    $table = id(new PhutilConsoleTable())
      ->addColumn('target', array('title' => pht('Target')))
      ->addColumn('error', array('title' => pht('Error')));

    foreach ($failed as $failure) {
      list($adjust, $ex) = $failure;

      $pieces = array_select_keys($adjust, array('database', 'table', 'name'));
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

    return 1;
  }

  private function findAdjustments() {
    list($comp, $expect, $actual) = $this->loadSchemata();

    $issue_charset = PhabricatorConfigStorageSchema::ISSUE_CHARSET;
    $issue_collation = PhabricatorConfigStorageSchema::ISSUE_COLLATION;
    $issue_columntype = PhabricatorConfigStorageSchema::ISSUE_COLUMNTYPE;
    $issue_surpluskey = PhabricatorConfigStorageSchema::ISSUE_SURPLUSKEY;
    $issue_missingkey = PhabricatorConfigStorageSchema::ISSUE_MISSINGKEY;
    $issue_columns = PhabricatorConfigStorageSchema::ISSUE_KEYCOLUMNS;
    $issue_unique = PhabricatorConfigStorageSchema::ISSUE_UNIQUE;
    $issue_longkey = PhabricatorConfigStorageSchema::ISSUE_LONGKEY;
    $issue_auto = PhabricatorConfigStorageSchema::ISSUE_AUTOINCREMENT;

    $adjustments = array();
    foreach ($comp->getDatabases() as $database_name => $database) {
      $expect_database = $expect->getDatabase($database_name);
      $actual_database = $actual->getDatabase($database_name);

      if (!$expect_database || !$actual_database) {
        // If there's a real issue here, skip this stuff.
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
        $expect_table = $expect_database->getTable($table_name);
        $actual_table = $actual_database->getTable($table_name);

        if (!$expect_table || !$actual_table) {
          continue;
        }

        $issues = array();
        if ($table->hasIssue($issue_collation)) {
          $issues[] = $issue_collation;
        }

        if ($issues) {
          $adjustments[] = array(
            'kind' => 'table',
            'database' => $database_name,
            'table' => $table_name,
            'issues' => $issues,
            'collation' => $expect_table->getCollation(),
          );
        }

        foreach ($table->getColumns() as $column_name => $column) {
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

    return $adjustments;
  }


}
