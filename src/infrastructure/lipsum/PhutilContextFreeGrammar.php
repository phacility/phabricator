<?php

/**
 * Generate nonsense test data according to a context-free grammar definition.
 */
abstract class PhutilContextFreeGrammar extends Phobject {

  private $limit = 65535;

  abstract protected function getRules();

  public function generateSeveral($count, $implode = ' ') {
    $paragraph = array();
    for ($ii = 0; $ii < $count; $ii++) {
      $paragraph[$ii] = $this->generate();
    }
    return implode($implode, $paragraph);
  }

  public function generate() {
    $count = 0;
    $rules = $this->getRules();
    return $this->applyRules('[start]', $count, $rules);
  }

  final protected function applyRules($input, &$count, array $rules) {
    if (++$count > $this->limit) {
      throw new Exception(pht('Token replacement count exceeded limit!'));
    }

    $matches = null;
    preg_match_all('/(\\[[^\\]]+\\])/', $input, $matches, PREG_OFFSET_CAPTURE);

    foreach (array_reverse($matches[1]) as $token_spec) {
      list($token, $offset) = $token_spec;
      $token_name = substr($token, 1, -1);
      $options = array();

      if (($name_end = strpos($token_name, ','))) {
        $options_parser = new PhutilSimpleOptions();
        $options = $options_parser->parse($token_name);
        $token_name = substr($token_name, 0, $name_end);
      }

      if (empty($rules[$token_name])) {
        throw new Exception(pht("Invalid token '%s' in grammar.", $token_name));
      }

      $key = array_rand($rules[$token_name]);
      $replacement = $this->applyRules($rules[$token_name][$key],
        $count, $rules);

      if (isset($options['indent'])) {
        if (is_numeric($options['indent'])) {
          $replacement = self::strPadLines($replacement, $options['indent']);
        } else {
          $replacement = self::strPadLines($replacement);
        }
      }
      if (isset($options['trim'])) {
        switch ($options['trim']) {
          case 'left':
            $replacement = ltrim($replacement);
            break;
          case 'right':
            $replacement = rtrim($replacement);
            break;
          default:
          case 'both':
            $replacement = trim($replacement);
            break;
        }
      }
      if (isset($options['block'])) {
        $replacement = "\n".$replacement."\n";
      }

      $input = substr_replace($input, $replacement, $offset, strlen($token));
    }

    return $input;
  }

  private static function strPadLines($text, $num_spaces = 2) {
    $text_lines = phutil_split_lines($text);
    foreach ($text_lines as $linenr => $line) {
      $text_lines[$linenr] = str_repeat(' ', $num_spaces).$line;
    }

    return implode('', $text_lines);
  }

}
