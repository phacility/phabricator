<?php

final class PhabricatorSystemRemoveDestroyWorkflow
  extends PhabricatorSystemRemoveWorkflow {

  protected function didConstruct() {
    $this
      ->setName('destroy')
      ->setSynopsis(pht('Permanently destroy objects.'))
      ->setExamples('**destroy** [__options__] __object__ ...')
      ->setArguments(
        array(
          array(
            'name' => 'force',
            'help' => pht('Destroy objects without prompting.'),
          ),
          array(
            'name' => 'objects',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $object_names = $args->getArg('objects');
    if (!$object_names) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more objects to destroy.'));
    }

    $object_query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withNames($object_names);

    $object_query->execute();

    $named_objects = $object_query->getNamedResults();
    foreach ($object_names as $object_name) {
      if (empty($named_objects[$object_name])) {
        throw new PhutilArgumentUsageException(
          pht('No such object "%s" exists!', $object_name));
      }
    }

    foreach ($named_objects as $object_name => $object) {
      if (!($object instanceof PhabricatorDestructibleInterface)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Object "%s" can not be destroyed (it does not implement %s).',
            $object_name,
            'PhabricatorDestructibleInterface'));
      }
    }

    $banner = <<<EOBANNER
                                  uuuuuuu
                               uu###########uu
                            uu#################uu
                           u#####################u
                          u#######################u
                         u#########################u
                         u#########################u
                         u######"   "###"   "######u
                         "####"      u#u       ####"
                          ###u       u#u       u###
                          ###u      u###u      u###
                           "####uu###   ###uu####"
                            "#######"   "#######"
                              u#######u#######u
                               u#"#"#"#"#"#"#u
                    uuu        ##u# # # # #u##       uuu
                   u####        #####u#u#u###       u####
                    #####uu      "#########"     uu######
                  u###########uu    """""    uuuu##########
                  ####"""##########uuu   uu#########"""###"
                   """      ""###########uu ""#"""
                             uuuu ""##########uuu
                    u###uuu#########uu ""###########uuu###
                    ##########""""           ""###########"
                     "#####"                      ""####""
                       ###"                         ####"

EOBANNER;


    $console->writeOut("\n\n<fg:red>%s</fg>\n\n", $banner);

    $console->writeOut(
      "<bg:red>** %s **</bg> %s\n\n%s\n\n".
      "<bg:red>** %s **</bg> %s\n\n%s\n\n",
      pht('IMPORTANT'),
      pht('DATA WILL BE PERMANENTLY DESTROYED'),
      phutil_console_wrap(
        pht(
          'Objects will be permanently destroyed. There is no way to '.
          'undo this operation or ever retrieve this data unless you '.
          'maintain external backups.')),
      pht('IMPORTANT'),
      pht('DELETING OBJECTS OFTEN BREAKS THINGS'),
      phutil_console_wrap(
        pht(
          'Destroying objects may cause related objects to stop working, '.
          'and may leave scattered references to objects which no longer '.
          'exist. In most cases, it is much better to disable or archive '.
          'objects instead of destroying them. This risk is greatest when '.
          'deleting complex or highly connected objects like repositories, '.
          'projects and users.'.
          "\n\n".
          'These tattered edges are an expected consquence of destroying '.
          'objects, and the Phabricator upstream will not help you fix '.
          'them. We strongly recomend disabling or archiving objects '.
          'instead.')));

    $phids = mpull($named_objects, 'getPHID');
    $handles = PhabricatorUser::getOmnipotentUser()->loadHandles($phids);

    $console->writeOut(
      pht(
        'These %s object(s) will be destroyed forever:',
        new PhutilNumber(count($named_objects)))."\n\n");

    foreach ($named_objects as $object_name => $object) {
      $phid = $object->getPHID();

      $console->writeOut(
        "    - %s (%s) %s\n",
        $object_name,
        get_class($object),
        $handles[$phid]->getFullName());
    }

    $force = $args->getArg('force');
    if (!$force) {
      $ok = $console->confirm(
        pht(
          'Are you absolutely certain you want to destroy these %s object(s)?',
          new PhutilNumber(count($named_objects))));
      if (!$ok) {
        throw new PhutilArgumentUsageException(
          pht('Aborted, your objects are safe.'));
      }
    }

    $console->writeOut("%s\n", pht('Destroying objects...'));

    foreach ($named_objects as $object_name => $object) {
      $console->writeOut(
        pht(
          "Destroying %s **%s**...\n",
          get_class($object),
          $object_name));

      id(new PhabricatorDestructionEngine())
        ->destroyObject($object);
    }

    $console->writeOut(
      "%s\n",
      pht(
        'Permanently destroyed %s object(s).',
        new PhutilNumber(count($named_objects))));

    return 0;
  }

}
