<?php

final class PhabricatorSyntaxHighlighter extends Phobject {

  public static function newEngine() {
    $engine = PhabricatorEnv::newObjectFromConfig('syntax-highlighter.engine');

    $config = array(
      'pygments.enabled' => PhabricatorEnv::getEnvConfig('pygments.enabled'),
      'filename.map'     => PhabricatorEnv::getEnvConfig('syntax.filemap'),
    );

    foreach ($config as $key => $value) {
      $engine->setConfig($key, $value);
    }

    return $engine;
  }

  public static function highlightWithFilename($filename, $source) {
    $engine = self::newEngine();
    $language = $engine->getLanguageFromFilename($filename);
    return $engine->highlightSource($language, $source);
  }

  public static function highlightWithLanguage($language, $source) {
    $engine = self::newEngine();
    return $engine->highlightSource($language, $source);
  }

}
