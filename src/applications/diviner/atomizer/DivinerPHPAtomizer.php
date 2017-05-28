<?php

final class DivinerPHPAtomizer extends DivinerAtomizer {

  protected function newAtom($type) {
    return parent::newAtom($type)->setLanguage('php');
  }

  protected function executeAtomize($file_name, $file_data) {
    $future = PhutilXHPASTBinary::getParserFuture($file_data);
    $tree = XHPASTTree::newFromDataAndResolvedExecFuture(
      $file_data,
      $future->resolve());

    $atoms = array();
    $root = $tree->getRootNode();

    $func_decl = $root->selectDescendantsOfType('n_FUNCTION_DECLARATION');
    foreach ($func_decl as $func) {
      $name = $func->getChildByIndex(2);

      // Don't atomize closures
      if ($name->getTypeName() === 'n_EMPTY') {
        continue;
      }

      $atom = $this->newAtom(DivinerAtom::TYPE_FUNCTION)
        ->setName($name->getConcreteString())
        ->setLine($func->getLineNumber())
        ->setFile($file_name);

      $this->findAtomDocblock($atom, $func);
      $this->parseParams($atom, $func);
      $this->parseReturnType($atom, $func);

      $atoms[] = $atom;
    }

    $class_types = array(
      DivinerAtom::TYPE_CLASS => 'n_CLASS_DECLARATION',
      DivinerAtom::TYPE_INTERFACE => 'n_INTERFACE_DECLARATION',
    );
    foreach ($class_types as $atom_type => $node_type) {
      $class_decls = $root->selectDescendantsOfType($node_type);

      foreach ($class_decls as $class) {
        $name = $class->getChildByIndex(1, 'n_CLASS_NAME');

        $atom = $this->newAtom($atom_type)
          ->setName($name->getConcreteString())
          ->setFile($file_name)
          ->setLine($class->getLineNumber());

        // This parses `final` and `abstract`.
        $attributes = $class->getChildByIndex(0, 'n_CLASS_ATTRIBUTES');
        foreach ($attributes->selectDescendantsOfType('n_STRING') as $attr) {
          $atom->setProperty($attr->getConcreteString(), true);
        }

        // If this exists, it is `n_EXTENDS_LIST`.
        $extends = $class->getChildByIndex(2);
        $extends_class = $extends->selectDescendantsOfType('n_CLASS_NAME');
        foreach ($extends_class as $parent_class) {
          $atom->addExtends(
            $this->newRef(
              DivinerAtom::TYPE_CLASS,
              $parent_class->getConcreteString()));
        }

        // If this exists, it is `n_IMPLEMENTS_LIST`.
        $implements = $class->getChildByIndex(3);
        $iface_names = $implements->selectDescendantsOfType('n_CLASS_NAME');
        foreach ($iface_names as $iface_name) {
          $atom->addExtends(
            $this->newRef(
              DivinerAtom::TYPE_INTERFACE,
              $iface_name->getConcreteString()));
        }

        $this->findAtomDocblock($atom, $class);

        $methods = $class->selectDescendantsOfType('n_METHOD_DECLARATION');
        foreach ($methods as $method) {
          $matom = $this->newAtom(DivinerAtom::TYPE_METHOD);

          $this->findAtomDocblock($matom, $method);

          $attribute_list = $method->getChildByIndex(0);
          $attributes = $attribute_list->selectDescendantsOfType('n_STRING');
          if ($attributes) {
            foreach ($attributes as $attribute) {
              $attr = strtolower($attribute->getConcreteString());
              switch ($attr) {
                case 'final':
                case 'abstract':
                case 'static':
                  $matom->setProperty($attr, true);
                  break;
                case 'public':
                case 'protected':
                case 'private':
                  $matom->setProperty('access', $attr);
                  break;
              }
            }
          } else {
            $matom->setProperty('access', 'public');
          }

          $this->parseParams($matom, $method);

          $matom->setName($method->getChildByIndex(2)->getConcreteString());
          $matom->setLine($method->getLineNumber());
          $matom->setFile($file_name);

          $this->parseReturnType($matom, $method);
          $atom->addChild($matom);

          $atoms[] = $matom;
        }

        $atoms[] = $atom;
      }
    }

    return $atoms;
  }

