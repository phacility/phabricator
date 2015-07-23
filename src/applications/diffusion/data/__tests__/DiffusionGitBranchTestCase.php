<?php

final class DiffusionGitBranchTestCase
  extends PhabricatorTestCase {

  public function testRemoteBranchParser() {

    $output = <<<EOTXT
  origin/HEAD           -> origin/master
  origin/accent-folding bfaea2e72197506e028c604cd1a294b6e37aa17d Add...
  origin/eventordering  185a90a3c1b0556015e5f318fb86ccf8f7a6f3e3 RFC: Order...
  origin/master         713f1fc54f9cfc830acbf6bbdb46a2883f772896 Automat...
  alternate/stuff       4444444444444444444444444444444444444444 Hmm...
origin/HEAD 713f1fc54f9cfc830acbf6bbdb46a2883f772896
origin/refactoring 6e947ab0498b82075ca6195ac168385a11326c4b
alternate/release-1.0.0 9ddd5d67962dd89fa167f9989954468b6c517b87

EOTXT;

    $this->assertEqual(
      array(
        'origin/accent-folding'   => 'bfaea2e72197506e028c604cd1a294b6e37aa17d',
        'origin/eventordering'    => '185a90a3c1b0556015e5f318fb86ccf8f7a6f3e3',
        'origin/master'           => '713f1fc54f9cfc830acbf6bbdb46a2883f772896',
        'alternate/stuff'         => '4444444444444444444444444444444444444444',
        'origin/refactoring'      => '6e947ab0498b82075ca6195ac168385a11326c4b',
        'alternate/release-1.0.0' => '9ddd5d67962dd89fa167f9989954468b6c517b87',
      ),
      DiffusionGitBranch::parseRemoteBranchOutput($output));

    $this->assertEqual(
      array(
        'accent-folding' => 'bfaea2e72197506e028c604cd1a294b6e37aa17d',
        'eventordering'  => '185a90a3c1b0556015e5f318fb86ccf8f7a6f3e3',
        'master'         => '713f1fc54f9cfc830acbf6bbdb46a2883f772896',
        'refactoring'    => '6e947ab0498b82075ca6195ac168385a11326c4b',
      ),
      DiffusionGitBranch::parseRemoteBranchOutput($output, 'origin'));
  }

}
