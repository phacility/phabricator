<?php

final class PhabricatorConduitMethodQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $isDeprecated;
  private $isStable;
  private $isUnstable;
  private $applicationNames;
  private $nameContains;
  private $methods;

  public function withMethods(array $methods) {
    $this->methods = $methods;
    return $this;
  }

  public function withNameContains($name_contains) {
    $this->nameContains = $name_contains;
    return $this;
  }

  public function withApplicationNames(array $application_names) {
    $this->applicationNames = $application_names;
    return $this;
  }

  public function withIsStable($is_stable) {
    $this->isStable = $is_stable;
    return $this;
  }

  public function withIsUnstable($is_unstable) {
    $this->isUnstable = $is_unstable;
    return $this;
  }

  public function withIsDeprecated($is_deprecated) {
    $this->isDeprecated = $is_deprecated;
    return $this;
  }

  protected function loadPage() {
    $methods = $this->getAllMethods();
    $methods = $this->filterMethods($methods);
    return $methods;
  }

  private function getAllMethods() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass('ConduitAPIMethod')
      ->setSortMethod('getSortOrder')
      ->execute();
  }

  private function filterMethods(array $methods) {
    foreach ($methods as $key => $method) {
      $application = $method->getApplication();
      if (!$application) {
        continue;
      }
      if (!$application->isInstalled()) {
        unset($methods[$key]);
      }
    }

    $status = array(
      ConduitAPIMethod::METHOD_STATUS_STABLE     => $this->isStable,
      ConduitAPIMethod::METHOD_STATUS_DEPRECATED => $this->isDeprecated,
      ConduitAPIMethod::METHOD_STATUS_UNSTABLE   => $this->isUnstable,
    );

    // Only apply status filters if any of them are set.
    if (array_filter($status)) {
      foreach ($methods as $key => $method) {
        $keep = idx($status, $method->getMethodStatus());
        if (!$keep) {
          unset($methods[$key]);
        }
      }
    }

    if ($this->applicationNames) {
      $map = array_fuse($this->applicationNames);
      foreach ($methods as $key => $method) {
        $needle = $method->getApplicationName();
        $needle = phutil_utf8_strtolower($needle);
        if (empty($map[$needle])) {
          unset($methods[$key]);
        }
      }
    }

    if ($this->nameContains) {
      $needle = phutil_utf8_strtolower($this->nameContains);
      foreach ($methods as $key => $method) {
        $haystack = $method->getAPIMethodName();
        $haystack = phutil_utf8_strtolower($haystack);
        if (strpos($haystack, $needle) === false) {
          unset($methods[$key]);
        }
      }
    }

    if ($this->methods) {
      $map = array_fuse($this->methods);
      foreach ($methods as $key => $method) {
        $needle = $method->getAPIMethodName();
        if (empty($map[$needle])) {
          unset($methods[$key]);
        }
      }
    }

    return $methods;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConduitApplication';
  }

}
