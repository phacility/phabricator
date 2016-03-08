<?php

final class NuanceSourceEditController
  extends NuanceSourceController {

  public function handleRequest(AphrontRequest $request) {
    $engine = id(new NuanceSourceEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if (!$id) {
      $this->requireApplicationCapability(
        NuanceSourceManageCapability::CAPABILITY);

      $cancel_uri = $this->getApplicationURI('source/');
      $map = NuanceSourceDefinition::getAllDefinitions();
      $source_type = $request->getStr('sourceType');
      if (!isset($map[$source_type])) {
        return $this->buildSourceTypeResponse($cancel_uri);
      }

      $engine
        ->setSourceDefinition($map[$source_type])
        ->addContextParameter('sourceType', $source_type);
    }

    return $engine->buildResponse();
  }

  private function buildSourceTypeResponse($cancel_uri) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $map = NuanceSourceDefinition::getAllDefinitions();

    $errors = array();
    $e_source = null;
    if ($request->isFormPost()) {
      $errors[] = pht('You must choose a source type.');
      $e_source = pht('Required');
    }

    $source_types = id(new AphrontFormRadioButtonControl())
      ->setName('sourceType')
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
      ->setFormErrors($errors)
      ->setHeaderText(pht('Choose Source Type'))
      ->appendChild($form);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Sources'), $cancel_uri);
    $crumbs->addTextCrumb(pht('New'));

    return $this->newPage()
      ->setTitle(pht('Choose Source Type'))
      ->setCrumbs($crumbs)
      ->appendChild($box);
  }

}
