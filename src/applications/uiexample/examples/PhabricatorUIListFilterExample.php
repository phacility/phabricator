<?php

final class PhabricatorUIListFilterExample extends PhabricatorUIExample {

  public function getName() {
    return 'ListFilter';
  }

  public function getDescription() {
    return hsprintf(
      'Use <tt>AphrontListFilterView</tt> to layout controls for filtering '.
      'and manipulating lists of objects.');
  }

  public function renderExample() {

    $filter = new AphrontListFilterView();

    $form = new AphrontFormView();
    $form->setUser($this->getRequest()->getUser());
    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Query'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Search'));

    $filter->appendChild($form);


    return $filter;
  }
}
