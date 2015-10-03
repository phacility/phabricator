<?php

final class DiffusionCommitRemarkupRuleTestCase extends PhabricatorTestCase {

  public function testProjectObjectRemarkup() {
    $cases = array(
      '{rP12f3f6d3a9ef9c7731051815846810cb3c4cd248}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'rP12f3f6d3a9ef9c7731051815846810cb3c4cd248',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'rP12f3f6d3a9ef9c7731051815846810cb3c4cd248',
          ),
        ),
      ),
      '{rP1234, key=value}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'rP1234',
            'tail' => ', key=value',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'rP1234',
          ),
        ),
      ),
      '{rP1234 key=value}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'rP1234',
            'tail' => ' key=value',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'rP1234',
          ),
        ),
      ),
      '{rP:1234 key=value}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'rP:1234',
            'tail' => ' key=value',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'rP:1234',
          ),
        ),
      ),
      '{R123:1234 key=value}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'R123:1234',
            'tail' => ' key=value',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'R123:1234',
          ),
        ),
      ),
      '{rP:12f3f6d3a9ef9c7731051815846810cb3c4cd248}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'rP:12f3f6d3a9ef9c7731051815846810cb3c4cd248',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'rP:12f3f6d3a9ef9c7731051815846810cb3c4cd248',
          ),
        ),
      ),
      '{R123:12f3f6d3a9ef9c7731051815846810cb3c4cd248}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'R123:12f3f6d3a9ef9c7731051815846810cb3c4cd248',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'R123:12f3f6d3a9ef9c7731051815846810cb3c4cd248',
          ),
        ),
      ),
      '{R123:12f3f6d3a9ef9c7731051815846810cb3c4cd248, key=value}' => array(
        'embed' => array(
          array(
            'offset' => 1,
            'id' => 'R123:12f3f6d3a9ef9c7731051815846810cb3c4cd248',
            'tail' => ', key=value',
          ),
        ),
        'ref' => array(
          array(
            'offset' => 1,
            'id' => 'R123:12f3f6d3a9ef9c7731051815846810cb3c4cd248',
          ),
        ),
      ),

      // After an "@", we should not be recognizing references because these
      // are username mentions.
      'deadbeef' => array(
        'embed' => array(
        ),
        'ref' => array(
          array(
            'offset' => 0,
            'id' => 'deadbeef',
          ),
        ),
      ),
      '@deadbeef' => array(
        'embed' => array(
        ),
        'ref' => array(
        ),
      ),
    );

    foreach ($cases as $input => $expect) {
      $rule = new DiffusionCommitRemarkupRule();
      $matches = $rule->extractReferences($input);
      $this->assertEqual($expect, $matches, $input);
    }
  }

}
