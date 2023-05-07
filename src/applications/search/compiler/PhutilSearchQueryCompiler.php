<?php

final class PhutilSearchQueryCompiler
  extends Phobject {

  private $operators = '+ -><()~*:""&|';
  private $query;
  private $stemmer;
  private $enableFunctions = false;

  const OPERATOR_NOT = 'not';
  const OPERATOR_AND = 'and';
  const OPERATOR_SUBSTRING = 'sub';
  const OPERATOR_EXACT = 'exact';
  const OPERATOR_ABSENT = 'absent';
  const OPERATOR_PRESENT = 'present';

  public function setOperators($operators) {
    $this->operators = $operators;
    return $this;
  }

  public function getOperators() {
    return $this->operators;
  }

  public function setStemmer(PhutilSearchStemmer $stemmer) {
    $this->stemmer = $stemmer;
    return $this;
  }

  public function getStemmer() {
    return $this->stemmer;
  }

  public function setEnableFunctions($enable_functions) {
    $this->enableFunctions = $enable_functions;
    return $this;
  }

  public function getEnableFunctions() {
    return $this->enableFunctions;
  }

  public function compileQuery(array $tokens) {
    assert_instances_of($tokens, 'PhutilSearchQueryToken');

    $result = array();
    foreach ($tokens as $token) {
      $result[] = $this->renderToken($token);
    }

    return $this->compileRenderedTokens($result);
  }

  public function compileLiteralQuery(array $tokens) {
    assert_instances_of($tokens, 'PhutilSearchQueryToken');

    $result = array();
    foreach ($tokens as $token) {
      if (!$token->isQuoted()) {
        continue;
      }
      $result[] = $this->renderToken($token);
    }

    return $this->compileRenderedTokens($result);
  }

  public function compileStemmedQuery(array $tokens) {
    assert_instances_of($tokens, 'PhutilSearchQueryToken');

    $result = array();
    foreach ($tokens as $token) {
      if ($token->isQuoted()) {
        continue;
      }
      $result[] = $this->renderToken($token, $this->getStemmer());
    }

    return $this->compileRenderedTokens($result);
  }

  private function compileRenderedTokens(array $list) {
    if (!$list) {
      return null;
    }

    $list = array_unique($list);
    return implode(' ', $list);
  }

  public function newTokens($query) {
    $results = $this->tokenizeQuery($query);

    $tokens = array();
    foreach ($results as $result) {
      $tokens[] = PhutilSearchQueryToken::newFromDictionary($result);
    }

    return $tokens;
  }

  private function tokenizeQuery($query) {
    $maximum_bytes = 1024;

    if ($query === null) {
      $query = '';
    }
    $query_bytes = strlen($query);
    if ($query_bytes > $maximum_bytes) {
      throw new PhutilSearchQueryCompilerSyntaxException(
        pht(
          'Query is too long (%s bytes, maximum is %s bytes).',
          new PhutilNumber($query_bytes),
          new PhutilNumber($maximum_bytes)));
    }

    $query = phutil_utf8v($query);
    $length = count($query);

    $enable_functions = $this->getEnableFunctions();

    $mode = 'scan';
    $current_operator = array();
    $current_token = array();
    $current_function = null;
    $is_quoted = false;
    $tokens = array();

    if ($enable_functions) {
      $operator_characters = '[~=+-]';
    } else {
      $operator_characters = '[+-]';
    }

    for ($ii = 0; $ii < $length; $ii++) {
      $character = $query[$ii];

      if ($mode == 'scan') {
        if (preg_match('/^\s\z/u', $character)) {
          continue;
        }

        $mode = 'function';
      }

      if ($mode == 'function') {
        $mode = 'operator';

        if ($enable_functions) {
          $found = false;
          for ($jj = $ii; $jj < $length; $jj++) {
            if (preg_match('/^[a-zA-Z-]\z/u', $query[$jj])) {
              continue;
            }
            if ($query[$jj] == ':') {
              $found = $jj;
            }
            break;
          }

          if ($found !== false) {
            $function = array_slice($query, $ii, ($jj - $ii));
            $current_function = implode('', $function);

            if (!strlen($current_function)) {
              $current_function = null;
            }

            $ii = $jj;
            continue;
          }
        }
      }

      if ($mode == 'operator') {
        if (!$current_operator) {
          if (preg_match('/^\s\z/u', $character)) {
            continue;
          }
        }

        if (preg_match('/^'.$operator_characters.'\z/', $character)) {
          $current_operator[] = $character;
          continue;
        }

        $mode = 'quote';
      }

      if ($mode == 'quote') {
        if (preg_match('/^"\z/', $character)) {
          $is_quoted = true;
          $mode = 'token';
          continue;
        }

        $mode = 'token';
      }

      if ($mode == 'token') {
        $capture = false;
        $was_quoted = $is_quoted;
        if ($is_quoted) {
          if (preg_match('/^"\z/', $character)) {
            $capture = true;
            $mode = 'scan';
            $is_quoted = false;
          }
        } else {
          if (preg_match('/^\s\z/u', $character)) {
            $capture = true;
            $mode = 'scan';
          }

          if (preg_match('/^"\z/', $character)) {
            $capture = true;
            $mode = 'token';
            $is_quoted = true;
          }
        }

        if ($capture) {
          $token = array(
            'operator' => $current_operator,
            'quoted' => $was_quoted,
            'value' => $current_token,
          );

          if ($enable_functions) {
            $token['function'] = $current_function;
          }

          $tokens[] = $token;

          $current_operator = array();
          $current_token = array();
          $current_function = null;
          continue;
        } else {
          $current_token[] = $character;
        }
      }
    }

    if ($is_quoted) {
      throw new PhutilSearchQueryCompilerSyntaxException(
        pht(
          'Query contains unmatched double quotes.'));
    }

    // If the input query has trailing space, like "a b ", we may exit the
    // parser without a final token.
    if ($current_function !== null || $current_operator || $current_token) {
      $token = array(
        'operator' => $current_operator,
        'quoted' => false,
        'value' => $current_token,
      );

      if ($enable_functions) {
        $token['function'] = $current_function;
      }

      $tokens[] = $token;
    }

    $results = array();
    $last_function = null;
    foreach ($tokens as $token) {
      $value = implode('', $token['value']);
      $operator_string = implode('', $token['operator']);
      $is_quoted = $token['quoted'];

      switch ($operator_string) {
        case '-':
          $operator = self::OPERATOR_NOT;
          break;
        case '~':
          $operator = self::OPERATOR_SUBSTRING;
          break;
        case '=':
          $operator = self::OPERATOR_EXACT;
          break;
        case '+':
          $operator = self::OPERATOR_AND;
          break;
        case '':
          $use_substring = false;

          if ($enable_functions && !$is_quoted) {
            // See T12995. If this query term contains Chinese, Japanese or
            // Korean characters, treat the term as a substring term by default.
            // These languages do not separate words with spaces, so the term
            // search mode is normally useless.
            if (phutil_utf8_is_cjk($value)) {
              $use_substring = true;
            } else if (phutil_preg_match('/^_/', $value)) {
              // See T13632. Assume users searching for any term that begins
              // with an undescore intend to perform substring search if they
              // don't provide an explicit search function.
              $use_substring = true;
            }
          }

          if ($use_substring) {
            $operator = self::OPERATOR_SUBSTRING;
          } else {
            $operator = self::OPERATOR_AND;
          }
          break;
        default:
          throw new PhutilSearchQueryCompilerSyntaxException(
            pht(
              'Query has an invalid sequence of operators ("%s").',
              $operator_string));
      }

      if (!strlen($value)) {
        $require_value = $is_quoted;

        switch ($operator) {
          case self::OPERATOR_NOT:
            if ($enable_functions && ($token['function'] !== null)) {
              $operator = self::OPERATOR_ABSENT;
              $value = null;
            } else {
              $require_value = true;
            }
            break;
          case self::OPERATOR_SUBSTRING:
            if ($enable_functions && ($token['function'] !== null)) {
              $operator = self::OPERATOR_PRESENT;
              $value = null;
            } else {
              $require_value = true;
            }
            break;
          default:
            $require_value = true;
            break;
        }

        if ($require_value) {
          throw new PhutilSearchQueryCompilerSyntaxException(
            pht(
              'Query contains a token ("%s") with no search term. Query '.
              'tokens specify text to search for.',
              $this->getDisplayToken($token)));
        }
      }

      $result = array(
        'operator' => $operator,
        'quoted' => $is_quoted,
        'value' => $value,
        'raw' => $this->getDisplayToken($token),
      );

      if ($enable_functions) {
        // If a user provides a query like "title:a b c", we interpret all
        // of the terms to be title terms: the "title:" function sticks
        // until we encounter another function.

        // If a user provides a query like "title:"a"" (with a quoted term),
        // the function is not sticky.

        if ($token['function'] !== null) {
          $function = $token['function'];
        } else {
          $function = $last_function;
        }

        $result['function'] = $function;

        // Note that the function remains sticky across quoted terms appearing
        // after the function term. For example, all of these terms are title
        // terms:
        //
        //   title:a "b c" d

        $is_sticky = (!$result['quoted'] || ($token['function'] === null));

        switch ($operator) {
          case self::OPERATOR_ABSENT:
          case self::OPERATOR_PRESENT:
            $is_sticky = false;
            break;
        }

        if ($is_sticky) {
          $last_function = $function;
        } else {
          $last_function = null;
        }
      }

      $results[] = $result;
    }

    if ($enable_functions) {
      // If any function is required to be "absent", there must be no other
      // terms which make assertions about it.

      $present_tokens = array();
      $absent_tokens = array();
      foreach ($results as $result) {
        $function = $result['function'];

        if ($result['operator'] === self::OPERATOR_ABSENT) {
          $absent_tokens[$function][] = $result;
        } else {
          $present_tokens[$function][] = $result;
        }
      }

      foreach ($absent_tokens as $function => $tokens) {
        $absent_token = head($tokens);

        if (empty($present_tokens[$function])) {
          continue;
        }

        $present_token = head($present_tokens[$function]);

        throw new PhutilSearchQueryCompilerSyntaxException(
          pht(
            'Query field must be absent ("%s") and present ("%s"). This '.
            'is impossible, so the query is not valid.',
            $absent_token['raw'],
            $present_token['raw']));
      }
    }

    return $results;
  }

  private function renderToken(
    PhutilSearchQueryToken $token,
    PhutilSearchStemmer $stemmer = null) {
    $value = $token->getValue();

    if ($stemmer) {
      $value = $stemmer->stemToken($value);
    }

    $value = $this->quoteToken($value);
    $operator = $token->getOperator();
    $prefix = $this->getOperatorPrefix($operator);

    $value = $prefix.$value;

    return $value;
  }

  private function getOperatorPrefix($operator) {
    $operators = $this->operators;

    switch ($operator) {
      case self::OPERATOR_AND:
        $prefix = $operators[0];
        break;
      case self::OPERATOR_NOT:
        $prefix = $operators[2];
        break;
      default:
        throw new PhutilSearchQueryCompilerSyntaxException(
          pht(
            'Unsupported operator prefix "%s".',
            $operator));
    }

    if ($prefix == ' ') {
      $prefix = null;
    }

    return $prefix;
  }

  private function quoteToken($value) {
    $operators = $this->operators;

    $open_quote = $this->operators[10];
    $close_quote = $this->operators[11];

    return $open_quote.$value.$close_quote;
  }

  private function getDisplayToken(array $token) {
    if (isset($token['function'])) {
      $function = $token['function'].':';
    } else {
      $function = '';
    }

    $operator_string = implode('', $token['operator']);

    $value = implode('', $token['value']);

    $is_quoted = $token['quoted'];
    if ($is_quoted) {
      $value = $this->quoteToken($value);
    }

    return sprintf('%s%s%s', $function, $operator_string, $value);
  }

}
