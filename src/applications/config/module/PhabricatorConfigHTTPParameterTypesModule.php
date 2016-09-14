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

    return id(new PhabricatorHTTPParameterTypeTableView())
      ->setHTTPParameterTypes($types);
  }

}
