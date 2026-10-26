<?php
/**
 * AuthController — halaman + aksi auth (login/register/logout).
 */

declare(strict_types=1);

final class AuthController
{
    public static function showLogin(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        View::auth('login');
    }

    public static function showRegister(): void
    {
        if (Auth::check()) {
            redirect('/dashboard');
        }
        View::auth('register');
    }

    public static function login(): void
    {
        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password   = (string) ($_POST['password'] ?? '');

        if (!Session::checkCsrf()) {
            Session::flash('login_error', 'Sesi kedaluwarsa — coba isi ulang form.');
            redirect('/login');
        }
        if ($identifier === '' || $password === '') {
            Session::flash('login_error', 'Email/username dan password wajib diisi.');
            Session::flash('login_identifier', $identifier);
            redirect('/login');
        }

        $result = Auth::attempt($identifier, $password);

        if ($result['ok']) {
            redirect('/dashboard');
        }

        if (($result['status'] ?? '') === 'pending') {
            redirect('/login?state=pending');
        }
        if (($result['status'] ?? '') === 'rejected') {
            redirect('/login?state=rejected');
        }

        // invalid — sticky identifier, jangan bocorkan field mana yang salah
        Session::flash('login_error', $result['error'] ?? 'Kombinasi email/username & password tidak cocok.');
        Session::flash('login_identifier', $identifier);
        redirect('/login');
    }

    public static function register(): void
    {
        if (!Session::checkCsrf()) {
            Session::flash('register_error', 'Sesi kedaluwarsa — coba isi ulang form.');
            redirect('/register');
        }

        $old = [
            'name'     => trim((string) ($_POST['name'] ?? '')),
            'username' => trim((string) ($_POST['username'] ?? '')),
            'email'    => trim((string) ($_POST['email'] ?? '')),
            'reason'   => trim((string) ($_POST['reason'] ?? '')),
        ];

        $result = User::register(
            $old['name'],
            $old['email'],
            $old['username'],
            (string) ($_POST['password'] ?? ''),
            $old['reason']
        );

        if (!$result['ok']) {
            Session::flash('register_error', $result['error']);
            Session::flash('register_old', $old);
            redirect('/register');
        }

        redirect('/pending-approval');
    }

    public static function logout(): void
    {
        Auth::logout();
        redirect('/login');
    }
}
