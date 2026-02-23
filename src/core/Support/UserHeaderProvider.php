<?php
namespace App\Supplier\core\Support;


class UserHeaderProvider
{
    public function getLanguage()
    {
        return GlobalRegistry::get('lang_code');
    }
}