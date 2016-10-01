<?php

final class PhabricatorConfigDatabaseIssueController
  extends PhabricatorConfigDatabaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $query = $this->buildSchemaQuery();

    $actual = $query->loadActualSchema();
    $expect = $query->loadExpectedSchema();
    $comp = $query->buildComparisonSchema($expect, $actual);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Database Issues'));
    $crumbs->setBorder(true);

    // Collect all open issues.
    $issues = array();
    foreach ($comp->getDatabases() as $database_name => $database) {
      foreach ($database->getLocalIssues() as $issue) {
        $issues[] = array(
          $database_name,
          null,
          null,
          null,
          $issue,
        );
      }
      foreach ($database->getTables() as $table_name => $table) {
        foreach ($table->getLocalIssues() as $issue) {
          $issues[] = array(
            $database_name,
            $table_name,
            null,
            null,
            $issue,
          );
        }
        foreach ($table->getColumns() as $column_name => $column) {
          foreach ($column->getLocalIssues() as $issue) {
            $issues[] = array(
              $database_name,
              $table_name,
              'column',
              $column_name,
              $issue,
            );
          }
        }
        foreach ($table->getKeys() as $key_name => $key) {
          foreach ($key->getLocalIssues() as $issue) {
            $issues[] = array(
              $database_name,
              $table_name,
              'key',
              $key_name,
              $issue,
            );
          }
        }
      }
    }


    // Sort all open issues so that the most severe issues appear first.
    $order = array();
    $counts = array();
    foreach ($issues as $key => $issue) {
      $const = $issue[4];
      $status = PhabricatorConfigStorageSchema::getIssueStatus($const);
      $severity = PhabricatorConfigStorageSchema::getStatusSeverity($status);
      $order[$key] = sprintf(
        '~%d~%s%s%s',
        9 - $severity,
        $issue[0],
        $issue[1],
        $issue[3]);

      if (empty($counts[$status])) {
        $counts[$status] = 0;
      }

      $counts[$status]++;
    }
    asort($order);
    $issues = array_select_keys($issues, array_keys($order));


    // Render the issues.
    $rows = array();
    foreach ($issues as $issue) {
      $const = $issue[4];

      $database_link = phutil_tag(
        'a',
        array(
          'href' => $this->getApplicationURI('/database/'.$issue[0].'/'),
        ),
        $issue[0]);

      $rows[] = array(
        $this->renderIcon(
          PhabricatorConfigStorageSchema::getIssueStatus($const)),
        $database_link,
        $issue[1],
        $issue[2],
        $issue[3],
        PhabricatorConfigStorageSchema::getIssueDescription($const),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht('No databases have any issues.'))
      ->setHeaders(
        array(
          null,
          pht('Database'),
          pht('Table'),
          pht('Type'),
          pht('Column/Key'),
          pht('Issue'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          null,
          null,
          'wide',
        ));

    $errors = array();

    if (isset($counts[PhabricatorConfigStorageSchema::STATUS_FAIL])) {
      $errors[] = pht(
        'Detected %s serious issue(s) with the schemata.',
        new PhutilNumber($counts[PhabricatorConfigStorageSchema::STATUS_FAIL]));
    }

    if (isset($counts[PhabricatorConfigStorageSchema::STATUS_WARN])) {
      $errors[] = pht(
        'Detected %s warning(s) with the schemata.',
        new PhutilNumber($counts[PhabricatorConfigStorageSchema::STATUS_WARN]));
    }

    $title = pht('Database Issues');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('dbissue/');

    $content = id(new PhabricatorConfigPageView())
      ->setHeader($header)
      ->setContent($table);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content)
      ->addClass('white-background');
  }

}
