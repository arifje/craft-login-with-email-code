<?php

declare(strict_types=1);

use arifje\loginwithemailcode\LoginWithEmailCode;
use arifje\loginwithemailcode\controllers\AuthController;
use arifje\loginwithemailcode\models\LoginResult;
use craft\elements\User;
use craft\helpers\FileHelper;
use yii\db\Query;


// Isolated regression tests: real Craft/Yii classes, disposable SQLite data,
// test users/mailer/session, and independent PHP processes for concurrency.
$autoload = getenv('CRAFT_VENDOR_AUTOLOAD');
if (!$autoload || !is_file($autoload)) {
    throw new RuntimeException('Set CRAFT_VENDOR_AUTOLOAD to the Craft installation vendor/autoload.php.');
}
define('YII_ENABLE_ERROR_HANDLER', false);
require $autoload;
require dirname($autoload) . '/yiisoft/yii2/Yii.php';
require dirname($autoload) . '/craftcms/cms/src/Craft.php';
Craft::setAlias('@craft', dirname($autoload) . '/craftcms/cms/src');
Craft::setAlias('@app', dirname($autoload) . '/craftcms/cms/src');
spl_autoload_register(static function (string $class): void {
    $prefix = 'arifje\\loginwithemailcode\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
}, true, true);


final class TestUsers
{
    public User $user;

    public function __construct()
    {
        // Avoid element initialization accessing an installed site's data.
        $this->user = (new ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->user->id = 1;
        $this->user->siteId = 1;
        $this->user->email = 'test@example.test';
        $this->user->active = true;
        $this->user->enabled = true;
        $this->user->setEnabledForSite(true);
    }

    public function getUserById(int $id): ?User
    {
        return $id === 1 ? $this->user : null;
    }

    public function getUserByUsernameOrEmail(string $email): ?User
    {
        return $email === $this->user->email ? $this->user : null;
    }
}

final class TestAuth
{
    public bool $active = true;
    public ?User $pending = null;
    public ?int $duration = null;

    public function hasActiveMethod(User $user): bool
    {
        return $this->active;
    }

    public function setUser(?User $user, ?int $duration = null): void
    {
        $this->pending = $user;
        $this->duration = $duration;
    }
}

final class TestSessionUser extends yii\web\User
{
    public int $logins = 0;
    public mixed $returnUrlForTest = null;

    public function login($identity, $duration = 0): bool
    {
        $this->logins++;
        return true;
    }

    public function setReturnUrl($url): void
    {
        $this->returnUrlForTest = $url;
    }
}

final class TestSecurity extends yii\base\Security
{
    public function validatePassword($password, $hash): bool
    {
        // Make concurrent read/check/write overlap reliably without serialization.
        usleep(30000);
        return parent::validatePassword($password, $hash);
    }
}

final class TestRequest extends craft\web\Request
{
    public bool $json = false;
    public function init(): void
    {
    }

    public function getIsCpRequest(): bool
    {
        return false;
    }

    public function getIsConsoleRequest(): bool
    {
        return false;
    }

    public function getIsSiteRequest(): bool
    {
        return true;
    }

    public function getIsLivePreview(): bool
    {
        return false;
    }

    public function getIsPreview(): bool
    {
        return false;
    }

    public function getSiteToken(): ?string
    {
        return null;
    }

    public function getToken(): ?string
    {
        return null;
    }

    public function getAcceptsJson(): bool
    {
        return $this->json;
    }

}

class TestApplication extends yii\web\Application
{
    public TestUsers $testUsers;
    public TestAuth $testAuth;
    public craft\config\GeneralConfig $general;

    public function getUsers(): TestUsers
    {
        return $this->testUsers;
    }

    public function getMutex(): craft\mutex\Mutex
    {
        return $this->get('mutex');
    }

    public function getConfig(): object
    {
        return new class ($this->general) {
            public function __construct(private craft\config\GeneralConfig $general)
            {
            }

            public function getGeneral(): craft\config\GeneralConfig
            {
                return $this->general;
            }
        };
    }
    public function onAfterRequest(callable $handler): void
    {
        $this->on(self::EVENT_AFTER_REQUEST, $handler);
    }

    public function getSystemName(): string
    {
        return 'Security tests';
    }

    public function getIsLive(): bool
    {
        return true;
    }

    public function getIsInstalled(): bool
    {
        return true;
    }

    public function getIsMultiSite(): bool
    {
        return false;
    }

    public function getIsInitialized(): bool
    {
        return false;
    }

