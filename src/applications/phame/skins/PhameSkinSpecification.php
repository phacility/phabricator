<?php

final class PhameSkinSpecification extends Phobject {

  const TYPE_ADVANCED   = 'advanced';
  const TYPE_BASIC      = 'basic';

  private $type;
  private $rootDirectory;
  private $skinClass;
  private $phutilLibraries = array();
  private $name;
  private $config;

  public static function loadAllSkinSpecifications() {
    static $specs;

    if ($specs === null) {
      $paths = PhabricatorEnv::getEnvConfig('phame.skins');
      $base  = dirname(phutil_get_library_root('phabricator'));

      $specs = array();

      foreach ($paths as $path) {
        $path = Filesystem::resolvePath($path, $base);
        foreach (Filesystem::listDirectory($path) as $skin_directory) {
          $skin_path = $path.DIRECTORY_SEPARATOR.$skin_directory;

          if (!is_dir($skin_path)) {
            continue;
          }
          $spec = self::loadSkinSpecification($skin_path);
          if (!$spec) {
            continue;
          }

          $name = trim($skin_directory, DIRECTORY_SEPARATOR);

          $spec->setName($name);

          if (isset($specs[$name])) {
            $that_dir = $specs[$name]->getRootDirectory();
            $this_dir = $spec->getRootDirectory();
            throw new Exception(
              pht(
                "Two skins have the same name ('%s'), in '%s' and '%s'. ".
                "Rename one or adjust your '%s' configuration.",
                $name,
                $this_dir,
                $that_dir,
                'phame.skins'));
          }

          $specs[$name] = $spec;
        }
      }
    }

    return $specs;
  }

  public static function loadOneSkinSpecification($name) {
    // Only allow skins which we know to exist to load. This prevents loading
    // skins like "../../secrets/evil/".
    $all = self::loadAllSkinSpecifications();
    if (empty($all[$name])) {
      throw new Exception(
        pht(
          'Blog skin "%s" is not a valid skin!',
          $name));
    }

    $paths = PhabricatorEnv::getEnvConfig('phame.skins');
    $base = dirname(phutil_get_library_root('phabricator'));
    foreach ($paths as $path) {
      $path = Filesystem::resolvePath($path, $base);
      $skin_path = $path.DIRECTORY_SEPARATOR.$name;
      if (is_dir($skin_path)) {

        // Double check that the skin really lives in the skin directory.
        if (!Filesystem::isDescendant($skin_path, $path)) {
          throw new Exception(
            pht(
              'Blog skin "%s" is not located in path "%s"!',
              $name,
              $path));
        }

        $spec = self::loadSkinSpecification($skin_path);
        if ($spec) {
          $spec->setName($name);
          return $spec;
        }
      }
    }
    return null;
  }

  private static function loadSkinSpecification($path) {
    $config_path = $path.DIRECTORY_SEPARATOR.'skin.json';
    $config = array();
    if (Filesystem::pathExists($config_path)) {
      $config = Filesystem::readFile($config_path);
      try {
        $config = phutil_json_decode($config);
      } catch (PhutilJSONParserException $ex) {
        throw new PhutilProxyException(
          pht(
            "Skin configuration file '%s' is not a valid JSON file.",
            $config_path),
          $ex);
      }
      $type = idx($config, 'type', self::TYPE_BASIC);
    } else {
      $type = self::TYPE_BASIC;
    }

    $spec = new PhameSkinSpecification();
    $spec->setRootDirectory($path);
    $spec->setConfig($config);

    switch ($type) {
      case self::TYPE_BASIC:
        $spec->setSkinClass('PhameBasicTemplateBlogSkin');
        break;
      case self::TYPE_ADVANCED:
        $spec->setSkinClass($config['class']);
        $spec->addPhutilLibrary($path.DIRECTORY_SEPARATOR.'src');
        break;
      default:
        throw new Exception(pht('Unknown skin type!'));
    }

    $spec->setType($type);

    return $spec;
  }

  public function setConfig(array $config) {
    $this->config = $config;
    return $this;
  }

  public function getConfig($key, $default = null) {
    return idx($this->config, $key, $default);
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->getConfig('name', $this->name);
  }

  public function setRootDirectory($root_directory) {
    $this->rootDirectory = $root_directory;
    return $this;
  }

  public function getRootDirectory() {
    return $this->rootDirectory;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setSkinClass($skin_class) {
    $this->skinClass = $skin_class;
    return $this;
  }

  public function getSkinClass() {
    return $this->skinClass;
  }

  public function addPhutilLibrary($library) {
    $this->phutilLibraries[] = $library;
    return $this;
  }

  public function buildSkin(AphrontRequest $request) {
    foreach ($this->phutilLibraries as $library) {
      phutil_load_library($library);
    }

    return newv($this->getSkinClass(), array($request, $this));
  }

}
