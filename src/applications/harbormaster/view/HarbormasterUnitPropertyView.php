<?php

final class HarbormasterUnitPropertyView extends AphrontView {

  private $pathURIMap = array();
  private $unitMessages = array();
  private $limit;

  public function setPathURIMap(array $map) {
    $this->pathURIMap = $map;
    return $this;
  }

  public function setUnitMessages(array $messages) {
    assert_instances_of($messages, 'HarbormasterBuildUnitMessage');
    $this->unitMessages = $messages;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function render() {
    $messages = $this->unitMessages;
    $messages = msort($messages, 'getSortKey');

    if ($this->limit) {
      $messages = array_slice($messages, 0, $this->limit);
    }

    $rows = array();
    $any_duration = false;
    foreach ($messages as $message) {
      $result = $this->renderResult($message->getResult());

      $duration = $message->getDuration();
      if ($duration !== null) {
        $any_duration = true;
        $duration = pht('%s ms', new PhutilNumber((int)(1000 * $duration)));
      }

      $name = $message->getName();

      $namespace = $message->getNamespace();
      if (strlen($namespace)) {
        $name = $namespace.'::'.$name;
      }

      $engine = $message->getEngine();
      if (strlen($engine)) {
        $name = $engine.' > '.$name;
      }

      $rows[] = array(
        $result,
        $duration,
        $name,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Result'),
          pht('Time'),
          pht('Test'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          'pri wide',
        ))
      ->setColumnVisibility(
        array(
          true,
          $any_duration,
        ));

    return $table;
  }

  private function renderResult($result) {
    $names = array(
      ArcanistUnitTestResult::RESULT_BROKEN     => pht('Broken'),
      ArcanistUnitTestResult::RESULT_FAIL       => pht('Failed'),
      ArcanistUnitTestResult::RESULT_UNSOUND    => pht('Unsound'),
      ArcanistUnitTestResult::RESULT_SKIP       => pht('Skipped'),
      ArcanistUnitTestResult::RESULT_PASS       => pht('Passed'),
    );
    $result = idx($names, $result, $result);

    // TODO: Add some color.

    return $result;
  }

}
