<?php

final class PhabricatorPeopleTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function generate() {

    while (true) {
      try {
        $realname = $this->generateRealname();
        $username = $this->generateUsername($realname);
        $email = $this->generateEmail($username);

        $admin = PhabricatorUser::getOmnipotentUser();
        $user = new PhabricatorUser();
        $user->setUsername($username);
        $user->setRealname($realname);

        $email_object = id(new PhabricatorUserEmail())
          ->setAddress($email)
          ->setIsVerified(1);

        id(new PhabricatorUserEditor())
          ->setActor($admin)
          ->createNewUser($user, $email_object);

        return $user;
      } catch (AphrontDuplicateKeyQueryException $ex) {}
    }
  }

  protected function generateRealname() {
    $realname_generator = new PhutilRealNameContextFreeGrammar();
    $random_real_name = $realname_generator->generate();
    return $random_real_name;
  }

  protected function generateUsername($random_real_name) {
    $name = strtolower($random_real_name);
    $name = preg_replace('/[^a-z]/s'  , ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    $words = explode(' ', $name);
    $random = rand(0, 4);
    $reduced = '';
    if ($random == 0) {
      foreach ($words as $w) {
         if ($w == end($words)) {
          $reduced .= $w;
        } else {
          $reduced .= $w[0];
        }
      }
    } else if ($random == 1) {
        foreach ($words as $w) {
          if ($w == $words[0]) {
            $reduced .= $w;
          } else {
            $reduced .= $w[0];
          }
        }
    } else if ($random == 2) {
        foreach ($words as $w) {
          if ($w == $words[0] || $w == end($words)) {
            $reduced .= $w;
          } else {
            $reduced .= $w[0];
          }
        }
    } else if ($random == 3) {
        foreach ($words as $w) {
          if ($w == $words[0] || $w == end($words)) {
            $reduced .= $w;
          } else {
            $reduced .= $w[0].'.';
          }
        }
      } else if ($random == 4) {
        foreach ($words as $w) {
          if ($w == $words[0] || $w == end($words)) {
            $reduced .= $w;
          } else {
            $reduced .= $w[0].'_';
          }
        }
      }
      $random1 = rand(0, 4);
      if ($random1 >= 1) {
        $reduced = ucfirst($reduced);
      }
      $username = $reduced;
      return $username;
  }

  protected function generateEmail($username) {
    $default_email_domain = 'example.com';
    $email = $username.'@'.$default_email_domain;
    return $email;
  }
}
