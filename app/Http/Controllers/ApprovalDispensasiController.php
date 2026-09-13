<?php

namespace App\Http\Controllers;

use App\Models\Dispensasi;
use App\Models\Pengguna;
use App\Services\DispensasiJurnalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            ->firstOrFail();

        $wakasekData = Pengguna::where('id_pengguna', $wakasek)
            ->where('role', 'wakasek')
            ->firstOrFail();

        return view('approve-dispensasi', [
            'dispensasi' => $dispensasi,
            'wakasek' => $wakasekData,
        ]);
    }

    public function detail($id)
    {
        $dispensasi = Dispensasi::with([
            'siswa',
            'kelas',
            'guruPiket',
            'wakasek',
        ])->findOrFail($id);

        return view('surat-dispensasi', [
            'dispensasi' => $dispensasi,
        ]);
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

            if (!$wakasekData) {
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

        if (!$berhasil) {
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
            'catatan_wakasek.required' =>
                'Catatan wajib diisi saat menolak.',
        ]);

        $berhasil = DB::transaction(function () use (
            $request,
            $token,
            $wakasek
        ) {
            $dispensasi = Dispensasi::where('token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ($dispensasi->status !== 'Menunggu Persetujuan') {
                return false;
            }

            $wakasekData = Pengguna::where(
                'id_pengguna',
                $wakasek
            )
                ->where('role', 'wakasek')
                ->first();

            if (!$wakasekData) {
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

        if (!$berhasil) {
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