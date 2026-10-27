<?php
namespace App\Kitchen\core\Support;
/** Tablet preference, not an authorization boundary. Scope includes the current shop. */
final class ProductionSector
{
    public const COOKIE='kitchen_production_sector';
    public static function current(int $shop): ?int
    {
        $value=json_decode($_COOKIE[self::COOKIE]??'',true);
        return is_array($value) && ($value['shop']??null)===$shop && is_int($value['sector']??null) && $value['sector']>0?$value['sector']:null;
    }
    public static function name(int $shop): ?string {$v=json_decode($_COOKIE[self::COOKIE]??'',true);return self::current($shop)!==null && is_string($v['name']??null)?$v['name']:null;}
    public static function save(int $shop,?int $sector,?string $name=null): void
    {
        $value=json_encode(['shop'=>$shop,'sector'=>$sector,'name'=>$name],JSON_THROW_ON_ERROR);
        setcookie(self::COOKIE,$value,['expires'=>time()+31536000,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
        $_COOKIE[self::COOKIE]=$value;
    }
}
