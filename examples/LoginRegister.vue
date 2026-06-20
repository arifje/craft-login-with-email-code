<template>
	<div class="component login-register uk-position-relative">

		<ul class="uk-tab uk-flex-center" uk-grid uk-switcher="animation: uk-animation-fade">
			<li><a href="#">Inloggen</a></li>
			<li><a href="#">Registreren</a></li>
			<li class="uk-hidden"><a href="#">Wachtwoord</a></li>
		</ul>

		<div class="uk-text-center uk-margin-top" v-show="socialLogin">
			<span v-show="facebookLoginUrl">
				<a :href="facebookLoginUrl" class="uk-button uk-button-small facebook uk-margin-small-right" uk-icon="facebook"></a>
			</span>
			<span v-show="twitterLoginUrl">
				<a :href="twitterLoginUrl" class="uk-button uk-button-small x uk-margin-small-right" uk-icon="x"></a>
			</span>
			<span v-show="googleLoginUrl">
				<a :href="googleLoginUrl" class="uk-button uk-button-small google uk-margin-small-right" uk-icon="google"></a>
			</span>
		</div>

		<ul class="uk-switcher uk-margin">
			<li>
				<h3 v-show="welcomeBackTitle" class="uk-card-title uk-text-center">{{ welcomeBackTitle }}</h3>
				<form method="post" accept-charset="UTF-8" v-on:submit="login" :id="'login-form-' + id">

					<input type="hidden" :name="csrfName" :value="csrfToken">

					<div class="uk-margin" style="margin: 0 !important;">
						<div class="uk-inline uk-width-1-1">
							<span class="uk-form-icon" uk-icon="icon: mail"></span>
							<input
								name="loginName"
								class="uk-input uk-form-large uk-margin-small"
								type="text"
								autocomplete="username"
								:placeholder="emailPlaceholder"
								v-model="loginLoginName"
								required
							>
						</div>
					</div>

					<div class="uk-margin" style="margin-top: 0 !important">
						<div class="uk-inline uk-width-1-1">
							<span class="uk-form-icon" uk-icon="icon: lock"></span>
							<input
								name="password"
								class="uk-input uk-form-large"
								type="password"
								ref="loginPassword"
								autocomplete="current-password"
								:placeholder="passwordPlaceholder"
								v-model="loginPassword" required
							>
						</div>
					</div>
					<div uk-grid>
						<div class="uk-width-1-2 uk-text-left uk-text-small uk-text-light">
							<input type="checkbox" name="rememberMe" value="1" v-model="rememberMe" checked="checked"> ingelogd blijven
						</div>
						<div class="uk-width-1-2 uk-text-right@s uk-text-small uk-text-light">
							<a href="#" uk-switcher-item="2">{{ forgotPasswordText }}</a>
						</div>
					</div>
					<div class="uk-margin">
						<button class="uk-button uk-button-primary uk-button-medium uk-width-1-1">{{ loginButton }}</button>
					</div>
					<div class="uk-text-small uk-text-center">
						{{ notRegisteredText }}<br /><a href="#" uk-switcher-item="1">{{ createAccountText }}</a>
					</div>
				</form>

				<div v-if="passwordlessLogin && (emailCodeLogin || magicLinkLogin)" class="passwordless-login uk-margin-large-top uk-text-center">
					<hr class="uk-divider-small">
					<h4 class="uk-margin-small-bottom">{{ passwordlessTitle }}</h4>
					<p class="uk-text-small uk-margin-small-top">{{ passwordlessDescription }}</p>

					<form method="post" accept-charset="UTF-8" @submit.prevent="requestLoginCode">
						<input type="hidden" :name="csrfName" :value="csrfToken">

						<div class="uk-margin">
							<div class="uk-inline uk-width-1-1">
								<span class="uk-form-icon" uk-icon="icon: mail"></span>
								<input
									class="uk-input uk-form-large"
									type="email"
									name="email"
									autocomplete="email"
									:placeholder="emailPlaceholder"
									v-model="passwordlessEmail"
									required
								>
							</div>
						</div>

						<div class="uk-grid-small" uk-grid>
							<div v-if="emailCodeLogin" :class="magicLinkLogin ? 'uk-width-1-2@s' : 'uk-width-1-1'">
								<button class="uk-button uk-button-secondary uk-button-medium uk-width-1-1" type="submit">
									{{ loginCodeButton }}
								</button>
							</div>
							<div v-if="magicLinkLogin" :class="emailCodeLogin ? 'uk-width-1-2@s' : 'uk-width-1-1'">
								<button class="uk-button uk-button-default uk-button-medium uk-width-1-1" type="button" @click="requestMagicLink">
									{{ magicLinkButton }}
								</button>
							</div>
						</div>
					</form>

					<form
						v-if="emailCodeLogin"
						v-show="passwordlessCodeRequested"
						method="post"
						accept-charset="UTF-8"
						class="uk-margin"
						@submit.prevent="verifyLoginCode"
					>
						<input type="hidden" :name="csrfName" :value="csrfToken">

						<div class="uk-inline uk-width-1-1">
							<span class="uk-form-icon" uk-icon="icon: lock"></span>
							<input
								class="uk-input uk-form-large"
								type="text"
								name="code"
								inputmode="numeric"
								autocomplete="one-time-code"
								:placeholder="codePlaceholder"
								v-model="passwordlessCode"
								required
							>
						</div>

						<div class="uk-margin">
							<button class="uk-button uk-button-primary uk-button-medium uk-width-1-1" type="submit">
								{{ verifyCodeButton }}
							</button>
						</div>
					</form>
				</div>
			</li>
			<li>
				<h3 v-show="signUpTitle" class="uk-card-title uk-text-center">{{ signUpTitle }}</h3>
				<form method="post" accept-charset="UTF-8" id="register-form" v-on:submit="register">

					<input type="hidden" :name="csrfName" :value="csrfToken">
					<input type="hidden" name="action" value="users/save-user">

					<div class="uk-grid-small" uk-grid>
						<div class="uk-width-1-1">
							<div class="uk-inline uk-width-1-1">
								<span class="uk-form-icon" uk-icon="icon: mail"></span>
								<input
									class="uk-input uk-form-large"
									type="email"
									v-model="registerEmail"
									:placeholder="emailPlaceholder"
									name="email"
									autocomplete="off"
									autocorrect="off"
									autocapitalize="off"
									required="true"
									spellcheck="false"
								>
							</div>
						</div>
						<div class="uk-width-1-1">
							<div class="uk-inline uk-width-1-1">
								<span class="uk-form-icon" uk-icon="icon: user"></span>
								<input
									class="uk-input uk-form-large"
									type="text"
									v-model="registerFullName"
									:placeholder="fullNamePlaceholder"
									name="fullName"
									autocomplete="off"
									autocorrect="off"
									autocapitalize="off"
									required="true"
									spellcheck="false"
								>
							</div>
						</div>
					</div>
					<div class="uk-margin uk-text-left" v-show="registerFullName">
						<div class="uk-grid uk-grid-small">
						<div class="uk-width-1-2">
							<label>Geboren op</label>
							<input
								class="uk-input uk-form-large"
								type="date"
								v-model="registerBirthdate"
								:placeholder="birthdatePlaceholder"
								name="fields[birthdate]"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="off"
								required="true"
								spellcheck="false"
							>
						</div>
						<div class="uk-width-1-2">
							<label>Geslacht</label>
							<select class="uk-select uk-form-large" v-model="registerGender" required>
								<option v-for="option in genderOptions" :key="option.value" :value="option.value">
									{{ option.label }}
								</option>
							</select>
						</div>
					</div>
					</div>
					<div class="uk-margin" v-show="registerBirthdate && registerFullName">
						<div class="uk-inline uk-width-1-1">
							<span class="uk-form-icon" uk-icon="icon: comment"></span>
							<input
								class="uk-input uk-form-large"
								type="text"
								v-model="registerNickName"
								:placeholder="nickNamePlaceholder"
								name="fields[nickName]"
								autocomplete="off"
								autocorrect="off"
								autocapitalize="off"
								required="true"
								spellcheck="false"
							>
						</div>
					</div>
					<div class="uk-margin" v-show="registerNickName">
						<div class="uk-inline uk-width-1-1">
							<span class="uk-form-icon" uk-icon="icon: lock"></span>
							<input
								class="uk-input uk-form-large"
								type="password"
								name="password"
								v-model="registerPassword"
								ref="registerPassword"
								:placeholder="passwordPlaceholder"
								autocorrect="off"
								autocomplete="new-password"
								spellcheck="false"
								autocapitalize="off"
								required />
						</div>
					</div>
					<div class="uk-margin uk-grid-small uk-child-width-auto uk-grid">
						<label><input class="uk-checkbox" type="checkbox" required>
							&nbsp;<a target="_blank" href="/avg/voorwaarden/">AV</a> akkoord
					 </label>
					</div>
					<div class="uk-margin">
						<button class="uk-button uk-button-primary uk-button-medium uk-width-1-1">{{ signUpButton }}</button>
					</div>
					<div class="uk-text-small uk-text-center">
						{{ alreadyHaveAccountText }} <a href="#" uk-switcher-item="0">{{ loginText }}</a>
					</div>
				</form>
			</li>
			<li>
				<h3 class="uk-card-title uk-text-center">{{ forgotPasswordTitle }}</h3>
				<p class="uk-text-center uk-width-medium@s uk-margin-auto">{{ forgotPasswordDescription }}</p>

				 <form method="post" id="password-form" accept-charset="UTF-8" autocomplete="off" @submit.prevent="resetPassword">

					<input type="hidden" name="action" value="users/send-password-reset-email">
					<input type="hidden" :name="csrfName" :value="csrfToken">

					<div class="uk-margin">
						<div class="uk-inline uk-width-1-1">
							<span class="uk-form-icon" uk-icon="icon: mail"></span>
							<input
								id="loginName"
								class="uk-input uk-form-large"
								v-model="resetLoginName"
								type="text"
								name="loginName"
								:placeholder="passwordResetPlaceholder"
								autocomplete="new-username"
								required
							>
						</div>
					</div>
					<div class="uk-margin">
						<button class="uk-button uk-button-primary uk-button-medium uk-width-1-1">{{ sendEmailButton }}</button>
					</div>
					<div class="uk-text-small uk-text-center">
						<a href="#" uk-switcher-item="0">{{ backToLoginText }}</a>
					</div>
				</form>
			</li>
		</ul>

		<div class="overlay" v-show="overlay">
			 <div class="uk-position-center">
				 <div class="uk-text-center">
					 <span v-show="loading"><loading-indicator></loading-indicator></span>
					 <span v-show="error" class="icon material-symbols-outlined"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24"><path d="M480-280q17 0 28.5-11.5T520-320q0-17-11.5-28.5T480-360q-17 0-28.5 11.5T440-320q0 17 11.5 28.5T480-280Zm-40-160h80v-240h-80v240Zm40 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg></span>
					 <span v-show="success" class="icon material-symbols-outlined"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24"><path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"/></svg></span>
				 </div>
				 <p class="uk-text-center" v-html="message"></p>
			 </div>
		</div>

	</div>
