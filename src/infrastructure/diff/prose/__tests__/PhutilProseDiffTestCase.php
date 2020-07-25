<?php

final class PhutilProseDiffTestCase
  extends PhabricatorTestCase {

  public function testTrimApart() {
    $map = array(
      '' => array(),
      'a' => array('a'),
      ' a ' => array(
        ' ',
        'a',
        ' ',
      ),
      ' a' => array(
        ' ',
        'a',
      ),
      'a ' => array(
        'a',
        ' ',
      ),
      ' a b ' => array(
        ' ',
        'a b',
        ' ',
      ),
    );

    foreach ($map as $input => $expect) {
      $actual = PhutilProseDifferenceEngine::trimApart($input);
      $this->assertEqual(
        $expect,
        $actual,
        pht('Trim Apart: %s', $input));
    }
  }

  public function testProseDiffsDistance() {
    $this->assertProseParts(
      '',
      '',
      array(),
      pht('Empty'));

    $this->assertProseParts(
      "xxx\nyyy",
      "xxx\nzzz\nyyy",
      array(
        "= xxx\n",
        "+ zzz\n",
        '= yyy',
      ),
      pht('Add Paragraph'));

    $this->assertProseParts(
      "xxx\nzzz\nyyy",
      "xxx\nyyy",
      array(
        "= xxx\n",
        "- zzz\n",
        '= yyy',
      ),
      pht('Remove Paragraph'));

    $this->assertProseParts(
       'xxx',
       "xxxyyy\n.zzz",
       array(
         '= xxx',
         "+ yyy\n.zzz",
       ),
       pht('Amend paragraph, and add paragraph starting with punctuation'));

    // Without smoothing, the alogorithm identifies that "shark" and "cat"
    // both contain the letter "a" and tries to express this as a very
    // fine-grained edit which replaces "sh" with "c" and then "rk" with "t".
    // This is technically correct, but it is much easier for human viewers to
    // parse if we smooth this into a single removal and a single addition.

    $this->assertProseParts(
      'They say the shark has nine lives.',
      'They say the cat has nine lives.',
      array(
        '= They say the ',
        '- shark',
        '+ cat',
        '=  has nine lives.',
      ),
      pht('"Shark/cat" word edit smoothenss.'));

    $this->assertProseParts(
      'Rising quickly, she says',
      'Rising quickly, she remarks:',
      array(
        '= Rising quickly, she ',
        '- says',
        '+ remarks:',
      ),
      pht('"Says/remarks" word edit smoothenss.'));

    $this->assertProseParts(
      'See screenshots',
      'Viewed video files',
      array(
        '- See screenshots',
        '+ Viewed video files',
      ),
      pht('Complete paragraph rewrite.'));

    $this->assertProseParts(
      'xaaax',
      'xbbbx',
      array(
        '- xaaax',
        '+ xbbbx',
      ),
      pht('Whole word rewrite with common prefix and suffix.'));

    $this->assertProseParts(
      ' aaa ',
      ' bbb ',
      array(
        '=  ',
        '- aaa',
        '+ bbb',
        '=  ',
      ),
      pht('Whole word rewrite with whitespace prefix and suffix.'));

    $this->assertSummaryProseParts(
      "a\nb\nc\nd\ne\nf\ng\nh\n",
      "a\nb\nc\nd\nX\nf\ng\nh\n",
      array(
        '.',
        "= d\n",
        '- e',
        '+ X',
        "= \nf",
        '.',
      ),
      pht('Summary diff with middle change.'));

    $this->assertSummaryProseParts(
      "a\nb\nc\nd\ne\nf\ng\nh\n",
      "X\nb\nc\nd\ne\nf\ng\nh\n",
      array(
        '- a',
        '+ X',
        "= \nb",
        '.',
      ),
      pht('Summary diff with head change.'));

    $this->assertSummaryProseParts(
      "a\nb\nc\nd\ne\nf\ng\nh\n",
      "a\nb\nc\nd\ne\nf\ng\nX\n",
      array(
        '.',
        "= g\n",
        '- h',
        '+ X',
        "= \n",
      ),
      pht('Summary diff with last change.'));

    $this->assertProseParts(
      'aaa aaa aaa aaa, bbb bbb bbb bbb.',
      "aaa aaa aaa aaa, bbb bbb bbb bbb.\n\n- ccc ccc ccc",
      array(
        '= aaa aaa aaa aaa, bbb bbb bbb bbb.',
        "+ \n\n- ccc ccc ccc",
      ),
      pht('Diff with new trailing content.'));

    $this->assertProseParts(
      'aaa aaa aaa aaa, bbb bbb bbb bbb.',
      'aaa aaa aaa aaa bbb bbb bbb bbb.',
      array(
        '= aaa aaa aaa aaa',
        '- ,',
        '=  bbb bbb bbb bbb.',
      ),
      pht('Diff with a removed comma.'));

    $this->assertProseParts(
      'aaa aaa aaa aaa, bbb bbb bbb bbb.',
      "aaa aaa aaa aaa bbb bbb bbb bbb.\n\n- ccc ccc ccc!",
      array(
        '= aaa aaa aaa aaa',
        '- ,',
        '=  bbb bbb bbb bbb.',
        "+ \n\n- ccc ccc ccc!",
      ),
      pht('Diff with a removed comma and new trailing content.'));

    $this->assertProseParts(
      '[ ] Walnuts',
      '[X] Walnuts',
      array(
        '= [',
        '-  ',
        '+ X',
        '= ] Walnuts',
      ),
      pht('Diff adding a tickmark to a checkbox list.'));

    $this->assertProseParts(
      '[[ ./week49 ]]',
      '[[ ./week50 ]]',
      array(
        '= [[ ./week',
        '- 49',
        '+ 50',
        '=  ]]',
      ),
      pht('Diff changing a remarkup wiki link target.'));

    // Create a large corpus with many sentences and paragraphs.
    $large_paragraph = 'xyz. ';
    $large_paragraph = str_repeat($large_paragraph, 50);
    $large_paragraph = rtrim($large_paragraph);

    $large_corpus = $large_paragraph."\n\n";
    $large_corpus = str_repeat($large_corpus, 50);
    $large_corpus = rtrim($large_corpus);

    $this->assertProseParts(
      $large_corpus,
      "aaa\n\n".$large_corpus."\n\nzzz",
      array(
        "+ aaa\n\n",
        '= '.$large_corpus,
        "+ \n\nzzz",
      ),
      pht('Adding initial and final lines to a large corpus.'));

  }

  private function assertProseParts($old, $new, array $expect_parts, $label) {
    $engine = new PhutilProseDifferenceEngine();
    $diff = $engine->getDiff($old, $new);

    $parts = $diff->getParts();

    $this->assertParts($expect_parts, $parts, $label);
  }

  private function assertSummaryProseParts(
    $old,
    $new,
    array $expect_parts,
    $label) {

    $engine = new PhutilProseDifferenceEngine();
    $diff = $engine->getDiff($old, $new);

    $parts = $diff->getSummaryParts();

    $this->assertParts($expect_parts, $parts, $label);
  }

  private function assertParts(
    array $expect,
    array $actual_parts,
    $label) {

    $actual = array();
    foreach ($actual_parts as $actual_part) {
      $type = $actual_part['type'];
      $text = $actual_part['text'];

      switch ($type) {
        case '.':
          $actual[] = $type;
          break;
        default:
          $actual[] = "{$type} {$text}";
          break;
      }
    }

    $this->assertEqual($expect, $actual, $label);
  }


}
