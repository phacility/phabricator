<?php

final class PHUIInvisibleCharacterTestCase extends PhabricatorTestCase {

  public function testEmptyString() {
    $view = new PHUIInvisibleCharacterView('');
    $res = $view->render();
    $this->assertEqual($res, array());
  }

  public function testEmptyPlainText() {
    $view = (new PHUIInvisibleCharacterView(''))
      ->setPlainText(true);
    $res = $view->render();
    $this->assertEqual($res, '');
  }

  public function testWithNamedChars() {
    $test_input = "\x00\n\t ";
    $view = (new PHUIInvisibleCharacterView($test_input))
      ->setPlainText(true);
    $res = $view->render();
    $this->assertEqual($res, '<NULL><NEWLINE><TAB><SPACE>');
  }

  public function testWithHexChars() {
    $test_input = "abc\x01";
    $view = (new PHUIInvisibleCharacterView($test_input))
      ->setPlainText(true);
    $res = $view->render();
    $this->assertEqual($res, 'abc<0x01>');
  }

  public function testWithNamedAsHex() {
    $test_input = "\x00\x0a\x09\x20";
    $view = (new PHUIInvisibleCharacterView($test_input))
      ->setPlainText(true);
    $res = $view->render();
    $this->assertEqual($res, '<NULL><NEWLINE><TAB><SPACE>');
  }

  public function testHtmlDecoration() {
    $test_input = "a\x00\n\t ";
    $view = new PHUIInvisibleCharacterView($test_input);
    $res = $view->render();
    $this->assertFalse($res[0] instanceof PhutilSafeHTML);
    $this->assertTrue($res[1] instanceof PhutilSafeHTML);
    $this->assertTrue($res[2] instanceof PhutilSafeHTML);
    $this->assertTrue($res[3] instanceof PhutilSafeHTML);
    $this->assertTrue($res[4] instanceof PhutilSafeHTML);
  }
}
