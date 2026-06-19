<?php

namespace arifje\loginwithemailcode\controllers;

use arifje\loginwithemailcode\LoginWithEmailCode;
use arifje\loginwithemailcode\models\LoginResult;
use Craft;
use craft\helpers\UrlHelper;
use craft\web\Controller;
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
        $settings = LoginWithEmailCode::$plugin->getSettings();
        if (!$settings->allowMagicLinks) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'Magic link login is not enabled.'));
        }

        $loginToken = $loginToken ?: (string)Craft::$app->getRequest()->getQueryParam('loginToken');
        $result = LoginWithEmailCode::$plugin->getTokens()->consumeMagicLink($loginToken);

        if (!$result) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'The magic link is invalid or has expired.'));
        }

        return $this->loginAndRedirect($result);
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

        if (!Craft::$app->getUser()->login($result->user, max(0, (int)$settings->rememberMeDuration))) {
            return $this->failureResponse(Craft::t('login-with-email-code', 'The user could not be logged in.'));
        }

        if (Craft::$app->getRequest()->getAcceptsJson()) {
            return $this->asJson([
                'success' => true,
                'redirect' => $this->siteUrl($this->postedRedirect() ?: $result->redirect ?: $settings->successRedirect),
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('login-with-email-code', 'You are now logged in.'));

        return $this->redirectToSite($this->postedRedirect() ?: $result->redirect ?: $settings->successRedirect);
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
