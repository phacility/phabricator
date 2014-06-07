<?php

final class PhabricatorAphlictManagementBuildWorkflow
  extends PhabricatorAphlictManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('build')
      ->setSynopsis(pht('Build the Aphlict client.'))
      ->setArguments(
        array(
          array(
            'name' => 'debug',
            'help' => 'Enable a debug build.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $root = dirname(__FILE__).'/../../../..';

    if (!Filesystem::binaryExists('mxmlc')) {
      $console->writeErr('`mxmlc` is not installed.');
      return 1;
    }

    $argv = array(
      "-source-path=$root/externals/vegas/src",
      '-static-link-runtime-shared-libraries=true',
      '-warnings=true',
      '-strict=true',
    );

    if ($args->getArg('debug')) {
      $argv[] = '-debug=true';
    }

    list ($err, $stdout, $stderr) = exec_manual('mxmlc %Ls -output=%s %s',
      $argv,
      $root.'/webroot/rsrc/swf/aphlict.swf',
      $root.'/support/aphlict/client/src/AphlictClient.as');

    if ($err) {
      $console->writeErr($stderr);
      return 1;
    }

    $console->writeOut("Done.\n");
    return 0;
  }

}
