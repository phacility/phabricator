<?php

final class PhabricatorEditorURIEngine
  extends Phobject {

  private $viewer;
  private $repository;
  private $pattern;
  private $rawTokens;
  private $repositoryTokens;

  public static function newForViewer(PhabricatorUser $viewer) {
    if (!$viewer->isLoggedIn()) {
      return null;
    }

    $pattern = $viewer->getUserSetting(PhabricatorEditorSetting::SETTINGKEY);

    if ($pattern === null || !strlen(trim($pattern))) {
      return null;
    }

    $engine = id(new self())
      ->setViewer($viewer)
      ->setPattern($pattern);

    // If there's a problem with the pattern,

    try {
      $engine->validatePattern();
    } catch (PhabricatorEditorURIParserException $ex) {
      return null;
    }

    return $engine;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function setPattern($pattern) {
    $this->pattern = $pattern;
    return $this;
  }

  public function getPattern() {
    return $this->pattern;
  }

  public function validatePattern() {
    $this->getRawURITokens();
    return true;
  }

  public function getURIForPath($path, $line) {
    $tokens = $this->getURITokensForRepository($path);

    $variables = array(
      'f' => $this->escapeToken($path),
      'l' => $this->escapeToken($line),
    );

    $tokens = $this->newTokensWithVariables($tokens, $variables);

    return $this->newStringFromTokens($tokens);
  }

  public function getURITokensForPath($path) {
    $tokens = $this->getURITokensForRepository($path);

    $variables = array(
      'f' => $this->escapeToken($path),
    );

    return $this->newTokensWithVariables($tokens, $variables);
  }

  public static function getVariableDefinitions() {
    return array(
      'f' => array(
        'name' => pht('File Name'),
        'example' => pht('path/to/source.c'),
      ),
      'l' => array(
        'name' => pht('Line Number'),
        'example' => '777',
      ),
      'n' => array(
        'name' => pht('Repository Short Name'),
        'example' => 'arcanist',
      ),
      'd' => array(
        'name' => pht('Repository ID'),
        'example' => '42',
      ),
      'p' => array(
        'name' => pht('Repository PHID'),
        'example' => 'PHID-REPO-abcdefghijklmnopqrst',
      ),
      'r' => array(
        'name' => pht('Repository Callsign'),
        'example' => 'XYZ',
      ),
      '%' => array(
        'name' => pht('Literal Percent Symbol'),
        'example' => '%',
      ),
    );
  }

  private function getURITokensForRepository() {
    if (!$this->repositoryTokens) {
      $this->repositoryTokens = $this->newURITokensForRepository();
    }

    return $this->repositoryTokens;
  }

  private function newURITokensForRepository() {
    $tokens = $this->getRawURITokens();

    $repository = $this->getRepository();
    if (!$repository) {
      throw new PhutilInvalidStateException('setRepository');
    }

    $variables = array(
      'r' => $this->escapeToken($repository->getCallsign()),
      'n' => $this->escapeToken($repository->getRepositorySlug()),
      'd' => $this->escapeToken($repository->getID()),
      'p' => $this->escapeToken($repository->getPHID()),
    );

    return $this->newTokensWithVariables($tokens, $variables);
  }

  private function getRawURITokens() {
    if (!$this->rawTokens) {
      $this->rawTokens = $this->newRawURITokens();
    }
    return $this->rawTokens;
  }

  private function newRawURITokens() {
    $raw_pattern = $this->getPattern();
    $raw_tokens = self::newPatternTokens($raw_pattern);

    $variable_definitions = self::getVariableDefinitions();

    foreach ($raw_tokens as $token) {
      if ($token['type'] !== 'variable') {
        continue;
      }

      $value = $token['value'];

      if (isset($variable_definitions[$value])) {
        continue;
      }

      throw new PhabricatorEditorURIParserException(
        pht(
          'Editor pattern "%s" is invalid: the pattern contains an '.
          'unrecognized variable ("%s"). Use "%%%%" to encode a literal '.
          'percent symbol.',
          $raw_pattern,
          '%'.$value));
    }

    $variables = array(
      '%' => '%',
    );

    $tokens = $this->newTokensWithVariables($raw_tokens, $variables);

    $first_literal = null;
    if ($tokens) {
      foreach ($tokens as $token) {
        if ($token['type'] === 'literal') {
          $first_literal = $token['value'];
        }
        break;
      }

      if ($first_literal === null) {
        throw new PhabricatorEditorURIParserException(
          pht(
            'Editor pattern "%s" is invalid: the pattern must begin with '.
            'a valid editor protocol, but begins with a variable. This is '.
            'very sneaky and also very forbidden.',
            $raw_pattern));
      }
    }

    $uri = new PhutilURI($first_literal);
    $editor_protocol = $uri->getProtocol();

    if (!$editor_protocol) {
      throw new PhabricatorEditorURIParserException(
        pht(
          'Editor pattern "%s" is invalid: the pattern must begin with '.
          'a valid editor protocol, but does not begin with a recognized '.
          'protocol string.',
          $raw_pattern));
    }

    $allowed_key = 'uri.allowed-editor-protocols';
    $allowed_protocols = PhabricatorEnv::getEnvConfig($allowed_key);
    if (empty($allowed_protocols[$editor_protocol])) {
      throw new PhabricatorEditorURIParserException(
        pht(
          'Editor pattern "%s" is invalid: the pattern must begin with '.
          'a valid editor protocol, but the protocol "%s://" is not allowed.',
          $raw_pattern,
          $editor_protocol));
    }

    return $tokens;
  }

  private function newTokensWithVariables(array $tokens, array $variables) {
    // Replace all "variable" tokens that we have replacements for with
    // the literal value.
    foreach ($tokens as $key => $token) {
      $type = $token['type'];

      if ($type == 'variable') {
        $variable = $token['value'];
        if (isset($variables[$variable])) {
          $tokens[$key] = array(
            'type' => 'literal',
            'value' => $variables[$variable],
          );
        }
      }
    }

    // Now, merge sequences of adjacent "literal" tokens into a single token.
    $last_literal = null;
    foreach ($tokens as $key => $token) {
      $is_literal = ($token['type'] === 'literal');

      if (!$is_literal) {
        $last_literal = null;
        continue;
      }

      if ($last_literal !== null) {
        $tokens[$key]['value'] =
          $tokens[$last_literal]['value'].$token['value'];
        unset($tokens[$last_literal]);
      }

      $last_literal = $key;
    }

    $tokens = array_values($tokens);

    return $tokens;
  }

  private function escapeToken($token) {
    // Paths are user controlled, so a clever user could potentially make
    // editor links do surprising things with paths containing "/../".

    // Find anything that looks like "/../" and mangle it.

    $token = preg_replace('((^|/)\.\.(/|\z))', '\1dot-dot\2', $token);

    return phutil_escape_uri($token);
  }

  private function newStringFromTokens(array $tokens) {
    $result = array();

    foreach ($tokens as $token) {
      $token_type = $token['type'];
      $token_value = $token['value'];

      $is_literal = ($token_type === 'literal');
      if (!$is_literal) {
        throw new Exception(
          pht(
            'Editor pattern token list can not be converted into a string: '.
            'it still contains a non-literal token ("%s", of type "%s").',
            $token_value,
            $token_type));
      }

      $result[] = $token_value;
    }

    $result = implode('', $result);

    return $result;
  }

  public static function newPatternTokens($raw_pattern) {
    $token_positions = array();

    $len = strlen($raw_pattern);

    for ($ii = 0; $ii < $len; $ii++) {
      $c = $raw_pattern[$ii];
      if ($c === '%') {
        if (!isset($raw_pattern[$ii + 1])) {
          throw new PhabricatorEditorURIParserException(
            pht(
              'Editor pattern "%s" is invalid: the final character in a '.
              'pattern may not be an unencoded percent symbol ("%%"). '.
              'Use "%%%%" to encode a literal percent symbol.',
              $raw_pattern));
        }

        $token_positions[] = $ii;
        $ii++;
      }
    }

    // Add a final marker past the end of the string, so we'll collect any
    // trailing literal bytes.
    $token_positions[] = $len;

    $tokens = array();
    $cursor = 0;
    foreach ($token_positions as $pos) {
      $token_len = ($pos - $cursor);

      if ($token_len > 0) {
        $tokens[] = array(
          'type' => 'literal',
          'value' => substr($raw_pattern, $cursor, $token_len),
        );
      }

      $cursor = $pos;

      if ($cursor < $len) {
        $tokens[] = array(
          'type' => 'variable',
          'value' => substr($raw_pattern, $cursor + 1, 1),
        );
      }

      $cursor = $pos + 2;
    }

    return $tokens;
  }

}
