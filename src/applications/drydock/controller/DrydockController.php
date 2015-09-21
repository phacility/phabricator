<?php

abstract class DrydockController extends PhabricatorController {

  abstract public function buildSideNavView();

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

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

}
