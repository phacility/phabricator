<?php

final class PhabricatorConfigHTTPParameterTypesModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'httpparameter';
  }

  public function getModuleName() {
    return pht('HTTP Parameter Types');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $types = AphrontHTTPParameterType::getAllTypes();

    $table = id(new PhabricatorHTTPParameterTypeTableView())
      ->setHTTPParameterTypes($types);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('HTTP Parameter Types'))
      ->setTable($table);
  }

}
