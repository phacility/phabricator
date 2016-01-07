<?php

final class DiffusionFileContentQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.filecontentquery';
  }

  public function getMethodDescription() {
    return pht('Retrieve file content from a repository.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'path' => 'required string',
      'commit' => 'required string',
      'timeout' => 'optional int',
      'byteLimit' => 'optional int',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();

    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest($drequest)
      ->setViewer($request->getUser());

    $timeout = $request->getValue('timeout');
    if ($timeout) {
      $file_query->setTimeout($timeout);
    }

    $byte_limit = $request->getValue('byteLimit');
    if ($byte_limit) {
      $file_query->setByteLimit($byte_limit);
    }

    $file_content = $file_query->loadFileContent();

    $text_list = $rev_list = $blame_dict = array();

    $file_content
      ->setBlameDict($blame_dict)
      ->setRevList($rev_list)
      ->setTextList($text_list);

    return $file_content->toDictionary();
  }

}
