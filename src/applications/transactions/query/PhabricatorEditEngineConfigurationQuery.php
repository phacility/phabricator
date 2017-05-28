<?php

final class PhabricatorEditEngineConfigurationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $engineKeys;
  private $builtinKeys;
  private $identifiers;
  private $default;
  private $isEdit;
  private $disabled;
  private $ignoreDatabaseConfigurations;
  private $subtypes;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withEngineKeys(array $engine_keys) {
    $this->engineKeys = $engine_keys;
    return $this;
  }

  public function withBuiltinKeys(array $builtin_keys) {
    $this->builtinKeys = $builtin_keys;
    return $this;
  }

  public function withIdentifiers(array $identifiers) {
    $this->identifiers = $identifiers;
    return $this;
  }

  public function withIsDefault($default) {
    $this->default = $default;
    return $this;
  }

  public function withIsEdit($edit) {
    $this->isEdit = $edit;
    return $this;
  }

  public function withIsDisabled($disabled) {
    $this->disabled = $disabled;
    return $this;
  }

  public function withIgnoreDatabaseConfigurations($ignore) {
    $this->ignoreDatabaseConfigurations = $ignore;
    return $this;
  }

  public function withSubtypes(array $subtypes) {
    $this->subtypes = $subtypes;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorEditEngineConfiguration();
  }

  protected function loadPage() {
    // TODO: The logic here is a little flimsy and won't survive pagination.
    // For now, I'm just not bothering with pagination since I believe it will
    // take some time before any install manages to produce a large enough
    // number of edit forms for any particular engine for the lack of UI
    // pagination to become a problem.

    if ($this->ignoreDatabaseConfigurations) {
      $page = array();
    } else {
      $page = $this->loadStandardPage($this->newResultObject());
    }

    // Now that we've loaded the real results from the database, we're going
    // to load builtins from the edit engines and add them to the list.

    $engines = PhabricatorEditEngine::getAllEditEngines();

    if ($this->engineKeys) {
      $engines = array_select_keys($engines, $this->engineKeys);
    }

    foreach ($engines as $engine) {
      $engine->setViewer($this->getViewer());
    }

    // List all the builtins which have already been saved to the database as
    // real objects.
    $concrete = array();
    foreach ($page as $config) {
      $builtin_key = $config->getBuiltinKey();
      if ($builtin_key !== null) {
        $engine_key = $config->getEngineKey();
        $concrete[$engine_key][$builtin_key] = $config;
      }
    }

    $builtins = array();
    foreach ($engines as $engine_key => $engine) {
      $engine_builtins = $engine->getBuiltinEngineConfigurations();
      foreach ($engine_builtins as $engine_builtin) {
        $builtin_key = $engine_builtin->getBuiltinKey();
        if (isset($concrete[$engine_key][$builtin_key])) {
          continue;
        } else {
          $builtins[] = $engine_builtin;
        }
      }
    }

    foreach ($builtins as $builtin) {
      $page[] = $builtin;
    }

    // Now we have to do some extra filtering to make sure everything we're
    // about to return really satisfies the query.

    if ($this->ids !== null) {
      $ids = array_fuse($this->ids);
      foreach ($page as $key => $config) {
        if (empty($ids[$config->getID()])) {
          unset($page[$key]);
        }
      }
    }

    if ($this->phids !== null) {
      $phids = array_fuse($this->phids);
      foreach ($page as $key => $config) {
        if (empty($phids[$config->getPHID()])) {
          unset($page[$key]);
        }
      }
    }

    if ($this->builtinKeys !== null) {
      $builtin_keys = array_fuse($this->builtinKeys);
      foreach ($page as $key => $config) {
        if (empty($builtin_keys[$config->getBuiltinKey()])) {
          unset($page[$key]);
        }
      }
    }

    if ($this->default !== null) {
      foreach ($page as $key => $config) {
        if ($config->getIsDefault() != $this->default) {
          unset($page[$key]);
        }
      }
    }

    if ($this->isEdit !== null) {
      foreach ($page as $key => $config) {
        if ($config->getIsEdit() != $this->isEdit) {
          unset($page[$key]);
        }
      }
    }

    if ($this->disabled !== null) {
      foreach ($page as $key => $config) {
        if ($config->getIsDisabled() != $this->disabled) {
          unset($page[$key]);
        }
      }
    }

    if ($this->identifiers !== null) {
      $identifiers = array_fuse($this->identifiers);
      foreach ($page as $key => $config) {
        if (isset($identifiers[$config->getBuiltinKey()])) {
          continue;
        }
        if (isset($identifiers[$config->getID()])) {
          continue;
        }
        unset($page[$key]);
      }
    }

    if ($this->subtypes !== null) {
      $subtypes = array_fuse($this->subtypes);
      foreach ($page as $key => $config) {
        if (isset($subtypes[$config->getSubtype()])) {
          continue;
        }

        unset($page[$key]);
      }
    }

    return $page;
  }

  protected function willFilterPage(array $configs) {
    $engine_keys = mpull($configs, 'getEngineKey');

    $engines = id(new PhabricatorEditEngineQuery())
      ->setParentQuery($this)
      ->setViewer($this->getViewer())
      ->withEngineKeys($engine_keys)
      ->execute();
    $engines = mpull($engines, null, 'getEngineKey');

    foreach ($configs as $key => $config) {
      $engine = idx($engines, $config->getEngineKey());

      if (!$engine) {
        $this->didRejectResult($config);
        unset($configs[$key]);
        continue;
      }

      $config->attachEngine($engine);
    }

    return $configs;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->engineKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'engineKey IN (%Ls)',
        $this->engineKeys);
    }

    if ($this->builtinKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'builtinKey IN (%Ls)',
        $this->builtinKeys);
    }

    if ($this->identifiers !== null) {
      $where[] = qsprintf(
        $conn,
        '(id IN (%Ls) OR builtinKey IN (%Ls))',
        $this->identifiers,
        $this->identifiers);
    }

    if ($this->subtypes !== null) {
      $where[] = qsprintf(
        $conn,
        'subtype IN (%Ls)',
        $this->subtypes);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorTransactionsApplication';
  }

}
