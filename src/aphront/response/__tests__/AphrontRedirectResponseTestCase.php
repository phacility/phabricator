<?php

final class AphrontRedirectResponseTestCase extends PhabricatorTestCase {

  public function testLocalRedirectURIs() {
    // Format a bunch of URIs for local and remote redirection, making sure
    // we get the expected results.

    $uris = array(
      '/a' => array(
        'http://phabricator.example.com/a',
        false,
      ),
      'a' => array(
        false,
        false,
      ),
      '/\\evil.com' => array(
        false,
        false,
      ),
      'http://www.evil.com/' => array(
        false,
        'http://www.evil.com/',
      ),
      '//evil.com' => array(
        false,
        false,
      ),
      '//' => array(
        false,
        false,
      ),
      '' => array(
        false,
        false,
      ),
    );

    foreach ($uris as $input => $cases) {
      foreach (array(false, true) as $idx => $is_external) {
        $expect = $cases[$idx];

        $caught = null;
        try {
          $result = AphrontRedirectResponse::getURIForRedirect(
            $input,
            $is_external);
        } catch (Exception $ex) {
          $caught = $ex;
        }

        if ($expect === false) {
          $this->assertTrue(($caught instanceof Exception), $input);
        } else {
          $this->assertEqual(null, $caught, $input);
          $this->assertEqual($expect, $result, $input);
        }
      }
    }
  }

}
