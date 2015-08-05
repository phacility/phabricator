<?php

final class PhabricatorXHPASTViewStreamController
  extends PhabricatorXHPASTViewPanelController {

  public function handleRequest(AphrontRequest $request) {
    $storage = $this->getStorageTree();
    $input = $storage->getInput();
    $stdout = $storage->getStdout();

    $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
      $input,
      array(0, $stdout, ''));

    $tokens = array();
    foreach ($tree->getRawTokenStream() as $id => $token) {
      $seq = $id;
      $name = $token->getTypeName();
      $title = pht('Token %s: %s', $seq, $name);

      $tokens[] = phutil_tag(
        'span',
        array(
          'title' => $title,
          'class' => 'token',
        ),
        $token->getValue());
    }

    return $this->buildXHPASTViewPanelResponse(
      phutil_implode_html('', $tokens));
  }
}
