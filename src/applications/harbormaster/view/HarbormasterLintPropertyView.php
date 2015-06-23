<?php

final class HarbormasterLintPropertyView extends AphrontView {

  private $pathURIMap = array();
  private $lintMessages = array();

  public function setPathURIMap(array $map) {
    $this->pathURIMap = $map;
    return $this;
  }

  public function setLintMessages(array $messages) {
    assert_instances_of($messages, 'HarbormasterBuildLintMessage');
    $this->lintMessages = $messages;
    return $this;
  }

  public function render() {
    $rows = array();
    foreach ($this->lintMessages as $message) {
      $path = $message->getPath();
      $line = $message->getLine();

      $href = null;
      if (strlen(idx($this->pathURIMap, $path))) {
        $href = $this->pathURIMap[$path].max($line, 1);
      }

      $severity = $this->renderSeverity($message->getSeverity());

      $location = $path.':'.$line;
      if (strlen($href)) {
        $location = phutil_tag(
          'a',
          array(
            'href' => $href,
          ),
          $location);
      }

      $rows[] = array(
        $location,
        $severity,
        $message->getCode(),
        $message->getName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Location'),
          pht('Severity'),
          pht('Code'),
          pht('Message'),
        ))
      ->setColumnClasses(
        array(
          'pri',
          null,
          null,
          'wide',
        ));

    return $table;
  }

  private function renderSeverity($severity) {
    $names = ArcanistLintSeverity::getLintSeverities();
    $name = idx($names, $severity, $severity);

    // TODO: Add some color here?

    return $name;
  }

}
