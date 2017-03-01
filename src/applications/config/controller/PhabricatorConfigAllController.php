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

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('all/');

    $content = id(new PhabricatorConfigPageView())
      ->setHeader($header)
      ->setContent($table);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content)
      ->addClass('white-background');

  }

}
