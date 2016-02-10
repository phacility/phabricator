<?php

final class PhabricatorProjectSearchField
  extends PhabricatorSearchTokenizerField {

  protected function getDefaultValue() {
    return array();
  }

  protected function newDatasource() {
    return new PhabricatorProjectLogicalDatasource();
  }

  protected function getValueFromRequest(AphrontRequest $request, $key) {
    $list = $this->getListFromRequest($request, $key);

    $phids = array();
    $slugs = array();
    $project_type = PhabricatorProjectProjectPHIDType::TYPECONST;
    foreach ($list as $item) {
      $type = phid_get_type($item);
      if ($type == $project_type) {
        $phids[] = $item;
      } else {
        if (PhabricatorTypeaheadDatasource::isFunctionToken($item)) {
          // If this is a function, pass it through unchanged; we'll evaluate
          // it later.
          $phids[] = $item;
        } else {
          $slugs[] = $item;
        }
      }
    }

    if ($slugs) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->getViewer())
        ->withSlugs($slugs)
        ->execute();
      foreach ($projects as $project) {
        $phids[] = $project->getPHID();
      }
      $phids = array_unique($phids);
    }

    return $phids;

  }

  protected function newConduitParameterType() {
    return new ConduitProjectListParameterType();
  }

}
