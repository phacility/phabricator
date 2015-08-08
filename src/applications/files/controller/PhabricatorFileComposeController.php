<?php

final class PhabricatorFileComposeController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $colors = array(
      'red' => pht('Verbillion'),
      'orange' => pht('Navel Orange'),
      'yellow' => pht('Prim Goldenrod'),
      'green' => pht('Lustrous Verdant'),
      'blue' => pht('Tropical Deep'),
      'sky' => pht('Wide Open Sky'),
      'indigo' => pht('Pleated Khaki'),
      'violet' => pht('Aged Merlot'),
      'pink' => pht('Easter Bunny'),
      'charcoal' => pht('Gemstone'),
      'backdrop' => pht('Driven Snow'),
    );

    $manifest = PHUIIconView::getSheetManifest(PHUIIconView::SPRITE_PROJECTS);

    if ($request->isFormPost()) {
      $project_phid = $request->getStr('projectPHID');
      if ($project_phid) {
        $project = id(new PhabricatorProjectQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($project_phid))
          ->requireCapabilities(
            array(
              PhabricatorPolicyCapability::CAN_VIEW,
              PhabricatorPolicyCapability::CAN_EDIT,
            ))
          ->executeOne();
        if (!$project) {
          return new Aphront404Response();
        }
        $icon = $project->getIcon();
        $color = $project->getColor();
        switch ($color) {
          case 'grey':
            $color = 'charcoal';
            break;
          case 'checkered':
            $color = 'backdrop';
            break;
        }
      } else {
        $icon = $request->getStr('icon');
        $color = $request->getStr('color');
      }

      if (!isset($colors[$color]) || !isset($manifest['projects-'.$icon])) {
        return new Aphront404Response();
      }

      $root = dirname(phutil_get_library_root('phabricator'));
      $icon_file = $root.'/resources/sprite/projects_2x/'.$icon.'.png';
      $icon_data = Filesystem::readFile($icon_file);


      $data = $this->composeImage($color, $icon_data);

      $file = PhabricatorFile::buildFromFileDataOrHash(
        $data,
        array(
          'name' => 'project.png',
          'profile' => true,
          'canCDN' => true,
        ));

      if ($project_phid) {
        $edit_uri = '/project/profile/'.$project->getID().'/';

        $xactions = array();
        $xactions[] = id(new PhabricatorProjectTransaction())
          ->setTransactionType(PhabricatorProjectTransaction::TYPE_IMAGE)
          ->setNewValue($file->getPHID());

        $editor = id(new PhabricatorProjectTransactionEditor())
          ->setActor($viewer)
          ->setContentSourceFromRequest($request)
          ->setContinueOnMissingFields(true)
          ->setContinueOnNoEffect(true);

        $editor->applyTransactions($project, $xactions);

        return id(new AphrontRedirectResponse())->setURI($edit_uri);
      } else {
        $content = array(
          'phid' => $file->getPHID(),
        );

        return id(new AphrontAjaxResponse())->setContent($content);
      }
    }

    $value_color = head_key($colors);
    $value_icon = head_key($manifest);
    $value_icon = substr($value_icon, strlen('projects-'));

    require_celerity_resource('people-profile-css');

    $buttons = array();
    foreach ($colors as $color => $name) {
      $buttons[] = javelin_tag(
        'button',
        array(
          'class' => 'grey profile-image-button',
          'sigil' => 'has-tooltip compose-select-color',
          'style' => 'margin: 0 8px 8px 0',
          'meta' => array(
            'color' => $color,
            'tip' => $name,
          ),
        ),
        id(new PHUIIconView())
          ->addClass('compose-background-'.$color));
    }

     $sort_these_first = array(
      'projects-fa-briefcase',
      'projects-fa-tags',
      'projects-fa-folder',
      'projects-fa-group',
      'projects-fa-bug',
      'projects-fa-trash-o',
      'projects-fa-calendar',
      'projects-fa-flag-checkered',
      'projects-fa-envelope',
      'projects-fa-truck',
      'projects-fa-lock',
      'projects-fa-umbrella',
      'projects-fa-cloud',
      'projects-fa-building',
      'projects-fa-credit-card',
      'projects-fa-flask',
    );

    $manifest = array_select_keys(
      $manifest,
      $sort_these_first)
    + $manifest;

    $icons = array();

    $icon_quips = array(
      '8ball' => pht('Take a Risk'),
      'alien' => pht('Foreign Interface'),
      'announce' => pht('Louder is Better'),
      'art' => pht('Unique Snowflake'),
      'award' => pht('Shooting Star'),
      'bacon' => pht('Healthy Vegetables'),
      'bandaid' => pht('Durable Infrastructure'),
      'beer' => pht('Healthy Vegetable Juice'),
      'bomb' => pht('Imminent Success'),
      'briefcase' => pht('Adventure Pack'),
      'bug' => pht('Costumed Egg'),
      'calendar' => pht('Everyone Loves Meetings'),
      'cloud' => pht('Water Cycle'),
      'coffee' => pht('Half-Whip Nonfat Soy Latte'),
      'creditcard' => pht('Expense It'),
      'death' => pht('Calcium Promotes Bone Health'),
      'desktop' => pht('Magical Portal'),
      'dropbox' => pht('Cardboard Box'),
      'education' => pht('Debt'),
      'experimental' => pht('CAUTION: Dangerous Chemicals'),
      'facebook' => pht('Popular Social Network'),
      'facility' => pht('Pollution Solves Problems'),
      'film' => pht('Actual Physical Film'),
      'forked' => pht('You Can\'t Eat Soup'),
      'games' => pht('Serious Business'),
      'ghost' => pht('Haunted'),
      'gift' => pht('Surprise!'),
      'globe' => pht('Scanner Sweep'),
      'golf' => pht('Business Meeting'),
      'heart' => pht('Undergoing a Major Surgery'),
      'intergalactic' => pht('Jupiter'),
      'lock' => pht('Extremely Secret'),
      'mail' => pht('Oragami'),
      'martini' => pht('Healthy Olive Drink'),
      'medical' => pht('Medic!'),
      'mobile' => pht('Cellular Telephone'),
      'music' => pht("\xE2\x99\xAB"),
      'news' => pht('Actual Physical Newspaper'),
      'orgchart' => pht('It\'s Good to be King'),
      'peoples' => pht('Angel and Devil'),
      'piechart' => pht('Actual Physical Pie'),
      'poison' => pht('Healthy Bone Juice'),
      'putabirdonit' => pht('Put a Bird On It'),
      'radiate' => pht('Radiant Beauty'),
      'savings' => pht('Oink Oink'),
      'search' => pht('Sleuthing'),
      'shield' => pht('Royal Crest'),
      'speed' => pht('Slow and Steady'),
      'sprint' => pht('Fire Exit'),
      'star' => pht('The More You Know'),
      'storage' => pht('Stack of Pancakes'),
      'tablet' => pht('Cellular Telephone For Giants'),
      'travel' => pht('Pretty Clearly an Airplane'),
      'twitter' => pht('Bird Stencil'),
      'warning' => pht('No Caution Required, Everything Looks Safe'),
      'whale' => pht('Friendly Walrus'),
      'fa-flask' => pht('Experimental'),
      'fa-briefcase' => pht('Briefcase'),
      'fa-bug' => pht('Bug'),
      'fa-building' => pht('Company'),
      'fa-calendar' => pht('Deadline'),
      'fa-cloud' => pht('The Cloud'),
      'fa-credit-card' => pht('Accounting'),
      'fa-envelope' => pht('Communication'),
      'fa-flag-checkered' => pht('Goal'),
      'fa-folder' => pht('Folder'),
      'fa-group' => pht('Team'),
      'fa-lock' => pht('Policy'),
      'fa-tags' => pht('Tag'),
      'fa-trash-o' => pht('Garbage'),
      'fa-truck' => pht('Release'),
      'fa-umbrella' => pht('An Umbrella'),
    );

    foreach ($manifest as $icon => $spec) {
      $icon = substr($icon, strlen('projects-'));

      $icons[] = javelin_tag(
        'button',
        array(
          'class' => 'grey profile-image-button',
          'sigil' => 'has-tooltip compose-select-icon',
          'style' => 'margin: 0 8px 8px 0',
          'meta' => array(
            'icon' => $icon,
            'tip' => idx($icon_quips, $icon, $icon),
          ),
        ),
        id(new PHUIIconView())
          ->setSpriteIcon($icon)
          ->setSpriteSheet(PHUIIconView::SPRITE_PROJECTS));
    }

    $dialog_id = celerity_generate_unique_node_id();
    $color_input_id = celerity_generate_unique_node_id();
    $icon_input_id = celerity_generate_unique_node_id();
    $preview_id = celerity_generate_unique_node_id();

    $preview = id(new PHUIIconView())
      ->setID($preview_id)
      ->addClass('compose-background-'.$value_color)
      ->setSpriteIcon($value_icon)
      ->setSpriteSheet(PHUIIconView::SPRITE_PROJECTS);

    $color_input = javelin_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'color',
        'value' => $value_color,
        'id' => $color_input_id,
      ));

    $icon_input = javelin_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'icon',
        'value' => $value_icon,
        'id' => $icon_input_id,
      ));

    Javelin::initBehavior('phabricator-tooltips');
    Javelin::initBehavior(
      'icon-composer',
      array(
        'dialogID' => $dialog_id,
        'colorInputID' => $color_input_id,
        'iconInputID' => $icon_input_id,
        'previewID' => $preview_id,
        'defaultColor' => $value_color,
        'defaultIcon' => $value_icon,
      ));

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setFormID($dialog_id)
      ->setClass('compose-dialog')
      ->setTitle(pht('Compose Image'))
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'compose-header',
          ),
          pht('Choose Background Color')))
      ->appendChild($buttons)
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'compose-header',
          ),
          pht('Choose Icon')))
      ->appendChild($icons)
      ->appendChild(
        phutil_tag(
          'div',
          array(
            'class' => 'compose-header',
          ),
          pht('Preview')))
      ->appendChild($preview)
      ->appendChild($color_input)
      ->appendChild($icon_input)
      ->addCancelButton('/')
      ->addSubmitButton(pht('Save Image'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  private function composeImage($color, $icon_data) {
    $icon_img = imagecreatefromstring($icon_data);

    $map = id(new CelerityResourceTransformer())
      ->getCSSVariableMap();

    $color_string = idx($map, $color, '#ff00ff');
    $color_const = hexdec(trim($color_string, '#'));

    $canvas = imagecreatetruecolor(100, 100);
    imagefill($canvas, 0, 0, $color_const);

    imagecopy($canvas, $icon_img, 0, 0, 0, 0, 100, 100);

    return PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $canvas,
      'image/png');
  }

}
