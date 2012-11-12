<?php

final class PhabricatorXHPASTViewTreeController
  extends PhabricatorXHPASTViewPanelController {

  public function processRequest() {
    $storage = $this->getStorageTree();
    $input = $storage->getInput();
    $stdout = $storage->getStdout();

    $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
      $input,
      array(0, $stdout, ''));

    $tree = '<ul>'.$this->buildTree($tree->getRootNode()).'</ul>';
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
    $tree[] =
      '<li>'.
        phutil_render_tag(
          'span',
          array(
            'title' => $title,
          ),
          phutil_escape_html($name)).
      '</li>';
    foreach ($root->getChildren() as $child) {
      $tree[] = '<ul>'.$this->buildTree($child).'</ul>';
    }
    return implode("\n", $tree);
  }

}
