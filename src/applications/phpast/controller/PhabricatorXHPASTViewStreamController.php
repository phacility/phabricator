<?php

final class PhabricatorXHPASTViewStreamController
  extends PhabricatorXHPASTViewPanelController {

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

    $tokens = array();
    foreach ($tree->getRawTokenStream() as $id => $token) {
      $seq = $id;
      $name = $token->getTypeName();
      $title = pht('Token %d: %s', $seq, $name);

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
