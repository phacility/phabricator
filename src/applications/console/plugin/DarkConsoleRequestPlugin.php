<?php

final class DarkConsoleRequestPlugin extends DarkConsolePlugin {

  public function getName() {
    return pht('Request');
  }

  public function getDescription() {
    return pht(
      'Information about %s and %s.',
      '$_REQUEST',
      '$_SERVER');
  }

  public function generateData() {
    $addr = idx($_SERVER, 'SERVER_ADDR');
    if ($addr) {
      $hostname = @gethostbyaddr($addr);
    } else {
      $hostname = null;
    }

    $controller = $this->getRequest()->getController();
    if ($controller) {
      $controller_class = get_class($controller);
    } else {
      $controller_class = null;
    }

    $site = $this->getRequest()->getSite();
    if ($site) {
      $site_class = get_class($site);
    } else {
      $site_class = null;
    }

    return array(
      'request' => $_REQUEST,
      'server' => $_SERVER,
      'special' => array(
        'site' => $site_class,
        'controller' => $controller_class,
        'machine' => php_uname('n'),
        'host' => $addr,
        'hostname' => $hostname,
      ),
    );
  }

  public function renderPanel() {
    $data = $this->getData();

    $special_map = array(
      'site' => pht('Site'),
      'controller' => pht('Controller'),
      'machine' => pht('Machine'),
      'host' => pht('Host'),
      'hostname' => pht('Hostname'),
    );

    $special = idx($data, 'special', array());

    $rows = array();
    foreach ($special_map as $key => $label) {
      $rows[] = array(
        $label,
        idx($special, $key),
      );
    }

    $sections = array();
    $sections[] = array(
      'name' => pht('Basics'),
      'rows' => $rows,
    );

    $mask = array(
      'HTTP_COOKIE' => true,
      'HTTP_X_PHABRICATOR_CSRF' => true,
    );

    $maps = array(
      array(
        'name' => pht('Request'),
        'data' => idx($data, 'request', array()),
      ),
      array(
        'name' => pht('Server'),
        'data' => idx($data, 'server', array()),
      ),
    );

    foreach ($maps as $map) {
      $data = $map['data'];
      $rows = array();
      foreach ($data as $key => $value) {
        if (isset($mask[$key])) {
          $value = phutil_tag('em', array(), pht('(Masked)'));
        } else if (is_array($value)) {
          $value = @json_encode($value);
        } else {
          $value = $value;
        }

        $rows[] = array(
          $key,
          $value,
        );
      }

      $sections[] = array(
        'name' => $map['name'],
        'rows' => $rows,
      );
    }

    $out = array();
    foreach ($sections as $section) {
      $out[] = id(new AphrontTableView($section['rows']))
        ->setHeaders(
          array(
            $section['name'],
            null,
          ))
        ->setColumnClasses(
          array(
            'header',
            'wide wrap',
          ));
    }
    return $out;
  }
}
