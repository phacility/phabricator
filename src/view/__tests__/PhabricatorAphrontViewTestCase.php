<?php

final class PhabricatorAphrontViewTestCase extends PhabricatorTestCase {

  public function testHasChildren() {
    $view = new AphrontNullView();
    $this->assertEqual(false, $view->hasChildren());

    $values = array(
      null,
      '',
      array(),
      array(null, ''),
    );

    foreach ($values as $value) {
      $view->appendChild($value);
      $this->assertEqual(false, $view->hasChildren());
    }

    $view->appendChild("!");
    $this->assertEqual(true, $view->hasChildren());
  }

}
