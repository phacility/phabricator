<?php

final class DivinerDefaultRenderer extends DivinerRenderer {

  public function renderAtom(DivinerAtom $atom) {
    $out = array(
      $this->renderAtomTitle($atom),
      $this->renderAtomProperties($atom),
      $this->renderAtomDescription($atom),
    );

    return phutil_tag(
      'div',
      array(
        'class' => 'diviner-atom',
      ),
      $out);
  }

  protected function renderAtomTitle(DivinerAtom $atom) {
    $name = $this->renderAtomName($atom);
    $type = $this->renderAtomType($atom);

    return phutil_tag(
      'h1',
      array(
        'class' => 'atom-title',
      ),
      array($name, ' ', $type));
  }

  protected function renderAtomName(DivinerAtom $atom) {
    return phutil_tag(
      'div',
      array(
        'class' => 'atom-name',
      ),
      $this->getAtomName($atom));
  }

  protected function getAtomName(DivinerAtom $atom) {
    if ($atom->getDocblockMetaValue('title')) {
      return $atom->getDocblockMetaValue('title');
    }

    return $atom->getName();
  }

  protected function renderAtomType(DivinerAtom $atom) {
    return phutil_tag(
      'div',
      array(
        'class' => 'atom-name',
      ),
      $this->getAtomType($atom));
  }

  protected function getAtomType(DivinerAtom $atom) {
    return ucwords($atom->getType());
  }

  protected function renderAtomProperties(DivinerAtom $atom) {
    $props = $this->getAtomProperties($atom);

    $out = array();
    foreach ($props as $prop) {
      list($key, $value) = $prop;

      $out[] = phutil_tag('dt', array(), $key);
      $out[] = phutil_tag('dd', array(), $value);
    }

    return phutil_tag(
      'dl',
      array(
        'class' => 'atom-properties',
      ),
      $out);
  }

  protected function getAtomProperties(DivinerAtom $atom) {
    $properties = array();
    $properties[] = array(
      pht('Defined'),
      $atom->getFile().':'.$atom->getLine(),
    );

    return $properties;
  }

  protected function renderAtomDescription(DivinerAtom $atom) {
    $text = $this->getAtomDescription($atom);
    $engine = $this->getBlockMarkupEngine();

    $this->pushAtomStack($atom);
      $description = $engine->markupText($text);
    $this->popAtomStack();

    return phutil_tag(
      'div',
      array(
        'class' => 'atom-description',
      ),
      $description);
  }

  protected function getAtomDescription(DivinerAtom $atom) {
    return $atom->getDocblockText();
  }

  public function renderAtomSummary(DivinerAtom $atom) {
    $text = $this->getAtomSummary($atom);
    $engine = $this->getInlineMarkupEngine();

    $this->pushAtomStack($atom);
      $summary = $engine->markupText($text);
    $this->popAtomStack();

    return phutil_tag(
      'span',
      array(
        'class' => 'atom-summary',
      ),
      $summary);
  }

  public function getAtomSummary(DivinerAtom $atom) {
    if ($atom->getDocblockMetaValue('summary')) {
      return $atom->getDocblockMetaValue('summary');
    }

    $text = $this->getAtomDescription($atom);
    return PhabricatorMarkupEngine::summarize($text);
  }

  public function renderAtomIndex(array $refs) {
    $refs = msort($refs, 'getSortKey');

    $groups = mgroup($refs, 'getGroup');

    $out = array();
    foreach ($groups as $group_key => $refs) {
      $out[] = phutil_tag(
        'h1',
        array(
          'class' => 'atom-group-name',
        ),
        $this->getGroupName($group_key));

      $items = array();
      foreach ($refs as $ref) {
        $items[] = phutil_tag(
          'li',
          array(
            'class' => 'atom-index-item',
          ),
          array(
            $this->renderAtomRefLink($ref),
            ' - ',
            $ref->getSummary(),
          ));
      }

      $out[] = phutil_tag(
        'ul',
        array(
          'class' => 'atom-index-list',
        ),
        $items);
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'atom-index',
      ),
      $out);
  }

  protected function getGroupName($group_key) {
    return $group_key;
  }

  protected function getBlockMarkupEngine() {
    $engine = PhabricatorMarkupEngine::newMarkupEngine(array());

    $engine->setConfig('preserve-linebreaks', false);
    $engine->setConfig('viewer', new PhabricatorUser());
    $engine->setConfig('diviner.renderer', $this);
    $engine->setConfig('header.generate-toc', true);

    return $engine;
  }

  protected function getInlineMarkupEngine() {
    return $this->getBlockMarkupEngine();
  }

  public function normalizeAtomRef(DivinerAtomRef $ref) {
    if (!strlen($ref->getBook())) {
      $ref->setBook($this->getConfig('name'));
    }

    if ($ref->getBook() != $this->getConfig('name')) {
      // If the ref is from a different book, we can't normalize it.
      // Just return it as-is if it has enough information to resolve.
      if ($ref->getName() && $ref->getType()) {
        return $ref;
      } else {
        return null;
      }
    }

    $atom = $this->getPublisher()->findAtomByRef($ref);
    if ($atom) {
      return $atom->getRef();
    }

    return null;
  }

  protected function getAtomHrefDepth(DivinerAtom $atom) {
    if ($atom->getContext()) {
      return 4;
    } else {
      return 3;
    }
  }

  public function getHrefForAtomRef(DivinerAtomRef $ref) {
    $depth = 1;

    $atom = $this->peekAtomStack();
    if ($atom) {
      $depth = $this->getAtomHrefDepth($atom);
    }

    $href = str_repeat('../', $depth);

    $book = $ref->getBook();
    $type = $ref->getType();
    $name = $ref->getName();
    $context = $ref->getContext();

    $href .= $book.'/'.$type.'/';
    if ($context !== null) {
      $href .= $context.'/';
    }
    $href .= $name.'/index.html';

    return $href;
  }

  protected function renderAtomRefLink(DivinerAtomRef $ref) {
    return phutil_tag(
      'a',
      array(
        'href' => $this->getHrefForAtomRef($ref),
      ),
      $ref->getTitle());
  }

}
