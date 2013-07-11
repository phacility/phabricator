#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage mail');
$args->setSynopsis(<<<EOSYNOPSIS
**mail** __command__ [__options__]
    Manage Phabricator mail stuff.

EOSYNOPSIS
  );
$args->parseStandardArguments();

$workflows = array(
  new PhutilHelpArgumentWorkflow(),
  new PhabricatorMailManagementResendWorkflow(),
  new PhabricatorMailManagementShowOutboundWorkflow(),
  new PhabricatorMailManagementShowInboundWorkflow(),
  new PhabricatorMailManagementSendTestWorkflow(),
  new PhabricatorMailManagementReceiveTestWorkflow(),
  new PhabricatorMailManagementListInboundWorkflow(),
  new PhabricatorMailManagementListOutboundWorkflow(),
);

$args->parseWorkflows($workflows);
