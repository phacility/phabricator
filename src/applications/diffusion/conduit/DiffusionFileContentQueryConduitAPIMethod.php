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
      'needsBlame' => 'optional bool',
      'timeout' => 'optional int',
      'byteLimit' => 'optional int',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $needs_blame = $request->getValue('needsBlame');
    $file_query = DiffusionFileContentQuery::newFromDiffusionRequest(
      $drequest);
    $file_query
      ->setViewer($request->getUser())
      ->setNeedsBlame($needs_blame);

    $timeout = $request->getValue('timeout');
    if ($timeout) {
      $file_query->setTimeout($timeout);
    }

    $byte_limit = $request->getValue('byteLimit');
    if ($byte_limit) {
      $file_query->setByteLimit($byte_limit);
    }

    $file_content = $file_query->loadFileContent();

    if ($needs_blame) {
      list($text_list, $rev_list, $blame_dict) = $file_query->getBlameData();
    } else {
      $text_list = $rev_list = $blame_dict = array();
    }

    $file_content
      ->setBlameDict($blame_dict)
      ->setRevList($rev_list)
      ->setTextList($text_list);

    return $file_content->toDictionary();
  }

}
