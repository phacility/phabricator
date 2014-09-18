<?php

abstract class PhabricatorConfigSchemaSpec extends Phobject {

  abstract public function buildSchemata(PhabricatorConfigServerSchema $server);

}
