<?php

final class PhabricatorMacroMemeDialogController
  extends PhabricatorMacroController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $name = $request->getStr('macro');
    $above = $request->getStr('above');
    $below = $request->getStr('below');

    $e_macro = true;
    $errors = array();
    if ($request->isDialogFormPost()) {
      if (!$name) {
        $e_macro = pht('Required');
        $errors[] = pht('Macro name is required.');
      } else {
        $macro = id(new PhabricatorFileImageMacro())->loadOneWhere(
          'name = %s',
          $name);
        if (!$macro) {
          $e_macro = pht('Invalid');
          $errors[] = pht('No such macro.');
        }
      }

      if (!$errors) {
        $options = new PhutilSimpleOptions();
        $data = array(
          'src' => $name,
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

    $view = id(new AphrontFormLayoutView())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Macro'))
          ->setName('macro')
          ->setValue($name)
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
      ->setUser($user)
      ->setTitle(pht('Create Meme'))
      ->appendChild($view)
      ->addCancelButton('/')
      ->addSubmitButton(pht('rofllolo!!~'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
