<?php

abstract class PhragmentController extends PhabricatorController {

  protected function loadParentFragments($path) {
    $components = explode('/', $path);

    $combinations = array();
    $current = '';
    foreach ($components as $component) {
      $current .= '/'.$component;
      $current = trim($current, '/');
      if (trim($current) === '') {
        continue;
      }

      $combinations[] = $current;
    }

    $fragments = array();
    $results = id(new PhragmentFragmentQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withPaths($combinations)
      ->execute();
    foreach ($combinations as $combination) {
      $found = false;
      foreach ($results as $fragment) {
        if ($fragment->getPath() === $combination) {
          $fragments[] = $fragment;
          $found = true;
          break;
        }
      }
      if (!$found) {
        return null;
      }
    }
    return $fragments;
  }

  protected function buildApplicationCrumbsWithPath(array $fragments) {
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName('/')
        ->setHref('/phragment/'));
    foreach ($fragments as $parent) {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($parent->getName())
          ->setHref('/phragment/browse/'.$parent->getPath()));
    }
    return $crumbs;
  }

}
