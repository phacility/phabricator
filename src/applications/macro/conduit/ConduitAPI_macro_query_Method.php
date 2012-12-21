<?php

/**
 * @group conduit
 */
final class ConduitAPI_macro_query_Method extends ConduitAPI_macro_Method {

  public function getMethodDescription() {
    return "Retrieve image macro information.";
  }

  public function defineParamTypes() {
    return array(
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $macros = id(new PhabricatorFileImageMacro())->loadAll();

    $files = array();
    if ($macros) {
      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid IN (%Ls)',
        mpull($macros, 'getFilePHID'));
      $files = mpull($files, null, 'getPHID');
    }

    $results = array();
    foreach ($macros as $macro) {
      if (empty($files[$macro->getFilePHID()])) {
        continue;
      }
      $results[$macro->getName()] = array(
        'uri' => $files[$macro->getFilePHID()]->getBestURI(),
      );
    }

    return $results;
  }

}
