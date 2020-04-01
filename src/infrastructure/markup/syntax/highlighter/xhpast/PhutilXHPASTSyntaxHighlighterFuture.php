<?php

final class PhutilXHPASTSyntaxHighlighterFuture extends FutureProxy {

  private $source;
  private $scrub;

  public function __construct(Future $proxied, $source, $scrub = false) {
    parent::__construct($proxied);
    $this->source = $source;
    $this->scrub = $scrub;
  }

  protected function didReceiveResult($result) {
    try {
      return $this->applyXHPHighlight($result);
    } catch (Exception $ex) {
      // XHP can't highlight source that isn't syntactically valid. Fall back
      // to the fragment lexer.
      $source = ($this->scrub
        ? preg_replace('/^.*\n/', '', $this->source)
        : $this->source);
      return id(new PhutilLexerSyntaxHighlighter())
        ->setConfig('lexer', new PhutilPHPFragmentLexer())
        ->setConfig('language', 'php')
        ->getHighlightFuture($source)
        ->resolve();
    }
  }

  private function applyXHPHighlight($result) {

    // We perform two passes here: one using the AST to find symbols we care
    // about -- particularly, class names and function names. These are used
    // in the crossreference stuff to link into Diffusion. After we've done our
    // AST pass, we do a followup pass on the token stream to catch all the
    // simple stuff like strings and comments.

    $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
      $this->source,
      $result);

    $root = $tree->getRootNode();

    $tokens = $root->getTokens();
    $interesting_symbols = $this->findInterestingSymbols($root);


    if ($this->scrub) {
      // If we're scrubbing, we prepended "<?php\n" to the text to force the
      // highlighter to treat it as PHP source. Now, we need to remove that.

      $ok = false;
      if (count($tokens) >= 2) {
        if ($tokens[0]->getTypeName() === 'T_OPEN_TAG') {
          if ($tokens[1]->getTypeName() === 'T_WHITESPACE') {
            $ok = true;
          }
        }
      }

      if (!$ok) {
        throw new Exception(
          pht(
            'Expected T_OPEN_TAG, T_WHITESPACE tokens at head of results '.
            'for highlighting parse of PHP snippet.'));
      }

      // Remove the "<?php".
      unset($tokens[0]);

      $value = $tokens[1]->getValue();
      if ((strlen($value) < 1) || ($value[0] != "\n")) {
        throw new Exception(
          pht(
            'Expected "\\n" at beginning of T_WHITESPACE token at head of '.
            'tokens for highlighting parse of PHP snippet.'));
      }

      $value = substr($value, 1);
      $tokens[1]->overwriteValue($value);
    }

    $out = array();
    foreach ($tokens as $key => $token) {
      $value = $token->getValue();
      $class = null;
      $multi = false;
      $attrs = array();
      if (isset($interesting_symbols[$key])) {
        $sym = $interesting_symbols[$key];
        $class = $sym[0];
        $attrs['data-symbol-context'] = idx($sym, 'context');
        $attrs['data-symbol-name'] = idx($sym, 'symbol');
      } else {
        switch ($token->getTypeName()) {
          case 'T_WHITESPACE':
            break;
          case 'T_DOC_COMMENT':
            $class = 'dc';
            $multi = true;
            break;
          case 'T_COMMENT':
            $class = 'c';
            $multi = true;
            break;
          case 'T_CONSTANT_ENCAPSED_STRING':
          case 'T_ENCAPSED_AND_WHITESPACE':
          case 'T_INLINE_HTML':
            $class = 's';
            $multi = true;
            break;
          case 'T_VARIABLE':
            $class = 'nv';
            break;
          case 'T_OPEN_TAG':
          case 'T_OPEN_TAG_WITH_ECHO':
          case 'T_CLOSE_TAG':
            $class = 'o';
            break;
          case 'T_LNUMBER':
          case 'T_DNUMBER':
            $class = 'm';
            break;
          case 'T_STRING':
            static $magic = array(
              'true' => true,
              'false' => true,
              'null' => true,
            );
            if (isset($magic[strtolower($value)])) {
              $class = 'k';
              break;
            }
            $class = 'nx';
            break;
          default:
            $class = 'k';
            break;
        }
      }

      if ($class) {
        $attrs['class'] = $class;
        if ($multi) {
          // If the token may have multiple lines in it, make sure each
          // <span> crosses no more than one line so the lines can be put
          // in a table, etc., later.
          $value = phutil_split_lines($value, $retain_endings = true);
        } else {
          $value = array($value);
        }
        foreach ($value as $val) {
          $out[] = phutil_tag('span', $attrs, $val);
        }
      } else {
        $out[] = $value;
      }
    }

