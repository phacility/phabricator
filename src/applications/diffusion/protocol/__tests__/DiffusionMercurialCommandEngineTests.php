<?php

final class DiffusionMercurialCommandEngineTests extends PhabricatorTestCase {

  public function testFilteringDebugOutput() {
    $map = array(
      '' => '',

      "quack\n" => "quack\n",

      "ignoring untrusted configuration option x.y = z\nquack\n" =>
        "quack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "quack\n" =>
        "quack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "quack\n" =>
        "quack\n",

      "quack\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n" =>
        "quack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "duck\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "bread\n".
      "ignoring untrusted configuration option x.y = z\n".
      "quack\n" =>
        "duck\nbread\nquack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "duckignoring untrusted configuration option x.y = z\n".
      "quack" =>
        'duckquack',
    );

    foreach ($map as $input => $expect) {
      $actual = DiffusionMercurialCommandEngine::filterMercurialDebugOutput(
        $input);
      $this->assertEqual($expect, $actual, $input);
    }

    // Output that should be filtered out from the results
    $output =
      "ignoring untrusted configuration option\n".
      "couldn't write revision branch cache:\n".
      "couldn't write branch cache: blah blah blah\n".
      "invalid branchheads cache\n".
      "invalid branch cache (served): tip differs\n".
      "starting pager for command 'log'\n".
      "updated patterns: ".
        ".hglf/project/src/a/b/c/SomeClass.java, ".
        "project/src/a/b/c/SomeClass.java\n".
      "no terminfo entry for sitm\n";

    $filtered_output =
      DiffusionMercurialCommandEngine::filterMercurialDebugOutput($output);

    $this->assertEqual('', $filtered_output);

    // The output that should make it through the filtering
    $output =
      "0b33a9e5ceedba14b03214f743957357d7bb46a9;694".
        ":8b39f63eb209dd2bdfd4bd3d0721a9e38d75a6d3".
        "-1:0000000000000000000000000000000000000000\n".
      "8b39f63eb209dd2bdfd4bd3d0721a9e38d75a6d3;693".
        ":165bce9ce4ccc97024ba19ed5a22f6a066fa6844".
        "-1:0000000000000000000000000000000000000000\n".
      "165bce9ce4ccc97024ba19ed5a22f6a066fa6844;692:".
        "2337bc9e3cf212b3b386b5197801b1c81db64920".
        "-1:0000000000000000000000000000000000000000\n";

    $filtered_output =
      DiffusionMercurialCommandEngine::filterMercurialDebugOutput($output);

    $this->assertEqual($output, $filtered_output);
  }

}
