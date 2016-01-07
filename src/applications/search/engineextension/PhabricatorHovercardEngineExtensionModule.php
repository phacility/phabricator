<?php

final class PhabricatorHovercardEngineExtensionModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'hovercardengine';
  }

  public function getModuleName() {
    return pht('Engine: Hovercards');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $extensions = PhabricatorHovercardEngineExtension::getAllExtensions();

    $rows = array();
    foreach ($extensions as $extension) {
      $rows[] = array(
        $extension->getExtensionOrder(),
        $extension->getExtensionKey(),
        get_class($extension),
        $extension->getExtensionName(),
        $extension->isExtensionEnabled()
          ? pht('Yes')
          : pht('No'),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Order'),
          pht('Key'),
          pht('Class'),
          pht('Name'),
          pht('Enabled'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          'wide pri',
          null,
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('HovercardEngine Extensions'))
      ->setTable($table);
  }

}
