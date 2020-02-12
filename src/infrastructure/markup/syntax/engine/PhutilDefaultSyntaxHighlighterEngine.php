<?php

final class PhutilDefaultSyntaxHighlighterEngine
  extends PhutilSyntaxHighlighterEngine {

  private $config = array();

  public function setConfig($key, $value) {
    $this->config[$key] = $value;
    return $this;
  }

  public function getLanguageFromFilename($filename) {
    static $default_map = array(
      // All files which have file extensions that we haven't already matched
      // map to their extensions.
      '@\\.([^./]+)$@' => 1,
    );

    $maps = array();
    if (!empty($this->config['filename.map'])) {
      $maps[] = $this->config['filename.map'];
    }
    $maps[] = $default_map;

    foreach ($maps as $map) {
      foreach ($map as $regexp => $lang) {
        $matches = null;
        if (preg_match($regexp, $filename, $matches)) {
          if (is_numeric($lang)) {
            return idx($matches, $lang);
          } else {
            return $lang;
          }
        }
      }
    }

    return null;
  }

  public function getHighlightFuture($language, $source) {
    if ($language === null) {
      $language = PhutilLanguageGuesser::guessLanguage($source);
    }

    $have_pygments = !empty($this->config['pygments.enabled']);

    if ($language == 'php' && PhutilXHPASTBinary::isAvailable()) {
      return id(new PhutilXHPASTSyntaxHighlighter())
        ->getHighlightFuture($source);
    }

    if ($language == 'console') {
      return id(new PhutilConsoleSyntaxHighlighter())
        ->getHighlightFuture($source);
    }

    if ($language == 'diviner' || $language == 'remarkup') {
      return id(new PhutilDivinerSyntaxHighlighter())
        ->getHighlightFuture($source);
    }

    if ($language == 'rainbow') {
      return id(new PhutilRainbowSyntaxHighlighter())
        ->getHighlightFuture($source);
    }

    if ($language == 'php') {
      return id(new PhutilLexerSyntaxHighlighter())
        ->setConfig('lexer', new PhutilPHPFragmentLexer())
        ->setConfig('language', 'php')
        ->getHighlightFuture($source);
    }

    if ($language == 'py' || $language == 'python') {
      return id(new PhutilLexerSyntaxHighlighter())
        ->setConfig('lexer', new PhutilPythonFragmentLexer())
        ->setConfig('language', 'py')
        ->getHighlightFuture($source);
    }

    if ($language == 'java') {
      return id(new PhutilLexerSyntaxHighlighter())
        ->setConfig('lexer', new PhutilJavaFragmentLexer())
        ->setConfig('language', 'java')
        ->getHighlightFuture($source);
    }

    if ($language == 'json') {
      return id(new PhutilLexerSyntaxHighlighter())
        ->setConfig('lexer', new PhutilJSONFragmentLexer())
        ->getHighlightFuture($source);
    }

    if ($language == 'invisible') {
      return id(new PhutilInvisibleSyntaxHighlighter())
        ->getHighlightFuture($source);
    }

    // Don't invoke Pygments for plain text, since it's expensive and has
    // no effect.
    if ($language !== 'text' && $language !== 'txt') {
      if ($have_pygments) {
        return id(new PhutilPygmentsSyntaxHighlighter())
          ->setConfig('language', $language)
          ->getHighlightFuture($source);
      }
    }

    return id(new PhutilDefaultSyntaxHighlighter())
      ->getHighlightFuture($source);
  }

}
