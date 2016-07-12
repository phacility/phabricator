<?php

final class PhabricatorListFilterUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('ListFilter');
  }

  public function getDescription() {
    return pht(
      'Use %s to layout controls for filtering '.
      'and manipulating lists of objects.',
      phutil_tag('tt', array(), 'AphrontListFilterView'));
  }

  public function renderExample() {

    $filter = new AphrontListFilterView();

    $form = new AphrontFormView();
    $form->setUser($this->getRequest()->getUser());
    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Query')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Search')));

    $filter->appendChild($form);


    return $filter;
  }
}
