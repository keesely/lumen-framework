<?php
namespace Laravel\Lumen\Database\DBALTypes;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class TinyIntegerType extends Type {

  const TINYINT = "tinyint";

  public function getName() {
    return self::TINYINT;
  }

  public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) {
    return self::TINYINT;
    //return $platform->getIntegerTypeDeclarationSQL($fieldDeclaration);
  }

  public function convertToPHPValue($value, AbstractPlatform $platform) {
    return (null === $value) ? null : intval($value);
  }

  public function getBindingType() {
    return 'tinyint';
  }
}
