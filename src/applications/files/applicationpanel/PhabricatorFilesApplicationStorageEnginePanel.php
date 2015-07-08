<?php

final class PhabricatorFilesApplicationStorageEnginePanel
  extends PhabricatorApplicationConfigurationPanel {

  public function getPanelKey() {
    return 'storage';
  }

  public function shouldShowForApplication(
    PhabricatorApplication $application) {
    return ($application instanceof PhabricatorFilesApplication);
  }

  public function buildConfigurationPagePanel() {
    $viewer = $this->getViewer();
    $application = $this->getApplication();

    $engines = PhabricatorFileStorageEngine::loadAllEngines();
    $writable_engines = PhabricatorFileStorageEngine::loadWritableEngines();
    $chunk_engines = PhabricatorFileStorageEngine::loadWritableChunkEngines();

    $yes = pht('Yes');
    $no = pht('No');

    $rows = array();
    $rowc = array();
    foreach ($engines as $key => $engine) {
      $limited = $no;
      $limit = null;
      if ($engine->hasFilesizeLimit()) {
        $limited = $yes;
        $limit = phutil_format_bytes($engine->getFilesizeLimit());
      }

      if ($engine->canWriteFiles()) {
        $writable = $yes;
      } else {
        $writable = $no;
      }

      if ($engine->isTestEngine()) {
        $test = $yes;
      } else {
        $test = $no;
      }

      if (isset($writable_engines[$key]) || isset($chunk_engines[$key])) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }

      $rows[] = array(
        $key,
        get_class($engine),
        $test,
        $writable,
        $limited,
        $limit,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No storage engines available.'))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Class'),
          pht('Unit Test'),
          pht('Writable'),
          pht('Has Limit'),
          pht('Limit'),
        ))
      ->setRowClasses($rowc)
      ->setColumnClasses(
        array(
          '',
          'wide',
          '',
          '',
          '',
          'n',
        ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Storage Engines'))
      ->setTable($table);

    return $box;
  }

  public function handlePanelRequest(
    AphrontRequest $request,
    PhabricatorController $controller) {
    return new Aphront404Response();
  }

}
