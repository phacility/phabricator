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
      $class_name = $type->getPHIDTypeApplicationClass();
      if ($class_name !== null) {
        $app = PhabricatorApplication::getByClass($class_name);
        $app_name = $app->getName();

        $icon = $app->getIcon();
        if ($icon) {
          $app_icon = id(new PHUIIconView())->setIcon($icon);
        } else {
          $app_icon = null;
        }
      } else {
        $app_name = null;
        $app_icon = null;
      }

      $icon = $type->getTypeIcon();
      if ($icon) {
        $type_icon = id(new PHUIIconView())->setIcon($icon);
      } else {
        $type_icon = null;
      }

      $rows[] = array(
        $type->getTypeConstant(),
        get_class($type),
        $app_icon,
        $app_name,
        $type_icon,
        $type->getTypeName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Constant'),
          pht('Class'),
          null,
          pht('Application'),
          null,
          pht('Name'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri',
          'icon',
          null,
          'icon',
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('PHID Types'))
      ->setTable($table);
  }

}
