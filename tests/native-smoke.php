<?php

declare(strict_types=1);

$root = getenv('CRAFT_TEST_ROOT');
if (!$root || !is_file($root . '/bootstrap.php')) {
    throw new RuntimeException('Set CRAFT_TEST_ROOT to an existing Craft test installation.');
}
$_SERVER['SCRIPT_FILENAME'] = $root . '/web/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/bootstrap.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'arifje\\loginwithemailcode\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
}, true, true);
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
$plugin = new arifje\loginwithemailcode\LoginWithEmailCode('login-with-email-code', $app, [
    'basePath' => dirname(__DIR__) . '/src',
]);
$html = $app->getView()->renderTemplate('login-with-email-code/_confirm', [
    'email' => '<script>alert(1)</script>@example.test',
    'loginToken' => 'test" autofocus onfocus="alert(1)',
], craft\web\View::TEMPLATE_MODE_CP);
foreach ([
    !str_contains($html, '<script>alert(1)</script>'),
    str_contains($html, '&lt;script&gt;'),
    str_contains($html, 'name="' . $app->getRequest()->csrfParam . '"'),
    str_contains($html, 'confirm-magic-link'),
    str_contains($html, '&quot;'),
    str_contains($html, 'method="post"'),
] as $passed) {
    if (!$passed) {
        throw new RuntimeException('Confirmation template verification failed.');
    }
}
echo 'PASS Craft ' . $app->getVersion() . " bootstrap and native confirmation rendering/escaping/CSRF\n";
