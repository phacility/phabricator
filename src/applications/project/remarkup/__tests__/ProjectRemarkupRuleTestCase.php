<?php

final class ProjectRemarkupRuleTestCase extends PhabricatorTestCase {

  public function testProjectObjectRemarkup() {
    $cases = array(
      'I like #ducks.' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 8,
            'id' => 'ducks',
          ),
        ),
      ),
      'We should make a post on #blog.example.com tomorrow.' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 26,
            'id' => 'blog.example.com',
          ),
        ),
      ),
      'We should make a post on #blog.example.com.' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 26,
            'id' => 'blog.example.com',
          ),
        ),
      ),
      '#123' => array(
        'embed' => array(),
        'ref' => array(),
      ),
      '#security#123' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'security',
            'tail' => '123',
          ),
        ),
      ),
    );

    foreach ($cases as $input => $expect) {
      $rule = new ProjectRemarkupRule();
      $matches = $rule->extractReferences($input);
      $this->assertEqual($expect, $matches, $input);
    }
  }

}
