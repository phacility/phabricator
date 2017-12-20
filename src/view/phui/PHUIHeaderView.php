<?php

final class PHUIHeaderView extends AphrontTagView {

  const PROPERTY_STATUS = 1;

  private $header;
  private $tags = array();
  private $image;
  private $imageURL = null;
  private $imageEditURL = null;
  private $subheader;
  private $headerIcon;
  private $noBackground;
  private $bleedHeader;
  private $profileHeader;
  private $tall;
  private $properties = array();
  private $actionLinks = array();
  private $buttonBar = null;
  private $policyObject;
  private $epoch;
  private $actionItems = array();
  private $href;
  private $actionList;
  private $actionListID;

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setNoBackground($nada) {
    $this->noBackground = $nada;
    return $this;
  }

  public function setTall($tall) {
    $this->tall = $tall;
    return $this;
  }

  public function addTag(PHUITagView $tag) {
    $this->tags[] = $tag;
    return $this;
  }

  public function setImage($uri) {
    $this->image = $uri;
    return $this;
  }

  public function setImageURL($url) {
    $this->imageURL = $url;
    return $this;
  }

  public function setImageEditURL($url) {
    $this->imageEditURL = $url;
    return $this;
  }

  public function setSubheader($subheader) {
    $this->subheader = $subheader;
    return $this;
  }

  public function setBleedHeader($bleed) {
    $this->bleedHeader = $bleed;
    return $this;
  }

  public function setProfileHeader($bighead) {
    $this->profileHeader = $bighead;
    return $this;
  }

  public function setHeaderIcon($icon) {
    $this->headerIcon = $icon;
    return $this;
  }

  public function setActionList(PhabricatorActionListView $list) {
    $this->actionList = $list;
    return $this;
  }

  public function setActionListID($action_list_id) {
    $this->actionListID = $action_list_id;
    return $this;
  }

  public function setPolicyObject(PhabricatorPolicyInterface $object) {
    $this->policyObject = $object;
    return $this;
  }

  public function addProperty($property, $value) {
    $this->properties[$property] = $value;
    return $this;
  }

  public function addActionLink(PHUIButtonView $button) {
    $this->actionLinks[] = $button;
    return $this;
  }

  public function addActionItem($action) {
    $this->actionItems[] = $action;
    return $this;
  }

  public function setButtonBar(PHUIButtonBarView $bb) {
    $this->buttonBar = $bb;
    return $this;
  }

  public function setStatus($icon, $color, $name) {

    // TODO: Normalize "closed/archived" to constants.
    if ($color == 'dark') {
      $color = PHUITagView::COLOR_INDIGO;
    }

    $tag = id(new PHUITagView())
      ->setName($name)
      ->setIcon($icon)
      ->setColor($color)
      ->setType(PHUITagView::TYPE_SHADE);

    return $this->addProperty(self::PROPERTY_STATUS, $tag);
  }

  public function setEpoch($epoch) {
    $age = time() - $epoch;
    $age = floor($age / (60 * 60 * 24));
    if ($age < 1) {
      $when = pht('Today');
    } else if ($age == 1) {
      $when = pht('Yesterday');
    } else {
      $when = pht('%s Day(s) Ago', new PhutilNumber($age));
    }

    $this->setStatus('fa-clock-o bluegrey', null, pht('Updated %s', $when));
    return $this;
  }

  public function setHref($href) {
    $this->href = $href;
    return $this;
  }

