# DEMO SCRIPT — OGES-SHIELD (Kartika Cyber, kategori offensive security)

> Walkthrough 8–10 menit. Target demo: **low.my.id** (domain milik sendiri, lab,
> in-scope). Jangan scan asset publik lain — scope enforcement tetap aktif,
> jadi demo tetap aman secara etika.

## 0. Pra-demo (H-15 menit) — checklist

- [ ] Docker MySQL jalan: `docker ps | grep oges-mysql`
- [ ] PHP jalan: `php -S 127.0.0.1:8000 -t public` (127.0.0.1:8000 → 200)
- [ ] Orchestrator jalan: `curl 127.0.0.1:8100/health` → `{"status":"ok", "version":"0.4.0"}`
- [ ] Login admin: `admin` / (password seed — lihat catatan tim, JANGAN commit)
- [ ] Run "backup" sudah completed di DB (mis. 66260477 low.my.id + report.pdf)
  → dipakai kalau AI/GLM lambat saat live demo (rencana B)

## 1. Buka Dashboard (30 detik)

- Login → `/dashboard`. Tunjukkan: 6 stat card data asli, Security Summary
  (donut severity), AI Orchestration Log (keputusan AI dari run sebelumnya).
- Poin bicara: "dashboard SOC dark, semua angka dari DB asli — bukan mock."

## 2. Story akun baru (1 menit) — buka tab incognito

- `/register` → isi form + **justifikasi akses** → pending-approval page.
- Balik tab admin: **Admin → Approvals** → lihat justifikasi → **Approve**.
- Tab incognito: login akun baru → masuk. (RBAC + approval flow live.)

## 3. Daftar target + scope (30 detik)

- **Asset Discovery** → Register Target (contoh isi: domain sendiri) → toggle In Scope.
- Poin: "scan HANYA asset yang user daftarkan in-scope — tidak ada hardcoded
  target. Parameter AI yang menyimpang ditolak orchestrator."

## 4. Template + Set Workflow (1 menit)

- **Automation Workflows**: tunjukkan 2 template bawaan + 1 custom (recon-ringan-custom).
- (Opsional showcase) **+ Buat Template** → klik kartu phase → preview pipeline live.
- **Set Workflow** → target low.my.id + template full-web-app-assessment → Start.

## 5. Live Run — momen utama (3–4 menit)

- Halaman run: stepper phase + terminal + Module Pipeline update real-time (polling 2s).
- Ceritakan loop: AI (GLM-5.3) baca hasil → pilih tool (ai_decision) → tool jalan
  (subfinder → httpx → nuclei) → AI summary → ulang sampai phase cukup.
- Safety net: "max 10 tool/phase + 15 menit/phase — loop tidak bisa infinite."
- Kalau AI pilih tool destructive → modal konfirmasi muncul → tunjukkan
  **Izinkan / Tolak** (user tetap memegang kendali — human-in-the-loop).

## 6. Findings + Report (1–2 menit)

- **Findings**: tabel temuan + AI enrichment (summary/impact/remediation +
  severity tervalidasi ulang).
- **Reports** → Download PDF → buka: cover, executive summary, tabel severity,
  detail per finding, methodology.

## 7. Tutup dengan security-first (30 detik)

- **Audit Logs**: semua aksi tercatat (tool_executed, scope_violation, login) —
  filter + export CSV. Tidak bisa dihapus dari UI.
- Poin penutup: guardrail 9 point PROMPT — scope server-side, AI tak pernah
  rakit command (hanya pilih tool + parameter tervalidasi), subprocess tanpa
  shell, key AI cuma di orchestrator .env.

## Rencana B (kalau AI lambat / gagal live)

1. Jangan panik — buka run backup 66260477 (completed + report.pdf).
2. Terminal sudah tampilkan seluruh event loop (ai_decision → tool → summary).
3. Bilang: "run ini tadi dieksekusi penuh oleh AI — berikut jejak lengkapnya."
4. Regenerate report kalau perlu: Reports → Regenerate.

## Data demo yang tersedia

- Target in-scope: `low.my.id` (utama), `example.com` (backup).
- Runs completed dengan report: 66260477, 3c044989, a1035f0c.
- Akun: admin (superadmin), tester (user biasa — buat demo ownership), bad01 (rejected).
