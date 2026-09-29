<?php

namespace App\Http\Controllers;

use App\Models\Dispensasi;
use App\Models\Pengguna;
use App\Services\DispensasiJurnalService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
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

        if (session('role') === 'sekretaris') {
            abort_unless((int) $dispensasi->id_kelas === (int) session('id_kelas'), 403);
        }

        $ticketUrl = $dispensasi->status === Dispensasi::STATUS_DISETUJUI
            && $dispensasi->ticket_token
            && $this->suratMasihBerlaku($dispensasi)
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

        abort_unless($this->suratMasihBerlaku($dispensasi), 410, 'Surat ini sudah tidak berlaku.');

        return view('surat-dispensasi', [
            'dispensasi' => $dispensasi,
            'ticketUrl' => route('surat-dispensasi.ticket', [
                'id' => $dispensasi->id_dispensasi,
                'ticketToken' => $dispensasi->ticket_token,
            ]),
        ]);
    }

    public function unduhUntukSekretaris(int $id)
    {
        $dispensasi = $this->suratSekretarisYangBerlaku($id);
        $this->catatAksesSurat($dispensasi, 'unduh');

        return $this->pdfSurat($dispensasi)->download($dispensasi->nomor_surat.'.pdf');
    }

    public function lihatUntukSekretaris(int $id)
    {
        $dispensasi = $this->suratSekretarisYangBerlaku($id);
        $this->catatAksesSurat($dispensasi, 'lihat');

        return $this->pdfSurat($dispensasi)->stream($dispensasi->nomor_surat.'.pdf');
    }

    private function suratSekretarisYangBerlaku(int $id): Dispensasi
    {
        abort_unless(session('role') === 'sekretaris', 403);

        $idKelas = session('id_kelas');
        abort_unless($idKelas, 403);

        $dispensasi = Dispensasi::query()
            ->with(['siswa', 'kelas', 'guruPiket', 'wakasek'])
            ->whereKey($id)
            ->where('id_kelas', $idKelas)
            ->where('status', Dispensasi::STATUS_DISETUJUI)
            ->firstOrFail();

        abort_unless($this->suratMasihBerlaku($dispensasi), 410, 'Surat ini sudah tidak berlaku.');

        if (! $dispensasi->nomor_surat) {
            $tanggal = Carbon::parse($dispensasi->tanggal)->format('Ymd');
            $dispensasi->forceFill([
                'nomor_surat' => 'DIS-'.$tanggal.'-'.str_pad((string) $dispensasi->id_dispensasi, 5, '0', STR_PAD_LEFT),
            ])->save();
        }

        return $dispensasi;
    }

    private function catatAksesSurat(Dispensasi $dispensasi, string $aksi): void
    {
        DB::table('dispensasi_unduhan')->insert([
            'id_dispensasi' => $dispensasi->id_dispensasi,
            'id_pengguna' => session('id_pengguna'),
            'aksi' => $aksi,
            'diunduh_pada' => Carbon::now('Asia/Jakarta'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pdfSurat(Dispensasi $dispensasi)
    {
        $ticketUrl = route('surat-dispensasi.ticket', [
            'id' => $dispensasi->id_dispensasi,
            'ticketToken' => $dispensasi->ticket_token,
        ]);

        return Pdf::loadView('surat-dispensasi-pdf', [
            'dispensasi' => $dispensasi,
            'ticketUrl' => $ticketUrl,
        ])->setPaper('a4');
    }

    private function suratMasihBerlaku(Dispensasi $dispensasi): bool
    {
        $now = Carbon::now('Asia/Jakarta');
        $tanggal = Carbon::parse($dispensasi->tanggal, 'Asia/Jakarta');

        if (! $tanggal->isSameDay($now)) {
            return false;
        }

        if ($dispensasi->jenis_dispensasi !== 'Per Jam') {
            return true;
        }

        if (! $dispensasi->jam_mulai || ! $dispensasi->jam_selesai) {
            return false;
        }

        $mulai = Carbon::parse($tanggal->toDateString().' '.$dispensasi->jam_mulai, 'Asia/Jakarta')->subMinutes(15);
        $selesai = Carbon::parse($tanggal->toDateString().' '.$dispensasi->jam_selesai, 'Asia/Jakarta');

        return $now->betweenIncluded($mulai, $selesai);
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

    public function setujui($token, $wakasek)
    {
        $berhasil = DB::transaction(function () use (
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
            $dispensasi->catatan_wakasek = null;

            $dispensasi->save();

            return true;
        });

        if (! $berhasil) {
            return $this->redirectAfterDecision(
                $token,
                $wakasek,
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

        return $this->redirectAfterDecision(
            $token,
            $wakasek,
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
            return $this->redirectAfterDecision(
                $token,
                $wakasek,
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

        return $this->redirectAfterDecision(
            $token,
            $wakasek,
            'success',
            'Dispensasi ditolak. Penolakan telah dikirim ke Guru Piket.'
        );
    }

    private function redirectAfterDecision(
        string $token,
        int|string $wakasek,
        string $messageType,
        string $message
    ): RedirectResponse {
        $isWakasekSession = (int) session('id_pengguna') === (int) $wakasek
            && session('role') === 'wakasek';

        $destination = $isWakasekSession
            ? route('wakasek').'#dispensasi'
            : route('approve-dispensasi', [
                'token' => $token,
                'wakasek' => $wakasek,
            ]);

        return redirect()->to($destination)->with($messageType, $message);
    }
}
