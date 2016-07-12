<?php

final class PhabricatorConfigEdgeModule extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'edge';
  }

  public function getModuleName() {
    return pht('Edge Types');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $types = PhabricatorEdgeType::getAllTypes();
    $types = msort($types, 'getEdgeConstant');

    $rows = array();
    foreach ($types as $key => $type) {
      $rows[] = array(
        $type->getEdgeConstant(),
        $type->getInverseEdgeConstant(),
        get_class($type),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Constant'),
          pht('Inverse'),
          pht('Class'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'pri wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edge Types'))
      ->setTable($table);
  }

}
