<?php

final class DarkConsoleStartupPlugin extends DarkConsolePlugin {

  public function getName() {
    return pht('Startup');
  }

  public function getDescription() {
    return pht('Timing information about the startup sequence.');
  }

  /**
   * @phutil-external-symbol class PhabricatorStartup
   */
  public function generateData() {
    return PhabricatorStartup::getPhases();
  }

  public function renderPanel() {
    $data = $this->getData();

    // Compute the time offset and duration of each startup phase.
    $prev_key = null;
    $init = null;
    $phases = array();
    foreach ($data as $key => $value) {
      if ($init === null) {
        $init = $value;
      }

      $offset = (int)floor(1000 * ($value - $init));

      $phases[$key] = array(
        'time' => $value,
        'offset' => $value - $init,
      );


      if ($prev_key !== null) {
        $phases[$prev_key]['duration'] = $value - $phases[$prev_key]['time'];
      }
      $prev_key = $key;
    }

    // Render the phases.
    $rows = array();
    foreach ($phases as $key => $phase) {
      $offset_ms = (int)floor(1000 * $phase['offset']);

      if (isset($phase['duration'])) {
        $duration_us = (int)floor(1000000 * $phase['duration']);
      } else {
        $duration_us = null;
      }

      $rows[] = array(
        $key,
        pht('+%s ms', new PhutilNumber($offset_ms)),
        ($duration_us === null)
          ? pht('-')
          : pht('%s us', new PhutilNumber($duration_us)),
        null,
      );
    }

    return id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Phase'),
          pht('Offset'),
          pht('Duration'),
          null,
        ))
      ->setColumnClasses(
        array(
          '',
          'n right',
          'n right',
          'wide',
        ));
  }

}
