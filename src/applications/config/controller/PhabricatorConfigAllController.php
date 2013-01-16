<?php

final class PhabricatorConfigAllController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $rows = array();
    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    ksort($options);
    foreach ($options as $option) {
      $key = $option->getKey();

      if ($option->getMasked()) {
        $value = '<em>'.pht('Masked').'</em>';
      } else if ($option->getHidden()) {
        $value = '<em>'.pht('Hidden').'</em>';
      } else {
        $value = PhabricatorEnv::getEnvConfig($key);
        $value = PhabricatorConfigJSON::prettyPrintJSON($value);
        $value = phutil_escape_html($value);
      }

      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => $this->getApplicationURI('edit/'.$key.'/'),
          ),
          phutil_escape_html($key)),
        $value,
      );
    }
    $table = id(new AphrontTableView($rows))
      ->setDeviceReadyTable(true)
      ->setColumnClasses(
        array(
          '',
          'wide',
        ))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Value'),
        ));

    $title = pht('Current Settings');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($title));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('all/');
    $nav->setCrumbs($crumbs);
    $nav->appendChild($table);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      )
    );
  }

}
