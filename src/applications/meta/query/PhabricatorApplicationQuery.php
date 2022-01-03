<?php

final class PhabricatorApplicationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $installed;
  private $prototypes;
  private $firstParty;
  private $nameContains;
  private $unlisted;
  private $classes;
  private $launchable;
  private $applicationEmailSupport;
  private $phids;

  const ORDER_APPLICATION = 'order:application';
  const ORDER_NAME = 'order:name';

  private $order = self::ORDER_APPLICATION;

  public function withNameContains($name_contains) {
    $this->nameContains = $name_contains;
    return $this;
  }

  public function withInstalled($installed) {
    $this->installed = $installed;
    return $this;
  }

  public function withPrototypes($prototypes) {
    $this->prototypes = $prototypes;
    return $this;
  }

  public function withFirstParty($first_party) {
    $this->firstParty = $first_party;
    return $this;
  }

  public function withUnlisted($unlisted) {
    $this->unlisted = $unlisted;
    return $this;
  }

  public function withLaunchable($launchable) {
    $this->launchable = $launchable;
    return $this;
  }

  public function withApplicationEmailSupport($appemails) {
    $this->applicationEmailSupport = $appemails;
    return $this;
  }

  public function withClasses(array $classes) {
    $this->classes = $classes;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function setOrder($order) {
    $this->order = $order;
    return $this;
  }

  protected function loadPage() {
    $apps = PhabricatorApplication::getAllApplications();

    if ($this->classes) {
      $classes = array_fuse($this->classes);
      foreach ($apps as $key => $app) {
        if (empty($classes[get_class($app)])) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->phids) {
      $phids = array_fuse($this->phids);
      foreach ($apps as $key => $app) {
        if (empty($phids[$app->getPHID()])) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->nameContains !== null) {
      foreach ($apps as $key => $app) {
        if (stripos($app->getName(), $this->nameContains) === false) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->installed !== null) {
      foreach ($apps as $key => $app) {
        if ($app->isInstalled() != $this->installed) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->prototypes !== null) {
      foreach ($apps as $key => $app) {
        if ($app->isPrototype() != $this->prototypes) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->firstParty !== null) {
      foreach ($apps as $key => $app) {
        if ($app->isFirstParty() != $this->firstParty) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->unlisted !== null) {
      foreach ($apps as $key => $app) {
        if ($app->isUnlisted() != $this->unlisted) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->launchable !== null) {
      foreach ($apps as $key => $app) {
        if ($app->isLaunchable() != $this->launchable) {
          unset($apps[$key]);
        }
      }
    }

    if ($this->applicationEmailSupport !== null) {
      foreach ($apps as $key => $app) {
        if ($app->supportsEmailIntegration() !=
            $this->applicationEmailSupport) {
          unset($apps[$key]);
        }
      }
    }

    switch ($this->order) {
      case self::ORDER_NAME:
        $apps = msort($apps, 'getName');
        break;
      case self::ORDER_APPLICATION:
        $apps = $apps;
        break;
      default:
        throw new Exception(
          pht('Unknown order "%s"!', $this->order));
    }

    return $apps;
  }


  public function getQueryApplicationClass() {
    // NOTE: Although this belongs to the "Applications" application, trying
    // to filter its results just leaves us recursing indefinitely. Users
    // always have access to applications regardless of other policy settings
    // anyway.
    return null;
  }

}
