<?php

final class HarbormasterCreateArtifactConduitAPIMethod
  extends HarbormasterConduitAPIMethod {

  public function getAPIMethodName() {
    return 'harbormaster.createartifact';
  }

  public function getMethodSummary() {
    return pht('Create a build artifact.');
  }

  public function getMethodDescription() {
    $types = HarbormasterArtifact::getAllArtifactTypes();
    $types = msort($types, 'getArtifactTypeName');

    $head_key = pht('Key');
    $head_type = pht('Type');
    $head_desc = pht('Description');
    $head_atype = pht('Artifact Type');
    $head_name = pht('Name');
    $head_summary = pht('Summary');

    $out = array();
    $out[] = pht(
      'Use this method to attach artifacts to build targets while running '.
      'builds. Artifacts can be used to carry data through a complex build '.
      'workflow, provide extra information to users, or store build results.');
    $out[] = null;
    $out[] = pht(
      'When creating an artifact, you will choose an `artifactType` from '.
      'this table. These types of artifacts are supported:');

    $out[] = "| {$head_atype} | {$head_name} | {$head_summary} |";
    $out[] = '|-------------|--------------|--------------|';
    foreach ($types as $type) {
      $type_name = $type->getArtifactTypeName();
      $type_const = $type->getArtifactConstant();
      $type_summary = $type->getArtifactTypeSummary();
      $out[] = "| `{$type_const}` | **{$type_name}** | {$type_summary} |";
    }

    $out[] = null;
    $out[] = pht(
      'Each artifact also needs an `artifactKey`, which names the artifact. '.
      'Finally, you will provide some `artifactData` to fill in the content '.
      'of the artifact. The data you provide depends on what type of artifact '.
      'you are creating.');

    foreach ($types as $type) {
      $type_name = $type->getArtifactTypeName();
      $type_const = $type->getArtifactConstant();

      $out[] = $type_name;
      $out[] = '--------------------------';
      $out[] = null;
      $out[] = $type->getArtifactTypeDescription();
      $out[] = null;
      $out[] = pht(
        'Create an artifact of this type by passing `%s` as the '.
        '`artifactType`. When creating an artifact of this type, provide '.
        'these parameters as a dictionary to `artifactData`:',
        $type_const);

      $spec = $type->getArtifactParameterSpecification();
      $desc = $type->getArtifactParameterDescriptions();
      $out[] = "| {$head_key} | {$head_type} | {$head_desc} |";
      $out[] = '|-------------|--------------|--------------|';
      foreach ($spec as $key => $key_type) {
        $key_desc = idx($desc, $key);
        $out[] = "| `{$key}` | //{$key_type}// | {$key_desc} |";
      }

      $example = $type->getArtifactDataExample();
      if ($example !== null) {
        $json = new PhutilJSON();
        $rendered = $json->encodeFormatted($example);

        $out[] = pht('For example:');
        $out[] = '```lang=json';
        $out[] = $rendered;
        $out[] = '```';
      }
    }

    return implode("\n", $out);
  }

  protected function defineParamTypes() {
    return array(
      'buildTargetPHID' => 'phid',
      'artifactKey' => 'string',
      'artifactType' => 'string',
      'artifactData' => 'map<string, wild>',
    );
  }

  protected function defineReturnType() {
    return 'wild';
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $build_target_phid = $request->getValue('buildTargetPHID');
    $build_target = id(new HarbormasterBuildTargetQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($build_target_phid))
      ->executeOne();
    if (!$build_target) {
      throw new Exception(
        pht(
          'No such build target "%s"!',
          $build_target_phid));
    }

    $artifact_type = $request->getValue('artifactType');

    // Cast "artifactData" parameters to acceptable types if this request
    // is submitting raw HTTP parameters. This is not ideal. See T11887 for
    // discussion.
    $artifact_data = $request->getValue('artifactData');
    if (!$request->getIsStrictlyTyped()) {
      $impl = HarbormasterArtifact::getArtifactType($artifact_type);
      if ($impl) {
        foreach ($artifact_data as $key => $value) {
          $artifact_data[$key] = $impl->readArtifactHTTPParameter(
            $key,
            $value);
        }
      }
    }

    $artifact = $build_target->createArtifact(
      $viewer,
      $request->getValue('artifactKey'),
      $artifact_type,
      $artifact_data);

    return array(
      'data' => $this->returnArtifactList(array($artifact)),
    );
  }

}
