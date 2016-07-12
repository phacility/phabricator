<?php

final class PhabricatorAuthManagementCachePKCS8Workflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('cache-pkcs8')
      ->setExamples('**cache-pkcs8** --public __keyfile__ --pkcs8 __keyfile__')
      ->setSynopsis(
        pht(
          'Cache the PKCS8 format of a public key. When developing on OSX, '.
          'this can be used to work around issues with ssh-keygen. Use '.
          '`%s` to generate a PKCS8 key to feed to this command.',
          'ssh-keygen -e -m PKCS8 -f key.pub'))
      ->setArguments(
        array(
          array(
            'name' => 'public',
            'param' => 'keyfile',
            'help' => pht('Path to public keyfile.'),
          ),
          array(
            'name' => 'pkcs8',
            'param' => 'keyfile',
            'help' => pht('Path to corresponding PKCS8 key.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $public_keyfile = $args->getArg('public');
    if (!strlen($public_keyfile)) {
      throw new PhutilArgumentUsageException(
        pht(
          'You must specify the path to a public keyfile with %s.',
          '--public'));
    }

    if (!Filesystem::pathExists($public_keyfile)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specified public keyfile "%s" does not exist!',
          $public_keyfile));
    }

    $public_key = Filesystem::readFile($public_keyfile);

    $pkcs8_keyfile = $args->getArg('pkcs8');
    if (!strlen($pkcs8_keyfile)) {
      throw new PhutilArgumentUsageException(
        pht(
          'You must specify the path to a pkcs8 keyfile with %s.',
          '--pkc8s'));
    }

    if (!Filesystem::pathExists($pkcs8_keyfile)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specified pkcs8 keyfile "%s" does not exist!',
          $pkcs8_keyfile));
    }

    $pkcs8_key = Filesystem::readFile($pkcs8_keyfile);

    $warning = pht(
      'Adding a PKCS8 keyfile to the cache can be very dangerous. If the '.
      'PKCS8 file really encodes a different public key than the one '.
      'specified, an attacker could use it to gain unauthorized access.'.
      "\n\n".
      'Generally, you should use this option only in a development '.
      'environment where ssh-keygen is broken and it is inconvenient to '.
      'fix it, and only if you are certain you understand the risks. You '.
      'should never cache a PKCS8 file you did not generate yourself.');

    $console->writeOut(
      "%s\n",
      phutil_console_wrap($warning));

    $prompt = pht('Really trust this PKCS8 keyfile?');
    if (!phutil_console_confirm($prompt)) {
      throw new PhutilArgumentUsageException(
        pht('Aborted workflow.'));
    }

    $key = PhabricatorAuthSSHPublicKey::newFromRawKey($public_key);
    $key->forcePopulatePKCS8Cache($pkcs8_key);

    $console->writeOut(
      "%s\n",
      pht('Cached PKCS8 key for public key.'));

    return 0;
  }

}
