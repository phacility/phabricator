<?php

final class DivinerRemarkupRuleSymbol extends PhutilRemarkupRule {

  const KEY_RULE_ATOM_REF = 'rule.diviner.atomref';

  public function apply($text) {
    // Grammar here is:
    //
    //         rule = '@{' maybe_type name maybe_title '}'
    //   maybe_type = null | type ':' | type '@' book ':'
    //         name = name | name '@' context
    //  maybe_title = null | '|' title
    //
    // So these are all valid:
    //
    //   @{name}
    //   @{type : name}
    //   @{name | title}
    //   @{type @ book : name @ context | title}

    return preg_replace_callback(
      '/(?:^|\B)@{'.
        '(?:(?P<type>[^:]+?):)?'.
        '(?P<name>[^}|]+?)'.
        '(?:[|](?P<title>[^}]+))?'.
      '}/',
      array($this, 'markupSymbol'),
      $text);
  }

  public function markupSymbol($matches) {
    $type = (string)idx($matches, 'type');
    $name = (string)$matches['name'];
    $title = (string)idx($matches, 'title');

    // Collapse sequences of whitespace into a single space.
    $type = preg_replace('/\s+/', ' ', trim($type));
    $name = preg_replace('/\s+/', ' ', trim($name));
    $title = preg_replace('/\s+/', ' ', trim($title));

    $ref = array();

    if (strpos($type, '@') !== false) {
      list($type, $book) = explode('@', $type, 2);
      $ref['type'] = trim($type);
      $ref['book'] = trim($book);
    } else {
      $ref['type'] = $type;
    }

    if (strpos($name, '@') !== false) {
      list($name, $context) = explode('@', $name, 2);
      $ref['name'] = trim($name);
      $ref['context'] = trim($context);
    } else {
      $ref['name'] = $name;
    }

    $ref['title'] = $title;

    foreach ($ref as $key => $value) {
      if ($value === '') {
        unset($ref[$key]);
      }
    }

    $engine = $this->getEngine();
    $token = $engine->storeText('');

    $key = self::KEY_RULE_ATOM_REF;
    $data = $engine->getTextMetadata($key, array());
    $data[$token] = $ref;
    $engine->setTextMetadata($key, $data);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $key = self::KEY_RULE_ATOM_REF;
    $data = $engine->getTextMetadata($key, array());

    $renderer = $engine->getConfig('diviner.renderer');

    foreach ($data as $token => $ref_dict) {
      $ref = DivinerAtomRef::newFromDictionary($ref_dict);
      $title = nonempty($ref->getTitle(), $ref->getName());

      $href = null;
      if ($renderer) {
        // Here, we're generating documentation. If possible, we want to find
        // the real atom ref so we can render the correct default title and
        // render invalid links in an alternate style.

        $ref = $renderer->normalizeAtomRef($ref);
        if ($ref) {
          $title = nonempty($ref->getTitle(), $ref->getName());
          $href = $renderer->getHrefForAtomRef($ref);
        }
      } else {
        // Here, we're generating comment text or something like that. Just
        // link to Diviner and let it sort things out.

        $href = id(new PhutilURI('/diviner/find/'))
          ->setQueryParams($ref_dict + array('jump' => true));
      }

      if ($this->getEngine()->isTextMode()) {
        if ($href) {
          $link = $title.' <'.PhabricatorEnv::getProductionURI($href).'>';
        } else {
          $link = $title;
        }
      } else if ($href) {
        $link = phutil_tag(
          'a',
          array(
            'class' => 'atom-ref',
            'href' => $href,
          ),
          $title);
      } else {
        $link = phutil_tag(
          'span',
          array(
            'class' => 'atom-ref-invalid',
          ),
          $title);
      }

      $engine->overwriteStoredText($token, $link);
    }
  }

}
