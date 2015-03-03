<?php

final class JavelinViewUIExample extends PhabricatorUIExample {

  public function getName() {
    return 'Javelin Views';
  }

  public function getDescription() {
    return 'Mix and match client and server views.';
  }

  public function renderExample() {

    $request = $this->getRequest();

    $init = $request->getStr('init');

    $parent_server_template = new JavelinViewExampleServerView();

    $parent_client_template = new AphrontJavelinView();
    $parent_client_template
      ->setName('JavelinViewExample')
      ->setCelerityResource('phabricator-uiexample-javelin-view');

    $child_server_template = new JavelinViewExampleServerView();

    $child_client_template = new AphrontJavelinView();
    $child_client_template
      ->setName('JavelinViewExample')
      ->setCelerityResource('phabricator-uiexample-javelin-view');

    $parent_server_template->appendChild($parent_client_template);
    $parent_client_template->appendChild($child_server_template);
    $child_server_template->appendChild($child_client_template);
    $child_client_template->appendChild('Hey, it worked.');

    $panel = new PHUIObjectBoxView();
    $panel->setHeaderText(pht('Example'));
    $panel->appendChild(
      phutil_tag_div('ml', $parent_server_template));

    return $panel;
  }
}
