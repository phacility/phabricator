<?php

final class PhabricatorStandardCustomFieldUsers
  extends PhabricatorStandardCustomFieldTokenizer {

  public function getFieldType() {
    return 'users';
  }

  public function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

  protected function getHTTPParameterType() {
    return new AphrontUserListHTTPParameterType();
  }

}