  public function getHref() {
    return $this->href;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-header-view-css');

    $classes = array();
    $classes[] = 'phui-header-shell';

    if ($this->noBackground) {
      $classes[] = 'phui-header-no-background';
    }

    if ($this->bleedHeader) {
      $classes[] = 'phui-bleed-header';
    }

    if ($this->profileHeader) {
      $classes[] = 'phui-profile-header';
    }

    if ($this->properties || $this->policyObject ||
        $this->subheader || $this->tall) {
      $classes[] = 'phui-header-tall';
    }

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {

    if ($this->actionList || $this->actionListID) {
      $action_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('Actions'))
        ->setHref('#')
        ->setIcon('fa-bars')
        ->addClass('phui-mobile-menu');

      if ($this->actionList) {
        $action_button->setDropdownMenu($this->actionList);
      } else if ($this->actionListID) {
        $action_button->setDropdownMenuID($this->actionListID);
      }

      $this->addActionLink($action_button);
    }

    $image = null;
    if ($this->image) {
      $image_href = null;
      if ($this->imageURL) {
        $image_href = $this->imageURL;
      } else if ($this->imageEditURL) {
        $image_href = $this->imageEditURL;
      }

      $image = phutil_tag(
        'span',
        array(
          'class' => 'phui-header-image',
          'style' => 'background-image: url('.$this->image.')',
        ));

      if ($image_href) {
        $edit_view = null;
        if ($this->imageEditURL) {
          $edit_view = phutil_tag(
            'span',
            array(
              'class' => 'phui-header-image-edit',
            ),
            pht('Edit'));
        }

        $image = phutil_tag(
          'a',
          array(
            'href' => $image_href,
            'class' => 'phui-header-image-href',
          ),
          array(
            $image,
            $edit_view,
          ));
      }
    }

    $viewer = $this->getUser();

    $left = array();
    $right = array();

    $space_header = null;
    if ($viewer) {
      $space_header = id(new PHUISpacesNamespaceContextView())
        ->setUser($viewer)
        ->setObject($this->policyObject);
    }

    if ($this->actionLinks) {
      $actions = array();
      foreach ($this->actionLinks as $button) {
        if (!$button->getColor()) {
          $button->setColor(PHUIButtonView::GREY);
        }
        $button->addClass(PHUI::MARGIN_SMALL_LEFT);
        $button->addClass('phui-header-action-link');
        $actions[] = $button;
      }
      $right[] = phutil_tag(
        'div',
        array(
          'class' => 'phui-header-action-links',
        ),
        $actions);
    }

    if ($this->buttonBar) {
      $right[] = phutil_tag(
        'div',
        array(
          'class' => 'phui-header-action-links',
        ),
        $this->buttonBar);
    }

    if ($this->actionItems) {
      $action_list = array();
      if ($this->actionItems) {
        foreach ($this->actionItems as $item) {
          $action_list[] = phutil_tag(
            'li',
            array(
              'class' => 'phui-header-action-item',
            ),
            $item);
        }
      }
      $right[] = phutil_tag(
        'ul',
          array(
            'class' => 'phui-header-action-list',
          ),
          $action_list);
    }

    $icon = null;
    if ($this->headerIcon) {
      $icon = id(new PHUIIconView())
        ->setIcon($this->headerIcon)
        ->addClass('phui-header-icon');
    }

    $header_content = $this->header;

    $href = $this->getHref();
    if ($href !== null) {
      $header_content = phutil_tag(
        'a',
        array(
          'href' => $href,
        ),
        $header_content);
    }

    $left[] = phutil_tag(
      'span',
      array(
        'class' => 'phui-header-header',
      ),
      array(
        $space_header,
        $icon,
        $header_content,
      ));

    if ($this->subheader) {
      $left[] = phutil_tag(
        'div',
        array(
          'class' => 'phui-header-subheader',
        ),
        array(
          $this->subheader,
        ));
    }

    if ($this->properties || $this->policyObject || $this->tags) {
      $property_list = array();
      foreach ($this->properties as $type => $property) {
        switch ($type) {
          case self::PROPERTY_STATUS:
            $property_list[] = $property;
          break;
          default:
            throw new Exception(pht('Incorrect Property Passed'));
          break;
        }
      }

      if ($this->policyObject) {
        $property_list[] = $this->renderPolicyProperty($this->policyObject);
      }

      if ($this->tags) {
        $property_list[] = $this->tags;
      }

      $left[] = phutil_tag(
        'div',
        array(
          'class' => 'phui-header-subheader',
        ),
        $property_list);
    }

    // We here at @phabricator
    $header_image = null;
    if ($image) {
    $header_image = phutil_tag(
      'div',
      array(
        'class' => 'phui-header-col1',
      ),
      $image);
    }

    // All really love
    $header_left = phutil_tag(
      'div',
      array(
        'class' => 'phui-header-col2',
      ),
      $left);

    // Tables and Pokemon.
    $header_right = phutil_tag(
      'div',
      array(
        'class' => 'phui-header-col3',
      ),
      $right);

    $header_row = phutil_tag(
      'div',
      array(
        'class' => 'phui-header-row',
      ),
      array(
        $header_image,
        $header_left,
        $header_right,
      ));

    return phutil_tag(
      'h1',
      array(
        'class' => 'phui-header-view',
      ),
      $header_row);
  }

