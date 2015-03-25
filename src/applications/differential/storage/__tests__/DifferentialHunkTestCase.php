<?php

final class DifferentialHunkTestCase extends ArcanistPhutilTestCase {

  public function testMakeChanges() {
    $root = dirname(__FILE__).'/hunk/';

    $hunk = new DifferentialModernHunk();
    $hunk->setChanges(Filesystem::readFile($root.'basic.diff'));
    $hunk->setOldOffset(1);
    $hunk->setNewOffset(11);

    $old = Filesystem::readFile($root.'old.txt');
    $this->assertEqual($old, $hunk->makeOldFile());

    $new = Filesystem::readFile($root.'new.txt');
    $this->assertEqual($new, $hunk->makeNewFile());

    $added = array(
      12 => "1 quack\n",
      13 => "1 quack\n",
      16 => "5 drake\n",
    );
    $this->assertEqual($added, $hunk->getAddedLines());

    $hunk = new DifferentialModernHunk();
    $hunk->setChanges(Filesystem::readFile($root.'newline.diff'));
    $hunk->setOldOffset(1);
    $hunk->setNewOffset(11);

    $this->assertEqual("a\n", $hunk->makeOldFile());
    $this->assertEqual('a', $hunk->makeNewFile());
    $this->assertEqual(array(11 => 'a'), $hunk->getAddedLines());

  }

  public function testMakeStructuredChanges1() {
    $hunk = $this->loadHunk('fruit1.diff');

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "apple\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "cherry\n",
        ),
      ),
      $hunk->getStructuredOldFile());

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "apple\n",
        ),
        2 => array(
          'type' => '+',
          'text' => "banana\n",
        ),
        3 => array(
          'type' => '+',
          'text' => "plum\n",
        ),
        4 => array(
          'type' => ' ',
          'text' => "cherry\n",
        ),
      ),
      $hunk->getStructuredNewFile());
  }

  public function testMakeStructuredChanges2() {
    $hunk = $this->loadHunk('fruit2.diff');

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "apple\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "banana\n",
        ),
        3 => array(
          'type' => '-',
          'text' => "plum\n",
        ),
        4 => array(
          'type' => ' ',
          'text' => "cherry\n",
        ),
      ),
      $hunk->getStructuredOldFile());

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "apple\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "banana\n",
        ),
        3 => array(
          'type' => ' ',
          'text' => "cherry\n",
        ),
      ),
      $hunk->getStructuredNewFile());
  }

  public function testMakeStructuredNewlineAdded() {
    $hunk = $this->loadHunk('trailing_newline_added.diff');

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        3 => array(
          'type' => '-',
          'text' => 'quack',
        ),
        4 => array(
          'type' => '\\',
          'text' => " No newline at end of file\n",
        ),
      ),
      $hunk->getStructuredOldFile());

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        3 => array(
          'type' => '+',
          'text' => "quack\n",
        ),
      ),
      $hunk->getStructuredNewFile());
  }

  public function testMakeStructuredNewlineRemoved() {
    $hunk = $this->loadHunk('trailing_newline_removed.diff');

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        3 => array(
          'type' => '-',
          'text' => "quack\n",
        ),
      ),
      $hunk->getStructuredOldFile());

    $this->assertEqual(
      array(
        1 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        3 => array(
          'type' => '+',
          'text' => 'quack',
        ),
        4 => array(
          'type' => '\\',
          'text' => " No newline at end of file\n",
        ),
      ),
      $hunk->getStructuredNewFile());
  }

  public function testMakeStructuredNewlineAbsent() {
    $hunk = $this->loadHunk('trailing_newline_absent.diff');

    $this->assertEqual(
      array(
        1 => array(
          'type' => '-',
          'text' => "quack\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        3 => array(
          'type' => ' ',
          'text' => 'quack',
        ),
        4 => array(
          'type' => '\\',
          'text' => " No newline at end of file\n",
        ),
      ),
      $hunk->getStructuredOldFile());

    $this->assertEqual(
      array(
        1 => array(
          'type' => '+',
          'text' => "meow\n",
        ),
        2 => array(
          'type' => ' ',
          'text' => "quack\n",
        ),
        3 => array(
          'type' => ' ',
          'text' => 'quack',
        ),
        4 => array(
          'type' => '\\',
          'text' => " No newline at end of file\n",
        ),
      ),
      $hunk->getStructuredNewFile());
  }

  public function testMakeStructuredOffset() {
    $hunk = $this->loadHunk('offset.diff');

    $this->assertEqual(
      array(
        76 => array(
          'type' => ' ',
          'text' => "            \$bits .= '0';\n",
        ),
        77 => array(
          'type' => ' ',
          'text' => "          }\n",
        ),
        78 => array(
          'type' => ' ',
          'text' => "        }\n",
        ),
        79 => array(
          'type' => ' ',
          'text' => "      }\n",
        ),
        80 => array(
          'type' => '-',
          'text' => "      \$this->bits = \$bits;\n",
        ),
        81 => array(
          'type' => ' ',
          'text' => "    }\n",
        ),
        82 => array(
          'type' => ' ',
          'text' => "    return \$this->bits;\n",
        ),
      ),
      $hunk->getStructuredOldFile());

    $this->assertEqual(
      array(
        76 => array(
          'type' => ' ',
          'text' => "            \$bits .= '0';\n",
        ),
        77 => array(
          'type' => ' ',
          'text' => "          }\n",
        ),
        78 => array(
          'type' => ' ',
          'text' => "        }\n",
        ),
        79 => array(
          'type' => '+',
          'text' => "        break;\n",
        ),
        80 => array(
          'type' => ' ',
          'text' => "      }\n",
        ),
        81 => array(
          'type' => '+',
          'text' => "      \$this->bits = \$bytes;\n",
        ),
        82 => array(
          'type' => ' ',
          'text' => "    }\n",
        ),
        83 => array(
          'type' => ' ',
          'text' => "    return \$this->bits;\n",
        ),
      ),
      $hunk->getStructuredNewFile());
  }

  private function loadHunk($name) {
    $root = dirname(__FILE__).'/hunk/';
    $data = Filesystem::readFile($root.$name);

    $parser = new ArcanistDiffParser();
    $changes = $parser->parseDiff($data);

    $viewer = PhabricatorUser::getOmnipotentUser();
    $diff = DifferentialDiff::newFromRawChanges($viewer, $changes);

    $changesets = $diff->getChangesets();
    if (count($changesets) !== 1) {
      throw new Exception(
        pht(
          'Expected exactly one changeset from "%s".',
          $name));
    }
    $changeset = head($changesets);

    $hunks = $changeset->getHunks();
    if (count($hunks) !== 1) {
      throw new Exception(
        pht(
          'Expected exactly one hunk from "%s".',
          $name));
    }
    $hunk = head($hunks);

    return $hunk;
  }


}
