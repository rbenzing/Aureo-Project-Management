<?php

// file: Controllers/AuthController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Middleware\AuthMiddleware;
use App\Middleware\SessionMiddleware;
use App\Models\User;
use App\Services\SecurityService;
use App\Utils\Email;
use App\Utils\Validator;
use InvalidArgumentException;
use RuntimeException;

class AuthController extends BaseController
{
    private User $userModel;
    private SecurityService $securityService;

    /**
     * Constructor - Now supports dependency injection
     *
     * @param AuthMiddleware|null $authMiddleware Optional AuthMiddleware instance
     * @param User|null $userModel Optional User model instance
     * @param SecurityService|null $securityService Optional SecurityService instance
     */
    public function __construct(
        ?AuthMiddleware $authMiddleware = null,
        ?User $userModel = null,
        ?SecurityService $securityService = null
    ) {
        parent::__construct($authMiddleware);
        $this->userModel = $userModel ?? new User();
        $this->securityService = $securityService ?? SecurityService::getInstance();
    }

    /**
     * Display login form
     * @param string $requestMethod
     * @param array $data
     */
    public function loginForm(string $requestMethod, array $data): void
    {
        $companyName = Config::get('company_name', 'Aureo');

        $this->render('Auth/login', compact('companyName'));
    }

    /**
     * Handle user login
     * @param string $requestMethod
     * @param array $data
     */
    public function login(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->loginForm($requestMethod, $data);

            return;
        }

        try {
            $validator = new Validator($data, [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
            $user = $this->userModel->findByEmail($email);

            if (!$user || !password_verify($data['password'], $user->password_hash)) {
                throw new InvalidArgumentException('Invalid email or password');
            }

            if (!$user->is_active) {
                throw new InvalidArgumentException('Account not activated. Please check your email for activation instructions');
            }

            $rolesAndPermissions = $this->userModel->getRolesAndPermissions($user->id);

            // Save session data
            SessionMiddleware::saveSession($user->id, [
                'id' => $user->id,
                'profile' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'company_id' => $user->company_id,
                    'is_active' => $user->is_active,
                ],
                'roles' => $rolesAndPermissions['roles'],
                'permissions' => $rolesAndPermissions['permissions'],
                'config' => Config::all(),
            ]);

            $this->redirect('/dashboard');

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/login', $e->getMessage());
        } catch (\Exception $e) {
            $this->redirectWithError('/login', $this->securityService->handleError($e, 'AuthController::login', 'An error occurred during login.'));
        }
    }

    /**
     * Handle user logout
     * @param string $requestMethod
     * @param array $data
     */
    public function logout(string $requestMethod, array $data): void
    {
        try {
            SessionMiddleware::destroySession();
            $this->redirect('/login');
        } catch (\Exception $e) {
            $this->redirectWithError('/dashboard', Config::getErrorMessage(
                $e,
                'AuthController::logout',
                'An error occurred during logout.'
            ));
        }
    }

    /**
     * Display registration form
     * @param string $requestMethod
     * @param array $data
     */
    public function registerForm(string $requestMethod, array $data): void
    {
        $this->render('Auth/register');
    }

    /**
     * Handle user registration
     * @param string $requestMethod
     * @param array $data
     */
    public function register(string $requestMethod, array $data): void
    {
        if ($requestMethod !== 'POST') {
            $this->registerForm($requestMethod, $data);

            return;
        }

        try {
            $validator = new Validator($data, [
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|strong_password',
                'confirm_password' => 'required|string|same:password',
            ]);

            if ($validator->fails()) {
                throw new InvalidArgumentException(implode(', ', $validator->errors()));
            }

            $defaultRoleId = (int) (\App\Core\Config::get('default_registration_role_id', 2));

            $userData = [
                'first_name' => htmlspecialchars($data['first_name']),
                'last_name' => htmlspecialchars($data['last_name']),
                'email' => filter_var($data['email'], FILTER_SANITIZE_EMAIL),
                'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
                'role_id' => $defaultRoleId,
                'is_active' => false,
            ];

            $userId = $this->userModel->create($userData);
            $activationToken = $this->userModel->generateActivationToken($userId);

            // This needs to be updated in your Email class to match schema
            Email::sendActivationEmail($userData['email'], $activationToken);

            $this->redirectWithSuccess('/login', 'Registration successful. Please check your email to activate your account.');

        } catch (InvalidArgumentException $e) {
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $data;
            $this->redirect('/register');
        } catch (\Exception $e) {
            $this->redirectWithError('/register', $this->securityService->handleError($e, 'AuthController::register', 'An error occurred during registration.'));
        }
    }

