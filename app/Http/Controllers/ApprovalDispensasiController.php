<?php

namespace App\Http\Controllers;

use App\Models\Dispensasi;
use App\Models\Pengguna;
use App\Services\DispensasiJurnalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApprovalDispensasiController extends Controller
{
    public function show($token, $wakasek)
    {
        $dispensasi = Dispensasi::with([
            'siswa',
            'kelas',
            'guruPiket',
            'wakasek',
        ])
            ->where('token', $token)
            ->where('id_wakasek', $wakasek)
            ->firstOrFail();

        $wakasekData = Pengguna::where('id_pengguna', $wakasek)
            ->where('role', 'wakasek')
            ->firstOrFail();

        return view('approve-dispensasi', [
            'dispensasi' => $dispensasi,
            'wakasek' => $wakasekData,
        ]);
    }

    public function detail(int $id)
    {
        abort_unless(session()->has('id_pengguna'), 403);

        $dispensasi = Dispensasi::with([
            'siswa',
            'kelas',
            'guruPiket',
            'wakasek',
        ])->findOrFail($id);

        $ticketUrl = $dispensasi->status === Dispensasi::STATUS_DISETUJUI && $dispensasi->ticket_token
            ? route('surat-dispensasi.ticket', [
                'id' => $dispensasi->id_dispensasi,
                'ticketToken' => $dispensasi->ticket_token,
            ])
            : null;

        return view('surat-dispensasi', [
            'dispensasi' => $dispensasi,
            'ticketUrl' => $ticketUrl,
        ]);
    }

    public function ticket(int $id, string $ticketToken)
    {
        $dispensasi = Dispensasi::query()
            ->with(['siswa', 'kelas', 'guruPiket', 'wakasek'])
            ->whereKey($id)
            ->where('ticket_token', $ticketToken)
            ->where('status', Dispensasi::STATUS_DISETUJUI)
            ->firstOrFail();

        return view('surat-dispensasi', [
            'dispensasi' => $dispensasi,
            'ticketUrl' => route('surat-dispensasi.ticket', [
                'id' => $dispensasi->id_dispensasi,
                'ticketToken' => $dispensasi->ticket_token,
            ]),
        ]);
    }

    public function lampiran(int $id, string $token): BinaryFileResponse
    {
        $dispensasi = Dispensasi::query()
            ->whereKey($id)
            ->where(function ($query) use ($token): void {
                $query->where('token', $token)
                    ->orWhere('ticket_token', $token);
            })
            ->firstOrFail();

        abort_unless($dispensasi->lampiran_path && Storage::disk('local')->exists($dispensasi->lampiran_path), 404);

        return response()->download(Storage::disk('local')->path($dispensasi->lampiran_path));
    }

    public function setujui(Request $request, $token, $wakasek)
    {
        $request->validate([
            'catatan_wakasek' => 'nullable|string|max:500',
        ]);

        $berhasil = DB::transaction(function () use (
            $request,
            $token,
            $wakasek
        ) {
            $dispensasi = Dispensasi::where('token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $dispensasi->id_wakasek !== (int) $wakasek) {
                return false;
            }

            // Kalau sudah diproses Wakasek lain
            if ($dispensasi->status !== 'Menunggu Persetujuan') {
                return false;
            }

            $wakasekData = Pengguna::where(
                'id_pengguna',
                $wakasek
            )
                ->where('role', 'wakasek')
                ->first();

            if (! $wakasekData) {
                return false;
            }

            $dispensasi->status = 'Disetujui';
            $dispensasi->id_wakasek = $wakasekData->id_pengguna;
            $dispensasi->waktu_approval = Carbon::now('Asia/Jakarta');
            $dispensasi->catatan_wakasek =
                $request->catatan_wakasek ?: null;

            $dispensasi->save();

            return true;
        });

        if (! $berhasil) {
            return redirect()
                ->route('approve-dispensasi', [
                    'token' => $token,
                    'wakasek' => $wakasek,
                ])
                ->with(
                    'error',
                    'Dispensasi ini sudah diproses oleh Wakasek lain.'
                );
        }

        // Masukkan dispensasi ke jurnal + kirim notifikasi ke guru yang mengajar
        $dispensasi = Dispensasi::where('token', $token)->firstOrFail();

        app(DispensasiJurnalService::class)
            ->sync($dispensasi);

        // Notifikasi untuk Guru Piket
        DB::table('dispensasi_penerima')->updateOrInsert(
            [
                'id_dispensasi' => $dispensasi->id_dispensasi,
                'id_guru' => $dispensasi->id_guru_piket,
            ],
            [
                'dibaca_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return redirect()
            ->route('approve-dispensasi', [
                'token' => $token,
                'wakasek' => $wakasek,
            ])
            ->with(
                'success',
                'Dispensasi berhasil disetujui. Persetujuan telah dikirim ke Guru Piket.'
            );
    }

    public function tolak(Request $request, $token, $wakasek)
    {
        $request->validate([
            'catatan_wakasek' => 'required|string|max:500',
        ], [
            'catatan_wakasek.required' => 'Catatan wajib diisi saat menolak.',
        ]);

        $berhasil = DB::transaction(function () use (
            $request,
            $token,
            $wakasek
        ) {
            $dispensasi = Dispensasi::where('token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $dispensasi->id_wakasek !== (int) $wakasek) {
                return false;
            }

            if ($dispensasi->status !== 'Menunggu Persetujuan') {
                return false;
            }

            $wakasekData = Pengguna::where(
                'id_pengguna',
                $wakasek
            )
                ->where('role', 'wakasek')
                ->first();

            if (! $wakasekData) {
                return false;
            }

            $dispensasi->status = 'Ditolak';
            $dispensasi->id_wakasek = $wakasekData->id_pengguna;
            $dispensasi->waktu_approval = Carbon::now('Asia/Jakarta');
            $dispensasi->catatan_wakasek =
                $request->catatan_wakasek;

            $dispensasi->save();

            return true;
        });

        if (! $berhasil) {
            return redirect()
                ->route('approve-dispensasi', [
                    'token' => $token,
                    'wakasek' => $wakasek,
                ])
                ->with(
                    'error',
                    'Dispensasi ini sudah diproses oleh Wakasek lain.'
                );
        }

        $dispensasi = Dispensasi::where('token', $token)->firstOrFail();

        // Notifikasi untuk Guru Piket
        DB::table('dispensasi_penerima')->updateOrInsert(
            [
                'id_dispensasi' => $dispensasi->id_dispensasi,
                'id_guru' => $dispensasi->id_guru_piket,
            ],
            [
                'dibaca_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return redirect()
            ->route('approve-dispensasi', [
                'token' => $token,
                'wakasek' => $wakasek,
            ])
            ->with(
                'success',
                'Dispensasi ditolak. Penolakan telah dikirim ke Guru Piket.'
            );
    }
}
