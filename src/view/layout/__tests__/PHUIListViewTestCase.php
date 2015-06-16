<?php

final class PHUIListViewTestCase extends PhabricatorTestCase {

  public function testAppend() {
    $menu = $this->newABCMenu();

    $this->assertMenuKeys(
      array(
        'a',
        'b',
        'c',
      ),
      $menu);
  }

  public function testAppendAfter() {
    $menu = $this->newABCMenu();

    $caught = null;
    try {
      $menu->addMenuItemAfter('x', $this->newLink('test1'));
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);

    $menu->addMenuItemAfter('a', $this->newLink('test2'));
    $menu->addMenuItemAfter(null, $this->newLink('test3'));
    $menu->addMenuItemAfter('a', $this->newLink('test4'));
    $menu->addMenuItemAfter('test3', $this->newLink('test5'));

    $this->assertMenuKeys(
      array(
        'a',
        'test4',
        'test2',
        'b',
        'c',
        'test3',
        'test5',
      ),
      $menu);
  }

  public function testAppendBefore() {
    $menu = $this->newABCMenu();

    $caught = null;
    try {
      $menu->addMenuItemBefore('x', $this->newLink('test1'));
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);

    $menu->addMenuItemBefore('b', $this->newLink('test2'));
    $menu->addMenuItemBefore(null, $this->newLink('test3'));
    $menu->addMenuItemBefore('a', $this->newLink('test4'));
    $menu->addMenuItemBefore('test3', $this->newLink('test5'));

    $this->assertMenuKeys(
      array(
        'test5',
        'test3',
        'test4',
        'a',
        'test2',
        'b',
        'c',
      ),
      $menu);
  }

  public function testAppendLabel() {
    $menu = new PHUIListView();
    $menu->addMenuItem($this->newLabel('fruit'));
    $menu->addMenuItem($this->newLabel('animals'));

    $caught = null;
    try {
      $menu->addMenuItemToLabel('x', $this->newLink('test1'));
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertTrue($caught instanceof Exception);

    $menu->addMenuItemToLabel('fruit', $this->newLink('apple'));
    $menu->addMenuItemToLabel('fruit', $this->newLink('banana'));

    $menu->addMenuItemToLabel('animals', $this->newLink('dog'));
    $menu->addMenuItemToLabel('animals', $this->newLink('cat'));

    $menu->addMenuItemToLabel('fruit', $this->newLink('cherry'));

    $this->assertMenuKeys(
      array(
        'fruit',
          'apple',
          'banana',
          'cherry',
        'animals',
          'dog',
          'cat',
      ),
      $menu);
  }

  private function newLink($key) {
    return id(new PHUIListItemView())
      ->setKey($key)
      ->setHref('#')
      ->setName(pht('Link'));
  }

  private function newLabel($key) {
    return id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setKey($key)
      ->setName(pht('Label'));
  }

  private function newABCMenu() {
    $menu = new PHUIListView();

    $menu->addMenuItem($this->newLink('a'));
    $menu->addMenuItem($this->newLink('b'));
    $menu->addMenuItem($this->newLink('c'));

    return $menu;
  }

  private function assertMenuKeys(array $expect, PHUIListView $menu) {
    $items = $menu->getItems();
    $keys = mpull($items, 'getKey');
    $keys = array_values($keys);

    $this->assertEqual($expect, $keys);
  }

}
