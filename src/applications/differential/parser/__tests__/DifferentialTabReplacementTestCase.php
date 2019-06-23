<?php

final class DifferentialTabReplacementTestCase
  extends PhabricatorTestCase {

  public function testTabReplacement() {
    $tab1 = "<span data-copy-text=\"\t\"> </span>";
    $tab2 = "<span data-copy-text=\"\t\">  </span>";

    $cat = "\xF0\x9F\x90\xB1";

    $cases = array(
      '' => '',
      'x' => 'x',

      // Tabs inside HTML tags should not be replaced.
      "<\t>x" => "<\t>x",

      // Normal tabs should be replaced. These are all aligned to the tab
      // width, so they'll be replaced inline.
      "\tx" => "{$tab2}x",
      "  \tx" => "  {$tab2}x",
      "\t x" => "{$tab2} x",
      "aa\tx" => "aa{$tab2}x",
      "aa  \tx" => "aa  {$tab2}x",
      "aa\t x" => "aa{$tab2} x",

      // This tab is not tabstop-aligned, so it is replaced with fewer
      // spaces to bring us to the next tabstop.
      " \tx" => " {$tab1}x",

      // Text inside HTML tags should not count when aligning tabs with
      // tabstops.
      "<tag> </tag>\tx" => "<tag> </tag>{$tab1}x",
      "<tag2> </tag>\tx" => "<tag2> </tag>{$tab1}x",

      // The code has to take a slow path when inputs contain unicode, but
      // should produce the right results and align tabs to tabstops while
      // respecting UTF8 display character widths, not byte widths.
      "{$cat}\tx" => "{$cat}{$tab1}x",
      "{$cat}{$cat}\tx" => "{$cat}{$cat}{$tab2}x",
    );

    foreach ($cases as $input => $expect) {
      $actual = DifferentialChangesetParser::replaceTabsWithSpaces(
        $input,
        2);

      $this->assertEqual(
        $expect,
        $actual,
        pht('Tabs to Spaces: %s', $input));
    }
  }

}
