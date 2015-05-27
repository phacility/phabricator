<?php

final class PHUITypeaheadExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Typeaheads');
  }

  public function getDescription() {
    return pht('Typeaheads, tokenizers and tokens.');
  }

  public function renderExample() {

    $token_list = array();

    $token_list[] = id(new PhabricatorTypeaheadTokenView())
      ->setValue(pht('Normal Object'))
      ->setIcon('fa-user');

    $token_list[] = id(new PhabricatorTypeaheadTokenView())
      ->setValue(pht('Disabled Object'))
      ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_DISABLED)
      ->setIcon('fa-user');

    $token_list[] = id(new PhabricatorTypeaheadTokenView())
      ->setValue(pht('Object with Color'))
      ->setIcon('fa-tag')
      ->setColor('green');

    $token_list[] = id(new PhabricatorTypeaheadTokenView())
      ->setValue(pht('Function Token'))
      ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_FUNCTION)
      ->setIcon('fa-users');

    $token_list[] = id(new PhabricatorTypeaheadTokenView())
      ->setValue(pht('Invalid Token'))
      ->setTokenType(PhabricatorTypeaheadTokenView::TYPE_INVALID)
      ->setIcon('fa-exclamation-circle');


    $token_list = phutil_tag(
      'div',
      array(
        'class' => 'grouped',
        'style' => 'padding: 8px',
      ),
      $token_list);

    $output = array();

    $output[] = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Tokens'))
      ->appendChild($token_list);

    return $output;
  }
}
