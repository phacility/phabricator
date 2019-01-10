<?php

final class HeraldTestManagementWorkflow
  extends HeraldManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('test')
      ->setExamples('**test** --object __object__ --type __type__')
      ->setSynopsis(
        pht(
          'Test content rules for an object. Executes a dry run, like the '.
          'web UI test console.'))
      ->setArguments(
        array(
          array(
            'name' => 'object',
            'param' => 'object',
            'help' => pht('Run rules on this object.'),
          ),
          array(
            'name' => 'type',
            'param' => 'type',
            'help' => pht('Run rules for this content type.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $object_name = $args->getArg('object');
    if (!strlen($object_name)) {
      throw new PhutilArgumentUsageException(
        pht('Specify an object to test rules for with "--object".'));
    }

    $objects = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($object_name))
      ->execute();
    if (!$objects) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to load specified object ("%s").',
          $object_name));
    }
    $object = head($objects);

    $adapters = HeraldAdapter::getAllAdapters();

    $can_select = array();
    $display_adapters = array();
    foreach ($adapters as $key => $adapter) {
      if (!$adapter->isTestAdapterForObject($object)) {
        continue;
      }

      if (!$adapter->isAvailableToUser($viewer)) {
        continue;
      }

      $display_adapters[$key] = $adapter;

      if ($adapter->canCreateTestAdapterForObject($object)) {
        $can_select[$key] = $adapter;
      }
    }


    $content_type = $args->getArg('type');
    if (!strlen($content_type)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a content type to run rules for. For this object, valid '.
          'content types are: %s.',
          implode(', ', array_keys($can_select))));
    }

    if (!isset($can_select[$content_type])) {
      if (!isset($display_adapters[$content_type])) {
        throw new PhutilArgumentUsageException(
          pht(
            'Specify a content type to run rules for. The specified content '.
            'type ("%s") is not valid. For this object, valid content types '.
            'are: %s.',
            $content_type,
            implode(', ', array_keys($can_select))));
      } else {
        throw new PhutilArgumentUsageException(
          pht(
            'The specified content type ("%s") does not support dry runs. '.
            'Choose a testable content type. For this object, valid content '.
            'types are: %s.',
            $content_type,
            implode(', ', array_keys($can_select))));
      }
    }

    $adapter = $can_select[$content_type]->newTestAdapter(
      $viewer,
      $object);

    $content_source = $this->newContentSource();

    $adapter
      ->setContentSource($content_source)
      ->setIsNewObject(false)
      ->setViewer($viewer);

    $rules = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withContentTypes(array($adapter->getAdapterContentType()))
      ->withDisabled(false)
      ->needConditionsAndActions(true)
      ->needAppliedToPHIDs(array($object->getPHID()))
      ->needValidateAuthors(true)
      ->execute();

    $engine = id(new HeraldEngine())
      ->setDryRun(true);

    $effects = $engine->applyRules($rules, $adapter);
    $engine->applyEffects($effects, $adapter, $rules);

    $xscript = $engine->getTranscript();

    $uri = '/herald/transcript/'.$xscript->getID().'/';
    $uri = PhabricatorEnv::getProductionURI($uri);

    echo tsprintf(
      "%s\n\n    __%s__\n\n",
      pht('Test run complete. Transcript:'),
      $uri);

    return 0;
  }

}
