<?php

abstract class PhabricatorXHPASTViewPanelController
  extends PhabricatorXHPASTViewController {

  private $id;
  private $storageTree;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->storageTree = id(new PhabricatorXHPASTViewParseTree())
      ->load($this->id);
    if (!$this->storageTree) {
      throw new Exception(pht('No such AST!'));
    }
  }

  protected function getStorageTree() {
    return $this->storageTree;
  }

  protected function buildXHPASTViewPanelResponse($content) {
    $content = hsprintf(
      '<!DOCTYPE html>'.
      '<html>'.
        '<head>'.
          '<style type="text/css">
body {
  white-space: pre;
  font: 10px "Monaco";
  cursor: pointer;
}

.token {
  padding: 2px 4px;
  margin: 2px 2px;
  border: 1px solid #bbbbbb;
  line-height: 24px;
}

ul {
  margin: 0 0 0 1em;
  padding: 0;
  list-style: none;
  line-height: 1em;
}

li {
  margin: 0;
  padding: 0;
}

li span {
  background: #dddddd;
  padding: 3px 6px;
}

          </style>'.
        '</head>'.
        '<body>%s</body>'.
      '</html>',
      $content);

    $response = new AphrontWebpageResponse();
    $response->setFrameable(true);
    $response->setContent($content);
    return $response;
  }

}
