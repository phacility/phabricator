<?php

final class PhabricatorXHPASTViewRunController
  extends PhabricatorXHPASTViewController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if ($request->isFormPost()) {
      $source = $request->getStr('source');

      $future = PhutilXHPASTBinary::getParserFuture($source);
      $resolved = $future->resolve();

      // This is just to let it throw exceptions if stuff is broken.
      try {
        XHPASTTree::newFromDataAndResolvedExecFuture($source, $resolved);
      } catch (XHPASTSyntaxErrorException $ex) {
        // This is possibly expected.
      }

      list($err, $stdout, $stderr) = $resolved;

      $storage_tree = id(new PhabricatorXHPASTParseTree())
        ->setInput($source)
        ->setReturnCode($err)
        ->setStdout($stdout)
        ->setStderr($stderr)
        ->setAuthorPHID($viewer->getPHID())
        ->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/xhpast/view/'.$storage_tree->getID().'/');
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Source'))
          ->setName('source')
          ->setValue("<?php\n\n")
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Parse')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Generate XHP AST'))
      ->setForm($form);

    return $this->buildApplicationPage(
      $form_box,
      array(
        'title' => pht('XHPAST View'),
      ));
  }

}
