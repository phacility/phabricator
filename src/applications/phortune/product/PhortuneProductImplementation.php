<?php

abstract class PhortuneProductImplementation {

  abstract public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs);

  abstract public function getRef();
  abstract public function getName(PhortuneProduct $product);
  abstract public function getPriceAsCurrency(PhortuneProduct $product);

}
