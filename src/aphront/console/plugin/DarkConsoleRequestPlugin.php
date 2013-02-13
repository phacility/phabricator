<?php

/**
 * @group console
 */
final class DarkConsoleRequestPlugin extends DarkConsolePlugin {

  public function getName() {
    return 'Request';
  }

  public function getDescription() {
    return 'Information about $_REQUEST and $_SERVER.';
  }

  public function generateData() {
    return array(
      'Request'  => $_REQUEST,
      'Server'   => $_SERVER,
    );
  }

  public function renderPanel() {
    $data = $this->getData();

    $sections = array(
      'Basics' => array(
        'Machine'   => php_uname('n'),
      ),
    );

    // NOTE: This may not be present for some SAPIs, like php-fpm.
    if (!empty($data['Server']['SERVER_ADDR'])) {
      $addr = $data['Server']['SERVER_ADDR'];
      $sections['Basics']['Host'] = $addr;
      $sections['Basics']['Hostname'] = @gethostbyaddr($addr);
    }

    $sections = array_merge($sections, $data);

    $out = array();
    foreach ($sections as $header => $map) {
      $rows = array();
      foreach ($map as $key => $value) {
        $rows[] = array(
          $key,
          (is_array($value) ? json_encode($value) : $value),
        );
      }

      $table = new AphrontTableView($rows);
      $table->setHeaders(
        array(
          $header,
          null,
        ));
      $table->setColumnClasses(
        array(
          'header',
          'wide wrap',
        ));
      $out[] = $table->render();
    }

    return phutil_implode_html("\n", $out);
  }
}
