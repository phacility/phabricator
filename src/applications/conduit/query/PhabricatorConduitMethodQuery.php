<?php

final class PhabricatorConduitMethodQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $isDeprecated;
  private $isStable;
  private $isUnstable;
  private $applicationNames;
  private $nameContains;
  private $methods;
  private $isInternal;

  public function withMethods(array $methods) {
    $this->methods = $methods;
    return $this;
  }

  public function withNameContains($name_contains) {
    $this->nameContains = $name_contains;
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

  public function withIsInternal($is_internal) {
    $this->isInternal = $is_internal;
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
      ConduitAPIMethod::METHOD_STATUS_STABLE => $this->isStable,
      ConduitAPIMethod::METHOD_STATUS_FROZEN => $this->isStable,
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

    if ($this->isInternal !== null) {
      foreach ($methods as $key => $method) {
        if ($method->isInternalAPI() !== $this->isInternal) {
          unset($methods[$key]);
        }
      }
    }

    return $methods;
  }

  protected function willFilterPage(array $methods) {
    $application_phids = array();
    foreach ($methods as $method) {
      $application = $method->getApplication();
      if ($application === null) {
        continue;
      }
      $application_phids[] = $application->getPHID();
    }

    if ($application_phids) {
      $applications = id(new PhabricatorApplicationQuery())
        ->setParentQuery($this)
        ->setViewer($this->getViewer())
        ->withPHIDs($application_phids)
        ->execute();
      $applications = mpull($applications, null, 'getPHID');
    } else {
      $applications = array();
    }

    // Remove methods which belong to an application the viewer can not see.
    foreach ($methods as $key => $method) {
      $application = $method->getApplication();
      if ($application === null) {
        continue;
      }

      if (empty($applications[$application->getPHID()])) {
        $this->didRejectResult($method);
        unset($methods[$key]);
      }
    }

    return $methods;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorConduitApplication';
  }

}
