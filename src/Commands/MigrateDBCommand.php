<?php

namespace Package\LumenExtra\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class MigrateDBCommand extends Command {
  protected $signature = 'migratedb:flush {file}';

  protected $description = '根据config/databases 文件内容更新数据库';

  protected $_types = [];

  public function __construct() {
    \Doctrine\DBAL\Types\Type::addType('tinyinteger', \Package\LumenExtra\Support\DBALTypes\TinyIntegerType::class);
    \Doctrine\DBAL\Types\Type::addType('timestamp', \Package\LumenExtra\Support\DBALTypes\TimestampType::class);
    \Doctrine\DBAL\Types\Type::addType('uuid', \Package\LumenExtra\Support\DBALTypes\UUIDType::class);
    \Doctrine\DBAL\Types\Type::addType('enum', \Package\LumenExtra\Support\DBALTypes\EnumType::class);
    $this->_types = \Doctrine\DBAL\Types\Type::getTypesMap();

    parent::__construct();
  }

  public function handle() {
    $dbfile = $this->argument('file');
    $dbfile = config_path('databases/'. $dbfile . '.php');
    if (!file_exists($dbfile)) return $this->error($dbfile . ' 资源文件不存在');

    $structs = include($dbfile);

    if (!is_array($structs)) return $this->error('无效的资源结构体: 资源结构体应该是数组');

    foreach ($structs as $tab => $struct) {

      if (!$struct) {
        $this->error("TABLE {$tab} Struct is empty!");
        continue;
      }

      if (Schema::hasTable($tab)) {
        $this->info("TABLE [{$tab}] is exists");
        $this->upset($tab, $struct);
        $this->info("UPDATE TABLE [{$tab}]");
      }
      else {
        $this->create($tab, $struct);
        $this->info('CREATE TABLE: '.$tab);
      }
    }
  }

  protected function create ($tab, array $struct) {
    $fieldset  = isset($struct['fieldset']) ? $struct['fieldset'] : [];
    $increment = isset($struct['increment'])? $struct['increment']: null;
    $primary   = isset($struct['primary'])  ? $struct['primary']  : null;
    $index     = isset($struct['index'])    ? $struct['index']    : null;
    $timestamp = isset($struct['timestamp'])? $struct['timestamp']: null;
    $tabComment= array_get($struct, 'name');

    if (is_array($timestamp)) {
      foreach ($timestamp as $time) {
        $fieldset[$time] = ['datetime', null, null, false, null];
      } 
    }

    Schema::create($tab, function (Blueprint $table) use($tab, $fieldset, $increment, $primary, $index, $timestamp) {
      foreach ($fieldset as $key => $field) {
        if (count($field) < 5) {
          $this->error("TABLE: {$tab} FIELDSET {$key} define err");
          continue;
        }

        @list($typeof, $length, $comment, $nullable, $default) = $field;
        $unique = array_get($field, 5);

        if ($increment && is_string($increment)) $increment = [$increment];
        elseif (null == $increment) $increment = [];

        $set = $this->setKey($table, $tab, $key, $typeof, $length, $increment);
        if ($unique) $set->unique();

        if (false === $set) continue;

        if ($comment && is_string($comment)) $set->comment($comment);
        if (true != $nullable) $set->nullable();
        if (null !== $default) $set->default($default);
      }

      if (!$increment && $primary) $table->primary($primary);
      if (is_array($index)) {
        if (!is_array(current($index))) $index = [$index];
        foreach ($index as $idx) $table->index($idx);
      }
    });
    if ($tabComment) \DB::statement("ALTER TABLE `{$tab}` comment '{$tabComment}'");

  }

