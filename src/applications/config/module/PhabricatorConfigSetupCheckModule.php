<?php

final class PhabricatorConfigSetupCheckModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'setup';
  }

  public function getModuleName() {
    return pht('Setup Checks');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $checks = PhabricatorSetupCheck::loadAllChecks();

    $rows = array();
    foreach ($checks as $key => $check) {
      if ($check->isPreflightCheck()) {
        $icon = id(new PHUIIconView())->setIcon('fa-plane blue');
      } else {
        $icon = id(new PHUIIconView())->setIcon('fa-times grey');
      }

      $rows[] = array(
        $check->getExecutionOrder(),
        $icon,
        get_class($check),
      );
    }

    return id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Order'),
          pht('Preflight'),
          pht('Class'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'pri wide',
        ));
  }

}
