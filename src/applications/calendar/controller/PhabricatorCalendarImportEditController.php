<?php

final class PhabricatorCalendarImportEditController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    $engine = id(new PhabricatorCalendarImportEditEngine())
      ->setController($this);

    $id = $request->getURIData('id');
    if (!$id) {
      $list_uri = $this->getApplicationURI('import/');

      $import_type = $request->getStr('importType');
      $import_engines = PhabricatorCalendarImportEngine::getAllImportEngines();
      if (empty($import_engines[$import_type])) {
        return $this->buildEngineTypeResponse($list_uri);
      }

      $import_engine = $import_engines[$import_type];

      $engine
        ->addContextParameter('importType', $import_type)
        ->setImportEngine($import_engine);
    }

    return $engine->buildResponse();
  }

  private function buildEngineTypeResponse($cancel_uri) {
    $import_engines = PhabricatorCalendarImportEngine::getAllImportEngines();

    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $e_import = null;
    $errors = array();
    if ($request->isFormPost()) {
      $e_import = pht('Required');
      $errors[] = pht(
        'To import events, you must select a source to import from.');
    }

    $type_control = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Import Type'))
      ->setName('importType')
      ->setError($e_import);

    foreach ($import_engines as $import_engine) {
      $type_control->addButton(
        $import_engine->getImportEngineType(),
        $import_engine->getImportEngineName(),
        $import_engine->getImportEngineHint());
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('New Import'));
    $crumbs->setBorder(true);

    $title = pht('Choose Import Type');
    $header = id(new PHUIHeaderView())
      ->setHeader(pht('New Import'))
      ->setHeaderIcon('fa-upload');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($type_control)
      ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Continue'))
            ->addCancelButton($cancel_uri));

    $box = id(new PHUIObjectBoxView())
      ->setFormErrors($errors)
      ->setHeaderText(pht('Import'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $box,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
