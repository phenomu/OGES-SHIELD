<?php
/**
 * AdminController — approvals (khusus superadmin, guard server-side).
 */

declare(strict_types=1);

final class AdminController
{
    public static function approvals(): void
    {
        Auth::requireSuperadmin();
        View::page('admin/approvals', ['pending' => User::pendingList()]);
    }

    public static function approve(): void
    {
        Auth::requireSuperadmin();
        if (!Session::checkCsrf()) {
            Session::flash('approvals_error', 'Sesi kedaluwarsa — aksi dibatalkan.');
            redirect('/admin/approvals');
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $admin  = Auth::user();
        $target = User::findById($userId);

        if ($target === null || $target['role'] !== 'pending') {
            Session::flash('approvals_error', 'User tidak ditemukan atau sudah diproses.');
            redirect('/admin/approvals');
        }

        if (User::approve($userId, (int) $admin['id'])) {
            Audit::log((int) $admin['id'], 'user_approved', [
                'user_id'   => $userId,
                'username'  => $target['username'],
            ]);
            Session::flash('approvals_ok', 'Akun ' . $target['username'] . ' disetujui — sekarang bisa login.');
        } else {
            Session::flash('approvals_error', 'Gagal approve — akun mungkin sudah diproses.');
        }
        redirect('/admin/approvals');
    }

    public static function reject(): void
    {
        Auth::requireSuperadmin();
        if (!Session::checkCsrf()) {
            Session::flash('approvals_error', 'Sesi kedaluwarsa — aksi dibatalkan.');
            redirect('/admin/approvals');
        }

        $userId = (int) ($_POST['user_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $admin  = Auth::user();
        $target = User::findById($userId);

        if ($target === null || $target['role'] !== 'pending') {
            Session::flash('approvals_error', 'User tidak ditemukan atau sudah diproses.');
            redirect('/admin/approvals');
        }

        if (User::reject($userId, $reason)) {
            Audit::log((int) $admin['id'], 'user_rejected', [
                'user_id'  => $userId,
                'username' => $target['username'],
                'reason'   => substr($reason, 0, 500),
            ]);
            Session::flash('approvals_ok', 'Akun ' . $target['username'] . ' ditolak.');
        } else {
            Session::flash('approvals_error', 'Gagal reject — akun mungkin sudah diproses.');
        }
        redirect('/admin/approvals');
    }
}
