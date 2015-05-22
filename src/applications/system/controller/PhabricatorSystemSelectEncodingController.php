<?php

final class PhabricatorSystemSelectEncodingController
  extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    $request = $this->getRequest();

    if (!function_exists('mb_list_encodings')) {
      return $this->newDialog()
        ->setTitle(pht('No Encoding Support'))
        ->appendParagraph(
          pht(
            'This system does not have the "%s" extension installed, '.
            'so character encodings are not supported. Install "%s" to '.
            'enable support.',
            'mbstring',
            'mbstring'))
        ->addCancelButton('/');
    }

    if ($request->isFormPost()) {
      $result = array('encoding' => $request->getStr('encoding'));
      return id(new AphrontAjaxResponse())->setContent($result);
    }

    $encodings = mb_list_encodings();
    $encodings = array_fuse($encodings);
    asort($encodings);
    unset($encodings['pass']);
    unset($encodings['auto']);

    $encodings = array(
      '' => pht('(Use Default)'),
    ) + $encodings;

    $form = id(new AphrontFormView())
      ->setUser($this->getRequest()->getUser())
      ->appendRemarkupInstructions(pht('Choose a text encoding to use.'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Encoding'))
          ->setName('encoding')
          ->setValue($request->getStr('encoding'))
          ->setOptions($encodings));

    return $this->newDialog()
      ->setTitle(pht('Select Character Encoding'))
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Choose Encoding'))
      ->addCancelButton('/');
  }
}
