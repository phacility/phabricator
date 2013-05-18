<?php

/**
 * @group project
 */
final class ProjectRemarkupRule
  extends PhabricatorRemarkupRuleObject {

  protected function getObjectNamePrefix() {
    return '#';
  }

  protected function getObjectIDPattern() {
    // NOTE: This explicitly does not match strings which contain only
    // digits, because digit strings like "#123" are used to reference tasks at
    // Facebook and are somewhat conventional in general.
    return '[^\s.!,:;]*[^\s\d.!,:;]+[^\s.!,:;]*';
  }

  protected function loadObjects(array $ids) {
    $viewer = $this->getEngine()->getConfig('viewer');

    // If the user types "#YoloSwag", we still want to match "#yoloswag", so
    // we normalize, query, and then map back to the original inputs.

    $map = array();
    foreach ($ids as $key => $slug) {
      $map[PhabricatorSlug::normalize($slug)][] = $slug;
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withPhrictionSlugs(array_keys($map))
      ->execute();

    $result = array();
    foreach ($projects as $project) {
      $slugs = array($project->getPhrictionSlug());
      foreach ($slugs as $slug) {
        foreach ($map[$slug] as $original) {
          $result[$original] = $project;
        }
      }
    }


    return $result;
  }

}
