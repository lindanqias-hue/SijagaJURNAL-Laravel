<?php

use Livewire\Component;
use App\Models\Siswa;
use App\Models\Kelas;
use App\Models\Dispensasi;
use App\Models\Jadwal;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\GuruPiketAccessService;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    /*
    |--------------------------------------------------------------------------
    | FORM
    |--------------------------------------------------------------------------
    */

    public $id_kelas = '';

    public $siswaTerpilih = [];

    public $jenis_dispensasi = 'Per Jam';

    public $id_jadwal = '';
    public $mapel = '';

    public $jam_ke_mulai = '';
    public $jam_ke_selesai = '';

    public $jam_mulai = '';
    public $jam_selesai = '';

    public $tanggal_sekarang = '';
    public $jam_sekarang = '';

    public $alasan = '';

    public string $jenisSurat = 'Dispensasi';
    public string $idWakasek = '';
    public $lampiran;

    /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */

    public $kelasList = [];
    public $siswaList = [];
    public $wakasekList = [];

    public $jadwalAktif = null;

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP
    |--------------------------------------------------------------------------
    */

    public $waLinkWakasek = null;
    public $waLinksWakasek = [];

    public bool $bolehInputPiket = false;

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount()
    {
        $this->bolehInputPiket = session('role') === 'guru'
            && app(GuruPiketAccessService::class)
            ->bertugasHariIni((int) session('id_pengguna'));

        $this->kelasList = Kelas::orderBy('nama_kelas')->get();
        $this->wakasekList = Pengguna::query()
            ->where('role', 'wakasek')
            ->whereNotNull('no_hp')
            ->orderBy('nama')
            ->get();

        $this->updateWaktu();
    }

    public function updatedJenisSurat(): void
    {
        if ($this->jenisSurat === 'Izin') {
            $this->jenis_dispensasi = 'Sehari Penuh';
            $this->updatedJenisDispensasi();
        }
    }

    private function piketMasihBertugas(): bool
    {
        return session('role') === 'guru'
            && app(GuruPiketAccessService::class)
            ->bertugasHariIni((int) session('id_pengguna'));
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE WAKTU
    |--------------------------------------------------------------------------
    */

    public function updateWaktu()
    {
        $now = Carbon::now('Asia/Jakarta');

        $this->jam_sekarang = $now->format('H:i:s');
        $this->tanggal_sekarang = $now->translatedFormat('d F Y');

        $this->cariJadwalAktif();
    }

    /*
    |--------------------------------------------------------------------------
    | KETIKA KELAS DIPILIH
    |--------------------------------------------------------------------------
    */

    public function updatedIdKelas($value)
    {
        $this->siswaTerpilih = [];

        $this->jadwalAktif = null;

        $this->jam_ke_mulai = '';
        $this->jam_ke_selesai = '';
        $this->jam_mulai = '';
        $this->jam_selesai = '';
        $this->id_jadwal = '';
        $this->mapel = '';

        if (!$value) {
            $this->siswaList = [];
            return;
        }

        $this->siswaList = Siswa::where('id_kelas', $value)
            ->orderBy('nama_siswa')
            ->get();

        $this->cariJadwalAktif();
    }

    /*
    |--------------------------------------------------------------------------
    | CARI JADWAL
    |--------------------------------------------------------------------------
    */

    public function cariJadwalAktif()
    {
        if (!$this->id_kelas) {
            return;
        }

        $now = Carbon::now('Asia/Jakarta');

        $hari = $now
            ->locale('id')
            ->translatedFormat('l');

        $jamSekarang = $now->format('H:i:s');

        $jadwalHariIni = $this->jadwalHariIni;

        $this->jadwalAktif = collect($jadwalHariIni)
            ->first(function ($jadwal) use ($jamSekarang) {

                return $jadwal->jam_mulai <= $jamSekarang
                    && $jadwal->jam_selesai >= $jamSekarang;
            });

        if (!$this->jadwalAktif) {
            $this->jadwalAktif = collect($jadwalHariIni)->first();
        }

        if (!$this->jadwalAktif) {
            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';

            return;
        }

        $this->jam_ke_mulai = $this->jadwalAktif->jam_ke;
        $this->jam_ke_selesai = $this->jadwalAktif->jam_ke;
        $this->jam_mulai = $this->jadwalAktif->jam_mulai;
        $this->jam_selesai = $this->jadwalAktif->jam_selesai;
    }

    public function getJadwalHariIniProperty(): \Illuminate\Support\Collection
    {
        if (!$this->id_kelas) {
            return collect();
        }

        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l');

        return Jadwal::query()
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->where('jadwal.id_kelas', $this->id_kelas)
            ->where('jadwal.hari', $hariIni)
            ->select('jadwal.*', 'pengguna.mapel_diampu', 'pengguna.nama as nama_guru')
            ->orderBy('jam_ke')
            ->get();
    }

    public function updatedJamKeMulai($value): void
    {
        $jadwalMulai = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $value);

        if (!$jadwalMulai) {
            return;
        }

        $this->jam_ke_mulai = $jadwalMulai->jam_ke;

        if (!$this->jam_ke_selesai || (int) $this->jam_ke_selesai < (int) $value) {
            $this->jam_ke_selesai = $jadwalMulai->jam_ke;
        }

        $jadwalSelesai = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $this->jam_ke_selesai);
        $this->jam_mulai = $jadwalMulai->jam_mulai;
        $this->jam_selesai = $jadwalSelesai?->jam_selesai ?? $jadwalMulai->jam_selesai;
    }

    /*
    |--------------------------------------------------------------------------
    | KETIKA JAM SELESAI BERUBAH
    |--------------------------------------------------------------------------
    */

    public function updatedJamKeSelesai($value)
    {
        if (!$value) {
            return;
        }

        $jadwal = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $value);

        if ($jadwal) {
            $this->jam_selesai = $jadwal->jam_selesai;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | JENIS DISPENSASI
    |--------------------------------------------------------------------------
    */

    public function updatedJenisDispensasi()
    {
        if ($this->jenis_dispensasi === 'Sehari Penuh') {

            $this->id_jadwal = '';
            $this->mapel = '';
            $this->jam_ke_mulai = null;
            $this->jam_ke_selesai = null;
            $this->jam_mulai = null;
            $this->jam_selesai = null;
        } elseif ($this->jenis_dispensasi === 'Per Mapel') {
            $this->id_jadwal = $this->jadwalAktif?->id_jadwal ?? '';
            $this->updatedIdJadwal($this->id_jadwal);
        } else {

            $this->id_jadwal = '';
            $this->mapel = '';
            $this->cariJadwalAktif();
        }
    }

    public function updatedIdJadwal($value): void
    {
        if (!$value || $this->jenis_dispensasi !== 'Per Mapel') {
            return;
        }

        $jadwal = $this->jadwalMapelTerpilih;

        if (!$jadwal) {
            $this->id_jadwal = '';
            $this->mapel = '';
            return;
        }

        $this->jam_ke_mulai = $jadwal->jam_ke;
        $this->jam_ke_selesai = $jadwal->jam_ke;
        $this->jam_mulai = $jadwal->jam_mulai;
        $this->jam_selesai = $jadwal->jam_selesai;
        $this->mapel = $jadwal->mapel_diampu ?? '';
    }

    public function getJadwalMapelTerpilihProperty(): ?Jadwal
    {
        if (!$this->id_jadwal || !$this->id_kelas) {
            return null;
        }

        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l');

        return Jadwal::query()
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->where('jadwal.id_jadwal', $this->id_jadwal)
            ->where('jadwal.id_kelas', $this->id_kelas)
            ->where('jadwal.hari', $hariIni)
            ->select('jadwal.*', 'pengguna.mapel_diampu', 'pengguna.nama as nama_guru')
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | NAMA KELAS
    |--------------------------------------------------------------------------
    */

    public function getKelasNamaProperty()
    {
        return collect($this->kelasList)
            ->firstWhere('id_kelas', $this->id_kelas)
            ?->nama_kelas ?? '-';
    }

    /*
    |--------------------------------------------------------------------------
    | SISWA YANG DIPILIH
    |--------------------------------------------------------------------------
    */

    public function getSiswaTerpilihDataProperty()
    {
        if (empty($this->siswaTerpilih)) {
            return collect();
        }

        return Siswa::whereIn(
            'id_siswa',
            $this->siswaTerpilih
        )
            ->where('id_kelas', $this->id_kelas)
            ->orderBy('nama_siswa')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    public function simpan(): void
    {
        if (! $this->piketMasihBertugas()) {
            $this->addError('piket', 'Anda tidak memiliki hak akses piket hari ini.');

            return;
        }

        if ($this->jenisSurat === 'Izin') {
            $this->jenis_dispensasi = 'Sehari Penuh';
            $this->jam_ke_mulai = null;
            $this->jam_ke_selesai = null;
            $this->jam_mulai = null;
            $this->jam_selesai = null;
            $this->id_jadwal = '';
        }

        $this->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'siswaTerpilih' => 'required|array|min:1',
            'siswaTerpilih.*' => ['required', Rule::exists('siswa', 'id_siswa')],
            'jenisSurat' => 'required|in:Dispensasi,Izin',
            'idWakasek' => [
                'nullable',
                'required_if:jenisSurat,Dispensasi',
                Rule::exists('pengguna', 'id_pengguna')->where(fn($query) => $query->where('role', 'wakasek')->whereNotNull('no_hp')),
            ],
            'jenis_dispensasi' => 'required_if:jenisSurat,Dispensasi|in:Per Jam,Sehari Penuh,Per Mapel',
            'jam_ke_mulai' => 'required_if:jenis_dispensasi,Per Jam|nullable|integer|min:1',
            'jam_ke_selesai' => 'required_if:jenis_dispensasi,Per Jam|nullable|integer|min:1',
            'id_jadwal' => 'required_if:jenis_dispensasi,Per Mapel|nullable|exists:jadwal,id_jadwal',
            'alasan' => 'required|string|min:3|max:1000',
            'lampiran' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'id_kelas.required' => 'Kelas wajib dipilih.',
            'siswaTerpilih.required' => 'Minimal pilih satu siswa.',
            'siswaTerpilih.min' => 'Minimal pilih satu siswa.',
            'alasan.required' => 'Alasan dispensasi wajib diisi.',
            'alasan.min' => 'Alasan minimal 3 karakter.',
            'lampiran.required' => 'Unggah foto atau file bukti terlebih dahulu.',
            'lampiran.mimes' => 'Bukti harus berupa JPG, PNG, WEBP, atau PDF.',
            'lampiran.max' => 'Ukuran bukti maksimal 5 MB.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | PASTIKAN SEMUA SISWA MEMANG DARI KELAS TERPILIH
        |--------------------------------------------------------------------------
        */

        $siswaData = Siswa::whereIn(
            'id_siswa',
            $this->siswaTerpilih
        )
            ->where('id_kelas', $this->id_kelas)
            ->orderBy('nama_siswa')
            ->get();

        if ($siswaData->count() !== count($this->siswaTerpilih)) {

            $this->addError(
                'siswaTerpilih',
                'Ada siswa yang tidak sesuai dengan kelas.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | PER JAM
        |--------------------------------------------------------------------------
        */

        if ($this->jenisSurat === 'Dispensasi' && $this->jenis_dispensasi === 'Per Jam') {
            $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l');
            $jadwalJam = Jadwal::query()
                ->where('id_kelas', $this->id_kelas)
                ->where('hari', $hariIni)
                ->whereIn('jam_ke', [(int) $this->jam_ke_mulai, (int) $this->jam_ke_selesai])
                ->get()
                ->keyBy('jam_ke');
            $jadwalMulai = $jadwalJam->get((int) $this->jam_ke_mulai);
            $jadwalSelesai = $jadwalJam->get((int) $this->jam_ke_selesai);

            if (!$jadwalMulai || !$jadwalSelesai) {
                $this->addError('jam_ke_mulai', 'Jam harus dipilih dari jadwal kelas hari ini.');

                return;
            }

            if ((int) $jadwalSelesai->jam_ke < (int) $jadwalMulai->jam_ke) {
                $this->addError('jam_ke_selesai', 'Jam selesai tidak boleh sebelum jam mulai.');

                return;
            }

            $this->jam_mulai = $jadwalMulai->jam_mulai;
            $this->jam_selesai = $jadwalSelesai->jam_selesai;
        }

        $jadwalMapel = null;
        $guruMapel = null;
        $mapelSnapshot = null;

        if ($this->jenisSurat === 'Dispensasi' && $this->jenis_dispensasi === 'Per Mapel') {
            $jadwalMapel = Jadwal::query()
                ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
                ->where('jadwal.id_jadwal', $this->id_jadwal)
                ->where('jadwal.id_kelas', $this->id_kelas)
                ->where('jadwal.hari', Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'))
                ->select('jadwal.*', 'pengguna.mapel_diampu')
                ->first();

            if (!$jadwalMapel) {
                $this->addError('id_jadwal', 'Mapel tidak tersedia untuk kelas dan tanggal ini.');
                return;
            }

            $guruMapel = $jadwalMapel->id_guru;
            $mapelSnapshot = $jadwalMapel->mapel_diampu;
            $this->jam_ke_mulai = $jadwalMapel->jam_ke;
            $this->jam_ke_selesai = $jadwalMapel->jam_ke;
            $this->jam_mulai = $jadwalMapel->jam_mulai;
            $this->jam_selesai = $jadwalMapel->jam_selesai;
        }

        /*
        |--------------------------------------------------------------------------
        | SEHARI PENUH
        |--------------------------------------------------------------------------
        */

        if ($this->jenisSurat === 'Izin' || $this->jenis_dispensasi === 'Sehari Penuh') {

            $this->jam_ke_mulai = null;
            $this->jam_ke_selesai = null;

            $this->jam_mulai = null;
            $this->jam_selesai = null;
        }

        $tanggal = Carbon::now('Asia/Jakarta')->toDateString();
        $lampiranPath = $this->lampiran->store('surat-piket', 'local');

        $dispensasiList = DB::transaction(function () use (
            $siswaData,
            $tanggal,
            $jadwalMapel,
            $guruMapel,
            $mapelSnapshot,
            $lampiranPath
        ) {
            $created = collect();

            foreach ($siswaData as $siswa) {
                $dispensasi = Dispensasi::create([
                    'id_siswa' => $siswa->id_siswa,
                    'id_kelas' => $this->id_kelas,
                    'id_jadwal' => $jadwalMapel?->id_jadwal,
                    'id_guru' => $guruMapel,
                    'mapel' => $mapelSnapshot,
                    'jenis_dispensasi' => $this->jenis_dispensasi,
                    'jenis_surat' => $this->jenisSurat,
                    'tanggal' => $tanggal,
                    'jam_ke' => $this->jam_ke_mulai,
                    'jam_ke_mulai' => $this->jam_ke_mulai,
                    'jam_ke_selesai' => $this->jam_ke_selesai,
                    'jam_mulai' => $this->jam_mulai,
                    'jam_selesai' => $this->jam_selesai,
                    'alasan' => $this->alasan,
                    'lampiran_path' => $lampiranPath,
                    'id_guru_piket' => session('id_pengguna'),
                    'id_wakasek' => $this->jenisSurat === 'Dispensasi' ? $this->idWakasek : null,
                    'token' => Str::random(64),
                    'ticket_token' => Str::random(64),
                    'status' => $this->jenisSurat === 'Izin'
                        ? Dispensasi::STATUS_DISETUJUI
                        : Dispensasi::STATUS_MENUNGGU,
                ]);

                if ($this->jenisSurat === 'Izin') {
                    app(\App\Services\DispensasiJurnalService::class)->sync($dispensasi);
                }

                $created->push($dispensasi);
            }

            return $created;
        });

        $this->waLinkWakasek = null;
        $this->waLinksWakasek = [];
        if ($this->jenisSurat === 'Dispensasi') {
            $wakasek = Pengguna::query()
                ->where('id_pengguna', $this->idWakasek)
                ->where('role', 'wakasek')
                ->firstOrFail();
            $semuaTerkirim = true;

            foreach ($dispensasiList as $dispensasi) {
                $linkApproval = route('approve-dispensasi', [
                    'token' => $dispensasi->token,
                    'wakasek' => $wakasek->id_pengguna,
                ]);
                $keteranganWaktu = $this->jenis_dispensasi === 'Per Jam'
                    ? 'Jam ke-' . $this->jam_ke_mulai . ' s/d ' . $this->jam_ke_selesai
                    : 'Sehari penuh';
                $pesanWa = "PENGAJUAN DISPENSASI\n\n"
                    . 'Siswa: ' . $dispensasi->siswa?->nama_siswa . "\n"
                    . 'Kelas: ' . $this->kelasNama . "\n"
                    . 'Tanggal: ' . $this->tanggal_sekarang . "\n"
                    . 'Waktu: ' . $keteranganWaktu . "\n"
                    . 'Alasan: ' . $this->alasan . "\n"
                    . 'Guru Piket: ' . session('nama') . "\n\n"
                    . 'Buka link untuk memberikan keputusan: ' . $linkApproval;

                if (! app(\App\Services\WhatsAppMessageService::class)->sendText($wakasek->no_hp, $pesanWa)) {
                    $semuaTerkirim = false;
                    $nomorWa = preg_replace('/\D+/', '', $wakasek->no_hp);
                    $nomorWa = str_starts_with($nomorWa, '0')
                        ? '62' . substr($nomorWa, 1)
                        : (str_starts_with($nomorWa, '62') ? $nomorWa : '62' . $nomorWa);

                    $this->waLinksWakasek[] = [
                        'nama' => $wakasek->nama,
                        'siswa' => $dispensasi->siswa?->nama_siswa,
                        'link' => 'https://wa.me/' . $nomorWa . '?text=' . urlencode($pesanWa),
                    ];
                }
            }

            $this->waLinkWakasek = $this->waLinksWakasek[0]['link'] ?? null;

            if ($semuaTerkirim) {
                session()->flash('success', 'Pengajuan tersimpan dan notifikasi WhatsApp berhasil terkirim ke ' . $wakasek->nama . '.');
            } else {
                session()->flash('warning', 'Pengajuan tersimpan, tetapi notifikasi WhatsApp belum terkirim. Gunakan tautan cadangan atau periksa konfigurasi WhatsApp API.');
            }
        } else {
            session()->flash('success', 'Surat Izin berhasil disimpan.');
        }

        /*
        |--------------------------------------------------------------------------
        | RESET FORM / INPUT
        |--------------------------------------------------------------------------
        */

        $this->siswaTerpilih = [];
        $this->alasan = '';
        $this->lampiran = null;
    }
};
?>

<div>

    {{-- HEADER --}}
    <div class="mb-4">
        <h2 class="fw-bold mb-1">
            {{ $jenisSurat === 'Izin' ? 'Input Surat Izin dari Luar' : 'Pengajuan Dispensasi' }}
        </h2>
        <p class="text-muted mb-0">
            Pilih kelas dan siswa, lalu lengkapi surat serta bukti pendukung.
        </p>
    </div>

    {{-- NOTIFIKASI BERHASIL / PERINGATAN --}}
    @if (session()->has('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>
        <strong>Berhasil!</strong> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if (session()->has('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Perhatian!</strong> {{ session('warning') }}
        @if ($waLinkWakasek)
        <div class="mt-2">
            <a href="{{ $waLinkWakasek }}" target="_blank" class="btn btn-sm btn-success">
                Kirim Manual via WhatsApp
            </a>
        </div>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    {{-- FORM --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">

            @unless ($this->bolehInputPiket)
            <div class="alert alert-warning" role="status">Anda tidak memiliki hak akses piket hari ini</div>
            @endunless

            <fieldset class="border-0 p-0 m-0" @disabled(!$this->bolehInputPiket)>
                <div class="row g-3">

                    {{-- KELAS --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold">Kelas</label>
                        <select wire:model.live="id_kelas" class="form-select">
                            <option value="">-- Pilih Kelas --</option>
                            @foreach ($kelasList as $kelas)
                            <option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>
                            @endforeach
                        </select>
                        @error('id_kelas')
                        <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    {{-- SISWA --}}
                    @if ($id_kelas)
                    <div class="col-12">
                        <label class="form-label fw-semibold">Siswa yang Mendapat Dispensasi/Izin</label>
                        @if ($siswaList->count())
                        <div class="border rounded p-3" style="max-height: 280px; overflow-y: auto;">
                            @foreach ($siswaList as $siswa)
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" value="{{ $siswa->id_siswa }}"
                                    wire:model.live="siswaTerpilih" id="siswa-{{ $siswa->id_siswa }}">
                                <label class="form-check-label" for="siswa-{{ $siswa->id_siswa }}">
                                    {{ $siswa->nama_siswa }}
                                </label>
                            </div>
                            @endforeach
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-primary">
                                Siswa: {{ count($siswaTerpilih) }} orang
                            </span>
                        </div>
                        @else
                        <div class="alert alert-warning">
                            Belum ada siswa di kelas ini.
                        </div>
                        @endif
                        @error('siswaTerpilih')
                        <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="jenis-surat">Jenis Surat</label>
                        <select id="jenis-surat" wire:model.live="jenisSurat" class="form-select">
                            <option value="Dispensasi">Surat Dispensasi</option>
                            <option value="Izin">Surat Izin dari Luar</option>
                        </select>
                    </div>

                    @if ($jenisSurat === 'Dispensasi')
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Jenis Dispensasi</label>
                        <select wire:model.live="jenis_dispensasi" class="form-select">
                            <option value="Per Jam">Per Jam</option>
                            <option value="Sehari Penuh">Sehari Penuh</option>
                            <option value="Per Mapel">Per Mapel</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="id-wakasek">Wakasek Penanggung Jawab</label>
                        <select id="id-wakasek" wire:model="idWakasek" class="form-select">
                            <option value="">-- Pilih Wakasek --</option>
                            @foreach ($wakasekList as $wakasek)
                            <option value="{{ $wakasek->id_pengguna }}">{{ $wakasek->nama }}</option>
                            @endforeach
                        </select>
                        @error('idWakasek')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>
                    @endif

                    {{-- TANGGAL --}}
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tanggal</label>
                        <div class="form-control bg-light">
                            {{ $tanggal_sekarang }}
                        </div>
                    </div>

                    {{-- JAM SEKARANG --}}
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Jam Sekarang</label>
                        <div id="jam-sekarang" class="form-control bg-light fw-semibold">
                            {{ $jam_sekarang }}
                        </div>
                    </div>

                    {{-- MAPEL --}}
                    @if ($jenis_dispensasi === 'Per Mapel')
                    <div class="col-12">
                        <label class="form-label fw-semibold">Mapel yang Diikuti</label>
                        <select wire:model.live="id_jadwal" class="form-select">
                            <option value="">-- Pilih Mapel --</option>
                            @foreach ($this->jadwalHariIni as $jadwal)
                            <option value="{{ $jadwal->id_jadwal }}">
                                {{ $jadwal->mapel_diampu ?: 'Mapel belum diisi' }}
                                · Jam ke-{{ $jadwal->jam_ke }}
                                ({{ Carbon::parse($jadwal->jam_mulai)->format('H:i') }}-{{ Carbon::parse($jadwal->jam_selesai)->format('H:i') }})
                                · {{ $jadwal->nama_guru }}
                            </option>
                            @endforeach
                        </select>
                        @error('id_jadwal')
                        <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="lampiran">Foto atau berkas bukti</label>
                        <input id="lampiran" type="file" wire:model="lampiran" accept=".jpg,.jpeg,.png,.webp,.pdf"
                            class="form-control @error('lampiran') is-invalid @enderror">
                        <div class="form-text">JPG, PNG, WEBP, atau PDF. Maksimal 5 MB.</div>
                        @error('lampiran')<small class="text-danger">{{ $message }}</small>@enderror
                        <div wire:loading wire:target="lampiran" class="small text-muted">Mengunggah bukti...</div>
                        @if ($lampiran && str_starts_with($lampiran->getMimeType(), 'image/'))
                        <img src="{{ $lampiran->temporaryUrl() }}" alt="Pratinjau bukti" class="mt-2 rounded border"
                            style="max-width:180px; max-height:180px; object-fit:cover;">
                        @endif
                    </div>

                    {{-- JADWAL --}}
                    @if ($jenis_dispensasi === 'Per Jam')
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Mulai Dispensasi</label>
                        <select wire:model.live="jam_ke_mulai" class="form-select">
                            @forelse ($this->jadwalHariIni as $jadwal)
                            <option value="{{ $jadwal->jam_ke }}">
                                Jam ke-{{ $jadwal->jam_ke }}
                                ·
                                {{ Carbon::parse($jadwal->jam_mulai)->format('H:i') }}–{{ Carbon::parse($jadwal->jam_selesai)->format('H:i') }}
                            </option>
                            @empty
                            <option value="">Tidak ada jadwal untuk kelas ini hari ini</option>
                            @endforelse
                        </select>
                        @error('jam_ke_mulai')
                        <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Sampai Jam</label>
                        <select wire:model.live="jam_ke_selesai" class="form-select">
                            @if ($jadwalAktif)
                            @foreach ($this->jadwalHariIni as $jadwal)
                            @if ($jadwal->jam_ke >= $jam_ke_mulai)
                            <option value="{{ $jadwal->jam_ke }}">
                                Jam ke-{{ $jadwal->jam_ke }}
                                —
                                {{ Carbon::parse($jadwal->jam_mulai)->format('H:i') }}
                                -
                                {{ Carbon::parse($jadwal->jam_selesai)->format('H:i') }}
                            </option>
                            @endif
                            @endforeach
                            @endif
                        </select>
                        @error('jam_ke_selesai')
                        <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    @endif

                    {{-- ALASAN --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="alasan">Alasan / Keterangan</label>
                        <textarea id="alasan" wire:model="alasan" class="form-control" rows="3"
                            placeholder="Tuliskan alasan pengajuan..."></textarea>
                        @error('alasan')<small class="text-danger">{{ $message }}</small>@enderror
                    </div>

                    {{-- TOMBOL SUBMIT --}}
                    <div class="col-12 text-end mt-4">
                        <button type="button" wire:click="simpan" class="btn btn-primary px-4">
                            Simpan Pengajuan
                        </button>
                    </div>

                </div>
            </fieldset>
        </div>
    </div>
</div>