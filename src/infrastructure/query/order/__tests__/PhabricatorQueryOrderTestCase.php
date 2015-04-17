<?php

final class PhabricatorQueryOrderTestCase extends PhabricatorTestCase {

  public function testQueryOrderItem() {
    $item = PhabricatorQueryOrderItem::newFromScalar('id');
    $this->assertEqual('id', $item->getOrderKey());
    $this->assertEqual(false, $item->getIsReversed());

    $item = PhabricatorQueryOrderItem::newFromScalar('-id');
    $this->assertEqual('id', $item->getOrderKey());
    $this->assertEqual(true, $item->getIsReversed());
  }

  public function testQueryOrderBadVectors() {
    $bad = array(
      array(),
      null,
      1,
      array(2),
      array('id', 'id'),
      array('id', '-id'),
    );

    foreach ($bad as $input) {
      $caught = null;
      try {
        PhabricatorQueryOrderVector::newFromVector($input);
      } catch (Exception $ex) {
        $caught = $ex;
      }

      $this->assertTrue(($caught instanceof Exception));
    }
  }

  public function testQueryOrderVector() {
    $vector = PhabricatorQueryOrderVector::newFromVector(
      array(
        'a',
        'b',
        '-c',
        'd',
      ));

    $this->assertEqual(
      array(
        'a' => 'a',
        'b' => 'b',
        'c' => 'c',
        'd' => 'd',
      ),
      mpull(iterator_to_array($vector), 'getOrderKey'));

    $this->assertEqual(
      array(
        'a' => false,
        'b' => false,
        'c' => true,
        'd' => false,
      ),
      mpull(iterator_to_array($vector), 'getIsReversed'));
  }

}
