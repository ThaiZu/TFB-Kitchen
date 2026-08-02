<?php

namespace App\Kitchen\app\Http\Controllers;


use App\Kitchen\core\Exceptions\DataNotFoundException;
use App\Kitchen\core\Exceptions\ProtectedResourceException;
use App\Kitchen\core\Support\DeviceMode;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Twig\AppExtension;
use Exception;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

class Controller
{
    public $errors = []; // krytyczne błędy, blokują działanie
    public $information = []; // informacje niezwiązane bezpośrednio z akcją
    public $warnings = []; // błędy operacji, nie blokują GUI
    public $successes = []; // sukcesy operacji
    public $ignored_messages = [];

    /**
     * Bezpieczne pobieranie danych z serwisu z obsługą wyjątków.
     * @param callable $callback - Funkcja zwracająca dane.
     * @param array &$errors - Tablica błędów do zapisu komunikatów.
     * @param mixed $default - Wartość domyślna w przypadku błędu (np. pusta tablica).
     * @return mixed - Zwraca wynik funkcji lub wartość domyślną.
     */
    protected function safeFetch(callable $callback, array &$errors, mixed $params = null, $default = []) {
        try {
            if ($params === null) {
                return call_user_func($callback);
            } elseif (is_array($params)) {
                return call_user_func_array($callback, $params);
            } else {
                return call_user_func($callback, $params);
            }
        } catch (DataNotFoundException $e) {
            $errors[] = $e->getMessage();
            return $default;
        } catch (ProtectedResourceException $e) {
            $errors[] = $e->getMessage();
            return $default;
        } catch (Exception $e) {
            $errors[] = "Nieoczekiwany błąd: " . $e->getMessage();
            error_log($e->getMessage());
            return $default;
        }
    }

    public function view($name, $data = [])
    {
        $reflector = new ReflectionClass($this);
        $namespace = $reflector->getNamespaceName();

        $baseViewPath =  __DIR__ . "/../../../app/";
        if (strpos($namespace, 'App\\Kitchen\\app\\Http\\Controllers') !== false) {
            $baseViewPath .= "Views/";
        }

        $moduleName = "login";
        $splittedPathElems = explode("/", $name);
        //nazwą modułu jest katalog w którym znajdują się pliki otwierane przez Controller/view
        if(isset($splittedPathElems[0])){
            $moduleName = $splittedPathElems[0];
        }

        $langCode = GlobalRegistry::get('lang_code');

        $globalTranslations = loadTranslations('page', $langCode, 'page_components');
        $moduleTranslations = loadTranslations('page', $langCode, $moduleName);

        $data['translations'] = array_merge($globalTranslations, $moduleTranslations);

        $data['errors'] = $this->errors;
        $data['information'] = $this->information;
        $data['warnings'] = $this->warnings;
        $data['successes'] = $this->successes;

        $data['ROOT'] = ROOT;
        $data['shared_files_url'] = SHARED_FILES_URL;
        // Toutes les pages portent la version servie : c'est ce qui permet de
        // distinguer « la fonctionnalité n'y est pas » de « le navigateur
        // montre une page d'hier ».
        $data['app_build'] = APP_BUILD;
        $data['current_path'] = '/' . trim($_GET['url'] ?? '', '/');

        // Le mode de la tablette décide de la navigation, donc de toutes les
        // pages : il est exposé ici, au seul endroit par où passent toutes les
        // vues, plutôt que répété dans chaque contrôleur. Voir
        // core/Support/DeviceMode et docs/MODE_TABLETTE.md.
        $mode  = DeviceMode::current();
        $rules = DeviceMode::rules();

        // Ces valeurs sont des DÉFAUTS, pas des surcharges : un contrôleur qui
        // a déjà tranché garde son verdict. Sans cette règle, l'écran WebShop
        // ne pouvait pas dire « jeton révoqué » — le calcul générique, qui ne
        // connaît que les cookies, écrasait sa réponse par « non configuré ».
        $defaults = [
            'device_mode' => $mode,
            'nav_keys'    => $rules->navKeys($mode),
            'tab_keys'    => $rules->tabKeys($mode),
            'mode_home'   => $rules->home($mode),
            // La vue courante d'un module à onglets internes : la barre du bas
            // en a besoin pour savoir lequel de ses trois onglets est actif,
            // tous trois pointant sur le même chemin.
            'current_view' => $_GET['view'] ?? '',
            // Le menu doit savoir si l'entrée WebShop mène quelque part avant
            // de l'afficher : une entrée qui ouvre une page d'erreur est une
            // entrée morte, et le brief n'en veut aucune.
            'webshop_blocker' => $rules->webshopBlocker(
                DeviceMode::webshopBase(),
                DeviceMode::webshopToken()
            ),
        ];
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $data)) {
                $data[$key] = $value;
            }
        }

        // Jeśli istnieje plik .twig, renderuj przez Twig
        $twigTemplate = $name . ".twig";
        if (file_exists($baseViewPath . $twigTemplate)) {
            $this->render($baseViewPath, $twigTemplate, $data);
            return;
        } else {
            $this->render($baseViewPath, "errors/404.twig", $data);
        }
    }

    private function render($baseViewPath, $twigTemplate, $data)
    {
        $loader = new FilesystemLoader($baseViewPath);
        $twig = new Environment($loader, [
            'cache' => false, // Możesz zmienić na ścieżkę do cache w produkcji
            'autoescape' => 'html',
            'debug' => true, // Włącz debugowanie, jeśli potrzebujesz
        ]);

        $twig->addExtension(new DebugExtension());
        $twig->addExtension(new AppExtension($_POST));
        echo $twig->render($twigTemplate, $data);
    }

    protected function getJson(Request $request): array
    {
        return json_decode($request->getContent(), true) ?? [];
    }

    protected function json($data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status);
    }
}