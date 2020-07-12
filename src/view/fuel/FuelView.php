<?php

abstract class FuelView
  extends AphrontView {

  final protected function canAppendChild() {
    return false;
  }

}
