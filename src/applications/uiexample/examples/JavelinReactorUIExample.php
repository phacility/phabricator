<?php

final class JavelinReactorUIExample extends PhabricatorUIExample {

  public function getName() {
    return 'Javelin Reactor';
  }

  public function getDescription() {
    return 'Lots of code';
  }

  public function renderExample() {
    $rows = array();

    $examples = array(
      array(
        'Reactive button only generates a stream of events',
        'ReactorButtonExample',
        'phabricator-uiexample-reactor-button',
        array(),
      ),
      array(
        'Reactive checkbox generates a boolean dynamic value',
        'ReactorCheckboxExample',
        'phabricator-uiexample-reactor-checkbox',
        array('checked' => true),
      ),
      array(
        'Reactive focus detector generates a boolean dynamic value',
        'ReactorFocusExample',
        'phabricator-uiexample-reactor-focus',
        array(),
      ),
      array(
        'Reactive input box, with normal and calmed output',
        'ReactorInputExample',
        'phabricator-uiexample-reactor-input',
        array('init' => 'Initial value'),
      ),
      array(
        'Reactive mouseover detector generates a boolean dynamic value',
        'ReactorMouseoverExample',
        'phabricator-uiexample-reactor-mouseover',
        array(),
      ),
      array(
        'Reactive radio buttons generate a string dynamic value',
        'ReactorRadioExample',
        'phabricator-uiexample-reactor-radio',
        array(),
      ),
      array(
        'Reactive select box generates a string dynamic value',
        'ReactorSelectExample',
        'phabricator-uiexample-reactor-select',
        array(),
      ),
      array(
        'sendclass makes the class of an element a string dynamic value',
        'ReactorSendClassExample',
        'phabricator-uiexample-reactor-sendclass',
        array(),
      ),
      array(
        'sendproperties makes some properties of an object into dynamic values',
        'ReactorSendPropertiesExample',
        'phabricator-uiexample-reactor-sendproperties',
        array(),
      ),
    );

    foreach ($examples as $example) {
      list($desc, $name, $resource, $params) = $example;
      $template = new AphrontJavelinView();
      $template
        ->setName($name)
        ->setParameters($params)
        ->setCelerityResource($resource);
      $rows[] = array($desc, $template->render());
    }

    $table = new AphrontTableView($rows);

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Example'));
    $panel->appendChild($table);

    return $panel;
  }
}
