<?php

final class PhabricatorCalendarImportLogView extends AphrontView {

  private $logs = array();
  private $showImportSources = false;

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'PhabricatorCalendarImportLog');
    $this->logs = $logs;
    return $this;
  }

  public function getLogs() {
    return $this->logs;
  }

  public function setShowImportSources($show_import_sources) {
    $this->showImportSources = $show_import_sources;
    return $this;
  }

  public function getShowImportSources() {
    return $this->showImportSources;
  }

  public function render() {
    return $this->newTable();
  }

  public function newTable() {
    $viewer = $this->getViewer();
    $logs = $this->getLogs();

    $show_sources = $this->getShowImportSources();

    $rows = array();
    foreach ($logs as $log) {
      $icon = $log->getDisplayIcon($viewer);
      $color = $log->getDisplayColor($viewer);
      $name = $log->getDisplayType($viewer);
      $description = $log->getDisplayDescription($viewer);

      $rows[] = array(
        $log->getID(),
        ($show_sources
          ? $viewer->renderHandle($log->getImport()->getPHID())
          : null),
        id(new PHUIIconView())->setIcon($icon, $color),
        $name,
        phutil_escape_html_newlines($description),
        phabricator_datetime($log->getDateCreated(), $viewer),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Source'),
          null,
          pht('Type'),
          pht('Message'),
          pht('Date'),
        ))
      ->setColumnVisibility(
        array(
          true,
          $show_sources,
        ))
      ->setColumnClasses(
        array(
          'top',
          'top',
          'top',
          'top pri',
          'top wide',
          'top',
        ));

    return $table;
  }

}