    return phutil_implode_html('', $out);
  }

  private function findInterestingSymbols(XHPASTNode $root) {
    // Class name symbols appear in:
    //    class X extends X implements X, X { ... }
    //    new X();
    //    $x instanceof X
    //    catch (X $x)
    //    function f(X $x)
    //    X::f();
    //    X::$m;
    //    X::CONST;

    // These are PHP builtin tokens which can appear in a classname context.
    // Don't link them since they don't go anywhere useful.
    static $builtin_class_tokens = array(
      'self'    => true,
      'parent'  => true,
      'static'  => true,
    );

    // Fortunately XHPAST puts all of these in a special node type so it's
    // easy to find them.
    $result_map = array();
    $class_names = $root->selectDescendantsOfType('n_CLASS_NAME');
    foreach ($class_names as $class_name) {
      foreach ($class_name->getTokens() as $key => $token) {
        if (isset($builtin_class_tokens[$token->getValue()])) {
          // This is something like "self::method()".
          continue;
        }
        $result_map[$key] = array(
          'nc', // "Name, Class"
          'symbol' => $class_name->getConcreteString(),
        );
      }
    }

    // Function name symbols appear in:
    //    f()

    $function_calls = $root->selectDescendantsOfType('n_FUNCTION_CALL');
    foreach ($function_calls as $call) {
      $call = $call->getChildByIndex(0);
      if ($call->getTypeName() == 'n_SYMBOL_NAME') {
        // This is a normal function call, not some $f() shenanigans.
        foreach ($call->getTokens() as $key => $token) {
          $result_map[$key] = array(
            'nf', // "Name, Function"
            'symbol' => $call->getConcreteString(),
          );
        }
      }
    }

    // Upon encountering $x->y, link y without context, since $x is unknown.

    $prop_access = $root->selectDescendantsOfType('n_OBJECT_PROPERTY_ACCESS');
    foreach ($prop_access as $access) {
      $right = $access->getChildByIndex(1);
      if ($right->getTypeName() == 'n_INDEX_ACCESS') {
        // otherwise $x->y[0] doesn't get highlighted
        $right = $right->getChildByIndex(0);
      }
      if ($right->getTypeName() == 'n_STRING') {
        foreach ($right->getTokens() as $key => $token) {
          $result_map[$key] = array(
            'na', // "Name, Attribute"
            'symbol' => $right->getConcreteString(),
          );
        }
      }
    }

    // Upon encountering x::y, try to link y with context x.

    $static_access = $root->selectDescendantsOfType('n_CLASS_STATIC_ACCESS');
    foreach ($static_access as $access) {
      $class = $access->getChildByIndex(0);
      $right = $access->getChildByIndex(1);
      if ($class->getTypeName() == 'n_CLASS_NAME' &&
          ($right->getTypeName() == 'n_STRING' ||
           $right->getTypeName() == 'n_VARIABLE')) {
        $classname = head($class->getTokens())->getValue();
        $result = array(
          'na',
          'symbol' => ltrim($right->getConcreteString(), '$'),
        );
        if (!isset($builtin_class_tokens[$classname])) {
          $result['context'] = $classname;
        }
        foreach ($right->getTokens() as $key => $token) {
          $result_map[$key] = $result;
        }
      }
    }

    return $result_map;
  }

}
