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
      throw new PhutilArgumentUsageException(
        pht(
          "The `mxmlc` binary was not found in PATH. This compiler binary ".
          "is required to rebuild the Aphlict client.\n\n".
          "Adjust your PATH, or install the Flex SDK from:\n\n".
          "    http://flex.apache.org\n\n".
          "You may also be able to install it with `npm`:\n\n".
          "    $ npm install flex-sdk\n\n".
          "(Note: you should only need to rebuild Aphlict if you are ".
          "developing Phabricator.)"));
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
