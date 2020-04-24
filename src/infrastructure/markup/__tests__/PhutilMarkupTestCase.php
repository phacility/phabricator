<?php

final class PhutilMarkupTestCase extends PhutilTestCase {

  public function testTagDefaults() {
    $this->assertEqual(
      (string)phutil_tag('br'),
      (string)phutil_tag('br', array()));

    $this->assertEqual(
      (string)phutil_tag('br', array()),
      (string)phutil_tag('br', array(), null));
  }

  public function testTagEmpty() {
    $this->assertEqual(
      '<br />',
      (string)phutil_tag('br', array(), null));

    $this->assertEqual(
      '<div></div>',
      (string)phutil_tag('div', array(), null));

    $this->assertEqual(
      '<div></div>',
      (string)phutil_tag('div', array(), ''));
  }

  public function testTagBasics() {
    $this->assertEqual(
      '<br />',
      (string)phutil_tag('br'));

    $this->assertEqual(
      '<div>y</div>',
      (string)phutil_tag('div', array(), 'y'));
  }

  public function testTagAttributes() {
    $this->assertEqual(
      '<div u="v">y</div>',
      (string)phutil_tag('div', array('u' => 'v'), 'y'));

    $this->assertEqual(
      '<br u="v" />',
      (string)phutil_tag('br', array('u' => 'v')));
  }

  public function testTagEscapes() {
    $this->assertEqual(
      '<br u="&lt;" />',
      (string)phutil_tag('br', array('u' => '<')));

    $this->assertEqual(
      '<div><br /></div>',
      (string)phutil_tag('div', array(), phutil_tag('br')));
  }

  public function testTagNullAttribute() {
    $this->assertEqual(
      '<br />',
      (string)phutil_tag('br', array('y' => null)));
  }

  public function testTagJavascriptProtocolRejection() {
    $hrefs = array(
      'javascript:alert(1)'         => true,
      'JAVASCRIPT:alert(2)'         => true,

      // NOTE: When interpreted as a URI, this is dropped because of leading
      // whitespace.
      '     javascript:alert(3)'    => array(true, false),
      '/'                           => false,
      '/path/to/stuff/'             => false,
      ''                            => false,
      'http://example.com/'         => false,
      '#'                           => false,
      'javascript://anything'       => true,

      // Chrome 33 and IE11, at a minimum, treat this as Javascript.
      "javascript\n:alert(4)"       => true,

      // Opera currently accepts a variety of unicode spaces. This test case
      // has a smattering of them.
      "\xE2\x80\x89javascript:"     => true,
      "javascript\xE2\x80\x89:"     => true,
      "\xE2\x80\x84javascript:"     => true,
      "javascript\xE2\x80\x84:"     => true,

      // Because we're aggressive, all of unicode should trigger detection
      // by default.
      "\xE2\x98\x83javascript:"     => true,
      "javascript\xE2\x98\x83:"     => true,
      "\xE2\x98\x83javascript\xE2\x98\x83:" => true,

      // We're aggressive about this, so we'll intentionally raise false
      // positives in these cases.
      'javascript~:alert(5)'        => true,
      '!!!javascript!!!!:alert(6)'  => true,

      // However, we should raise true negatives in these slightly more
      // reasonable cases.
      'javascript/:docs.html'       => false,
      'javascripts:x.png'           => false,
      'COOLjavascript:page'         => false,
      '/javascript:alert(1)'        => false,
    );

    foreach (array(true, false) as $use_uri) {
      foreach ($hrefs as $href => $expect) {
        if (is_array($expect)) {
          $expect = ($use_uri ? $expect[1] : $expect[0]);
        }

        if ($use_uri) {
          $href_value = new PhutilURI($href);
        } else {
          $href_value = $href;
        }

        $caught = null;
        try {
          phutil_tag('a', array('href' => $href_value), 'click for candy');
        } catch (Exception $ex) {
          $caught = $ex;
        }

        $desc = pht(
          'Unexpected result for "%s". <uri = %s, expect exception = %s>',
          $href,
          $use_uri ? pht('Yes') : pht('No'),
          $expect ? pht('Yes') : pht('No'));

        $this->assertEqual(
          $expect,
          $caught instanceof Exception,
          $desc);
      }
    }
  }

  public function testURIEscape() {
    $this->assertEqual(
      '%2B/%20%3F%23%26%3A%21xyz%25',
      phutil_escape_uri('+/ ?#&:!xyz%'));
  }

  public function testURIPathComponentEscape() {
    $this->assertEqual(
      'a%252Fb',
      phutil_escape_uri_path_component('a/b'));

    $str = '';
    for ($ii = 0; $ii <= 255; $ii++) {
      $str .= chr($ii);
    }

    $this->assertEqual(
      $str,
      phutil_unescape_uri_path_component(
        rawurldecode( // Simulates webserver.
          phutil_escape_uri_path_component($str))));
  }

  public function testHsprintf() {
    $this->assertEqual(
      '<div>&lt;3</div>',
      (string)hsprintf('<div>%s</div>', '<3'));
  }

  public function testAppendHTML() {
    $html = phutil_tag('hr');
    $html->appendHTML(phutil_tag('br'), '<evil>');
    $this->assertEqual('<hr /><br />&lt;evil&gt;', $html->getHTMLContent());
  }

  public function testArrayEscaping() {
    $this->assertEqual(
      '<div>&lt;div&gt;</div>',
      phutil_escape_html(
        array(
          hsprintf('<div>'),
          array(
            array(
              '<',
              array(
                'd',
                array(
                  array(
                    hsprintf('i'),
                  ),
                  'v',
                ),
              ),
              array(
                array(
                  '>',
                ),
              ),
            ),
          ),
          hsprintf('</div>'),
        )));

    $this->assertEqual(
      '<div><br /><hr /><wbr /></div>',
      phutil_tag(
        'div',
        array(),
        array(
          array(
            array(
              phutil_tag('br'),
              array(
                phutil_tag('hr'),
              ),
              phutil_tag('wbr'),
            ),
          ),
        ))->getHTMLContent());
  }

}