  private function parseParams(DivinerAtom $atom, AASTNode $func) {
    $params = $func
      ->getChildOfType(3, 'n_DECLARATION_PARAMETER_LIST')
      ->selectDescendantsOfType('n_DECLARATION_PARAMETER');

    $param_spec = array();

    if ($atom->getDocblockRaw()) {
      $metadata = $atom->getDocblockMeta();
    } else {
      $metadata = array();
    }

    $docs = idx($metadata, 'param');
    if ($docs) {
      $docs = (array)$docs;
      $docs = array_filter($docs);
    } else {
      $docs = array();
    }

    if (count($docs)) {
      if (count($docs) < count($params)) {
        $atom->addWarning(
          pht(
            'This call takes %s parameter(s), but only %s are documented.',
            phutil_count($params),
            phutil_count($docs)));
      }
    }

    foreach ($params as $param) {
      $name = $param->getChildByIndex(1)->getConcreteString();
      $dict = array(
        'type'    => $param->getChildByIndex(0)->getConcreteString(),
        'default' => $param->getChildByIndex(2)->getConcreteString(),
      );

      if ($docs) {
        $doc = array_shift($docs);
        if ($doc) {
          $dict += $this->parseParamDoc($atom, $doc, $name);
        }
      }

      $param_spec[] = array(
        'name' => $name,
      ) + $dict;
    }

    if ($docs) {
      foreach ($docs as $doc) {
        if ($doc) {
          $param_spec[] = $this->parseParamDoc($atom, $doc, null);
        }
      }
    }

    // TODO: Find `assert_instances_of()` calls in the function body and
    // add their type information here. See T1089.

    $atom->setProperty('parameters', $param_spec);
  }

  private function findAtomDocblock(DivinerAtom $atom, XHPASTNode $node) {
    $token = $node->getDocblockToken();
    if ($token) {
      $atom->setDocblockRaw($token->getValue());
      return true;
    } else {
      $tokens = $node->getTokens();
      if ($tokens) {
        $prev = head($tokens);
        while ($prev = $prev->getPrevToken()) {
          if ($prev->isAnyWhitespace()) {
            continue;
          }
          break;
        }

        if ($prev && $prev->isComment()) {
          $value = $prev->getValue();
          $matches = null;
          if (preg_match('/@(return|param|task|author)/', $value, $matches)) {
            $atom->addWarning(
              pht(
                'Atom "%s" is preceded by a comment containing `%s`, but '.
                'the comment is not a documentation comment. Documentation '.
                'comments must begin with `%s`, followed by a newline. Did '.
                'you mean to use a documentation comment? (As the comment is '.
                'not a documentation comment, it will be ignored.)',
                $atom->getName(),
                '@'.$matches[1],
                '/**'));
          }
        }
      }

      $atom->setDocblockRaw('');
      return false;
    }
  }

  protected function parseParamDoc(DivinerAtom $atom, $doc, $name) {
    $dict = array();
    $split = preg_split('/\s+/', trim($doc), 2);
    if (!empty($split[0])) {
      $dict['doctype'] = $split[0];
    }

    if (!empty($split[1])) {
      $docs = $split[1];

      // If the parameter is documented like `@param int $num Blah blah ..`,
      // get rid of the `$num` part (which Diviner considers optional). If it
      // is present and different from the declared name, raise a warning.
      $matches = null;
      if (preg_match('/^(\\$\S+)\s+/', $docs, $matches)) {
        if ($name !== null) {
          if ($matches[1] !== $name) {
            $atom->addWarning(
              pht(
                'Parameter "%s" is named "%s" in the documentation. '.
                'The documentation may be out of date.',
                $name,
                $matches[1]));
          }
        }
        $docs = substr($docs, strlen($matches[0]));
      }

      $dict['docs'] = $docs;
    }

    return $dict;
  }

  private function parseReturnType(DivinerAtom $atom, XHPASTNode $decl) {
    $return_spec = array();

    $metadata = $atom->getDocblockMeta();
    $return = idx($metadata, 'return');

    $type = null;
    $docs = null;

    if (!$return) {
      $return = idx($metadata, 'returns');
      if ($return) {
        $atom->addWarning(
          pht(
            'Documentation uses `%s`, but should use `%s`.',
            '@returns',
            '@return'));
      }
    }

    $return = (array)$return;
    if (count($return) > 1) {
        $atom->addWarning(
          pht(
            'Documentation specifies `%s` multiple times.',
            '@return'));
    }
    $return = head($return);

    if ($atom->getName() == '__construct' && $atom->getType() == 'method') {
      $return_spec = array(
        'doctype' => 'this',
        'docs' => '//Implicit.//',
      );

      if ($return) {
        $atom->addWarning(
          pht(
            'Method `%s` has explicitly documented `%s`. The `%s` method '.
            'always returns `%s`. Diviner documents this implicitly.',
            '__construct()',
            '@return',
            '__construct()',
            '$this'));
      }
    } else if ($return) {
      $split = preg_split('/(?<!,)\s+/', trim($return), 2);
      if (!empty($split[0])) {
        $type = $split[0];
      }

      if ($decl->getChildByIndex(1)->getTypeName() == 'n_REFERENCE') {
        $type = $type.' &';
      }

      if (!empty($split[1])) {
        $docs = $split[1];
      }

      $return_spec = array(
        'doctype' => $type,
        'docs'    => $docs,
      );
    } else {
      $return_spec = array(
        'type' => 'wild',
      );
    }

    $atom->setProperty('return', $return_spec);
  }

}
