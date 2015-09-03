<?php

final class PhabricatorConfigPHIDModule extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'phid';
  }

  public function getModuleName() {
    return pht('PHID Types');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $types = PhabricatorPHIDType::getAllTypes();
    $types = msort($types, 'getTypeConstant');

    $rows = array();
    foreach ($types as $key => $type) {
      $rows[] = array(
        $type->getTypeConstant(),
        get_class($type),
        $type->getTypeName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Constant'),
          pht('Class'),
          pht('Name'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri',
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('PHID Types'))
      ->setTable($table);
  }

}
