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

    $services = PhabricatorSearchService::getAllServices();

    $rows = array();
    $rowc = array();

    foreach ($services as $key => $service) {
      try {
        $name = $service->getDisplayName();
      } catch (Exception $ex) {
        $name = phutil_tag('em', array(), pht('Error'));
      }

      try {
        $can_read = $service->isReadable() ? pht('Yes') : pht('No');
      } catch (Exception $ex) {
        $can_read = pht('N/A');
      }

      try {
        $can_write = $service->isWritable() ? pht('Yes') : pht('No');
      } catch (Exception $ex) {
        $can_write = pht('N/A');
      }

      $rows[] = array(
        $name,
        $can_read,
        $can_write,
      );
    }

    $instructions = pht(
      'To configure the search engines, edit [[ %s | `%s` ]] configuration. '.
      'See **[[ %s | %s ]]** for documentation.',
      '/config/edit/cluster.search/',
      'cluster.search',
      PhabricatorEnv::getDoclink('Cluster: Search'),
      pht('Cluster: Search'));


    $table = id(new AphrontTableView($rows))
      ->setNoDataString(pht('No search engines available.'))
      ->setNotice(new PHUIRemarkupView($viewer, $instructions))
      ->setHeaders(
        array(
          pht('Engine Name'),
          pht('Writable'),
          pht('Readable'),
        ))
      ->setRowClasses($rowc)
      ->setColumnClasses(
        array(
          'wide',
          '',
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
