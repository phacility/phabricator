<?php

final class PhabricatorSearchEngineExtensionModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'searchengine';
  }

  public function getModuleName() {
    return pht('SearchEngine Extensions');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $extensions = PhabricatorSearchEngineExtension::getAllExtensions();

    $rows = array();
    foreach ($extensions as $extension) {
      $rows[] = array(
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
          pht('Key'),
          pht('Class'),
          pht('Name'),
          pht('Enabled'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'wide pri',
          null,
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('SearchEngine Extensions'))
      ->setTable($table);
  }

}
