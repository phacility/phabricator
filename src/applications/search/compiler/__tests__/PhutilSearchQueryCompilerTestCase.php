<?php

final class PhutilSearchQueryCompilerTestCase
  extends PhutilTestCase {

  public function testCompileQueries() {
    $tests = array(
      '' => null,
      'cat dog' => '+"cat" +"dog"',
      'cat -dog' => '+"cat" -"dog"',
      'cat-dog' => '+"cat-dog"',

      // Double quotes serve as delimiters even if there is no whitespace
      // between terms.
      '"cat"dog' => '+"cat" +"dog"',

      // This query is too long.
      str_repeat('x', 2048) => false,

      // Multiple operators are not permitted.
      '++cat' => false,
      '+-cat' => false,
      '--cat' => false,

      // Stray operators are not permitted.
      '+' => false,
      'cat +' => false,

      // Double quotes must be paired.
      '"' => false,
      'cat "' => false,
      '"cat' => false,
      'A"' => false,
      'A"B"' => '+"A" +"B"',

      // Trailing whitespace should be discarded.
      'a b ' => '+"a" +"b"',

      // Tokens must have search text.
      '""' => false,
      '-' => false,

      // Previously, we permitted spaces to appear inside or after operators.

      // Now that "title:-" is now a valid construction meaning "title is
      // absent", this had to be tightened. We want "title:- duck" to mean
      // "title is absent, and any other field matches 'duck'".
      'cat - dog' => false,
    );

    $this->assertCompileQueries($tests);

    // Test that we compile queries correctly if the operators have been
    // swapped to use "AND" by default.
    $operator_tests = array(
      'cat dog' => '"cat" "dog"',
      'cat -dog' => '"cat" -"dog"',
    );
    $this->assertCompileQueries($operator_tests, ' |-><()~*:""&\'');


    // Test that we compile queries correctly if the quote operators have
    // been swapped to differ.
    $quote_tests = array(
      'cat dog' => '+[cat] +[dog]',
      'cat -dog' => '+[cat] -[dog]',
    );
    $this->assertCompileQueries($quote_tests, '+ -><()~*:[]&|');
  }

  public function testCompileQueriesWithStemming() {
    $stemming_tests = array(
      'cat dog' => array(
        null,
        '+"cat" +"dog"',
      ),
      'cats dogs' => array(
        null,
        '+"cat" +"dog"',
      ),
      'cats "dogs"' => array(
        '+"dogs"',
        '+"cat"',
      ),
      '"blessed blade" of the windseeker' => array(
        '+"blessed blade"',
        '+"of" +"the" +"windseek"',
      ),
      'mailing users for mentions on tasks' => array(
        null,
        '+"mail" +"user" +"for" +"mention" +"on" +"task"',
      ),
    );

    $stemmer = new PhutilSearchStemmer();
    $this->assertCompileQueries($stemming_tests, null, $stemmer);
  }

  public function testCompileQueriesWithFunctions() {
    $op_and = PhutilSearchQueryCompiler::OPERATOR_AND;
    $op_sub = PhutilSearchQueryCompiler::OPERATOR_SUBSTRING;
    $op_exact = PhutilSearchQueryCompiler::OPERATOR_EXACT;
    $op_present = PhutilSearchQueryCompiler::OPERATOR_PRESENT;
    $op_absent = PhutilSearchQueryCompiler::OPERATOR_ABSENT;

    $mao = "\xE7\x8C\xAB";

    $function_tests = array(
      'cat' => array(
        array(null, $op_and, 'cat'),
      ),
      ':cat' => array(
        array(null, $op_and, 'cat'),
      ),
      'title:cat' => array(
        array('title', $op_and, 'cat'),
      ),
      'title:cat:dog' => array(
        array('title', $op_and, 'cat:dog'),
      ),
      'title:~cat' => array(
        array('title', $op_sub, 'cat'),
      ),
      'cat title:="Meow Meow"' => array(
        array(null, $op_and, 'cat'),
        array('title', $op_exact, 'Meow Meow'),
      ),
      'title:cat title:dog' => array(
        array('title', $op_and, 'cat'),
        array('title', $op_and, 'dog'),
      ),
      '~"core and seven years ag"' => array(
        array(null, $op_sub, 'core and seven years ag'),
      ),
      $mao => array(
        array(null, $op_sub, $mao),
      ),
      '+'.$mao => array(
        array(null, $op_and, $mao),
      ),
      '~'.$mao => array(
        array(null, $op_sub, $mao),
      ),
      '"'.$mao.'"' => array(
        array(null, $op_and, $mao),
      ),
      'title:' => false,
      'title:+' => false,
      'title:+""' => false,
      'title:""' => false,

      'title:~' => array(
        array('title', $op_present, null),
      ),

      'title:-' => array(
        array('title', $op_absent, null),
      ),

      '~' => false,
      '-' => false,

      // Functions like "title:" apply to following terms if their term is
      // not specified with double quotes.
      'title:x y' => array(
        array('title', $op_and, 'x'),
        array('title', $op_and, 'y'),
      ),
      'title: x y' => array(
        array('title', $op_and, 'x'),
        array('title', $op_and, 'y'),
      ),
      'title:"x" y' => array(
        array('title', $op_and, 'x'),
        array(null, $op_and, 'y'),
      ),

      // The "present" and "absent" functions are not sticky.
      'title:~ x' => array(
        array('title', $op_present, null),
        array(null, $op_and, 'x'),
      ),
      'title:- x' => array(
        array('title', $op_absent, null),
        array(null, $op_and, 'x'),
      ),

      // Functions like "title:" continue to stick across quotes if the
      // quotes aren't the initial argument.
      'title:a "b c" d' => array(
        array('title', $op_and, 'a'),
        array('title', $op_and, 'b c'),
        array('title', $op_and, 'd'),
      ),

      // These queries require a field be both present and absent, which is
      // impossible.
      'title:- title:x' => false,
      'title:- title:~' => false,

      'abcdefghijklmnopqrstuvwxyz-ABCDEFGHIJKLMNOPQRSTUVWXYZ:xyz' => array(
        array(
          'abcdefghijklmnopqrstuvwxyz-ABCDEFGHIJKLMNOPQRSTUVWXYZ',
          $op_and,
          'xyz',
        ),
      ),

      // See T12995. Interpret CJK tokens as substring queries since these
      // languages do not use spaces as word separators.
      "\xE7\x8C\xAB" => array(
        array(null, $op_sub, "\xE7\x8C\xAB"),
      ),

      // See T13632. Interpret tokens that begin with "_" as substring tokens
      // if no function is specified.
      '_x _y_ "_z_"' => array(
        array(null, $op_sub, '_x'),
        array(null, $op_sub, '_y_'),
        array(null, $op_and, '_z_'),
      ),
    );

    $this->assertCompileFunctionQueries($function_tests);
  }

  private function assertCompileQueries(
    array $tests,
    $operators = null,
    PhutilSearchStemmer $stemmer = null) {
    foreach ($tests as $input => $expect) {
      $caught = null;

      $query = null;
      $literal_query = null;
      $stemmed_query = null;

      try {
        $compiler = new PhutilSearchQueryCompiler();

        if ($operators !== null) {
          $compiler->setOperators($operators);
        }

        if ($stemmer !== null) {
          $compiler->setStemmer($stemmer);
        }

        $tokens = $compiler->newTokens($input);

        if ($stemmer) {
          $literal_query = $compiler->compileLiteralQuery($tokens);
          $stemmed_query = $compiler->compileStemmedQuery($tokens);
        } else {
          $query = $compiler->compileQuery($tokens);
        }
      } catch (PhutilSearchQueryCompilerSyntaxException $ex) {
        $caught = $ex;
      }

      if ($caught !== null) {
        $query = false;
        $literal_query = false;
        $stemmed_query = false;
      }

      if (!$stemmer) {
        $this->assertEqual(
          $expect,
          $query,
          pht('Compilation of query: %s', $input));
      } else {
        $this->assertEqual(
          $expect,
          ($literal_query === false)
            ? false
            : array($literal_query, $stemmed_query),
          pht('Stemmed compilation of query: %s', $input));
      }
    }
  }

  private function assertCompileFunctionQueries(array $tests) {
    foreach ($tests as $input => $expect) {
      $compiler = id(new PhutilSearchQueryCompiler())
        ->setEnableFunctions(true);

      try {
        $tokens = $compiler->newTokens($input);

        $result = array();
        foreach ($tokens as $token) {
          $result[] = array(
            $token->getFunction(),
            $token->getOperator(),
            $token->getValue(),
          );
        }
      } catch (PhutilSearchQueryCompilerSyntaxException $ex) {
        $result = false;
      }

      $this->assertEqual(
        $expect,
        $result,
        pht('Function compilation of query: %s', $input));
    }
  }

}