  protected function upset ($tab, array $struct) {
    $fieldset  = isset($struct['fieldset']) ? $struct['fieldset'] : [];
    $increment = isset($struct['increment'])? $struct['increment']: null;
    $primary   = isset($struct['primary'])  ? $struct['primary']  : null;
    $index     = isset($struct['index'])    ? $struct['index']    : null;
    $timestamp = isset($struct['timestamp'])? $struct['timestamp']: null;
    $tabComment= array_get($struct, 'name');

    if (is_array($timestamp)) {
      foreach ($timestamp as $time) {
        //$fieldset[$time] = ['timestamp', null, null, false, null];
        $fieldset[$time] = ['datetime', null, null, false, null];
      } 
    }

    Schema::getConnection()->getDoctrineSchemaManager()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'enum');
    Schema::table($tab, function (Blueprint $table) use($tab, $fieldset, $increment, $primary, $index, $timestamp) {
      $before = null;

      foreach ($fieldset as $key => $field) {
        if (count($field) < 5) {
          $this->error("TABLE: {$tab} FIELDSET {$key} define err");
          continue;
        }

        @list($typeof, $length, $comment, $nullable, $default) = $field;
        $unique = array_get($field, 5);

        if ($increment && is_string($increment)) $increment = [$increment];
        elseif (null == $increment) $increment = [];

        $has = Schema::hasColumn($tab, $key);
        if ($has) {
          $this->info("{$key} typeof " . Schema::getColumnType($tab, $key));
          $type = Schema::getColumnType($tab,$key);
          if ('datetime' == $type && 'timestamp' == $typeof) continue;
          //elseif ('integer' == $type && 'int' == $typeof) continue;
          //elseif ($type == $typeof) continue;
        }

        $set = $this->setKey($table, $tab, $key, $typeof, $length, $increment);

        if (false === $set) continue;

        if ($unique && !$set->hasUnique()) $set->unique();
        if ($comment && is_string($comment)) $set->comment($comment);
        if (true != $nullable) $set->nullable();
        if (null !== $default) $set->default($default);
        else $set->default(null);

        if ($has) $set->change();
      }

      if (is_array($index)) {
        if (!is_array(current($index))) $index = [$index];
        foreach ($index as $idx) {
          if (!$this->hasIndex($tab, $idx)) $table->index($idx);
        }
      }

      if ($primary && !$this->hasPrimary($tab, $primary)) {
        $this->info('PRIMARY KEY '.$primary);
        $table->primary([$primary]);
      }
    });
    if ($tabComment) \DB::statement("ALTER TABLE `{$tab}` comment '{$tabComment}'");
  }

  protected function setKey (Blueprint $table,$tab, $key, $typeof, $length, $increments) {
    if (in_array($typeof, ['varchar', 'char', 'string'])) return $table->string($key, $length);

    elseif ('int' == $typeof)     return $table->integer($key, (in_array($key, $increments)));
    elseif ('tinyint' == $typeof) return $table->tinyInteger($key, in_array($key, $increments));
    elseif ('bigint' == $typeof)  return $table->bigInteger($key, in_array($key, $increments));
    elseif ('double' == $typeof)  {
      if (!is_array($length)) $length = [$length];
      return $table->double($key, $length[0], isset($length[1]) ? $length[1] : 0);
    }

    elseif ('enum' == $typeof) {
      if (!is_array($length)) {
        $this->error("TABLE: {$tab} FIELDSET {$key} TYPEOF enum, BUT LENGTH is not array");
        return false;
      }
      if (!Schema::hasColumn($tab, $key)) return $table->enum($key, $length);
      $type = Schema::getColumnType($tab,$key);
      // 强制触发更新
      if ($type == $typeof) \DB::statement("ALTER TABLE `{$tab}` MODIFY COLUMN `{$key}` varchar(255)");
      return $table->addColumn('enum', $key, ['platformoptions' => compact('length')]);
    }
    elseif (in_array($typeof, ['float', 'boolean', 'text', 'blob', 'binary', 'datetime', 'timestamp', 'date', 'time'])) {
      return $table->$typeof($key);
    }
    elseif ('decimal' == $typeof) {
      if (!is_array($length) || count($length) < 1) {
        $this->error("TABLE: {$tab} FIELDSET {$key} TYPEOF decimal, BUT LENGTH is not array['precision', 'scale']");
        return false;
      }
      return $table->decimal($key, $length[0], $length[1]);
    }
    elseif ('uuid' == $typeof)      return $table->uuid($key);
    elseif ('json' == $typeof)      return $table->json($key);
    elseif ('jsonb' == $typeof)     return $table->jsonb($key);
    elseif ('longtext' == $typeof)  return $table->longtext($key);
    elseif ('ipaddress' == $typeof) return $table->ipAddress($key);
    elseif ('macaddress' == $typeof)return $table->macAddress($key);
    else {
      $this->error("TABLE: {$tab} FIELDSET {$key} TYPEOF {$typeof} IS NOT DEFINE");
      return false;
    }
    return $set;
  }

  protected function hasIndex($table, array $columns) {
    $name = $this->createIndexName($table, 'index', $columns);
    $conn = Schema::getConnection();
    $dbSchemaManager = $conn->getDoctrineSchemaManager();
    $doctrineTable = $dbSchemaManager->listTableDetails($table);
    return $doctrineTable->hasIndex($name);
  }

  public function hasPrimary ($table, $primary) {
    $conn = Schema::getConnection();
    $dbSchemaManager = $conn->getDoctrineSchemaManager();
    $doctrineTable = $dbSchemaManager->listTableDetails($table);
    return $doctrineTable->hasPrimaryKey($primary);
  }

  protected function createIndexName($tab, $type, array $columns) {
    $index = strtolower($tab.'_'.implode('_', $columns).'_'.$type);
    return str_replace(['-', '.'], '_', $index);
  }

}
