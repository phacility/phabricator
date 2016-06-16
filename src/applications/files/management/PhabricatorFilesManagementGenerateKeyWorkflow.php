<?php

final class PhabricatorFilesManagementGenerateKeyWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('generate-key')
      ->setSynopsis(
        pht('Generate an encryption key.'))
      ->setArguments(
        array(
          array(
            'name' => 'type',
            'param' => 'keytype',
            'help' => pht('Select the type of key to generate.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $type = $args->getArg('type');
    if (!strlen($type)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify the type of key to generate with --type.'));
    }

    $format = PhabricatorFileStorageFormat::getFormat($type);
    if (!$format) {
      throw new PhutilArgumentUsageException(
        pht(
          'No key type "%s" exists.',
          $type));
    }

    if (!$format->canGenerateNewKeyMaterial()) {
      throw new PhutilArgumentUsageException(
        pht(
          'Storage format "%s" can not generate keys.',
          $format->getStorageFormatName()));
    }

    $material = $format->generateNewKeyMaterial();

    $structure = array(
      'name' => 'generated-key-'.Filesystem::readRandomCharacters(12),
      'type' => $type,
      'material.base64' => $material,
    );

    $json = id(new PhutilJSON())->encodeFormatted($structure);

    echo tsprintf(
      "%s: %s\n\n%B\n",
      pht('Key Material'),
      $format->getStorageFormatName(),
      $json);

    return 0;
  }

}
