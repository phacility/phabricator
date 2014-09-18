<?php

final class PhabricatorConfigDatabaseController
  extends PhabricatorConfigController {

  private $database;
  private $table;

  public function willProcessRequest(array $data) {
    $this->database = idx($data, 'database');
    $this->table = idx($data, 'table');
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

    if ($this->table) {
      return $this->renderTable(
        $actual,
        $expect,
        $this->database,
        $this->table);
    } else if ($this->database) {
      return $this->renderDatabase(
        $actual,
        $expect,
        $this->database);
    } else {
      return $this->renderServer(
        $actual,
        $expect);
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
    PhabricatorConfigServerSchema $schema,
    PhabricatorConfigServerSchema $expect) {

    $icon_ok = id(new PHUIIconView())
      ->setIconFont('fa-check-circle green');

    $icon_warn = id(new PHUIIconView())
      ->setIconFont('fa-exclamation-circle yellow');

    $rows = array();
    foreach ($schema->getDatabases() as $database_name => $database) {

      $expect_database = $expect->getDatabase($database_name);
      if ($expect_database) {
        $expect_set = $expect_database->getCharacterSet();
        $expect_collation = $expect_database->getCollation();

        if ($database->isSameSchema($expect_database)) {
          $icon = $icon_ok;
        } else {
          $icon = $icon_warn;
        }
      } else {
        $expect_set = null;
        $expect_collation = null;
        $icon = $icon_warn;
      }

      $actual_set = $database->getCharacterSet();
      $actual_collation = $database->getCollation();



      $rows[] = array(
        $icon,
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI(
              '/database/'.$database_name.'/'),
          ),
          $database_name),
        $actual_set,
        $expect_set,
        $actual_collation,
        $expect_collation,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Database'),
          pht('Charset'),
          pht('Expected Charset'),
          pht('Collation'),
          pht('Expected Collation'),
        ))
      ->setColumnClasses(
        array(
          '',
          'wide pri',
          null,
          null,
        ));

    $title = pht('Database Status');

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($table);

    return $this->buildResponse($title, $box);
  }

  private function renderDatabase(
    PhabricatorConfigServerSchema $schema,
    PhabricatorConfigServerSchema $expect,
    $database_name) {

    $database = $schema->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $rows = array();
    foreach ($database->getTables() as $table_name => $table) {
      $rows[] = array(
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
          pht('Table'),
          pht('Collation'),
        ))
      ->setColumnClasses(
        array(
          'wide pri',
          null,
        ));

    $title = pht('Database Status: %s', $database_name);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($table);

    return $this->buildResponse($title, $box);
  }

  private function renderTable(
    PhabricatorConfigServerSchema $schema,
    PhabricatorConfigServerSchema $expect,
    $database_name,
    $table_name) {

    $database = $schema->getDatabase($database_name);
    if (!$database) {
      return new Aphront404Response();
    }

    $table = $database->getTable($table_name);
    if (!$table) {
      return new Aphront404Response();
    }

    $rows = array();
    foreach ($table->getColumns() as $column_name => $column) {
      $rows[] = array(
        $column_name,
        $column->getColumnType(),
        $column->getCharacterSet(),
        $column->getCollation(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Table'),
          pht('Column Type'),
          pht('Character Set'),
          pht('Collation'),
        ))
      ->setColumnClasses(
        array(
          'wide pri',
          null,
          null,
          null
        ));

    $title = pht('Database Status: %s.%s', $database_name, $table_name);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($table);

    return $this->buildResponse($title, $box);
  }

}
