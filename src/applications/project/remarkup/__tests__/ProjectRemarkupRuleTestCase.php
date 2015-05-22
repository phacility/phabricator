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

      // Don't match a terminal parenthesis. This fixes these constructs in
      // natural language.
      'There is some documentation (see #guides).' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 34,
            'id' => 'guides',
          ),
        ),
      ),

      // Don't match internal parentheses either. This makes the terminal
      // parenthesis behavior less arbitrary (otherwise, we match open
      // parentheses but not closing parentheses, which is surprising).
      '#a(b)c' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'a',
          ),
        ),
      ),

      '#s3' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 's3',
          ),
        ),
      ),

      'Is this #urgent?' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 9,
            'id' => 'urgent',
          ),
        ),
      ),

      'This is "#urgent".' => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 10,
            'id' => 'urgent',
          ),
        ),
      ),

      "This is '#urgent'." => array(
        'embed' => array(),
        'ref' => array(
          array(
            'offset' => 10,
            'id' => 'urgent',
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
