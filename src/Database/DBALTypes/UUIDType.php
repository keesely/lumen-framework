<?php
namespace Laravel\Lumen\Database\DBALTypes;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class UUIDType extends Type {

  public function getName() {
    return 'char(36)';
  }

  public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform) {
    return 'char(36)';
  }

  public function convertToPHPValue($value, AbstractPlatform $platform) {
    return (null === $value) ? null : intval($value);
  }

  public function getBindingType() {
    return 'char(36)';
  }
}
