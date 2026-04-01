<?php

namespace App\Kitchen\app\Repositories\Knowledge\Recipe;


use App\Kitchen\app\Models\Knowledge\Recipe\RecipeModel;
use App\Kitchen\core\Http\ApiClient;

class RecipeRepository
{
    public function __construct(
        private ApiClient $apiClient
    )
    {
    }

    public function getAll($langCode = "pl")
    {
        $queryStr = "?" . http_build_query([
            'lang_code' => $langCode
        ]);

        $resp = $this->apiClient->get("/recipes$queryStr");
        return new RecipeModel($resp['data']) ?? null;
    }

}