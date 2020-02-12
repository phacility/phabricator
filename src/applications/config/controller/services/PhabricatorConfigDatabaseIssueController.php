<?php

final class PhabricatorConfigDatabaseIssueController
  extends PhabricatorConfigDatabaseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $query = new PhabricatorConfigSchemaQuery();

    $actual = $query->loadActualSchemata();
    $expect = $query->loadExpectedSchemata();
    $comp_servers = $query->buildComparisonSchemata($expect, $actual);

    // Collect all open issues.
    $issues = array();
    foreach ($comp_servers as $ref_name => $comp) {
      foreach ($comp->getDatabases() as $database_name => $database) {
        foreach ($database->getLocalIssues() as $issue) {
          $issues[] = array(
            $ref_name,
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
              $ref_name,
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
                $ref_name,
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
                $ref_name,
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
    }

    // Sort all open issues so that the most severe issues appear first.
    $order = array();
    $counts = array();
    foreach ($issues as $key => $issue) {
      $const = $issue[5];
      $status = PhabricatorConfigStorageSchema::getIssueStatus($const);
      $severity = PhabricatorConfigStorageSchema::getStatusSeverity($status);
      $order[$key] = sprintf(
        '~%d~%s%s%s',
        9 - $severity,
        $issue[1],
        $issue[2],
        $issue[4]);

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
      $const = $issue[5];

      $uri = $this->getApplicationURI('/database/'.$issue[0].'/'.$issue[1].'/');

      $database_link = phutil_tag(
        'a',
        array(
          'href' => $uri,
        ),
        $issue[1]);

      $rows[] = array(
        $this->renderIcon(
          PhabricatorConfigStorageSchema::getIssueStatus($const)),
        $issue[0],
        $database_link,
        $issue[2],
        $issue[3],
        $issue[4],
        PhabricatorConfigStorageSchema::getIssueDescription($const),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht('No databases have any issues.'))
      ->setHeaders(
        array(
          null,
          pht('Server'),
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

    $title = pht('Schemata Issues');
    $header = $this->buildHeaderView($title);

    $nav = $this->newNavigation('schemata-issues');

    $view = $this->buildConfigBoxView(pht('Issues'), $table);

    $crumbs = $this->newCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content);
  }

}
