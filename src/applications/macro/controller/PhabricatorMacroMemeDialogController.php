<?php

final class PhabricatorMacroMemeDialogController
  extends PhabricatorMacroController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $phid = head($request->getArr('macro'));
    $above = $request->getStr('above');
    $below = $request->getStr('below');

    $e_macro = true;
    $errors = array();
    if ($request->isDialogFormPost()) {
      if (!$phid) {
        $e_macro = pht('Required');
        $errors[] = pht('Macro name is required.');
      } else {
        $macro = id(new PhabricatorMacroQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($phid))
          ->executeOne();
        if (!$macro) {
          $e_macro = pht('Invalid');
          $errors[] = pht('No such macro.');
        }
      }

      if (!$errors) {
        $options = new PhutilSimpleOptions();
        $data = array(
          'src' => $macro->getName(),
          'above' => $above,
          'below' => $below,
        );
        $string = $options->unparse($data, $escape = '}');

        $result = array(
          'text' => "{meme, {$string}}",
        );
        return id(new AphrontAjaxResponse())->setContent($result);
      }
    }

    $view = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Macro'))
          ->setName('macro')
          ->setLimit(1)
          ->setDatasource(new PhabricatorMacroDatasource())
          ->setError($e_macro))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Above'))
          ->setName('above')
          ->setValue($above))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Below'))
          ->setName('below')
          ->setValue($below));

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle(pht('Create Meme'))
      ->appendForm($view)
      ->addCancelButton('/')
      ->addSubmitButton(pht('Llama Diorama'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
