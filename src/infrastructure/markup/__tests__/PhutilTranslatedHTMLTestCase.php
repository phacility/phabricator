<?php

final class PhutilTranslatedHTMLTestCase extends PhutilTestCase {

  public function testHTMLTranslations() {
    $string = '%s awoke <strong>suddenly</strong> at %s.';
    $when = '<4 AM>';

    $translator = $this->newTranslator('en_US');

    // When no components are HTML, everything is treated as a string.
    $who = '<span>Abraham</span>';
    $translation = $translator->translate(
      $string,
      $who,
      $when);
    $this->assertEqual(
      'string',
      gettype($translation));
    $this->assertEqual(
      '<span>Abraham</span> awoke <strong>suddenly</strong> at <4 AM>.',
      $translation);

    // When at least one component is HTML, everything is treated as HTML.
    $who = phutil_tag('span', array(), 'Abraham');
    $translation = $translator->translate(
      $string,
      $who,
      $when);
    $this->assertTrue($translation instanceof PhutilSafeHTML);
    $this->assertEqual(
      '<span>Abraham</span> awoke <strong>suddenly</strong> at &lt;4 AM&gt;.',
      $translation->getHTMLContent());

    $translation = $translator->translate(
      $string,
      $who,
      new PhutilNumber(1383930802));
    $this->assertEqual(
      '<span>Abraham</span> awoke <strong>suddenly</strong> at 1,383,930,802.',
      $translation->getHTMLContent());

    // In this translation, we have no alternatives for the first conversion.
    $translator->setTranslations(
      array(
        'Run the command %s %d time(s).' => array(
          array(
            'Run the command %s once.',
            'Run the command %s %d times.',
          ),
        ),
      ));

    $this->assertEqual(
      'Run the command <tt>ls</tt> 123 times.',
      (string)$translator->translate(
        'Run the command %s %d time(s).',
        hsprintf('<tt>%s</tt>', 'ls'),
        123));
  }

  private function newTranslator($locale_code) {
    $locale = PhutilLocale::loadLocale($locale_code);
    return id(new PhutilTranslator())
      ->setLocale($locale);
  }

}
