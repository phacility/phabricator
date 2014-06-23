<?php

final class PhabricatorSystemSelectHighlightController
  extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();

    if ($request->isFormPost()) {
      $result = array('highlight' => $request->getStr('highlight'));
      return id(new AphrontAjaxResponse())->setContent($result);
    }

    $languages = array(
      '' => pht('(Use Default)'),
    ) + PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->appendRemarkupInstructions(pht('Choose a syntax highlighting to use.'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Highlighting'))
          ->setName('highlight')
          ->setValue($request->getStr('highlight'))
          ->setOptions($languages));

    return $this->newDialog()
      ->setTitle(pht('Select Syntax Highlighting'))
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Choose Highlighting'))
      ->addCancelButton('/');
  }
}