    public function getMailer(): object
    {
        return new class {
            public function composeFromKey(string $key, array $variables): self
            {
                return $this;
            }

            public function setTo(User $user): self
            {
                return $this;
            }

            public function send(): bool
            {
                return true;
            }
        };
    }
}

final class TestCraft5Application extends TestApplication
{
    public function getAuth(): TestAuth
    {
        return $this->testAuth;
    }

}

$worker = ($argv[1] ?? '') === 'worker';
$directory = $worker ? $argv[2] : sys_get_temp_dir() . '/email-code-tests-' . bin2hex(random_bytes(8));
if (!$worker) {
    mkdir($directory, 0700);
    mkdir($directory . '/locks', 0700);
}
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$appClass = class_exists(craft\services\Auth::class) ? TestCraft5Application::class : TestApplication::class;
$app = new $appClass([
    'id' => 'email-code-security-tests',
    'basePath' => $directory,
    'runtimePath' => $directory,
    'vendorPath' => dirname($autoload),
    'components' => [
        'db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite:' . $directory . '/tokens.sqlite'],
        'request' => ['class' => TestRequest::class, 'cookieValidationKey' => 'isolated-test-key', 'hostInfo' => 'http://localhost', 'scriptUrl' => '/index.php', 'scriptFile' => __FILE__],
        'response' => ['class' => craft\web\Response::class],
        'security' => ['class' => TestSecurity::class, 'passwordHashCost' => 4],
        'mutex' => ['class' => craft\mutex\Mutex::class, 'mutex' => ['class' => yii\mutex\FileMutex::class, 'mutexPath' => $directory . '/locks']],
        'user' => ['class' => TestSessionUser::class, 'identityClass' => User::class, 'enableSession' => false],
        'i18n' => ['translations' => ['*' => ['class' => yii\i18n\PhpMessageSource::class, 'basePath' => dirname(__DIR__) . '/src/translations']]],
    ],
]);
Craft::setAlias('@web', 'http://localhost');
$app->testUsers = new TestUsers();
$app->testAuth = new TestAuth();
$app->general = (new ReflectionClass(craft\config\GeneralConfig::class))->newInstanceWithoutConstructor();
$app->general->omitScriptNameInUrls = true;
$plugin = (new ReflectionClass(LoginWithEmailCode::class))->newInstanceWithoutConstructor();
$plugin->id = 'login-with-email-code';
LoginWithEmailCode::$plugin = $plugin;
$plugin->setComponents(['tokens' => arifje\loginwithemailcode\services\Tokens::class]);
$tokens = $plugin->getTokens();
$db = $app->getDb();
$db->createCommand('PRAGMA busy_timeout=10000')->execute();

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS $message\n";
}

function seedToken(string $type, string $verifier, int $attempts = 0): string
{
    $db = Craft::$app->getDb();
    $db->createCommand()->delete('loginwithemailcode_tokens')->execute();
    $selector = bin2hex(random_bytes(9));
    $db->createCommand()->insert('loginwithemailcode_tokens', [
        'userId' => 1, 'type' => $type, 'selector' => $selector,
        'verifierHash' => Craft::$app->getSecurity()->generatePasswordHash($verifier),
        'expiresAt' => gmdate('Y-m-d H:i:s', time() + 600), 'attempts' => $attempts,
        'dateCreated' => gmdate('Y-m-d H:i:s'), 'dateUpdated' => gmdate('Y-m-d H:i:s'), 'uid' => bin2hex(random_bytes(16)),
    ])->execute();
    return $selector . ':' . $verifier;
}

function parallel(string $operation, string $value, int $count = 8): array
{
    global $directory;
    $processes = [];
    $gate = $directory . '/gate';
    @unlink($gate);
    for ($i = 0; $i < $count; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', $directory, $operation, $value], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    touch($gate);
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $errors !== '') {
            throw new RuntimeException($output . $errors);
        }
        if (!in_array(trim($output), ['0', '1'], true)) {
            throw new RuntimeException('Unexpected worker output: ' . json_encode($output));
        }
        $results[] = trim($output) === '1';
    }
    return $results;
}

if ($worker) {
    while (!is_file($directory . '/gate')) {
        usleep(1000);
    }
    $result = match ($argv[3]) {
        'code' => $tokens->consumeCode('test@example.test', $argv[4]),
        'link' => $tokens->consumeMagicLink($argv[4]),
        'issue' => $tokens->sendLoginCode('test@example.test'),
    };
    echo $result ? '1' : '0';
    exit;
}

