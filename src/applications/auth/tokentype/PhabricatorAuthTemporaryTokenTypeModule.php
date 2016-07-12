<?php

final class PhabricatorAuthTemporaryTokenTypeModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'temporarytoken';
  }

  public function getModuleName() {
    return pht('Temporary Tokens');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $types = PhabricatorAuthTemporaryTokenType::getAllTypes();

    $rows = array();
    foreach ($types as $type) {
      $rows[] = array(
        get_class($type),
        $type->getTokenTypeConstant(),
        $type->getTokenTypeDisplayName(),
      );
    }

    $table = id(new AphrontTableView($rows))
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
      ->setHeaderText(pht('Temporary Token Types'))
      ->setTable($table);
  }

}
