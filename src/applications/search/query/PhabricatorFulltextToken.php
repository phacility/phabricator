<?php

final class PhabricatorFulltextToken extends Phobject {

  private $token;
  private $isShort;
  private $isStopword;

  public function setToken(PhutilSearchQueryToken $token) {
    $this->token = $token;
    return $this;
  }

  public function getToken() {
    return $this->token;
  }

  public function isQueryable() {
    return !$this->getIsShort() && !$this->getIsStopword();
  }

  public function setIsShort($is_short) {
    $this->isShort = $is_short;
    return $this;
  }

  public function getIsShort() {
    return $this->isShort;
  }

  public function setIsStopword($is_stopword) {
    $this->isStopword = $is_stopword;
    return $this;
  }

  public function getIsStopword() {
    return $this->isStopword;
  }

  public function newTag() {
    $token = $this->getToken();

    $tip = null;
    $icon = null;

    $name = $token->getValue();
    $function = $token->getFunction();
    if ($function !== null) {
      $name = pht('%s: %s', $function, $name);
    }

    if ($this->getIsShort()) {
      $shade = PHUITagView::COLOR_GREY;
      $tip = pht('Ignored Short Word');
    } else if ($this->getIsStopword()) {
      $shade = PHUITagView::COLOR_GREY;
      $tip = pht('Ignored Common Word');
    } else {
      $operator = $token->getOperator();
      switch ($operator) {
        case PhutilSearchQueryCompiler::OPERATOR_NOT:
          $shade = PHUITagView::COLOR_RED;
          $icon = 'fa-minus';
          break;
        case PhutilSearchQueryCompiler::OPERATOR_SUBSTRING:
          $tip = pht('Substring Search');
          $shade = PHUITagView::COLOR_VIOLET;
          break;
        case PhutilSearchQueryCompiler::OPERATOR_EXACT:
          $tip = pht('Exact Search');
          $shade = PHUITagView::COLOR_GREEN;
          break;
        case PhutilSearchQueryCompiler::OPERATOR_PRESENT:
          $name = pht('Field Present: %s', $function);
          $shade = PHUITagView::COLOR_GREEN;
          break;
        case PhutilSearchQueryCompiler::OPERATOR_ABSENT:
          $name = pht('Field Absent: %s', $function);
          $shade = PHUITagView::COLOR_RED;
          break;
        default:
          $shade = PHUITagView::COLOR_BLUE;
          break;
      }
    }

    $tag = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_SHADE)
      ->setColor($shade)
      ->setName($name);

    if ($tip !== null) {
      Javelin::initBehavior('phabricator-tooltips');

      $tag
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $tip,
          ));
    }

    if ($icon !== null) {
      $tag->setIcon($icon);
    }

    return $tag;
  }

}
