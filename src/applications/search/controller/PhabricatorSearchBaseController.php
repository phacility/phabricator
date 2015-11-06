<?php

abstract class PhabricatorSearchBaseController extends PhabricatorController {

  const ACTION_ATTACH       = 'attach';
  const ACTION_MERGE        = 'merge';
  const ACTION_DEPENDENCIES = 'dependencies';
  const ACTION_BLOCKS       = 'blocks';
  const ACTION_EDGE         = 'edge';

}
