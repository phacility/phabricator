<?php

final class PhabricatorXHPASTViewRunController
  extends PhabricatorXHPASTViewController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $source = $request->getStr('source');

      $future = PhutilXHPASTBinary::getParserFuture($source);
      $resolved = $future->resolve();

      // This is just to let it throw exceptions if stuff is broken.
      $parse_tree = XHPASTTree::newFromDataAndResolvedExecFuture(
        $source,
        $resolved);

      list($err, $stdout, $stderr) = $resolved;

      $storage_tree = new PhabricatorXHPASTViewParseTree();
      $storage_tree->setInput($source);
      $storage_tree->setStdout($stdout);
      $storage_tree->setAuthorPHID($user->getPHID());
      $storage_tree->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/xhpast/view/'.$storage_tree->getID().'/');
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Source'))
          ->setName('source')
          ->setValue("<?php\n\n")
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Parse'));

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