</template>

<script>
import axios from 'axios';
import LoadingIndicator from './LoadingIndicator.vue'

export default {
	name: 'LoginRegister',
	components: { LoadingIndicator },
	props: {
		socialLogin: {
			type: Boolean,
			default: false
		},
		isApp: {
			type: Boolean,
			default: false
		},
		returnUrlWeb: {
			type: String,
			default: null
		},
		returnUrlApp: {
			type: String,
			default: '/ingelogd?isApp=1'
		},
		facebookLoginUrl: {
			type: String,
			default: null
		},
		twitterLoginUrl: {
			type: String,
			default: null
		},
		googleLoginUrl: {
			type: String,
			default: null
		},
		genderOptions: {
			type: Array,
			required: true
		},
		recaptchaSiteKey: {
			type: String
		},
		passwordlessLogin: {
			type: Boolean,
			default: true
		},
		emailCodeLogin: {
			type: Boolean,
			default: true
		},
		magicLinkLogin: {
			type: Boolean,
			default: true
		},
	},
	data() {
		return {

			id: Math.floor(Math.random() * 10000),
			overlay: false,
			error: null,
			errors: {},
			success: false,
			loading: false,
			message: null,

			// register
			registerEmail: '',
			registerUsername: '',
			registerNickName: '',
			registerFullName: '',
			registerGender: '',
			registerBirthdate: '1990-01-01',
			registerPassword: '',
			resetLoginName: '',

			// login
			rememberMe: true,
			loginLoginName: '',
			loginPassword: '',
			passwordlessEmail: '',
			passwordlessCode: '',
			passwordlessCodeRequested: false,

			// Placeholders
			signUpTitle: '',
			fullNamePlaceholder: 'Voor- en achternaam',
			nickNamePlaceholder: 'Naam bij reacties',
			emailPlaceholder: 'E-mailadres',
			birthdatePlaceholder: 'Geboortedatum (dd/mm/yyyy)',
			passwordPlaceholder: 'Wachtwoord',
			passwordResetPlaceholder: 'Emailadres of gebruikersnaam',
			codePlaceholder: 'Inlogcode',

			// Text
			termsAndConditions: 'Akkoord met AV',
			signUpButton: 'Registreren',
			alreadyHaveAccountText: 'Heb je al een account?',
			loginText: 'Inloggen',
			welcomeBackTitle: '',
			forgotPasswordText: 'wachtwoord vergeten',
			loginButton: 'Inloggen',
			notRegisteredText: 'Nog niet geregistreerd?',
			createAccountText: 'Maak een account aan',
			forgotPasswordTitle: 'Wachtwoord vergeten?',
			forgotPasswordDescription: 'Voer je e-mailadres in en we sturen je een link om je wachtwoord te resetten.',
			sendEmailButton: 'E-mail verzenden',
			backToLoginText: 'Terug naar inloggen',
			passwordlessTitle: 'Inloggen zonder wachtwoord',
			passwordlessDescription: 'Ontvang een inlogcode of magic link per e-mail.',
			loginCodeButton: 'Stuur inlogcode',
			magicLinkButton: 'Stuur magic link',
			verifyCodeButton: 'Code controleren',
			loginCodeSentMessage: 'We hebben je een inlogcode gestuurd. Vul de code hieronder in om in te loggen.',
			magicLinkSentMessage: 'We hebben je een magic link gestuurd. Controleer je inbox en spamfolder.',
			emailRequiredMessage: 'Vul je e-mailadres in.',
			codeRequiredMessage: 'Vul je inlogcode in.',

		};
	},
	computed: {
		csrfName () {
			return window.csrfTokenName
		},
		csrfToken () {
			return window.csrfTokenValue
		},
	},
	methods: {
		formatErrors(errors) {
			let messages = new Set();
			for (let key in errors) {
				if (errors.hasOwnProperty(key)) {
					errors[key].forEach(message => messages.add(message));
				}
			}
			return Array.from(messages).join('<br>');
		},
		appendCsrf(formData) {
			formData.append(window.csrfTokenName, window.csrfTokenValue);
		},
		getReturnUrl() {
			if (this.isApp) {
				return this.returnUrlApp;
			}

			return this.returnUrlWeb || '/';
		},
		getPasswordlessEmail() {
			return (this.passwordlessEmail || this.loginLoginName || '').trim();
		},
		createPasswordlessFormData(extraData) {
			var formData = new FormData();
			this.appendCsrf(formData);

			Object.keys(extraData).forEach(function(key) {
				if (extraData[key] !== null && typeof extraData[key] !== 'undefined') {
					formData.append(key, extraData[key]);
				}
			});

			return formData;
		},
		startOverlay() {
			this.overlay = true;
			this.loading = true;
			this.error = false;
			this.success = false;
			this.message = null;
		},
		showSuccessMessage(message, timeout) {
			this.overlay = true;
			this.loading = false;
			this.error = false;
			this.success = true;
			this.message = message;

			if (timeout) {
				setTimeout(() => {
					this.resetMessage();
				}, timeout);
			}
		},
		showErrorMessage(message, timeout) {
			this.overlay = true;
			this.loading = false;
			this.error = true;
			this.success = false;
			this.message = message;

			setTimeout(() => {
				this.resetMessage();
			}, timeout || 4000);
		},
		getAxiosErrorMessage(error, fallback) {
			var response = error.response ? error.response.data : null;

			if (!response) {
				return fallback;
			}

			if (response.error) {
				return response.error;
			}

			if (response.errors) {
				return this.formatErrors(response.errors);
			}

			return response.message || fallback;
		},
		redirectAfterLoginResponse(response) {
			var redirect = response.data && response.data.redirect ? response.data.redirect : this.getReturnUrl();

			if (this.isApp) {
				window.location = this.returnUrlApp;
				return;
			}

			window.location = redirect || '/';
		},
		requestLoginCode() {
			var component = this;
			var email = component.getPasswordlessEmail();

			if (!email) {
				component.showErrorMessage(component.emailRequiredMessage);
				return;
			}

			component.passwordlessEmail = email;
			component.startOverlay();

			axios({
				method: 'POST',
				url: '/actions/login-with-email-code/auth/request-code',
				responseType: 'json',
				data: component.createPasswordlessFormData({
					email: email,
					loginRedirect: component.getReturnUrl()
				}),
				headers: {
					'Accept': 'application/json',
					'Cache-Control': 'no-cache',
					'Pragma': 'no-cache',
					'Expires': '0'
				}
			})
			.then(function(response) {
				component.passwordlessCodeRequested = true;
				component.showSuccessMessage(component.loginCodeSentMessage, 2500);
			})
			.catch(function(error) {
				component.showErrorMessage(component.getAxiosErrorMessage(error, 'De inlogcode kon niet worden verstuurd.'));
			});
		},
		verifyLoginCode() {
			var component = this;
			var email = component.getPasswordlessEmail();
			var code = (component.passwordlessCode || '').trim();

			if (!email) {
				component.showErrorMessage(component.emailRequiredMessage);
				return;
			}

			if (!code) {
				component.showErrorMessage(component.codeRequiredMessage);
				return;
			}

			component.startOverlay();

			axios({
				method: 'POST',
				url: '/actions/login-with-email-code/auth/verify-code',
				responseType: 'json',
				data: component.createPasswordlessFormData({
					email: email,
					code: code
				}),
				headers: {
					'Accept': 'application/json',
					'Cache-Control': 'no-cache',
					'Pragma': 'no-cache',
					'Expires': '0'
				}
			})
			.then(function(response) {
				component.redirectAfterLoginResponse(response);
			})
			.catch(function(error) {
				component.showErrorMessage(component.getAxiosErrorMessage(error, 'De inlogcode is ongeldig of verlopen.'));
			});
		},
		requestMagicLink() {
			var component = this;
			var email = component.getPasswordlessEmail();

			if (!email) {
				component.showErrorMessage(component.emailRequiredMessage);
				return;
			}

			component.passwordlessEmail = email;
			component.startOverlay();

			axios({
				method: 'POST',
				url: '/actions/login-with-email-code/auth/request-magic-link',
				responseType: 'json',
				data: component.createPasswordlessFormData({
					email: email,
					loginRedirect: component.getReturnUrl()
				}),
				headers: {
					'Accept': 'application/json',
					'Cache-Control': 'no-cache',
					'Pragma': 'no-cache',
					'Expires': '0'
				}
			})
			.then(function(response) {
				component.showSuccessMessage(component.magicLinkSentMessage, 4000);
			})
			.catch(function(error) {
				component.showErrorMessage(component.getAxiosErrorMessage(error, 'De magic link kon niet worden verstuurd.'));
			});
		},
		login: function(e) {

			if(e) {
				e.preventDefault();
			}

			var component = this;

			component.$logger('Vue/components/login-register/login');

			// Form data
			var formData = new FormData(e.target);
			var currentUrl = window.location.href;

			// Append CSRF
			component.appendCsrf(formData);

			// Toggle overly and indicator
			component.overlay = true;
			component.loading = true;

			// Request
			axios({
				method: 'POST',
				url: '/actions/users/login',
				responseType: 'json',
				data: formData,
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Accept': 'application/json',
					'Cache-Control': 'no-cache',
					'Pragma': 'no-cache',
					'Expires': '0'
				}
			})
			.then(function (response) {

				component.loading = false;

				// Error
				if(response.data.error) {

					component.error = true;
					component.message = response.data.error;

					setTimeout(function() {
						component.resetMessage();
					}, 3000);

				// Success
				} else {

					// Redirect app requests to a new page so we can send the userData to the app
					if(component.isApp) {
						window.location = component.returnUrlApp;
					} else {

						if(component.returnUrlWeb != null) {
							window.location = component.returnUrlWeb;
						} else {
							// Update msg
							component.success = true;
							component.message = 'Je bent succesvol ingelogd, we sturen over enkele seconden automatisch naar de frontpage.';

							// Reset message and redirect to return url with small timeout
							setTimeout(function() {
								component.resetMessage();
								window.location = '/'; // frontpage
							}, 2000);
						}
					}
				}
			})
			.catch(function (error) {

				component.$logger('Vue/components/login-register/login/catch', 'error');
				component.$logger(error);

				// Json response
				var response = error.response.data;
				var errorCode = response.errorCode;
				var message = response.message;

				component.loading = false;
				component.message = true;
				component.error = true;
				component.message = (message) ? message : "Onbekende fout, probeer het opnieuw!";

				setTimeout(function() {
					component.resetMessage();
				}, 4000);
			});

		},
		register: function(e) {

			var component = this;

			component.$logger('Vue/components/login-register/register');

			if(e) {
				e.preventDefault();
			}

			var component = this;

			// Data
			var data = {
				email: this.registerEmail,
				password: this.registerPassword,
				fullName: this.registerFullName,
				fields: {
					birthdate: this.registerBirthdate,
					nickName: this.registerNickName,
					gender: this.registerGender
				},
				returnUrl: '/'
			};

			// Form data
			var formData = new FormData();

			// Append form data manually
			formData.append('email', data.email);
			formData.append('password', data.password);
			formData.append('fullName', data.fullName);
			formData.append('fields[birthdate]', data.fields.birthdate);
			formData.append('fields[nickName]', data.fields.nickName);
			formData.append('fields[gender]', data.fields.gender);
			formData.append('returnUrl', data.returnUrl);

			// Append CSRF
			component.appendCsrf(formData);

			// Toggle overly and indicator
			component.overlay = true;
			component.loading = true;

			// Request
			const handleFormSubmission = () => {

				axios({
					method: 'POST',
					url: '/actions/users/save-user',
					responseType: 'json',
					data: formData,
					headers: {
						'Content-Type' : 'application/x-www-form-urlencoded',
						'Accept' : 'application/json'
					}
				})
				.then(function (response) {

					component.$logger('Vue/components/login-register/register/response');
					component.$logger(response);

					component.loading = false;

					var message = null;
					var error = response.data.error;
					var errors = response.data.errors;

					if(error && typeof error != "undefined") {
						message = error;
					} else if(errors) {
						for (const item of Object.entries(errors)) {
							if(typeof item[1][0] != "undefined") {
								message += item[1][0] + '<br>';
							}
						}
					}

					// Error
					if(error || errors) {

						// Update UI
						component.loading = false;
						component.error = true;
						component.message = message;

						setTimeout(function() {
							component.resetMessage();
						}, 5000);

					// Success
					} else {

						component.success = true;
						component.message = "Je account is aangemaakt! <br><br>Er is een activatiemail verstuurd naar het emailadres dat je hebt opgegeven.";

						// Reset form
						component.registerEmail = '';
						component.registerUsername = '';
						component.registerPassword = '';
						component.registerNickName = '';
					}
				})
				.catch(function (error) {

					component.$logger('Vue/components/login-register/register/catched-error');
					component.$logger(error.response);

					// Json response
					var response = error.response.data;
					var errorCode = response.errorCode;
					var errors = response.errors || {};

					// Update UI
					component.loading = false;
					component.error = true;

					if(errors) {
						component.message = component.formatErrors(errors);
					} else {
						component.message = (response.message) ? response.message : "Onbekende fout, probeer het opnieuw!";
					}

					setTimeout(function() {
						component.resetMessage();
					}, 4000);
				});
			}

			// Check if reCAPTCHA is enabled
			if (component.recaptchaSiteKey) {
				// Get the reCAPTCHA token
				grecaptcha.ready(function() {
					grecaptcha.execute(component.recaptchaSiteKey, { action: 'register' }).then(function(token) {

						// Add the token to the form data
						formData.append('g-recaptcha-response', token);

						component.$logger('Vue/component/login-register/register/recaptcha-token: ' + token);

						// Proceed with form submission
						handleFormSubmission();
					});
				});
			} else {
				// Proceed with form submission without reCAPTCHA
				handleFormSubmission();
			}
		},
		resetPassword: function(e) {
			var component = this;

			component.$logger('Vue/components/login-register/reset');

			// Data
			var data = {};

			// Form data
			var formData = new FormData(e.target);

			// Append CSRF
			component.appendCsrf(formData);

			// Toggle overly and indicator
			component.overlay = true;
			component.loading = true;

			// Request
			axios({
				method: 'post',
				url: '/actions/users/send-password-reset-email',
				responseType: 'json',
				data: formData,
				headers: {
					'Content-Type' : 'application/x-www-form-urlencoded',
					'Accept' : 'application/json'
				}
			})
			.then(function (response) {

				component.loading = false;

				var message = null;
				var error = response.data.error;
				var errors = response.data.errors;

				if(error && typeof error != "undefined") {
					message = error;
				} else if(errors) {
					for (const item of Object.entries(errors)) {
						if(typeof item[1][0] != "undefined") {
							message += item[1][0] + '<br>';
						}
					}
				}

				// Error
				if(error || errors) {

					// Update UI
					component.loading = false;
					component.error = true;
					component.message = message;

					setTimeout(function() {
						component.resetMessage();
					}, 5000);

				// Success
				} else {

					component.success = true;
					component.message = "Een email met instructies is verstuurd. Het kan enkele minuten duren voordat je hem ontvangt.\n\nLet op: Controleer ook je spambox!";

					// Reset form
					component.resetLoginName = '';
				}
			})
			.catch(function (error) {

				// Json response
				var response = error.response.data;
				var errorCode = response.errorCode;

				component.loading = false;
				component.error = true;
				component.message = (response.message) ? response.message : "Onbekende fout, probeer het opnieuw.";

				setTimeout(function() {
					component.resetMessage();
				}, 4000);
			});

		},
		resetMessage: function() {

			var component = this;

			component.message = null;
			component.error = false;
			component.overlay = false;
			component.loading = false;
		}
	}
};
</script>

<style scoped>
.overlay {
	position: absolute; top: 0; height: 100%; width: 100%; z-index: 999; background-color: var(--background-color);
}
.overlay .icon svg {
	width: 40px;
	fill: #a5a5a5;
}
.component.login-register .uk-button.facebook {
	fill: #a5a5a5;
}
.component.login-register .uk-button.x {
	fill: #a5a5a5;
}
.component.login-register .uk-button.google {
	fill: #a5a5a5;
}
.component.login-register .passwordless-login h4 {
	font-size: 18px;
}
</style>
