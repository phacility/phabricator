<?php

final class PhabricatorKeyboardRemarkupRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 200.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@{key\b((?:[^}\\\\]+|\\\\.)*)}@m',
      array($this, 'markupKeystrokes'),
      $text);
  }

  public function markupKeystrokes(array $matches) {
    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    $keys = explode(' ', $matches[1]);
    foreach ($keys as $k => $v) {
      $v = trim($v, " \n");
      $v = preg_replace('/\\\\(.)/', '\\1', $v);
      if (!strlen($v)) {
        unset($keys[$k]);
        continue;
      }
      $keys[$k] = $v;
    }

    $special = array(
      array(
        'name' => pht('Command'),
        'symbol' => "\xE2\x8C\x98",
        'aliases' => array(
          'cmd',
          'command',
        ),
      ),
      array(
        'name' => pht('Option'),
        'symbol' => "\xE2\x8C\xA5",
        'aliases' => array(
          'opt',
          'option',
        ),
      ),
      array(
        'name' => pht('Shift'),
        'symbol' => "\xE2\x87\xA7",
        'aliases' => array(
          'shift',
        ),
      ),
      array(
        'name' => pht('Escape'),
        'symbol' => "\xE2\x8E\x8B",
        'aliases' => array(
          'esc',
          'escape',
        ),
      ),
      array(
        'name' => pht('Up'),
        'symbol' => "\xE2\x86\x91",
        'heavy' => "\xE2\xAC\x86",
        'aliases' => array(
          'up',
          'arrow-up',
          'up-arrow',
          'north',
        ),
      ),
      array(
        'name' => pht('Tab'),
        'symbol' => "\xE2\x87\xA5",
        'aliases' => array(
          'tab',
        ),
      ),
      array(
        'name' => pht('Right'),
        'symbol' => "\xE2\x86\x92",
        'heavy' => "\xE2\x9E\xA1",
        'aliases' => array(
          'right',
          'right-arrow',
          'arrow-right',
          'east',
        ),
      ),
      array(
        'name' => pht('Left'),
        'symbol' => "\xE2\x86\x90",
        'heavy' => "\xE2\xAC\x85",
        'aliases' => array(
          'left',
          'left-arrow',
          'arrow-left',
          'west',
        ),
      ),
      array(
        'name' => pht('Down'),
        'symbol' => "\xE2\x86\x93",
        'heavy' => "\xE2\xAC\x87",
        'aliases' => array(
          'down',
          'down-arrow',
          'arrow-down',
          'south',
        ),
      ),
      array(
        'name' => pht('Up Right'),
        'symbol' => "\xE2\x86\x97",
        'heavy' => "\xE2\xAC\x88",
        'aliases' => array(
          'up-right',
          'upright',
          'up-right-arrow',
          'upright-arrow',
          'arrow-up-right',
          'arrow-upright',
          'northeast',
          'north-east',
        ),
      ),
      array(
        'name' => pht('Down Right'),
        'symbol' => "\xE2\x86\x98",
        'heavy' => "\xE2\xAC\x8A",
        'aliases' => array(
          'down-right',
          'downright',
          'down-right-arrow',
          'downright-arrow',
          'arrow-down-right',
          'arrow-downright',
          'southeast',
          'south-east',
        ),
      ),
      array(
        'name' => pht('Down Left'),
        'symbol' => "\xE2\x86\x99",
        'heavy' => "\xE2\xAC\x8B",
        'aliases' => array(
          'down-left',
          'downleft',
          'down-left-arrow',
          'downleft-arrow',
          'arrow-down-left',
          'arrow-downleft',
          'southwest',
          'south-west',
        ),
      ),
      array(
        'name' => pht('Up Left'),
        'symbol' => "\xE2\x86\x96",
        'heavy' => "\xE2\xAC\x89",
        'aliases' => array(
          'up-left',
          'upleft',
          'up-left-arrow',
          'upleft-arrow',
          'arrow-up-left',
          'arrow-upleft',
          'northwest',
          'north-west',
        ),
      ),
    );

    $map = array();
    foreach ($special as $spec) {
      foreach ($spec['aliases'] as $alias) {
        $map[$alias] = $spec;
      }
    }

    $is_text = $this->getEngine()->isTextMode();

    $parts = array();
    foreach ($keys as $k => $v) {
      $normal = phutil_utf8_strtolower($v);
      if (isset($map[$normal])) {
        $spec = $map[$normal];
      } else {
        $spec = array(
          'name' => null,
          'symbol' => $v,
        );
      }

      if ($is_text) {
        $parts[] = '['.$spec['symbol'].']';
      } else {
        $parts[] = phutil_tag(
          'kbd',
          array(
            'title' => $spec['name'],
          ),
          $spec['symbol']);
      }
    }

    if ($is_text) {
      $parts = implode(' + ', $parts);
    } else {
      $glue = phutil_tag(
        'span',
        array(
          'class' => 'kbd-join',
        ),
        '+');
      $parts = phutil_implode_html($glue, $parts);
    }

    return $this->getEngine()->storeText($parts);
  }

}
