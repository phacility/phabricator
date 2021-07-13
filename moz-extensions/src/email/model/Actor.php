<?php


class Actor
{
  public string $userName;
  public string $realName;

  public function __construct(string $userName, string $realName)
  {
    $this->userName = $userName;
    $this->realName = $realName;
  }

  public static function from(PhabricatorUser $user): Actor {
    return new Actor($user->getUserName(), $user->getRealName());
  }
}