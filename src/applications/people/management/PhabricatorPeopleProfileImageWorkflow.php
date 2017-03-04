<?php

final class PhabricatorPeopleProfileImageWorkflow
  extends PhabricatorPeopleManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('profileimage')
      ->setExamples('**profileimage** --users __username__')
      ->setSynopsis(pht('Generate default profile images.'))
      ->setArguments(
        array(
          array(
            'name' => 'user',
            'help' => pht(
              'Generate a default profile image for a specific user'),
          ),
          array(
            'name' => 'all',
            'help' => pht(
              'Generate default profile images for all users.'),
          ),
          array(
            'name' => 'force',
            'short' => 'f',
            'help' => pht(
              'Force a default profile image to be replaced.'),
          ),
          array(
            'name'     => 'users',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $is_force = $args->getArg('force');
    $is_all = $args->getArg('all');
    $is_user = $args->getArg('user');

    $gd = function_exists('imagecreatefromstring');
    if (!$gd) {
      throw new PhutilArgumentUsageException(
        pht(
          'GD is not installed for php-cli. Aborting.'));
    }

    $iterator = $this->buildIterator($args);
    if (!$iterator) {
      throw new PhutilArgumentUsageException(
        pht(
          'Either specify a list of users to update, or use `%s` '.
          'to update all users.',
          '--all'));
    }

    $version = PhabricatorFilesComposeAvatarBuiltinFile::VERSION;

    foreach ($iterator as $user) {
      $username = $user->getUsername();
      $default_phid = $user->getDefaultProfileImagePHID();

      if ($default_phid == null || $is_force) {
        $file = id(new PhabricatorFilesComposeAvatarBuiltinFile())
          ->getUserProfileImageFile($username);
        $user->setDefaultProfileImagePHID($file->getPHID());
        $user->setDefaultProfileImageVersion($version);
        $user->save();
        $console->writeOut(
          "%s\n",
          pht(
            'Generating profile image for "%s".',
            $username));
      } else {
        $console->writeOut(
          "%s\n",
          pht(
            'Default profile image "%s" already set for "%s".',
            $version,
            $username));
      }
    }
  }

}
