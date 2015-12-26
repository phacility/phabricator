<?php

final class PhabricatorProjectsEditField
  extends PhabricatorTokenizerEditField {

  protected function newDatasource() {
    return new PhabricatorProjectDatasource();
  }

  protected function newHTTPParameterType() {
    return new AphrontProjectListHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitProjectListParameterType();
  }

}
