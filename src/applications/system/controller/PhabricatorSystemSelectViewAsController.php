<?php

final class PhabricatorSystemSelectViewAsController
  extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $v_engine = $request->getStr('engine');

    if ($request->isFormPost()) {
      $result = array('engine' => $v_engine);
      return id(new AphrontAjaxResponse())->setContent($result);
    }

    $engines = PhabricatorDocumentEngine::getAllEngines();

    $options = $request->getStrList('options');
    $options = array_fuse($options);

    // TODO: This controller is a bit rough because it isn't really using the
    // file ref to figure out which engines should work. See also T13513.
    // Callers can pass a list of "options" to control which options are
    // presented, at least.

    $ref = new PhabricatorDocumentRef();

    $map = array();
    foreach ($engines as $engine) {
      $key = $engine->getDocumentEngineKey();

      if ($options && !isset($options[$key])) {
        continue;
      }

      $label = $engine->getViewAsLabel($ref);

      if (!strlen($label)) {
        continue;
      }

      $map[$key] = $label;
    }

    asort($map);

    $map = array(
      '' => pht('(Use Default)'),
    ) + $map;

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendRemarkupInstructions(pht('Choose a document engine to use.'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('View As'))
          ->setName('engine')
          ->setValue($v_engine)
          ->setOptions($map));

    return $this->newDialog()
      ->setTitle(pht('Select Document Engine'))
      ->appendForm($form)
      ->addSubmitButton(pht('Choose Engine'))
      ->addCancelButton('/');
  }
}
