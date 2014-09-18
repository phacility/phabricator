<?php

final class PhabricatorConfigDatabaseController
  extends PhabricatorConfigController {

  private $database;
  private $table;
  private $column;

  public function willProcessRequest(array $data) {
    $this->database = idx($data, 'database');
    $this->table = idx($data, 'table');
    $this->column = idx($data, 'column');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $conf = PhabricatorEnv::newObjectFromConfig(
      'mysql.configuration-provider',
      array($dao = null, 'w'));

    $api = id(new PhabricatorStorageManagementAPI())
      ->setUser($conf->getUser())
      ->setHost($conf->getHost())
      ->setPort($conf->getPort())
      ->setNamespace(PhabricatorLiskDAO::getDefaultStorageNamespace())
      ->setPassword($conf->getPassword());

    $query = id(new PhabricatorConfigSchemaQuery())
      ->setAPI($api);

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
        $crumbs->addTextCrumb($this->table);
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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties)
      ->appendChild($table);

    return $this->buildResponse($title, $box);
  }

  private function renderDatabase(
    PhabricatorConfigServerSchema $comp,
    PhabricatorConfigServerSchema $expect,
    PhabricatorConfigServerSchema $actual,
    $database_name) {

    $database = $comp->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $rows = array();
    foreach ($database->getTables() as $table_name => $table) {

      $status = $table->getStatus();
      $issues = $table->getIssues();

      $rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI(
              '/database/'.$database_name.'/'.$table_name.'/'),
          ),
          $table_name),
        $table->getCollation(),
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

    $title = pht('Database Status: %s', $database_name);

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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties)
      ->appendChild($table);

    return $this->buildResponse($title, $box);
  }

  private function renderTable(
    PhabricatorConfigServerSchema $comp,
    PhabricatorConfigServerSchema $expect,
    PhabricatorConfigServerSchema $actual,
    $database_name,
    $table_name) {

    $database = $comp->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $table = $database->getTable($table_name);
    if (!$table) {
      return new Aphront404Response();
    }

    $rows = array();
    foreach ($table->getColumns() as $column_name => $column) {
      $status = $column->getStatus();

      $rows[] = array(
        $this->renderIcon($status),
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI(
              'database/'.
              $database_name.'/'.
              $table_name.'/'.
              $column_name.'/'),
          ),
          $column_name),
        $column->getColumnType(),
        $column->getCharacterSet(),
        $column->getCollation(),
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Table'),
          pht('Column Type'),
          pht('Character Set'),
          pht('Collation'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
          null,
          null,
          null
        ));

    $title = pht('Database Status: %s.%s', $database_name, $table_name);

    $properties = $this->buildProperties(
      array(
      ),
      $table->getIssues());

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties)
      ->appendChild($table_view);

    return $this->buildResponse($title, $box);
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
    if (!$table) {
      return new Aphront404Response();
    }

    $title = pht(
      'Database Status: %s.%s.%s',
      $database_name,
      $table_name,
      $column_name);

    $properties = $this->buildProperties(
      array(
      ),
      $column->getIssues());

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->addPropertyList($properties);

    return $this->buildResponse($title, $box);
  }
  private function renderIcon($status) {
    switch ($status) {
      case PhabricatorConfigStorageSchema::STATUS_OKAY:
        $icon = 'fa-check-circle green';
        break;
      case PhabricatorConfigStorageSchema::STATUS_WARN:
        $icon = 'fa-exclamation-circle yellow';
        break;
      case PhabricatorConfigStorageSchema::STATUS_FAIL:
      default:
        $icon = 'fa-times-circle red';
        break;
    }

    return id(new PHUIIconView())
      ->setIconFont($icon);
  }

  private function renderAttr($attr, $issue) {
    if ($issue) {
      return phutil_tag(
        'span',
        array(
          'style' => 'color: #aa0000;',
        ),
        $attr);
    } else {
      return $attr;
    }
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
