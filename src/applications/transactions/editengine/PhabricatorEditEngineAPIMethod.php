<?php

abstract class PhabricatorEditEngineAPIMethod
  extends ConduitAPIMethod {

  abstract public function newEditEngine();

  public function getApplication() {
    $engine = $this->newEditEngine();
    $class = $engine->getEngineApplicationClass();
    return PhabricatorApplication::getByClass($class);
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodStatusDescription() {
    return pht(
      'ApplicationEditor methods are fairly stable, but were introduced '.
      'relatively recently and may continue to evolve as more applications '.
      'adopt them.');
  }

  final protected function defineParamTypes() {
    return array(
      'transactions' => 'list<map<string, wild>>',
      'objectIdentifier' => 'optional id|phid|string',
    );
  }

  final protected function defineReturnType() {
    return 'map<string, wild>';
  }

  final protected function execute(ConduitAPIRequest $request) {
    $engine = $this->newEditEngine()
      ->setViewer($request->getUser());

    return $engine->buildConduitResponse($request);
  }

  final public function getMethodDescription() {
    return pht(
      'This is a standard **ApplicationEditor** method which allows you to '.
      'create and modify objects by applying transactions. For documentation '.
      'on these endpoints, see '.
      '**[[ %s | Conduit API: Using Edit Endpoints ]]**.',
      PhabricatorEnv::getDoclink('Conduit API: Using Edit Endpoints'));
  }

  final public function getMethodDocumentation() {
    $viewer = $this->getViewer();

    $engine = $this->newEditEngine()
      ->setViewer($viewer);

    $types = $engine->getConduitEditTypes();

    $out = array();

    $out[] = $this->buildEditTypesBoxes($engine, $types);

    return $out;
  }

  private function buildEditTypesBoxes(
    PhabricatorEditEngine $engine,
    array $types) {

    $boxes = array();

    $summary_info = pht(
      'This endpoint supports these types of transactions. See below for '.
      'detailed information about each transaction type.');

    $rows = array();
    foreach ($types as $type) {
      $rows[] = array(
        $type->getEditType(),
        $type->getConduitDescription(),
      );
    }

    $summary_table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          'prewrap',
          'wide',
        ));

    $boxes[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Transaction Types'))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($summary_info))
      ->appendChild($summary_table);

    foreach ($types as $type) {
      $section = array();

      $section[] = $type->getConduitDescription();

      $type_documentation = $type->getConduitDocumentation();
      if (strlen($type_documentation)) {
        $section[] = $type_documentation;
      }

      $section = implode("\n\n", $section);

      $rows = array();

      $rows[] = array(
        'type',
        'const',
        $type->getEditType(),
      );

      $rows[] = array(
        'value',
        $type->getConduitType(),
        $type->getConduitTypeDescription(),
      );

      $type_table = id(new AphrontTableView($rows))
        ->setHeaders(
          array(
            pht('Key'),
            pht('Type'),
            pht('Description'),
          ))
        ->setColumnClasses(
          array(
            'prewrap',
            'prewrap',
            'wide',
          ));

      $boxes[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Transaction Type: %s', $type->getEditType()))
      ->setCollapsed(true)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($this->buildRemarkup($section))
      ->appendChild($type_table);
    }

    return $boxes;
  }


  private function buildRemarkup($remarkup) {
    $viewer = $this->getViewer();

    $view = new PHUIRemarkupView($viewer, $remarkup);

    $view->setRemarkupOptions(
      array(
        PHUIRemarkupView::OPTION_PRESERVE_LINEBREAKS => false,
      ));

    return id(new PHUIBoxView())
      ->appendChild($view)
      ->addPadding(PHUI::PADDING_LARGE);
  }

}
