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
    return phutil_tag(
      'div',
      array(
        'class' => 'atom-description',
      ),
      $engine->markupText($text));
  }

  protected function getAtomDescription(DivinerAtom $atom) {
    return $atom->getDocblockText();
  }

  public function renderAtomSummary(DivinerAtom $atom) {
    $text = $this->getAtomSummary($atom);
    $engine = $this->getInlineMarkupEngine();
    return phutil_tag(
      'span',
      array(
        'class' => 'atom-summary',
      ),
      $engine->markupText($text));
  }

  protected function getAtomSummary(DivinerAtom $atom) {
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
            $ref->getName(),
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
    return PhabricatorMarkupEngine::newMarkupEngine(
      array(
        'preserve-linebreaks' => false,
      ));
  }

  protected function getInlineMarkupEngine() {
    return $this->getBlockMarkupEngine();
  }


}