try {
    $db->createCommand('CREATE TABLE loginwithemailcode_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, userId INTEGER NOT NULL, type TEXT NOT NULL, selector TEXT UNIQUE NOT NULL, verifierHash TEXT NOT NULL, redirect TEXT, expiresAt TEXT NOT NULL, usedAt TEXT, attempts INTEGER NOT NULL DEFAULT 0, dateCreated TEXT NOT NULL, dateUpdated TEXT NOT NULL, uid TEXT NOT NULL)')->execute();
    seedToken('code', '123456');
    $redemptions = parallel('code', '123456');
    if (count(array_filter($redemptions)) !== 1) {
        throw new RuntimeException('Unexpected code redemption results: ' . json_encode($redemptions));
    }
    check(true, 'concurrent code redemption has exactly one winner');
    $link = seedToken('magic_link', str_repeat('a', 43));
    check($tokens->previewMagicLink($link) instanceof LoginResult, 'magic link can be previewed');
    check($tokens->previewMagicLink($link) instanceof LoginResult, 'preview does not consume the token');
    check(count(array_filter(parallel('link', $link))) === 1, 'concurrent magic-link redemption has exactly one winner');
    check($tokens->previewMagicLink($link) === null, 'used link cannot be previewed');
    seedToken('code', '123456');
    parallel('code', '000000');
    check((int)(new Query())->from('loginwithemailcode_tokens')->select('attempts')->scalar() === 5, 'concurrent guesses stop at maxAttempts');
    check($tokens->consumeCode('test@example.test', '123456') === null, 'correct code rejected after maxAttempts');
    $link = seedToken('magic_link', str_repeat('a', 43));
    parallel('link', substr($link, 0, 19) . str_repeat('b', 43));
    check((int)(new Query())->from('loginwithemailcode_tokens')->select('attempts')->scalar() === 5, 'magic-link guesses stop at maxAttempts');
    $db->createCommand()->delete('loginwithemailcode_tokens')->execute();
    check(count(array_filter(parallel('issue', ''))) === 1, 'concurrent issuance sends only one email');
    check((int)(new Query())->from('loginwithemailcode_tokens')->count() === 1, 'concurrent issuance creates only one token');
    seedToken('code', '123456');
    check($tokens->consumeCode('test@example.test', '123456') instanceof LoginResult, 'valid code accepted');
    check(!$tokens->sendLoginCode('test@example.test'), 'redemption does not bypass request cooldown');
    foreach (['locked', 'passwordResetRequired', 'suspended', 'pending'] as $restriction) {
        $link = seedToken('magic_link', str_repeat('a', 43));
        $app->testUsers->user->$restriction = true;
        check(!$tokens->sendLoginCode('test@example.test'), "$restriction account cannot request code");
        check(!$tokens->sendMagicLink('test@example.test'), "$restriction account cannot request link");
        check($tokens->consumeMagicLink($link) === null, "$restriction account cannot redeem link");
        seedToken('code', '123456');
        check($tokens->consumeCode('test@example.test', '123456') === null, "$restriction account cannot redeem code");
        $app->testUsers->user->$restriction = false;
    }
    $controller = new AuthController('auth', $plugin);
    $_SERVER['REQUEST_METHOD'] = 'GET';
    try {
        $controller->actionConfirmMagicLink();
        throw new RuntimeException('GET confirmation accepted');
    } catch (yii\web\HttpException $exception) {
        check(in_array($exception->statusCode, [400, 405], true), 'magic-link confirmation requires POST');
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    try {
        $controller->beforeAction(new yii\base\InlineAction('confirm-magic-link', $controller, 'actionConfirmMagicLink'));
        throw new RuntimeException('CSRF-less confirmation accepted');
    } catch (yii\web\BadRequestHttpException $exception) {
        check(true, 'magic-link confirmation rejects missing CSRF');
    }
    $request = $app->getRequest();
    $request->json = true;
    $link = seedToken('magic_link', str_repeat('a', 43));
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $response = $controller->actionMagicLink($link);
    check($app->getUser()->logins === 0 && $tokens->previewMagicLink($link) !== null, 'GET confirmation leaves token and session untouched');
    check($response->getHeaders()->get('X-Frame-Options') === 'DENY', 'confirmation rejects framing');
    check($response->getHeaders()->get('Referrer-Policy') === 'no-referrer', 'confirmation suppresses token referrers');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $request->setBodyParams(['loginToken' => $link, $request->csrfParam => $request->getCsrfToken()]);
    $app->testAuth->active = false;
    check($controller->beforeAction(new yii\base\InlineAction('confirm-magic-link', $controller, 'actionConfirmMagicLink')), 'confirmation accepts valid CSRF');
    $response = $controller->actionConfirmMagicLink();
    check($response->data['success'] === true && $tokens->previewMagicLink($link) === null, 'confirmed POST consumes token and logs in');
    $response = $controller->actionConfirmMagicLink();
    check($response->data['success'] === false && $app->getUser()->logins === 1, 'confirmation replay rejected');
    $app->getUser()->logins = 0;
    $app->testAuth->active = true;
    $request->setBodyParams([]);
    $login = new ReflectionMethod(AuthController::class, 'loginAndRedirect');
    $login->setAccessible(true);
    if (class_exists(craft\services\Auth::class)) {
        $response = $login->invoke($controller, new LoginResult($app->testUsers->user));
        check($app->getUser()->logins === 0, 'active second factor does not create a session');
        check($response->data['requiresTwoFactor'] === true && $response->data['success'] === false, 'JSON clearly requires second-factor verification');
        check(str_contains($response->data['redirect'], 'users/auth-form'), 'second-factor redirect uses native Craft auth form');
        check($app->testAuth->pending === $app->testUsers->user, 'pending user passed to native auth service');
        $app->testAuth->active = false;
    }
    $response = $login->invoke($controller, new LoginResult($app->testUsers->user));
    check($app->getUser()->logins === 1 && $response->data['success'] === true, 'eligible account without second factor logs in');
    $app->testUsers->user->locked = true;
    $response = $login->invoke($controller, new LoginResult($app->testUsers->user));
    check($app->getUser()->logins === 1 && $response->data['success'] === false, 'account lock rechecked at session boundary');
    echo "Security regressions passed.\n";
} finally {
    $db->close();
    FileHelper::removeDirectory($directory);
}
