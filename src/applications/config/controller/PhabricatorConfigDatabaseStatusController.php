<?php

final class PhabricatorConfigDatabaseStatusController
  extends PhabricatorConfigDatabaseController {

  private $database;
  private $table;
  private $column;
  private $key;
  private $ref;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $this->database = $request->getURIData('database');
    $this->table = $request->getURIData('table');
    $this->column = $request->getURIData('column');
    $this->key = $request->getURIData('key');
    $this->ref = $request->getURIData('ref');

    $query = new PhabricatorConfigSchemaQuery();

    $actual = $query->loadActualSchemata();
    $expect = $query->loadExpectedSchemata();
    $comp = $query->buildComparisonSchemata($expect, $actual);

    if ($this->ref !== null) {
      $server_actual = idx($actual, $this->ref);
      if (!$server_actual) {
        return new Aphront404Response();
      }

      $server_comparison = $comp[$this->ref];
      $server_expect = $expect[$this->ref];

      if ($this->column) {
        return $this->renderColumn(
          $server_comparison,
          $server_expect,
          $server_actual,
          $this->database,
          $this->table,
          $this->column);
      } else if ($this->key) {
        return $this->renderKey(
          $server_comparison,
          $server_expect,
          $server_actual,
          $this->database,
          $this->table,
          $this->key);
      } else if ($this->table) {
        return $this->renderTable(
          $server_comparison,
          $server_expect,
          $server_actual,
          $this->database,
          $this->table);
      } else if ($this->database) {
        return $this->renderDatabase(
          $server_comparison,
          $server_expect,
          $server_actual,
          $this->database);
      }
    }

    return $this->renderServers(
      $comp,
      $expect,
      $actual);
  }

  private function buildResponse($title, $body) {
    $nav = $this->buildSideNavView();
    $nav->selectFilter('database/');

    if (!$title) {
      $title = pht('Database Status');
    }

    $ref = $this->ref;
    $database = $this->database;
    $table = $this->table;
    $column = $this->column;
    $key = $this->key;

    $links = array();
    $links[] = array(
      pht('Database Status'),
      'database/',
    );

    if ($database) {
      $links[] = array(
        $database,
        "database/{$ref}/{$database}/",
      );
    }

    if ($table) {
      $links[] = array(
        $table,
        "database/{$ref}/{$database}/{$table}/",
      );
    }

    if ($column) {
      $links[] = array(
        $column,
        "database/{$ref}/{$database}/{$table}/col/{$column}/",
      );
    }

    if ($key) {
      $links[] = array(
        $key,
        "database/{$ref}/{$database}/{$table}/key/{$key}/",
      );
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);

    $last_key = last_key($links);
    foreach ($links as $link_key => $link) {
      list($name, $href) = $link;
      if ($link_key == $last_key) {
        $crumbs->addTextCrumb($name);
      } else {
        $crumbs->addTextCrumb($name, $this->getApplicationURI($href));
      }
    }

    $doc_link = PhabricatorEnv::getDoclink('Managing Storage Adjustments');
    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-book')
      ->setHref($doc_link)
      ->setText(pht('Documentation'));

    $header = $this->buildHeaderView($title, $button);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($body);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }


  private function renderServers(
    array $comp_servers,
    array $expect_servers,
    array $actual_servers) {

    $charset_issue = PhabricatorConfigStorageSchema::ISSUE_CHARSET;
    $collation_issue = PhabricatorConfigStorageSchema::ISSUE_COLLATION;

    $rows = array();
    foreach ($comp_servers as $ref_key => $comp) {
      $actual = $actual_servers[$ref_key];
      $expect = $expect_servers[$ref_key];
      foreach ($comp->getDatabases() as $database_name => $database) {
        $actual_database = $actual->getDatabase($database_name);
        if ($actual_database) {
          $charset = $actual_database->getCharacterSet();
          $collation = $actual_database->getCollation();
        } else {
          $charset = null;
          $collation = null;
        }

        $status = $database->getStatus();
        $issues = $database->getIssues();

        $uri = $this->getURI(
          array(
            'ref' => $ref_key,
            'database' => $database_name,
          ));

        $rows[] = array(
          $this->renderIcon($status),
          $ref_key,
          phutil_tag(
            'a',
            array(
              'href' => $uri,
            ),
            $database_name),
          $this->renderAttr($charset, $database->hasIssue($charset_issue)),
          $this->renderAttr($collation, $database->hasIssue($collation_issue)),
        );
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Server'),
          pht('Database'),
          pht('Charset'),
          pht('Collation'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide pri',
          null,
          null,
        ));

    $title = pht('Database Status');
    $properties = $this->buildProperties(
      array(
      ),
      $comp->getIssues());
    $properties = $this->buildConfigBoxView(pht('Properties'), $properties);
    $table = $this->buildConfigBoxView(pht('Database'), $table);

    return $this->buildResponse($title, array($properties, $table));
  }

  private function renderDatabase(
    PhabricatorConfigServerSchema $comp,
    PhabricatorConfigServerSchema $expect,
    PhabricatorConfigServerSchema $actual,
    $database_name) {

    $collation_issue = PhabricatorConfigStorageSchema::ISSUE_COLLATION;

    $database = $comp->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $rows = array();
    foreach ($database->getTables() as $table_name => $table) {
      $status = $table->getStatus();

      $uri = $this->getURI(
        array(
          'table' => $table_name,
        ));

      $rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $uri,
          ),
          $table_name),
        $this->renderAttr(
          $table->getCollation(),
          $table->hasIssue($collation_issue)),
        $table->getPersistenceTypeDisplayName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Table'),
          pht('Collation'),
          pht('Persistence'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
          null,
          null,
        ));

    $title = $database_name;

    $actual_database = $actual->getDatabase($database_name);
    if ($actual_database) {
      $actual_charset = $actual_database->getCharacterSet();
      $actual_collation = $actual_database->getCollation();
    } else {
      $actual_charset = null;
      $actual_collation = null;
    }

    $expect_database = $expect->getDatabase($database_name);
    if ($expect_database) {
      $expect_charset = $expect_database->getCharacterSet();
      $expect_collation = $expect_database->getCollation();
    } else {
      $expect_charset = null;
      $expect_collation = null;
    }

    $properties = $this->buildProperties(
      array(
        array(
          pht('Server'),
          $this->ref,
        ),
        array(
          pht('Character Set'),
          $actual_charset,
        ),
        array(
          pht('Expected Character Set'),
          $expect_charset,
        ),
        array(
          pht('Collation'),
          $actual_collation,
        ),
        array(
          pht('Expected Collation'),
          $expect_collation,
        ),
      ),
      $database->getIssues());

    $properties = $this->buildConfigBoxView(pht('Properties'), $properties);
    $table = $this->buildConfigBoxView(pht('Database'), $table);

    return $this->buildResponse($title, array($properties, $table));
  }

  private function renderTable(
    PhabricatorConfigServerSchema $comp,
    PhabricatorConfigServerSchema $expect,
    PhabricatorConfigServerSchema $actual,
    $database_name,
    $table_name) {

    $type_issue = PhabricatorConfigStorageSchema::ISSUE_COLUMNTYPE;
    $charset_issue = PhabricatorConfigStorageSchema::ISSUE_CHARSET;
    $collation_issue = PhabricatorConfigStorageSchema::ISSUE_COLLATION;
    $nullable_issue = PhabricatorConfigStorageSchema::ISSUE_NULLABLE;
    $unique_issue = PhabricatorConfigStorageSchema::ISSUE_UNIQUE;
    $columns_issue = PhabricatorConfigStorageSchema::ISSUE_KEYCOLUMNS;
    $longkey_issue = PhabricatorConfigStorageSchema::ISSUE_LONGKEY;
    $auto_issue = PhabricatorConfigStorageSchema::ISSUE_AUTOINCREMENT;

    $database = $comp->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $table = $database->getTable($table_name);
    if (!$table) {
      return new Aphront404Response();
    }

    $actual_database = $actual->getDatabase($database_name);
    $actual_table = null;
    if ($actual_database) {
      $actual_table = $actual_database->getTable($table_name);
    }

    $expect_database = $expect->getDatabase($database_name);
    $expect_table = null;
    if ($expect_database) {
      $expect_table = $expect_database->getTable($table_name);
    }

    $rows = array();
    foreach ($table->getColumns() as $column_name => $column) {
      $expect_column = null;
      if ($expect_table) {
        $expect_column = $expect_table->getColumn($column_name);
      }

      $status = $column->getStatus();

      $data_type = null;
      if ($expect_column) {
        $data_type = $expect_column->getDataType();
      }

      $uri = $this->getURI(
        array(
          'column' => $column_name,
        ));

      $rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $uri,
          ),
          $column_name),
        $data_type,
        $this->renderAttr(
          $column->getColumnType(),
          $column->hasIssue($type_issue)),
        $this->renderAttr(
          $this->renderBoolean($column->getNullable()),
          $column->hasIssue($nullable_issue)),
        $this->renderAttr(
          $this->renderBoolean($column->getAutoIncrement()),
          $column->hasIssue($auto_issue)),
        $this->renderAttr(
          $column->getCharacterSet(),
          $column->hasIssue($charset_issue)),
        $this->renderAttr(
          $column->getCollation(),
          $column->hasIssue($collation_issue)),
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Column'),
          pht('Data Type'),
          pht('Column Type'),
          pht('Nullable'),
          pht('Autoincrement'),
          pht('Character Set'),
          pht('Collation'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
          null,
          null,
          null,
          null,
          null,
        ));

    $key_rows = array();
    foreach ($table->getKeys() as $key_name => $key) {
      $expect_key = null;
      if ($expect_table) {
        $expect_key = $expect_table->getKey($key_name);
      }

      $status = $key->getStatus();

      $size = 0;
      foreach ($key->getColumnNames() as $column_spec) {
        list($column_name, $prefix) = $key->getKeyColumnAndPrefix($column_spec);
        $column = $table->getColumn($column_name);
        if (!$column) {
          $size = 0;
          break;
        }
        $size += $column->getKeyByteLength($prefix);
      }

      $size_formatted = null;
      if ($size) {
        $size_formatted = $this->renderAttr(
          $size,
          $key->hasIssue($longkey_issue));
      }

      $uri = $this->getURI(
        array(
          'key' => $key_name,
        ));

      $key_rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $uri,
          ),
          $key_name),
        $this->renderAttr(
          implode(', ', $key->getColumnNames()),
          $key->hasIssue($columns_issue)),
        $this->renderAttr(
          $this->renderBoolean($key->getUnique()),
          $key->hasIssue($unique_issue)),
        $size_formatted,
      );
    }

    $keys_view = id(new AphrontTableView($key_rows))
      ->setHeaders(
        array(
          null,
          pht('Key'),
          pht('Columns'),
          pht('Unique'),
          pht('Size'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
          null,
          null,
          null,
        ));

    $title = pht('%s.%s', $database_name, $table_name);

    if ($actual_table) {
      $actual_collation = $actual_table->getCollation();
    } else {
      $actual_collation = null;
    }

    if ($expect_table) {
      $expect_collation = $expect_table->getCollation();
    } else {
      $expect_collation = null;
    }

    $properties = $this->buildProperties(
      array(
        array(
          pht('Server'),
          $this->ref,
        ),
        array(
          pht('Collation'),
          $actual_collation,
        ),
        array(
          pht('Expected Collation'),
          $expect_collation,
        ),
      ),
      $table->getIssues());

    $box_header = pht('%s.%s', $database_name, $table_name);

    $properties = $this->buildConfigBoxView(pht('Properties'), $properties);
    $table = $this->buildConfigBoxView(pht('Database'), $table_view);
    $keys = $this->buildConfigBoxView(pht('Keys'), $keys_view);

    return $this->buildResponse(
      $title, array($properties, $table, $keys));
  }

  private function renderColumn(
    PhabricatorConfigServerSchema $comp,
    PhabricatorConfigServerSchema $expect,
    PhabricatorConfigServerSchema $actual,
    $database_name,
    $table_name,
    $column_name) {

    $database = $comp->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $table = $database->getTable($table_name);
    if (!$table) {
      return new Aphront404Response();
    }

    $column = $table->getColumn($column_name);
    if (!$column) {
      return new Aphront404Response();
    }

    $actual_database = $actual->getDatabase($database_name);
    $actual_table = null;
    $actual_column = null;
    if ($actual_database) {
      $actual_table = $actual_database->getTable($table_name);
      if ($actual_table) {
        $actual_column = $actual_table->getColumn($column_name);
      }
    }

    $expect_database = $expect->getDatabase($database_name);
    $expect_table = null;
    $expect_column = null;
    if ($expect_database) {
      $expect_table = $expect_database->getTable($table_name);
      if ($expect_table) {
        $expect_column = $expect_table->getColumn($column_name);
      }
    }

    if ($actual_column) {
      $actual_coltype = $actual_column->getColumnType();
      $actual_charset = $actual_column->getCharacterSet();
      $actual_collation = $actual_column->getCollation();
      $actual_nullable = $actual_column->getNullable();
      $actual_auto = $actual_column->getAutoIncrement();
    } else {
      $actual_coltype = null;
      $actual_charset = null;
      $actual_collation = null;
      $actual_nullable = null;
      $actual_auto = null;
    }

    if ($expect_column) {
      $data_type = $expect_column->getDataType();
      $expect_coltype = $expect_column->getColumnType();
      $expect_charset = $expect_column->getCharacterSet();
      $expect_collation = $expect_column->getCollation();
      $expect_nullable = $expect_column->getNullable();
      $expect_auto = $expect_column->getAutoIncrement();
    } else {
      $data_type = null;
      $expect_coltype = null;
      $expect_charset = null;
      $expect_collation = null;
      $expect_nullable = null;
      $expect_auto = null;
    }


    $title = pht(
      '%s.%s.%s',
      $database_name,
      $table_name,
      $column_name);

    $properties = $this->buildProperties(
      array(
        array(
          pht('Server'),
          $this->ref,
        ),
        array(
          pht('Data Type'),
          $data_type,
        ),
        array(
          pht('Column Type'),
          $actual_coltype,
        ),
        array(
          pht('Expected Column Type'),
          $expect_coltype,
        ),
        array(
          pht('Character Set'),
          $actual_charset,
        ),
        array(
          pht('Expected Character Set'),
          $expect_charset,
        ),
        array(
          pht('Collation'),
          $actual_collation,
        ),
        array(
          pht('Expected Collation'),
          $expect_collation,
        ),
        array(
          pht('Nullable'),
          $this->renderBoolean($actual_nullable),
        ),
        array(
          pht('Expected Nullable'),
          $this->renderBoolean($expect_nullable),
        ),
        array(
          pht('Autoincrement'),
          $this->renderBoolean($actual_auto),
        ),
        array(
          pht('Expected Autoincrement'),
          $this->renderBoolean($expect_auto),
        ),
      ),
      $column->getIssues());

    $properties = $this->buildConfigBoxView(pht('Properties'), $properties);

    return $this->buildResponse($title, $properties);
  }

  private function renderKey(
    PhabricatorConfigServerSchema $comp,
    PhabricatorConfigServerSchema $expect,
    PhabricatorConfigServerSchema $actual,
    $database_name,
    $table_name,
    $key_name) {

    $database = $comp->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $table = $database->getTable($table_name);
    if (!$table) {
      return new Aphront404Response();
    }

    $key = $table->getKey($key_name);
    if (!$key) {
      return new Aphront404Response();
    }

    $actual_database = $actual->getDatabase($database_name);
    $actual_table = null;
    $actual_key = null;
    if ($actual_database) {
      $actual_table = $actual_database->getTable($table_name);
      if ($actual_table) {
        $actual_key = $actual_table->getKey($key_name);
      }
    }

    $expect_database = $expect->getDatabase($database_name);
    $expect_table = null;
    $expect_key = null;
    if ($expect_database) {
      $expect_table = $expect_database->getTable($table_name);
      if ($expect_table) {
        $expect_key = $expect_table->getKey($key_name);
      }
    }

    if ($actual_key) {
      $actual_columns = $actual_key->getColumnNames();
      $actual_unique = $actual_key->getUnique();
    } else {
      $actual_columns = array();
      $actual_unique = null;
    }

    if ($expect_key) {
      $expect_columns = $expect_key->getColumnNames();
      $expect_unique = $expect_key->getUnique();
    } else {
      $expect_columns = array();
      $expect_unique = null;
    }

    $title = pht(
      '%s.%s (%s)',
      $database_name,
      $table_name,
      $key_name);

    $properties = $this->buildProperties(
      array(
        array(
          pht('Server'),
          $this->ref,
        ),
        array(
          pht('Unique'),
          $this->renderBoolean($actual_unique),
        ),
        array(
          pht('Expected Unique'),
          $this->renderBoolean($expect_unique),
        ),
        array(
          pht('Columns'),
          implode(', ', $actual_columns),
        ),
        array(
          pht('Expected Columns'),
          implode(', ', $expect_columns),
        ),
      ),
      $key->getIssues());

    $properties = $this->buildConfigBoxView(pht('Properties'), $properties);

    return $this->buildResponse($title, $properties);
  }

  private function buildProperties(array $properties, array $issues) {
    $view = id(new PHUIPropertyListView())
      ->setUser($this->getRequest()->getUser());

    foreach ($properties as $property) {
      list($key, $value) = $property;
      $view->addProperty($key, $value);
    }

    $status_view = new PHUIStatusListView();
    if (!$issues) {
      $status_view->addItem(
        id(new PHUIStatusItemView())
          ->setIcon(PHUIStatusItemView::ICON_ACCEPT, 'green')
          ->setTarget(pht('No Schema Issues')));
    } else {
      foreach ($issues as $issue) {
        $note = PhabricatorConfigStorageSchema::getIssueDescription($issue);

        $status = PhabricatorConfigStorageSchema::getIssueStatus($issue);
        switch ($status) {
          case PhabricatorConfigStorageSchema::STATUS_WARN:
            $icon = PHUIStatusItemView::ICON_WARNING;
            $color = 'yellow';
            break;
          case PhabricatorConfigStorageSchema::STATUS_FAIL:
          default:
            $icon = PHUIStatusItemView::ICON_REJECT;
            $color = 'red';
            break;
        }

        $item = id(new PHUIStatusItemView())
          ->setTarget(PhabricatorConfigStorageSchema::getIssueName($issue))
          ->setIcon($icon, $color)
          ->setNote($note);

        $status_view->addItem($item);
      }
    }
    $view->addProperty(pht('Schema Status'), $status_view);

    return phutil_tag_div('config-page-property', $view);
  }

  private function getURI(array $properties) {
    $defaults =  array(
      'ref' => $this->ref,
      'database' => $this->database,
      'table' => $this->table,
      'column' => $this->column,
      'key' => $this->key,
    );

    $properties = $properties + $defaults;
    $properties = array_select_keys($properties, array_keys($defaults));

    $parts = array();
    foreach ($properties as $key => $property) {
      if (!strlen($property)) {
        continue;
      }

      if ($key == 'column') {
        $parts[] = 'col';
      } else if ($key == 'key') {
        $parts[] = 'key';
      }

      $parts[] = $property;
    }

    if ($parts) {
      $parts = implode('/', $parts).'/';
    } else {
      $parts = null;
    }

    return $this->getApplicationURI('/database/'.$parts);
  }

}
