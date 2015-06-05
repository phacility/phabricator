<?php

final class NuanceSourceCreateController extends NuanceController {

  public function handleRequest(AphrontRequest $request) {
    $can_edit = $this->requireApplicationCapability(
      NuanceSourceManageCapability::CAPABILITY);

    $viewer = $this->getViewer();
    $map = NuanceSourceDefinition::getAllDefinitions();
    $cancel_uri = $this->getApplicationURI('source/');

    if ($request->isFormPost()) {
      $type = $request->getStr('type');
      if (isset($map[$type])) {
        $uri = $this->getApplicationURI('source/new/'.$type.'/');
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $source_types = id(new AphrontFormRadioButtonControl())
      ->setName('type')
      ->setLabel(pht('Source Type'));

    foreach ($map as $type => $definition) {
      $source_types->addButton(
        $type,
        $definition->getName(),
        $definition->getSourceDescription());
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($source_types)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Continue'))
          ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Choose Source Type'))
      ->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Sources'), $cancel_uri);
    $crumbs->addTextCrumb(pht('New'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => pht('Choose Source Type'),
      ));
  }
}
