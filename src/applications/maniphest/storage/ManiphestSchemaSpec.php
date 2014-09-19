<?php

final class ManiphestSchemaSpec
  extends PhabricatorConfigSchemaSpec {

  public function buildSchemata() {
    $this->buildLiskSchemata('ManiphestDAO');

    $this->buildEdgeSchemata(new ManiphestTask());
    $this->buildTransactionSchema(
      new ManiphestTransaction(),
      new ManiphestTransactionComment());

    $this->buildCustomFieldSchemata(
      new ManiphestCustomFieldStorage(),
      array(
        new ManiphestCustomFieldNumericIndex(),
        new ManiphestCustomFieldStringIndex(),
      ));
  }

}
