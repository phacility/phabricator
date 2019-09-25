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


    // TODO: This controller isn't very good because the valid options depend
    // on the file being rendered and most of them can't even diff anything,
    // and this ref is completely bogus.

    // For now, we just show everything.
    $ref = new PhabricatorDocumentRef();

    $map = array();
    foreach ($engines as $engine) {
      $key = $engine->getDocumentEngineKey();
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
