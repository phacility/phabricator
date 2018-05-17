<?php

final class PhabricatorPointsEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new AphrontFormTextControl();
  }

  protected function newConduitParameterType() {
    return new ConduitPointsParameterType();
  }

  protected function newCommentAction() {
    return id(new PhabricatorEditEnginePointsCommentAction());
  }

  protected function newBulkParameterType() {
    return new BulkPointsParameterType();
  }

}
