<?php

final class PhabricatorXHPASTViewTreeController
  extends PhabricatorXHPASTViewPanelController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $storage = $this->getStorageTree();
    $input = $storage->getInput();
    $err = $storage->getReturnCode();
    $stdout = $storage->getStdout();
    $stderr = $storage->getStderr();

    try {
      $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
        $input,
        array($err, $stdout, $stderr));
    } catch (XHPASTSyntaxErrorException $ex) {
      return $this->buildXHPASTViewPanelResponse($ex->getMessage());
    }

    $tree = phutil_tag('ul', array(), $this->buildTree($tree->getRootNode()));
    return $this->buildXHPASTViewPanelResponse($tree);
  }

  protected function buildTree($root) {
    try {
      $name = $root->getTypeName();
      $title = pht('Node %d: %s', $root->getID(), $name);
    } catch (Exception $ex) {
      $name = '???';
      $title = '???';
    }

    $tree = array();
    $tree[] = phutil_tag(
      'li',
      array(),
      phutil_tag(
        'span',
        array(
          'title' => $title,
        ),
        $name));
    foreach ($root->getChildren() as $child) {
      $tree[] = phutil_tag('ul', array(), $this->buildTree($child));
    }
    return phutil_implode_html("\n", $tree);
  }

}
