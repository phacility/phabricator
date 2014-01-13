<?php

final class HarbormasterThrowExceptionBuildStep
  extends BuildStepImplementation {

  public function getName() {
    return pht('Throw Exception');
  }

  public function getGenericDescription() {
    return pht('Throw an exception.');
  }

  public function getDescription() {
    return pht('Throw an exception.');
  }

  public function execute(
    HarbormasterBuild $build,
    HarbormasterBuildTarget $build_target) {

    throw new Exception(pht('(This is an explicit exception.)'));
  }

}
