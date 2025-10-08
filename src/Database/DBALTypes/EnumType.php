<?php

namespace Laravel\Lumen\Database\DBALTypes;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

class EnumType extends Type {

  const ENUM = "enum";

  public function getName() {
    return self::ENUM;
  }

  public function getSQLDeclaration(array $column, AbstractPlatform $platform) {
    //$length = $column['length'] ?? [];
    return $platform->getClobTypeDeclarationSQL($column);
    //return sprintf("enum('%s')", implode("','", $length));
  }
  
  public function convertToPHPValue ($value, AbstractPlatform $platform) {
    return (null === $value) ? null : (string) $value;
  }

  public function convertToDatabaseValue($value, AbstractPlatform $platform) {
    return $value;
  }

  public function getBindingType () {
    return self::ENUM;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresSQLCommentHint(AbstractPlatform $platform)
  {
    return true;
  }
}
