<?php

final class PhabricatorAphrontViewTestCase extends PhabricatorTestCase {

  public function testHasChildren() {
    $view = new AphrontNullView();
    $this->assertFalse($view->hasChildren());

    $values = array(
      null,
      '',
      array(),
      array(null, ''),
    );

    foreach ($values as $value) {
      $view->appendChild($value);
      $this->assertFalse($view->hasChildren());
    }

    $view->appendChild('!');
    $this->assertTrue($view->hasChildren());
  }

}