  private function renderPolicyProperty(PhabricatorPolicyInterface $object) {
    $viewer = $this->getUser();

    $policies = PhabricatorPolicyQuery::loadPolicies($viewer, $object);

    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    $policy = idx($policies, $view_capability);
    if (!$policy) {
      return null;
    }

    // If an object is in a Space with a strictly stronger (more restrictive)
    // policy, we show the more restrictive policy. This better aligns the
    // UI hint with the actual behavior.

    // NOTE: We'll do this even if the viewer has access to only one space, and
    // show them information about the existence of spaces if they click
    // through.
    $use_space_policy = false;
    if ($object instanceof PhabricatorSpacesInterface) {
      $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
        $object);

      $spaces = PhabricatorSpacesNamespaceQuery::getViewerSpaces($viewer);
      $space = idx($spaces, $space_phid);
      if ($space) {
        $space_policies = PhabricatorPolicyQuery::loadPolicies(
          $viewer,
          $space);
        $space_policy = idx($space_policies, $view_capability);
        if ($space_policy) {
          if ($space_policy->isStrongerThan($policy)) {
            $policy = $space_policy;
            $use_space_policy = true;
          }
        }
      }
    }

    $container_classes = array();
    $container_classes[] = 'policy-header-callout';
    $phid = $object->getPHID();

    // If we're going to show the object policy, try to determine if the object
    // policy differs from the default policy. If it does, we'll call it out
    // as changed.
    if (!$use_space_policy) {
      $default_policy = PhabricatorPolicyQuery::getDefaultPolicyForObject(
        $viewer,
        $object,
        $view_capability);
      if ($default_policy) {
        if ($default_policy->getPHID() != $policy->getPHID()) {
          $container_classes[] = 'policy-adjusted';
          if ($default_policy->isStrongerThan($policy)) {
            // The policy has strictly been weakened. For example, the
            // default might be "All Users" and the current policy is "Public".
            $container_classes[] = 'policy-adjusted-weaker';
          } else if ($policy->isStrongerThan($default_policy)) {
            // The policy has strictly been strengthened, and is now more
            // restrictive than the default. For example, "All Users" has
            // been replaced with "No One".
            $container_classes[] = 'policy-adjusted-stronger';
          } else {
            // The policy has been adjusted but not strictly strengthened
            // or weakened. For example, "Members of X" has been replaced with
            // "Members of Y".
            $container_classes[] = 'policy-adjusted-different';
          }
        }
      }
    }

    $policy_name = array($policy->getShortName());
    $policy_icon = $policy->getIcon().' bluegrey';

    if ($object instanceof PhabricatorPolicyCodexInterface) {
      $codex = PhabricatorPolicyCodex::newFromObject($object, $viewer);

      $codex_name = $codex->getPolicyShortName($policy, $view_capability);
      if ($codex_name !== null) {
        $policy_name = $codex_name;
      }

      $codex_icon = $codex->getPolicyIcon($policy, $view_capability);
      if ($codex_icon !== null) {
        $policy_icon = $codex_icon;
      }

      $codex_classes = $codex->getPolicyTagClasses($policy, $view_capability);
      foreach ($codex_classes as $codex_class) {
        $container_classes[] = $codex_class;
      }
    }

    if (!is_array($policy_name)) {
      $policy_name = (array)$policy_name;
    }

    $arrow = id(new PHUIIconView())
      ->setIcon('fa-angle-right')
      ->addClass('policy-tier-separator');

    $policy_name = phutil_implode_html($arrow, $policy_name);

    $icon = id(new PHUIIconView())
      ->setIcon($policy_icon);

    $link = javelin_tag(
      'a',
      array(
        'class' => 'policy-link',
        'href' => '/policy/explain/'.$phid.'/'.$view_capability.'/',
        'sigil' => 'workflow',
      ),
      $policy_name);

    return phutil_tag(
      'span',
      array(
        'class' => implode(' ', $container_classes),
      ),
      array($icon, $link));
  }

}
