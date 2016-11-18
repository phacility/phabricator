<?php

final class PhabricatorMarkupEngineTestCase
  extends PhabricatorTestCase {

  public function testRemarkupSentenceSummmaries() {
    $this->assertSentenceSummary(
      'The quick brown fox. Jumped over the lazy dog.',
      'The quick brown fox.');

    $this->assertSentenceSummary(
      'Go to www.help.com for details. Good day.',
      'Go to www.help.com for details.');

    $this->assertSentenceSummary(
      'Coxy lummox gives squid who asks for job pen.',
      'Coxy lummox gives squid who asks for job pen.');

    $this->assertSentenceSummary(
      'DEPRECATED',
      'DEPRECATED');

    $this->assertSentenceSummary(
      'Never use this! It is deadly poison.',
      'Never use this!');

    $this->assertSentenceSummary(
      "a short poem\nmeow meow meow\nmeow meow meow\n\n- cat",
      'a short poem');

    $this->assertSentenceSummary(
      'WOW!! GREAT PROJECT!',
      'WOW!!');
  }

  private function assertSentenceSummary($corpus, $summary) {
    $this->assertEqual(
      $summary,
      PhabricatorMarkupEngine::summarizeSentence($corpus),
      pht('Summary of: %s', $corpus));
  }

}
