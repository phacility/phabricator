<?php

final class PhabricatorSearchApplicationStorageEnginePanel
  extends PhabricatorApplicationConfigurationPanel {

  public function getPanelKey() {
    return 'search';
  }

  public function shouldShowForApplication(
    PhabricatorApplication $application) {
    return $application instanceof PhabricatorSearchApplication;
  }

  public function buildConfigurationPagePanel() {
    $viewer = $this->getViewer();
    $application = $this->getApplication();

    $active_engine = PhabricatorSearchEngine::loadEngine();
    $engines = PhabricatorSearchEngine::loadAllEngines();

    $rows = array();
    $rowc = array();

    foreach ($engines as $key => $engine) {
      try {
        $index_exists = $engine->indexExists() ? pht('Yes') : pht('No');
      } catch (Exception $ex) {
        $index_exists = pht('N/A');
      }

      try {
        $index_is_sane = $engine->indexIsSane() ? pht('Yes') : pht('No');
      } catch (Exception $ex) {
        $index_is_sane = pht('N/A');
      }

      if ($engine == $active_engine) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }

      $rows[] = array(
        $key,
        get_class($engine),
        $index_exists,
        $index_is_sane,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No search engines available.'))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Class'),
          pht('Index Exists'),
          pht('Index Is Sane'),
        ))
      ->setRowClasses($rowc)
      ->setColumnClasses(
        array(
          '',
          'wide',
          '',
        ));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Search Engines'))
      ->appendChild($table);

    return $box;
  }

  public function handlePanelRequest(
    AphrontRequest $request,
    PhabricatorController $controller) {
    return new Aphront404Response();
  }

}
