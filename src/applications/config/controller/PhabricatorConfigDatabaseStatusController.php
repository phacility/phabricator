<?php

final class PhabricatorConfigDatabaseStatusController
  extends PhabricatorConfigDatabaseController {

  private $database;
  private $table;
  private $column;
  private $key;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $this->database = $request->getURIData('database');
    $this->table = $request->getURIData('table');
    $this->column = $request->getURIData('column');
    $this->key = $request->getURIData('key');

    $query = $this->buildSchemaQuery();

    $actual = $query->loadActualSchema();
    $expect = $query->loadExpectedSchema();
    $comp = $query->buildComparisonSchema($expect, $actual);

    if ($this->column) {
      return $this->renderColumn(
        $comp,
        $expect,
        $actual,
        $this->database,
        $this->table,
        $this->column);
    } else if ($this->key) {
      return $this->renderKey(
        $comp,
        $expect,
        $actual,
        $this->database,
        $this->table,
        $this->key);
    } else if ($this->table) {
      return $this->renderTable(
        $comp,
        $expect,
        $actual,
        $this->database,
        $this->table);
    } else if ($this->database) {
      return $this->renderDatabase(
        $comp,
        $expect,
        $actual,
        $this->database);
    } else {
      return $this->renderServer(
        $comp,
        $expect,
        $actual);
    }
  }

  private function buildResponse($title, $body) {
    $nav = $this->buildSideNavView();
    $nav->selectFilter('database/');

    $crumbs = $this->buildApplicationCrumbs();
    if ($this->database) {
      $crumbs->addTextCrumb(
        pht('Database Status'),
        $this->getApplicationURI('database/'));
      if ($this->table) {
        $crumbs->addTextCrumb(
          $this->database,
          $this->getApplicationURI('database/'.$this->database.'/'));
        if ($this->column || $this->key) {
          $crumbs->addTextCrumb(
            $this->table,
            $this->getApplicationURI(
              'database/'.$this->database.'/'.$this->table.'/'));
          if ($this->column) {
            $crumbs->addTextCrumb($this->column);
          } else {
            $crumbs->addTextCrumb($this->key);
          }
        } else {
          $crumbs->addTextCrumb($this->table);
        }
      } else {
        $crumbs->addTextCrumb($this->database);
      }
    } else {
      $crumbs->addTextCrumb(pht('Database Status'));
    }

    $nav->setCrumbs($crumbs);
    $nav->appendChild($body);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }


  private function renderServer(
    PhabricatorConfigServerSchema $comp,
    PhabricatorConfigServerSchema $expect,
    PhabricatorConfigServerSchema $actual) {

    $charset_issue = PhabricatorConfigStorageSchema::ISSUE_CHARSET;
    $collation_issue = PhabricatorConfigStorageSchema::ISSUE_COLLATION;

    $rows = array();
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

      $rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI(
              '/database/'.$database_name.'/'),
          ),
          $database_name),
        $this->renderAttr($charset, $database->hasIssue($charset_issue)),
        $this->renderAttr($collation, $database->hasIssue($collation_issue)),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Database'),
          pht('Charset'),
          pht('Collation'),
        ))
      ->setColumnClasses(
        array(
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

    $prop_box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderWithDocumentationLink($title))
      ->addPropertyList($properties);

    $table_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Databases'))
      ->setTable($table);

    return $this->buildResponse($title, array($prop_box, $table_box));
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

      $rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI(
              '/database/'.$database_name.'/'.$table_name.'/'),
          ),
          $table_name),
        $this->renderAttr(
          $table->getCollation(),
          $table->hasIssue($collation_issue)),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Table'),
          pht('Collation'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
          null,
        ));

    $title = pht('Database: %s', $database_name);

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

    $prop_box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderWithDocumentationLink($title))
      ->addPropertyList($properties);

    $table_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Database Status'))
      ->setTable($table);

    return $this->buildResponse($title, array($prop_box, $table_box));
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

      $rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI(
              'database/'.
              $database_name.'/'.
              $table_name.'/'.
              'col/'.
              $column_name.'/'),
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

      $key_rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI(
              'database/'.
              $database_name.'/'.
              $table_name.'/'.
              'key/'.
              $key_name.'/'),
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

    $title = pht('Database: %s.%s', $database_name, $table_name);

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
          pht('Collation'),
          $actual_collation,
        ),
        array(
          pht('Expected Collation'),
          $expect_collation,
        ),
      ),
      $table->getIssues());

    $prop_box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderWithDocumentationLink($title))
      ->addPropertyList($properties);

    $table_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Database'))
      ->setTable($table_view);

    $key_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Keys'))
      ->setTable($keys_view);

    return $this->buildResponse($title, array($prop_box, $table_box, $key_box));
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
      'Database Status: %s.%s.%s',
      $database_name,
      $table_name,
      $column_name);

    $properties = $this->buildProperties(
      array(
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

    $box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderWithDocumentationLink($title))
      ->addPropertyList($properties);

    return $this->buildResponse($title, $box);
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
      'Database Status: %s.%s (%s)',
      $database_name,
      $table_name,
      $key_name);

    $properties = $this->buildProperties(
      array(
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

    $box = id(new PHUIObjectBoxView())
      ->setHeader($this->buildHeaderWithDocumentationLink($title))
      ->addPropertyList($properties);

    return $this->buildResponse($title, $box);
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

    return $view;
  }

}
