<?php

final class PhabricatorSubscribersEditField
  extends PhabricatorTokenizerEditField {

  protected function newDatasource() {
    return new PhabricatorMetaMTAMailableDatasource();
  }

  protected function newHTTPParameterType() {
    // TODO: Implement a more expansive "Mailable" parameter type which
    // accepts users or projects.
    return new AphrontUserListHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitUserListParameterType();
  }

}
