<?php

final class PhabricatorAuthSessionEngineExtensionModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'sessionengine';
  }

  public function getModuleName() {
    return pht('Engine: Session');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $extensions = PhabricatorAuthSessionEngineExtension::getAllExtensions();

    $rows = array();
    foreach ($extensions as $extension) {
      $rows[] = array(
        get_class($extension),
        $extension->getExtensionKey(),
        $extension->getExtensionName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(
        pht('There are no registered session engine extensions.'))
      ->setHeaders(
        array(
          pht('Class'),
          pht('Key'),
          pht('Name'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide pri',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('SessionEngine Extensions'))
      ->setTable($table);
  }

}
