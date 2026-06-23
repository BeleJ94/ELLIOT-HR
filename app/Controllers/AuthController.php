<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class AuthController extends Controller
{
    public function login(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->authenticate();
            return;
        }

        if (Auth::check()) {
            $this->redirect('/dashboard');
            return;
        }

        $this->view('auth.login', [
            'title' => 'Connexion',
        ], 'auth');
    }

    public function logout(): void
    {
        Auth::logout();
        $this->redirect('/login');
    }

    private function authenticate(): void
    {
        if (!$this->validCsrfToken()) {
            $this->json([
                'success' => false,
                'message' => 'Session invalide. Rechargez la page puis reessayez.',
            ], 419);
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->json([
                'success' => false,
                'message' => 'Adresse email et mot de passe sont obligatoires.',
            ], 422);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json([
                'success' => false,
                'message' => 'Adresse email invalide.',
            ], 422);
            return;
        }

        if (!Auth::attempt($email, $password)) {
            $this->json([
                'success' => false,
                'message' => 'Identifiants incorrects ou compte inactif.',
            ], 401);
            return;
        }

        $this->json([
            'success' => true,
            'message' => 'Connexion reussie.',
            'redirect' => url('/dashboard'),
        ]);
    }

    private function validCsrfToken(): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        $submittedToken = $_POST['_csrf_token'] ?? '';

        return is_string($sessionToken)
            && is_string($submittedToken)
            && $sessionToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }
}
