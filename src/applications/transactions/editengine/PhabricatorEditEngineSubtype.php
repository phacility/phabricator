<?php


final class PhabricatorEditEngineSubtype
  extends Phobject {

  const SUBTYPE_DEFAULT = 'default';

  private $key;
  private $name;
  private $icon;
  private $tagText;
  private $color;
  private $childSubtypes = array();
  private $childIdentifiers = array();
  private $fieldConfiguration = array();
  private $mutations;

  public function setKey($key) {
    $this->key = $key;
    return $this;
  }

  public function getKey() {
    return $this->key;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setIcon($icon) {
    $this->icon = $icon;
    return $this;
  }

  public function getIcon() {
    return $this->icon;
  }

  public function setTagText($text) {
    $this->tagText = $text;
    return $this;
  }

  public function getTagText() {
    return $this->tagText;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function getColor() {
    return $this->color;
  }

  public function setChildSubtypes(array $child_subtypes) {
    $this->childSubtypes = $child_subtypes;
    return $this;
  }

  public function getChildSubtypes() {
    return $this->childSubtypes;
  }

  public function setChildFormIdentifiers(array $child_identifiers) {
    $this->childIdentifiers = $child_identifiers;
    return $this;
  }

  public function getChildFormIdentifiers() {
    return $this->childIdentifiers;
  }

  public function setMutations($mutations) {
    $this->mutations = $mutations;
    return $this;
  }

  public function getMutations() {
    return $this->mutations;
  }

  public function hasTagView() {
    if ($this->getTagText() === null) {
      return false;
    }
    return (bool)strlen($this->getTagText());
  }

  public function newTagView() {
    $view = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_OUTLINE)
      ->setName($this->getTagText());

    $color = $this->getColor();
    if ($color) {
      $view->setColor($color);
    }

    return $view;
  }

  public function setSubtypeFieldConfiguration(
    $subtype_key,
    array $configuration) {
    $this->fieldConfiguration[$subtype_key] = $configuration;
    return $this;
  }

  public function getSubtypeFieldConfiguration($subtype_key) {
    return idx($this->fieldConfiguration, $subtype_key);
  }

  public static function validateSubtypeKey($subtype) {
    if (strlen($subtype) > 64) {
      throw new Exception(
        pht(
          'Subtype "%s" is not valid: subtype keys must be no longer than '.
          '64 bytes.',
          $subtype));
    }

    if (strlen($subtype) < 3) {
      throw new Exception(
        pht(
          'Subtype "%s" is not valid: subtype keys must have a minimum '.
          'length of 3 bytes.',
          $subtype));
    }

    if (!preg_match('/^[a-z]+\z/', $subtype)) {
      throw new Exception(
        pht(
          'Subtype "%s" is not valid: subtype keys may only contain '.
          'lowercase latin letters ("a" through "z").',
          $subtype));
    }
  }

  public static function validateConfiguration($config) {
    if (!is_array($config)) {
      throw new Exception(
        pht(
          'Subtype configuration is invalid: it must be a list of subtype '.
          'specifications.'));
    }

    $map = array();
    foreach ($config as $value) {
      PhutilTypeSpec::checkMap(
        $value,
        array(
          'key' => 'string',
          'name' => 'string',
          'tag' => 'optional string',
          'color' => 'optional string',
          'icon' => 'optional string',
          'children' => 'optional map<string, wild>',
          'fields' => 'optional map<string, wild>',
          'mutations' => 'optional list<string>',
        ));

      $key = $value['key'];
      self::validateSubtypeKey($key);

      if (isset($map[$key])) {
        throw new Exception(
          pht(
            'Subtype configuration is invalid: two subtypes use the same '.
            'key ("%s"). Each subtype must have a unique key.',
            $key));
      }

      $map[$key] = true;

      $name = $value['name'];
      if (!strlen($name)) {
        throw new Exception(
          pht(
            'Subtype configuration is invalid: subtype with key "%s" has '.
            'no name. Subtypes must have a name.',
            $key));
      }

      $children = idx($value, 'children');
      if ($children) {
        PhutilTypeSpec::checkMap(
          $children,
          array(
            'subtypes' => 'optional list<string>',
            'forms' => 'optional list<string|int>',
          ));

        $child_subtypes = idx($children, 'subtypes');
        $child_forms = idx($children, 'forms');

        if ($child_subtypes && $child_forms) {
          throw new Exception(
            pht(
              'Subtype configuration is invalid: subtype with key "%s" '.
              'specifies both child subtypes and child forms. Specify one '.
              'or the other, but not both.',
              $key));
        }
      }

      $fields = idx($value, 'fields');
      if ($fields) {
        foreach ($fields as $field_key => $configuration) {
          PhutilTypeSpec::checkMap(
            $configuration,
            array(
              'disabled' => 'optional bool',
              'name' => 'optional string',
            ));
        }
      }
    }

    if (!isset($map[self::SUBTYPE_DEFAULT])) {
      throw new Exception(
        pht(
          'Subtype configuration is invalid: there is no subtype defined '.
          'with key "%s". This subtype is required and must be defined.',
          self::SUBTYPE_DEFAULT));
    }

    foreach ($config as $value) {
      $key = idx($value, 'key');

      $mutations = idx($value, 'mutations');
      if (!$mutations) {
        continue;
      }

      foreach ($mutations as $mutation) {
        if (!isset($map[$mutation])) {
          throw new Exception(
            pht(
              'Subtype configuration is invalid: subtype with key "%s" '.
              'specifies that it can mutate into subtype "%s", but that is '.
              'not a valid subtype.',
              $key,
              $mutation));
        }
      }
    }

  }

  public static function newSubtypeMap(array $config) {
    $map = array();

    foreach ($config as $entry) {
      $key = $entry['key'];
      $name = $entry['name'];

      $tag_text = idx($entry, 'tag');
      if ($tag_text === null) {
        if ($key != self::SUBTYPE_DEFAULT) {
          $tag_text = phutil_utf8_strtoupper($name);
        }
      }

      $color = idx($entry, 'color', 'blue');
      $icon = idx($entry, 'icon', 'fa-drivers-license-o');

      $subtype = id(new self())
        ->setKey($key)
        ->setName($name)
        ->setTagText($tag_text)
        ->setIcon($icon);

      if ($color) {
        $subtype->setColor($color);
      }

      $children = idx($entry, 'children', array());
      $child_subtypes = idx($children, 'subtypes');
      $child_forms = idx($children, 'forms');

      if ($child_subtypes) {
        $subtype->setChildSubtypes($child_subtypes);
      }

      if ($child_forms) {
        $subtype->setChildFormIdentifiers($child_forms);
      }

      $field_configurations = idx($entry, 'fields');
      if ($field_configurations) {
        foreach ($field_configurations as $field_key => $field_configuration) {
          $subtype->setSubtypeFieldConfiguration(
            $field_key,
            $field_configuration);
        }
      }

      $subtype->setMutations(idx($entry, 'mutations'));

      $map[$key] = $subtype;
    }

    return new PhabricatorEditEngineSubtypeMap($map);
  }

  public function newIconView() {
    return id(new PHUIIconView())
      ->setIcon($this->getIcon(), $this->getColor());
  }

}
