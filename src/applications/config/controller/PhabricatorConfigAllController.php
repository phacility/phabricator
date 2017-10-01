<?php

final class PhabricatorConfigAllController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $db_values = id(new PhabricatorConfigEntry())
      ->loadAllWhere('namespace = %s', 'default');
    $db_values = mpull($db_values, null, 'getConfigKey');

    $rows = array();
    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    ksort($options);
    foreach ($options as $option) {
      $key = $option->getKey();

      if ($option->getHidden()) {
        $value = phutil_tag('em', array(), pht('Hidden'));
      } else {
        $value = PhabricatorEnv::getEnvConfig($key);
        $value = PhabricatorConfigJSON::prettyPrintJSON($value);
      }

      $db_value = idx($db_values, $key);
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI('edit/'.$key.'/'),
          ),
          $key),
        $value,
        $db_value && !$db_value->getIsDeleted() ? pht('Customized') : '',
      );
    }
    $table = id(new AphrontTableView($rows))
      ->setColumnClasses(
        array(
          '',
          'wide',
        ))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Value'),
          pht('Customized'),
        ));

    $title = pht('Current Settings');
    $header = $this->buildHeaderView($title);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('all/');

    $view = $this->buildConfigBoxView(
      pht('All Settings'),
      $table);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($view);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);

  }

}
