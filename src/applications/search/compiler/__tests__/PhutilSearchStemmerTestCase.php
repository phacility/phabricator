<?php

final class PhutilSearchStemmerTestCase
  extends PhutilTestCase {

  public function testStemTokens() {
    $tests = array(
      // Various real-world cases collected from users before we implemented
      // stemming.
      'tokens' => 'token',
      'panels' => 'panel',

      'renames' => 'renam',
      'rename' => 'renam',

      'components' => 'compon',
      'component' => 'compon',

      'implementation' => 'implement',
      'implements' => 'implement',
      'implementing' => 'implement',
      'implementer' => 'implement',

      'deleting' => 'delet',
      'deletion' => 'delet',
      'delete' => 'delet',

      'erratically' => 'errat',
      'erratic' => 'errat',

      // Stems should be normalized.
      'DOG' => 'dog',

      // If stemming would bring a token under 3 characters, it should not
      // be stemmed.
      'dns' => 'dns',
      'nis' => 'nis',

      // Complex tokens with internal punctuation should be left untouched;
      // these are usually things like domain names, API calls, informal tags,
      // etc.
      'apples' => 'appl',
      'bananas' => 'banana',
      'apples_bananas' => 'apples_bananas',
      'apples_bananas.apples_bananas' => 'apples_bananas.apples_bananas',
    );

    $stemmer = new PhutilSearchStemmer();
    foreach ($tests as $input => $expect) {
      $stem = $stemmer->stemToken($input);
      $this->assertEqual(
        $expect,
        $stem,
        pht('Token stem of "%s".', $input));
    }
  }

  public function testStemDocuments() {
    $tests = array(
      'The wild boar meandered erratically.' =>
        'the wild boar meander errat',
      'Fool me onc, shame on you. Fool me twice, shame on me.' =>
        'fool onc shame you twice',
      'Fireball is a seventh-level spell which deals 2d16 points of damage '.
      'in a 1-meter radius around a target.' =>
        'firebal seventh level spell which deal 2d16 point damag meter '.
        'radiu around target',
      'apples-bananas' => 'appl banana',
      'apples_bananas' => 'apples_bananas',
      'apples.bananas' => 'apples.bananas',
      'oddly-proportioned' => 'oddli proport',
    );

    $stemmer = new PhutilSearchStemmer();
    foreach ($tests as $input => $expect) {
      $stem = $stemmer->stemCorpus($input);
      $this->assertEqual(
        $expect,
        $stem,
        pht('Corpus stem of: %s', $input));
    }
  }


}
