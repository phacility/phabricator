<?php

abstract class DrydockController extends PhabricatorController {

  protected function buildLocksTab($owner_phid) {
    $locks = DrydockSlotLock::loadLocks($owner_phid);

    $rows = array();
    foreach ($locks as $lock) {
      $rows[] = array(
        $lock->getID(),
        $lock->getLockKey(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No slot locks held.'))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Lock Key'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide',
        ));

    return id(new PHUIPropertyListView())
      ->addRawContent($table);
  }

  protected function buildCommandsTab($target_phid) {
    $viewer = $this->getViewer();

    $commands = id(new DrydockCommandQuery())
      ->setViewer($viewer)
      ->withTargetPHIDs(array($target_phid))
      ->execute();

    $consumed_yes = id(new PHUIIconView())
      ->setIcon('fa-check green');
    $consumed_no = id(new PHUIIconView())
      ->setIcon('fa-clock-o grey');

    $rows = array();
    foreach ($commands as $command) {
      $rows[] = array(
        $command->getID(),
        $viewer->renderHandle($command->getAuthorPHID()),
        $command->getCommand(),
        ($command->getIsConsumed()
          ? $consumed_yes
          : $consumed_no),
        phabricator_datetime($command->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No commands issued.'))
      ->setHeaders(
        array(
          pht('ID'),
          pht('From'),
          pht('Command'),
          null,
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide',
          null,
          null,
        ));

    return id(new PHUIPropertyListView())
      ->addRawContent($table);
  }

  protected function buildLogTable(DrydockLogQuery $query) {
    $viewer = $this->getViewer();

    $logs = $query
      ->setViewer($viewer)
      ->setLimit(100)
      ->execute();

    $log_table = id(new DrydockLogListView())
      ->setUser($viewer)
      ->setLogs($logs);

    return $log_table;
  }

  protected function buildLogBox(DrydockLogListView $log_table, $all_uri) {
    $log_header = id(new PHUIHeaderView())
      ->setHeader(pht('Logs'))
      ->addActionLink(
        id(new PHUIButtonView())
          ->setTag('a')
          ->setHref($all_uri)
          ->setIcon('fa-search')
          ->setText(pht('View All')));

    return id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeader($log_header)
      ->setTable($log_table);
  }

}
