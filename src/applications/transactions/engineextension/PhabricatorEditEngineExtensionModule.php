<?php

final class PhabricatorEditEngineExtensionModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'editengine';
  }

  public function getModuleName() {
    return pht('Engine: Edit');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $extensions = PhabricatorEditEngineExtension::getAllExtensions();

    $rows = array();
    foreach ($extensions as $extension) {
      $rows[] = array(
        $extension->getExtensionPriority(),
        get_class($extension),
        $extension->getExtensionName(),
        $extension->isExtensionEnabled()
          ? pht('Yes')
          : pht('No'),
      );
    }

    return id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Priority'),
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

  }

}