    /**
     * Handle password reset
     * @param string $requestMethod
     * @param array $data
     */
    public function resetPassword(string $requestMethod, array $data): void
    {
        try {
            $token = filter_var($data['token'] ?? '', FILTER_SANITIZE_STRING);
            if (!$token) {
                throw new InvalidArgumentException('Invalid or expired password reset link');
            }

            $user = $this->userModel->findByResetToken($token);
            if (!$user || strtotime($user->reset_password_token_expires_at) < time()) {
                throw new InvalidArgumentException('Invalid or expired password reset link');
            }

            if ($requestMethod === 'POST') {
                $validator = new Validator($data, [
                    'password' => 'required|string|min:8|strong_password',
                    'confirm_password' => 'required|string|same:password',
                ]);

                if ($validator->fails()) {
                    throw new InvalidArgumentException(implode(', ', $validator->errors()));
                }

                $this->userModel->update($user->id, ['password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID)]);

                $this->userModel->clearPasswordResetToken($user->id);

                $this->redirectWithSuccess('/login', 'Password reset successfully.');
            }

            $this->render('Auth/reset-password');

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/login', $e->getMessage());
        } catch (\Exception $e) {
            $this->redirectWithError('/login', $this->securityService->handleError($e, 'AuthController::resetPassword', 'An error occurred during password reset.'));
        }
    }

    /**
     * Handle account activation
     * @param string $requestMethod
     * @param array $data
     */
    public function activate(string $requestMethod, array $data): void
    {
        try {
            if ($requestMethod === 'GET') {
                $token = filter_var($data['token'] ?? '', FILTER_SANITIZE_STRING);
                if (!$token) {
                    throw new InvalidArgumentException('Invalid or missing token');
                }

                $user = $this->userModel->findByActivationToken($token);
                if (!$user || strtotime($user->activation_token_expires_at) < time()) {
                    throw new InvalidArgumentException('Invalid or expired activation token');
                }

                $this->userModel->update($user->id, ['is_active' => true]);

                $this->userModel->clearActivationToken($user->id);

                $rolesAndPermissions = $this->userModel->getRolesAndPermissions($user->id);

                SessionMiddleware::saveSession($user->id, [
                    'id' => $user->id,
                    'profile' => [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'company_id' => $user->company_id,
                        'is_active' => true,
                    ],
                    'roles' => $rolesAndPermissions['roles'],
                    'permissions' => $rolesAndPermissions['permissions'],
                    'config' => Config::all(),
                ]);

                $_SESSION['success'] = 'Account activated successfully.';
            }

            $this->render('Auth/login', compact('companyName'));

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/login', $e->getMessage());
        } catch (\Exception $e) {
            $this->redirectWithError('/login', $this->securityService->handleError($e, 'AuthController::activate', 'An error occurred during account activation.'));
        }
    }

    /**
     * Handle forgot password request
     * @param string $requestMethod
     * @param array $data
     */
    public function forgotPassword(string $requestMethod, array $data): void
    {
        try {
            if ($requestMethod === 'POST') {
                $validator = new Validator($data, [
                    'email' => 'required|email',
                ]);

                if ($validator->fails()) {
                    throw new InvalidArgumentException(implode(', ', $validator->errors()));
                }

                $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
                $user = $this->userModel->findByEmail($email);

                if (!$user) {
                    throw new InvalidArgumentException('No account found with that email address');
                }

                $resetToken = $this->userModel->generatePasswordResetToken($user->id);

                // This needs to be updated in your Email class to match schema
                if (!Email::sendPasswordResetEmail($email, $resetToken)) {
                    throw new RuntimeException('Failed to send password reset email');
                }

                $_SESSION['success'] = 'Password reset instructions have been sent to your email.';
            }

            $this->render('Auth/forgot-password');

        } catch (InvalidArgumentException $e) {
            $this->redirectWithError('/forgot-password', $e->getMessage());
        } catch (\Exception $e) {
            $this->redirectWithError('/forgot-password', $this->securityService->handleError($e, 'AuthController::forgotPassword'));
        }
    }
}
