<?php

final class PhabricatorXHPASTViewTreeController
  extends PhabricatorXHPASTViewPanelController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $storage = $this->getStorageTree();
    $input = $storage->getInput();
    $stdout = $storage->getStdout();

    $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
      $input,
      array(0, $stdout, ''));

    $tree = phutil_tag('ul', array(), $this->buildTree($tree->getRootNode()));
    return $this->buildXHPASTViewPanelResponse($tree);
  }

  protected function buildTree($root) {

    try {
      $name = $root->getTypeName();
      $title = $root->getDescription();
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
