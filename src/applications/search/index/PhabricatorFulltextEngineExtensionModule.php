<?php

final class PhabricatorFulltextEngineExtensionModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'fulltextengine';
  }

  public function getModuleName() {
    return pht('Engine: Fulltext');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $extensions = PhabricatorFulltextEngineExtension::getAllExtensions();

    $rows = array();
    foreach ($extensions as $extension) {
      $rows[] = array(
        get_class($extension),
        $extension->getExtensionName(),
      );
    }

    return id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Class'),
          pht('Name'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide pri',
        ));

  }

}
