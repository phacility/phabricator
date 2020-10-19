<?php

final class PhabricatorFileHasObjectEdgeType extends PhabricatorEdgeType {

  const EDGECONST = 26;

  public function getInverseEdgeConstant() {
    return PhabricatorObjectHasFileEdgeType::EDGECONST;
  }

  public function shouldWriteInverseTransactions() {
    return true;
  }

  public function getConduitKey() {
    return 'file.attached-objects';
  }

  public function getConduitName() {
    return pht('File Has Object');
  }

  public function getConduitDescription() {
    return pht('The source file is attached to the destination object.');
  }

}
