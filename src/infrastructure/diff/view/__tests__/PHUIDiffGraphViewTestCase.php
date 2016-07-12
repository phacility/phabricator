<?php

final class PHUIDiffGraphViewTestCase extends PhabricatorTestCase {

  public function testTailTermination() {
    $nodes = array(
      'A' => array('B'),
      'B' => array('C', 'D', 'E'),
      'E' => array(),
      'D' => array(),
      'C' => array('F', 'G'),
      'G' => array(),
      'F' => array(),
    );

    $graph = $this->newGraph($nodes);

    $picture = array(
      '^',
      'o',
      '||x',
      '|x ',
      'o  ',
      '|x ',
      'x  ',
    );

    $this->assertGraph($picture, $graph, pht('Terminating Tree'));
  }

  public function testReverseTree() {
    $nodes = array(
      'A' => array('B'),
      'C' => array('B'),
      'B' => array('D'),
      'E' => array('D'),
      'F' => array('D'),
      'D' => array('G'),
      'G' => array(),
    );

    $graph = $this->newGraph($nodes);

    $picture = array(
      '^',
      '|^',
      'o ',
      '|^',
      '||^',
      'o  ',
      'x',
    );

    $this->assertGraph($picture, $graph, pht('Reverse Tree'));
  }

  public function testJoinTerminateTree() {
    $nodes = array(
      'A' => array('D'),
      'B' => array('C'),
      'C' => array('D'),
      'D' => array(),
    );

    $graph = $this->newGraph($nodes);

    $picture = array(
      '^',
      '|^',
      '|o',
      'x ',
    );

    $this->assertGraph($picture, $graph, pht('Reverse Tree'));
  }

  private function newGraph(array $nodes) {
    return id(new PHUIDiffGraphView())
      ->setIsHead(true)
      ->setIsTail(true)
      ->renderRawGraph($nodes);
  }

  private function assertGraph($picture, $graph, $label) {
    list($data, $count) = $graph;
    $lines = ipull($data, 'line');

    $picture = implode("\n", $picture);
    $lines = implode("\n", $lines);

    $this->assertEqual($picture, $lines, $label);
  }

}
