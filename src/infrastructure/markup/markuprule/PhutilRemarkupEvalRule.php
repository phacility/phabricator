<?php

final class PhutilRemarkupEvalRule extends PhutilRemarkupRule {

  const KEY_EVAL = 'eval';

  public function getPriority() {
    return 50;
  }

  public function apply($text) {
    return preg_replace_callback(
      '/\${{{(.+?)}}}/',
      array($this, 'newExpressionToken'),
      $text);
  }

  public function newExpressionToken(array $matches) {
    $expression = $matches[1];

    if (!$this->isFlatText($expression)) {
      return $matches[0];
    }

    $engine = $this->getEngine();
    $token = $engine->storeText($expression);

    $list_key = self::KEY_EVAL;
    $expression_list = $engine->getTextMetadata($list_key, array());

    $expression_list[] = array(
      'token' => $token,
      'expression' => $expression,
      'original' => $matches[0],
    );

    $engine->setTextMetadata($list_key, $expression_list);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $list_key = self::KEY_EVAL;
    $expression_list = $engine->getTextMetadata($list_key, array());

    foreach ($expression_list as $expression_item) {
      $token = $expression_item['token'];
      $expression = $expression_item['expression'];

      $result = $this->evaluateExpression($expression);

      if ($result === null) {
        $result = $expression_item['original'];
      }

      $engine->overwriteStoredText($token, $result);
    }
  }

  private function evaluateExpression($expression) {
    static $string_map;

    if ($string_map === null) {
      $string_map = array(
        'strings' => array(
          'platform' => array(
            'server' => array(
              'name' => PlatformSymbols::getPlatformServerName(),
              'path' => pht('phabricator/'),
            ),
            'client' => array(
              'name' => PlatformSymbols::getPlatformClientName(),
              'path' => pht('arcanist/'),
            ),
          ),
        ),
      );
    }

    $parts = explode('.', $expression);

    $cursor = $string_map;
    foreach ($parts as $part) {
      if (isset($cursor[$part])) {
        $cursor = $cursor[$part];
      } else {
        break;
      }
    }

    if (is_string($cursor)) {
      return $cursor;
    }

    return null;
  }

}
