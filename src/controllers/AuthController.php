<?php

namespace arifje\loginwithemailcode\controllers;

use arifje\loginwithemailcode\LoginWithEmailCode;
use arifje\loginwithemailcode\models\LoginResult;
use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\View;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class AuthController extends Controller
{
    protected array|bool|int $allowAnonymous = true;

    public function actionRequestCode(): Response
    {
        $this->requirePostRequest();

        $message = Craft::t('login-with-email-code', 'If an account exists for that email address, a login email has been sent.');
        $settings = LoginWithEmailCode::$plugin->getSettings();

        if ($settings->allowEmailCodes) {
            LoginWithEmailCode::$plugin->getTokens()->sendLoginCode(
                (string)Craft::$app->getRequest()->getBodyParam('email'),
                (string)Craft::$app->getRequest()->getBodyParam('loginRedirect')
            );
        }

        return $this->requestResponse($message);
    }

    public function actionVerifyCode(): Response
    {
        $this->requirePostRequest();

        $settings = LoginWithEmailCode::$plugin->getSettings();
        if (!$settings->allowEmailCodes) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'Email code login is not enabled.'));
        }

        $result = LoginWithEmailCode::$plugin->getTokens()->consumeCode(
            (string)Craft::$app->getRequest()->getBodyParam('email'),
            (string)Craft::$app->getRequest()->getBodyParam('code')
        );

        if (!$result) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'The login code is invalid or has expired.'));
        }

        return $this->loginAndRedirect($result);
    }

    public function actionRequestMagicLink(): Response
    {
        $this->requirePostRequest();

        $message = Craft::t('login-with-email-code', 'If an account exists for that email address, a login email has been sent.');
        $settings = LoginWithEmailCode::$plugin->getSettings();

        if ($settings->allowMagicLinks) {
            LoginWithEmailCode::$plugin->getTokens()->sendMagicLink(
                (string)Craft::$app->getRequest()->getBodyParam('email'),
                (string)Craft::$app->getRequest()->getBodyParam('loginRedirect')
            );
        }

        return $this->requestResponse($message);
    }

    public function actionMagicLink(?string $loginToken = null): Response
    {
        if (!Craft::$app->getRequest()->getIsGet()) {
            throw new MethodNotAllowedHttpException();
        }
        $this->protectConfirmationResponse();

        if (!LoginWithEmailCode::$plugin->getSettings()->allowMagicLinks) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'Magic link login is not enabled.'));
        }

        $loginToken = $loginToken ?: (string)Craft::$app->getRequest()->getQueryParam('loginToken');
        $result = LoginWithEmailCode::$plugin->getTokens()->previewMagicLink($loginToken);
        if (!$result) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'The magic link is invalid or has expired.'));
        }

        return $this->renderTemplate('login-with-email-code/_confirm', [
            'loginToken' => $loginToken,
            'email' => $result->user->email,
        ], View::TEMPLATE_MODE_CP);
    }

    public function actionConfirmMagicLink(): Response
    {
        $this->requirePostRequest();
        $this->protectConfirmationResponse();

        if (!LoginWithEmailCode::$plugin->getSettings()->allowMagicLinks) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'Magic link login is not enabled.'));
        }

        $result = LoginWithEmailCode::$plugin->getTokens()->consumeMagicLink(
            (string)Craft::$app->getRequest()->getBodyParam('loginToken')
        );
        if (!$result) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'The magic link is invalid or has expired.'));
        }

        return $this->loginAndRedirect($result);
    }

    private function protectConfirmationResponse(): void
    {
        $headers = Craft::$app->getResponse()->getHeaders();
        $headers->set('Cache-Control', 'no-store');
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
    }

    private function requestResponse(string $message): Response
    {
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'message' => $message,
            ]);
        }

        Craft::$app->getSession()->setNotice($message);

        return $this->redirectToSite($this->postedRedirect());
    }

    private function failureResponse(string $message): Response
    {
        if (Craft::$app->getRequest()->getAcceptsJson()) {
            $response = $this->asJson([
                'success' => false,
                'error' => $message,
            ]);
            $response->setStatusCode(400);

            return $response;
        }

        Craft::$app->getSession()->setError($message);

        return $this->redirectToSite(LoginWithEmailCode::$plugin->getSettings()->failureRedirect);
    }

    private function loginAndRedirect(LoginResult $result): Response
    {
        $settings = LoginWithEmailCode::$plugin->getSettings();

        $tokens = LoginWithEmailCode::$plugin->getTokens();
        // Recheck account restrictions at the session boundary.
        $user = Craft::$app->getUsers()->getUserById((int)$result->user->id);
        if (!$user || !$tokens->isLoginableUser($user)) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'The user could not be logged in.'));
        }

        $redirect = $this->siteUrl($this->postedRedirect() ?: $result->redirect ?: $settings->successRedirect);
        $duration = max(0, (int)$settings->rememberMeDuration);

        // Craft 4 has no native Auth service. Keep the compatibility boundary here.
        if (method_exists(Craft::$app, 'getAuth') && !Craft::$app->getConfig()->getGeneral()->disable2fa) {
            $auth = Craft::$app->getAuth();
            if ($auth->hasActiveMethod($user)) {
                $auth->setUser($user, $duration);
                Craft::$app->getUser()->setReturnUrl($redirect);
                $verificationUrl = UrlHelper::actionUrl('users/auth-form');
                if (Craft::$app->getRequest()->getAcceptsJson()) {
                    return $this->asJson([
                        'success' => false,
                        'requiresTwoFactor' => true,
                        'redirect' => $verificationUrl,
                    ]);
                }

                return $this->redirect($verificationUrl);
            }
        }

        if (!Craft::$app->getUser()->login($user, $duration)) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'The user could not be logged in.'));
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'redirect' => $redirect,
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('login-with-email-code', 'You are now logged in.'));

        return $this->redirect($redirect);
    }

    private function postedRedirect(): ?string
    {
        $redirect = Craft::$app->getRequest()->getValidatedBodyParam('redirect');

        return LoginWithEmailCode::$plugin->getTokens()->normalizeSiteRedirect(is_string($redirect) ? $redirect : null);
    }

    private function redirectToSite(?string $url = null): Response
    {
        return $this->redirect($this->siteUrl($url));
    }

    private function siteUrl(?string $url = null): string
    {
        $url = LoginWithEmailCode::$plugin->getTokens()->normalizeSiteRedirect($url);

        if (!$url) {
            return Craft::$app->getHomeUrl();
        }

        return UrlHelper::siteUrl($url);
    }
}
