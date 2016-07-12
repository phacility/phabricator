<?php

final class HarbormasterLintPropertyView extends AphrontView {

  private $pathURIMap = array();
  private $lintMessages = array();
  private $limit;

  public function setPathURIMap(array $map) {
    $this->pathURIMap = $map;
    return $this;
  }

  public function setLintMessages(array $messages) {
    assert_instances_of($messages, 'HarbormasterBuildLintMessage');
    $this->lintMessages = $messages;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function render() {
    $messages = $this->lintMessages;
    $messages = msort($messages, 'getSortKey');

    if ($this->limit) {
      $messages = array_slice($messages, 0, $this->limit);
    }

    $rows = array();
    foreach ($messages as $message) {
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
        $severity,
        $location,
        $message->getCode(),
        $message->getName(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Severity'),
          pht('Location'),
          pht('Code'),
          pht('Message'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri',
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
