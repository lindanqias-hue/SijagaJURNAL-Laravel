<?php

use App\Models\Dispensasi;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Services\DispensasiJurnalService;
use App\Services\GuruPiketAccessService;
use App\Services\WhatsAppMessageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    /*
    |--------------------------------------------------------------------------
    | FORM PROPERTIES
    |--------------------------------------------------------------------------
    */

    /** @var array<int, string> Kelas yang dipilih (bisa lebih dari satu). */
    public array $id_kelas_terpilih = [];

    /** @var array<int, array<int, int>> [id_kelas => [id_siswa, ...]] */
    public array $siswaTerpilih = [];

    public string $jenis_dispensasi = 'Per Jam';
    public string $id_jadwal = '';
    public string $mapel = '';

    public string $jam_ke_mulai = '';
    public string $jam_ke_selesai = '';
    public string $jam_mulai = '';
    public string $jam_selesai = '';

    public string $tanggal_sekarang = '';
    public string $jam_sekarang = '';
    public string $alasan = '';

    public string $jenisSurat = 'Dispensasi';
    public string $idWakasek = '';
    public $lampiran;

    /*
    |--------------------------------------------------------------------------
    | DATA LISTS
    |--------------------------------------------------------------------------
    */

    public array $kelasList = [];
    public array $wakasekList = [];
    public array $siswaList = [];

    /** @var array<int, string> Cache jumlah siswa per kelas. */
    public array $jumlahSiswaTerpilih = [];

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP & ACCESS
    |--------------------------------------------------------------------------
    */

    public ?string $waLinkWakasek = null;
    public array $waLinksWakasek = [];
    public bool $bolehInputPiket = false;

    /** @var mixed Jadwal terpilih (Jadwal model atau null). */
    public $jadwalAktif = null;

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

        $sekretarisPerKelas = Pengguna::query()
            ->where('role', 'sekretaris')
            ->whereNotNull('id_kelas')
            ->orderBy('nama')
            ->get()
            ->groupBy('id_kelas');

        // Nama sekretaris ikut ditampilkan karena surat hanya muncul di menu
        // Surat Dispensasi milik sekretaris kelas tersebut.
        $this->kelasList = Kelas::query()
            ->orderBy('nama_kelas')
            ->get()
            ->map(function (Kelas $kelas) use ($sekretarisPerKelas): array {
                return [
                    'id_kelas' => $kelas->id_kelas,
                    'nama_kelas' => $kelas->nama_kelas,
                    'nama_sekretaris' => $sekretarisPerKelas->get($kelas->id_kelas)?->pluck('nama')->join(', ') ?? '',
                ];
            })
            ->all();

        $this->wakasekList = Pengguna::query()
            ->where('role', 'wakasek')
            ->whereNotNull('no_hp')
            ->orderBy('nama')
            ->get(['id_pengguna', 'nama', 'no_hp'])
            ->all();

        $this->updateWaktu();
    }

    public function updatedJenisSurat(): void
    {
        // Jika Izin atau Sakit, otomatis diset Sehari Penuh
        if (in_array($this->jenisSurat, ['Izin', 'Sakit'], true)) {
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

    public function updatedIdKelasTerpilih(mixed $value): void
    {
        $this->id_kelas_terpilih = $this->normalisasiKelasTerpilih($value);

        $this->siswaList = [];
        $this->siswaTerpilih = [];
        $this->jadwalAktif = null;
        $this->jam_ke_mulai = '';
        $this->jam_ke_selesai = '';
        $this->jam_mulai = '';
        $this->jam_selesai = '';
        $this->id_jadwal = '';
        $this->mapel = '';

        if ($this->id_kelas_terpilih === []) {
            $this->jumlahSiswaTerpilih = [];

            return;
        }

        $this->jumlahSiswaTerpilih = [];

        foreach ($this->id_kelas_terpilih as $idKelas) {
            $idKelas = (int) $idKelas;
            $this->siswaList[$idKelas] = Siswa::query()
                ->where('id_kelas', $idKelas)
                ->orderBy('nama_siswa')
                ->get(['id_siswa', 'nama_siswa'])
                ->all();
            $this->siswaTerpilih[$idKelas] = [];
            $this->jumlahSiswaTerpilih[$idKelas] = 0;
        }

        $this->cariJadwalAktif();
    }

    /**
     * Bersihkan pilihan kelas: bisa datang dari <select multiple> sebagai
     * array, string tunggal, atau string kosong dari Livewire.
     *
     * @return array<int, string>
     */
    private function normalisasiKelasTerpilih(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return collect($value)
            ->map(fn ($nilai): string => (string) $nilai)
            ->filter(fn (string $nilai): bool => $nilai !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function toggleSiswa(int $idKelas, int $idSiswa): void
    {
        $terpilih = $this->siswaTerpilih[(int) $idKelas] ?? [];

        if (in_array($idSiswa, $terpilih, true)) {
            $this->siswaTerpilih[(int) $idKelas] = array_values(
                array_diff($terpilih, [$idSiswa])
            );
        } else {
            $terpilih[] = $idSiswa;
            $this->siswaTerpilih[(int) $idKelas] = $terpilih;
        }

        $this->jumlahSiswaTerpilih[(int) $idKelas] = count($this->siswaTerpilih[(int) $idKelas]);
    }

    public function pilihSemuaSiswa(int $idKelas): void
    {
        $siswa = $this->siswaList[(int) $idKelas] ?? [];

        $idSiswa = array_map(fn (Siswa $s): int => (int) $s->id_siswa, $siswa);

        $sudahSemua = count($this->siswaTerpilih[(int) $idKelas] ?? []) === count($idSiswa);

        if ($sudahSemua) {
            $this->siswaTerpilih[(int) $idKelas] = [];
            $this->jumlahSiswaTerpilih[(int) $idKelas] = 0;

            return;
        }

        $this->siswaTerpilih[(int) $idKelas] = $idSiswa;
        $this->jumlahSiswaTerpilih[(int) $idKelas] = count($idSiswa);
    }

    /*
    |--------------------------------------------------------------------------
    | CARI JADWAL
    |--------------------------------------------------------------------------
    */

    public function cariJadwalAktif()
    {
        if (! $this->id_kelas_terpilih) {
            return;
        }

        $now = Carbon::now('Asia/Jakarta');
        $jamSekarang = $now->format('H:i:s');
        $jadwalHariIni = $this->jadwalHariIni;

        $this->jadwalAktif = collect($jadwalHariIni)
            ->first(function ($jadwal) use ($jamSekarang): bool {
                return $jadwal->jam_mulai <= $jamSekarang
                    && $jadwal->jam_selesai >= $jamSekarang;
            })
            ?? collect($jadwalHariIni)->first();

        if (! $this->jadwalAktif) {
            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';

            return;
        }

        $this->jam_ke_mulai = (string) $this->jadwalAktif->jam_ke;
        $this->jam_ke_selesai = (string) $this->jadwalAktif->jam_ke;
        $this->jam_mulai = $this->jadwalAktif->jam_mulai;
        $this->jam_selesai = $this->jadwalAktif->jam_selesai;
    }

    public function getJadwalHariIniProperty(): \Illuminate\Support\Collection
    {
        if (! $this->id_kelas_terpilih) {
            return collect();
        }

        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l');

        return Jadwal::query()
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->whereIn('jadwal.id_kelas', $this->id_kelas_terpilih)
            ->where('jadwal.hari', $hariIni)
            ->select('jadwal.*', 'pengguna.mapel_diampu', 'pengguna.nama as nama_guru')
            ->orderBy('jadwal.id_kelas')
            ->orderBy('jadwal.jam_ke')
            ->get();
    }

    public function updatedJamKeMulai($value): void
    {
        $jadwalMulai = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $value);

        if (! $jadwalMulai) {
            return;
        }

        $this->jam_ke_mulai = (string) $jadwalMulai->jam_ke;

        if (! $this->jam_ke_selesai || (int) $this->jam_ke_selesai < (int) $value) {
            $this->jam_ke_selesai = (string) $jadwalMulai->jam_ke;
        }

        $jadwalSelesai = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $this->jam_ke_selesai);

        $this->jam_mulai = $jadwalMulai->jam_mulai;
        $this->jam_selesai = $jadwalSelesai?->jam_selesai ?? $jadwalMulai->jam_selesai;
    }

    public function updatedJamKeSelesai($value)
    {
        if (! $value) {
            return;
        }

        $jadwal = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $value);

        if ($jadwal) {
            $this->jam_selesai = $jadwal->jam_selesai;
        }
    }

    public function updatedJenisDispensasi()
    {
        if ($this->jenis_dispensasi === 'Sehari Penuh') {
            $this->id_jadwal = '';
            $this->mapel = '';
            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';
        } elseif ($this->jenis_dispensasi === 'Per Mapel') {
            $this->id_jadwal = (string) ($this->jadwalAktif?->id_jadwal ?? '');
            $this->updatedIdJadwal($this->id_jadwal);
        } else {
            $this->id_jadwal = '';
            $this->mapel = '';
            $this->cariJadwalAktif();
        }
    }

    public function updatedIdJadwal($value): void
    {
        if (! $value || $this->jenis_dispensasi !== 'Per Mapel') {
            return;
        }

        $jadwal = $this->jadwalMapelTerpilih;

        if (! $jadwal) {
            $this->id_jadwal = '';
            $this->mapel = '';

            return;
        }

        $this->jam_ke_mulai = (string) $jadwal->jam_ke;
        $this->jam_ke_selesai = (string) $jadwal->jam_ke;
        $this->jam_mulai = $jadwal->jam_mulai;
        $this->jam_selesai = $jadwal->jam_selesai;
        $this->mapel = $jadwal->mapel_diampu ?? '';
    }

    public function getJadwalMapelTerpilihProperty(): ?Jadwal
    {
        if (! $this->id_jadwal || ! $this->id_kelas_terpilih) {
            return null;
        }

        $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l');

        return Jadwal::query()
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->where('jadwal.id_jadwal', $this->id_jadwal)
            ->whereIn('jadwal.id_kelas', $this->id_kelas_terpilih)
            ->where('jadwal.hari', $hariIni)
            ->select('jadwal.*', 'pengguna.mapel_diampu', 'pengguna.nama as nama_guru')
            ->first();
    }

    public function getNamaKelasTerpilihProperty(): string
    {
        if (! $this->id_kelas_terpilih) {
            return '-';
        }

        $nama = collect($this->kelasList)
            ->whereIn('id_kelas', array_map('intval', $this->id_kelas_terpilih))
            ->pluck('nama_kelas')
            ->all();

        return $nama === [] ? '-' : implode(', ', $nama);
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN PENGAJUAN (MULTI-KELAS / CROSS-CLASS)
    |--------------------------------------------------------------------------
    */

    public function simpan(): void
    {
        if (! $this->piketMasihBertugas()) {
            $this->addError('piket', 'Anda tidak memiliki hak akses piket hari ini.');

            return;
        }

        // Normalisasi ulang: nilai dari <select multiple> bisa berupa string.
        $this->id_kelas_terpilih = $this->normalisasiKelasTerpilih($this->id_kelas_terpilih);
        // Jika Surat Izin atau Sakit, format waktunya otomatis Sehari Penuh
        if (in_array($this->jenisSurat, ['Izin', 'Sakit'], true)) {
            $this->jenis_dispensasi = 'Sehari Penuh';
            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';
            $this->id_jadwal = '';
        }

        $this->validate([
            'id_kelas_terpilih' => 'required|array|min:1',
            'id_kelas_terpilih.*' => ['required', Rule::exists('kelas', 'id_kelas')],
            'siswaTerpilih' => 'required|array|min:1',
            'jenisSurat' => 'required|in:Dispensasi,Izin,Sakit',
            'idWakasek' => [
                'nullable',
                'required_if:jenisSurat,Dispensasi',
                Rule::exists('pengguna', 'id_pengguna')->where(fn ($query) => $query->where('role', 'wakasek')->whereNotNull('no_hp')),
            ],
            'jenis_dispensasi' => 'required_if:jenisSurat,Dispensasi|in:Per Jam,Sehari Penuh,Per Mapel',
            'jam_ke_mulai' => 'required_if:jenis_dispensasi,Per Jam|nullable|integer|min:1',
            'jam_ke_selesai' => 'required_if:jenis_dispensasi,Per Jam|nullable|integer|min:1',
            'id_jadwal' => 'required_if:jenis_dispensasi,Per Mapel|nullable|exists:jadwal,id_jadwal',
            'alasan' => 'required|string|min:3|max:1000',
            'lampiran' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'id_kelas_terpilih.required' => 'Minimal pilih satu kelas.',
            'siswaTerpilih.required' => 'Minimal pilih satu siswa.',
            'alasan.required' => 'Alasan wajib diisi.',
            'alasan.min' => 'Alasan minimal 3 karakter.',
            'lampiran.required' => 'Unggah foto atau file bukti terlebih dahulu.',
            'lampiran.mimes' => 'Bukti harus berupa JPG, PNG, WEBP, atau PDF.',
            'lampiran.max' => 'Ukuran bukti maksimal 5 MB.',
        ]);

        /*
         * Bersihkan kelas tanpa siswa terpilih.
         */
        foreach ($this->siswaTerpilih as $idKelas => $siswa) {
            if ($siswa === []) {
                unset($this->siswaTerpilih[$idKelas]);
            }
        }

        if ($this->siswaTerpilih === []) {
            $this->addError('siswaTerpilih', 'Minimal pilih satu siswa pada kelas yang dipilih.');

            return;
        }

        /*
         * Validasi jam per jam dari jadwal hari ini (per kelas).
         */
        if ($this->jenisSurat === 'Dispensasi' && $this->jenis_dispensasi === 'Per Jam') {
            $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l');

            foreach (array_keys($this->siswaTerpilih) as $idKelas) {
                $jadwalJam = Jadwal::query()
                    ->where('id_kelas', (int) $idKelas)
                    ->where('hari', $hariIni)
                    ->whereIn('jam_ke', [(int) $this->jam_ke_mulai, (int) $this->jam_ke_selesai])
                    ->get()
                    ->keyBy('jam_ke');

                $jadwalMulai = $jadwalJam->get((int) $this->jam_ke_mulai);
                $jadwalSelesai = $jadwalJam->get((int) $this->jam_ke_selesai);

                if (! $jadwalMulai || ! $jadwalSelesai) {
                    $this->addError('jam_ke_mulai', 'Jam harus dipilih dari jadwal kelas hari ini.');

                    return;
                }

                if ((int) $jadwalSelesai->jam_ke < (int) $jadwalMulai->jam_ke) {
                    $this->addError('jam_ke_selesai', 'Jam selesai tidak boleh sebelum jam mulai.');

                    return;
                }
            }

            $this->jam_mulai = (string) Jadwal::query()
                ->whereIn('id_kelas', array_map('intval', $this->id_kelas_terpilih))
                ->where('hari', $hariIni)
                ->where('jam_ke', (int) $this->jam_ke_mulai)
                ->value('jam_mulai');

            $this->jam_selesai = (string) Jadwal::query()
                ->whereIn('id_kelas', array_map('intval', $this->id_kelas_terpilih))
                ->where('hari', $hariIni)
                ->where('jam_ke', (int) $this->jam_ke_selesai)
                ->value('jam_selesai');
        }

        $mapelSnapshot = null;

        if ($this->jenisSurat === 'Dispensasi' && $this->jenis_dispensasi === 'Per Mapel') {
            $hariIni = Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l');

            $jadwalMapel = Jadwal::query()
                ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
                ->where('jadwal.id_jadwal', $this->id_jadwal)
                ->where('jadwal.hari', $hariIni)
                ->select('jadwal.*', 'pengguna.mapel_diampu')
                ->first();

            if (! $jadwalMapel) {
                $this->addError('id_jadwal', 'Mapel tidak tersedia untuk kelas dan tanggal ini.');

                return;
            }

            $mapelSnapshot = $jadwalMapel->mapel_diampu;
            $this->jam_ke_mulai = (string) $jadwalMapel->jam_ke;
            $this->jam_ke_selesai = (string) $jadwalMapel->jam_ke;
            $this->jam_mulai = $jadwalMapel->jam_mulai;
            $this->jam_selesai = $jadwalMapel->jam_selesai;
        }

        if (in_array($this->jenisSurat, ['Izin', 'Sakit'], true) || $this->jenis_dispensasi === 'Sehari Penuh') {
            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';
        }

        $tanggal = Carbon::now('Asia/Jakarta')->toDateString();
        $lampiranPath = $this->lampiran->store('surat-piket', 'local');

        $created = DB::transaction(function () use ($tanggal, $lampiranPath, $mapelSnapshot) {
            $semua = collect();

            foreach ($this->siswaTerpilih as $idKelas => $idSiswaList) {
                $siswaData = Siswa::query()
                    ->whereIn('id_siswa', $idSiswaList)
                    ->where('id_kelas', (int) $idKelas)
                    ->orderBy('nama_siswa')
                    ->get();

                foreach ($siswaData as $siswa) {
                    $statusSurat = in_array($this->jenisSurat, ['Izin', 'Sakit'], true)
                        ? Dispensasi::STATUS_DISETUJUI
                        : Dispensasi::STATUS_MENUNGGU;

                    $dispensasi = Dispensasi::create([
                        'id_siswa' => $siswa->id_siswa,
                        'id_kelas' => (int) $idKelas,
                        'id_jadwal' => $this->jenisSurat === 'Dispensasi' && $this->jenis_dispensasi === 'Per Mapel'
                            ? $this->id_jadwal
                            : null,
                        'id_guru' => $this->jenisSurat === 'Dispensasi' && $this->jenis_dispensasi === 'Per Mapel'
                            ? ($this->jadwalMapelTerpilih?->id_guru ?? null)
                            : null,
                        'mapel' => $mapelSnapshot,
                        'jenis_dispensasi' => $this->jenis_dispensasi,
                        'jenis_surat' => $this->jenisSurat,
                        'tanggal' => $tanggal,
                        'jam_ke' => $this->jam_ke_mulai !== '' ? (int) $this->jam_ke_mulai : null,
                        'jam_ke_mulai' => $this->jam_ke_mulai !== '' ? (int) $this->jam_ke_mulai : null,
                        'jam_ke_selesai' => $this->jam_ke_selesai !== '' ? (int) $this->jam_ke_selesai : null,
                        'jam_mulai' => $this->jam_mulai ?: null,
                        'jam_selesai' => $this->jam_selesai ?: null,
                        'alasan' => $this->alasan,
                        'lampiran_path' => $lampiranPath,
                        'id_guru_piket' => (int) session('id_pengguna'),
                        'id_wakasek' => $this->jenisSurat === 'Dispensasi' ? $this->idWakasek : null,
                        'token' => Str::random(64),
                        'ticket_token' => Str::random(64),
                        'status' => $statusSurat,
                    ]);

                    // Jika Izin atau Sakit, langsung sinkronisasi otomatis ke jurnal mengajar guru
                    if (in_array($this->jenisSurat, ['Izin', 'Sakit'], true)) {
                        app(DispensasiJurnalService::class)->sync($dispensasi);
                    }

                    $semua->push($dispensasi);
                }
            }

            return $semua;
        });

        $this->waLinkWakasek = null;
        $this->waLinksWakasek = [];

        if ($this->jenisSurat === 'Dispensasi') {
            $wakasek = Pengguna::query()
                ->where('id_pengguna', $this->idWakasek)
                ->where('role', 'wakasek')
                ->firstOrFail();

            $semuaTerkirim = true;

            foreach ($created as $dispensasi) {
                $linkApproval = route('approve-dispensasi', [
                    'token' => $dispensasi->token,
                    'wakasek' => $wakasek->id_pengguna,
                ]);

                $keteranganWaktu = $this->jenis_dispensasi === 'Per Jam'
                    ? 'Jam ke-'.$this->jam_ke_mulai.' s/d '.$this->jam_ke_selesai
                    : 'Sehari penuh';

                $pesanWa = "PENGAJUAN DISPENSASI\n\n"
                    . 'Siswa: '.$dispensasi->siswa?->nama_siswa."\n"
                    . 'Kelas: '.($dispensasi->kelas?->nama_kelas ?? '-')."\n"
                    . 'Tanggal: '.$this->tanggal_sekarang."\n"
                    . 'Waktu: '.$keteranganWaktu."\n"
                    . 'Alasan: '.$this->alasan."\n"
                    . 'Guru Piket: '.session('nama')."\n\n"
                    . 'Buka link untuk memberikan keputusan: '.$linkApproval;

                if (! app(WhatsAppMessageService::class)->sendText($wakasek->no_hp, $pesanWa)) {
                    $semuaTerkirim = false;

                    $nomorWa = preg_replace('/\D+/', '', (string) $wakasek->no_hp) ?? '';
                    $nomorWa = str_starts_with($nomorWa, '0')
                        ? '62'.substr($nomorWa, 1)
                        : (str_starts_with($nomorWa, '62') ? $nomorWa : '62'.$nomorWa);

                    $this->waLinksWakasek[] = [
                        'nama' => $wakasek->nama,
                        'siswa' => $dispensasi->siswa?->nama_siswa,
                        'link' => 'https://wa.me/'.$nomorWa.'?text='.urlencode($pesanWa),
                    ];
                }
            }

            $this->waLinkWakasek = $this->waLinksWakasek[0]['link'] ?? null;

            if ($semuaTerkirim) {
                session()->flash('success', 'Pengajuan tersimpan dan notifikasi WhatsApp berhasil terkirim ke '.$wakasek->nama.'.');
            } else {
                session()->flash('warning', 'Pengajuan tersimpan, tetapi notifikasi WhatsApp belum terkirim. Gunakan tautan cadangan atau periksa konfigurasi WhatsApp API.');
            }
        } else {
            $namaSuratLabel = $this->jenisSurat === 'Sakit' ? 'Surat Sakit' : 'Surat Izin';

            session()->flash('success', $namaSuratLabel.' berhasil disimpan dan disinkronkan ke jurnal.');
        }

        $this->siswaTerpilih = [];
        $this->alasan = '';
        $this->lampiran = null;
        $this->jumlahSiswaTerpilih = [];
    }
};
?>
<div>
    {{-- HEADER --}}
    <div class="role-page-header">
        <h2>
            @if ($jenisSurat === 'Izin')
            Input Surat Izin dari Luar
            @elseif ($jenisSurat === 'Sakit')
            Input Surat Sakit
            @else
            Pengajuan Dispensasi
            @endif
        </h2>
        <p>
            Pilih kelas dan siswa, lalu lengkapi surat serta bukti pendukung.
        </p>
    </div>
    <div class="role-page-actions mb-3">
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

            <form wire:submit="simpan">
                <fieldset class="border-0 p-0 m-0" @disabled(!$this->bolehInputPiket)>
                    <div class="row g-3">

                        {{-- KELAS (CROSS-CLASS: bisa lebih dari satu) --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold">Kelas</label>
                            <select wire:model.live="id_kelas_terpilih" class="form-select" multiple size="4">
                                @foreach ($kelasList as $kelas)
                                <option value="{{ $kelas['id_kelas'] }}">{{ $kelas['nama_kelas'] }}@if ($kelas['nama_sekretaris']) — {{ $kelas['nama_sekretaris'] }}@endif</option>
                                @endforeach
                            </select>
                            <div class="form-text">Tahan Ctrl (Windows) atau Cmd (Mac) untuk memilih lebih dari satu kelas.</div>
                            @error('id_kelas_terpilih')
                            <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        {{-- SISWA PER KELAS --}}
                        @if (! empty($siswaList))
                        @foreach ($siswaList as $idKelas => $siswaItems)
                        <div class="col-12" wire:key="siswa-kelas-{{ $idKelas }}">
                            <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                <span>
                                    Siswa {{ collect($kelasList)->firstWhere('id_kelas', (int) $idKelas)['nama_kelas'] ?? '' }}
                                </span>
                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                    wire:click="pilihSemuaSiswa({{ (int) $idKelas }})">
                                    Pilih / Batal Semua
                                </button>
                            </label>
                            @if (count($siswaItems))
                            <div class="border rounded p-3" style="max-height: 240px; overflow-y: auto;">
                                @foreach ($siswaItems as $siswa)
                                <div class="form-check mb-2" wire:key="siswa-{{ $idKelas }}-{{ $siswa['id_siswa'] }}">
                                    <input class="form-check-input" type="checkbox"
                                        value="{{ $siswa['id_siswa'] }}"
                                        wire:click="toggleSiswa({{ (int) $idKelas }}, {{ $siswa['id_siswa'] }})"
                                        @checked(in_array($siswa['id_siswa'], $siswaTerpilih[$idKelas] ?? [], true))
                                        id="siswa-{{ $idKelas }}-{{ $siswa['id_siswa'] }}">
                                    <label class="form-check-label" for="siswa-{{ $idKelas }}-{{ $siswa['id_siswa'] }}">
                                        {{ $siswa['nama_siswa'] }}
                                    </label>
                                </div>
                                @endforeach
                            </div>
                            <div class="mt-2">
                                <span class="badge bg-primary">
                                    {{ $jumlahSiswaTerpilih[(int) $idKelas] ?? 0 }} siswa dipilih
                                </span>
                            </div>
                            @else
                            <div class="alert alert-warning">Belum ada siswa di kelas ini.</div>
                            @endif
                        </div>
                        @endforeach
                        @error('siswaTerpilih')
                        <div class="col-12"><small class="text-danger">{{ $message }}</small></div>
                        @enderror
                        @endif

                        {{-- JENIS SURAT (Opsi Sakit Ditambahkan Di Sini) --}}
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="jenis-surat">Jenis Surat</label>
                            <select id="jenis-surat" wire:model.live="jenisSurat" class="form-select">
                                <option value="Dispensasi">Surat Dispensasi</option>
                                <option value="Izin">Surat Izin dari Luar</option>
                                <option value="Sakit">Surat Sakit</option>
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
                        @if ($jenisSurat === 'Dispensasi' && $jenis_dispensasi === 'Per Mapel')
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

                        {{-- JADWAL PER JAM --}}
                        @if ($jenisSurat === 'Dispensasi' && $jenis_dispensasi === 'Per Jam')
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
                            <label class="form-label fw-semibold" for="alasan">Alasan</label>
                            <textarea id="alasan" wire:model="alasan" rows="3"
                                class="form-control @error('alasan') is-invalid @enderror"
                                placeholder="Tuliskan alasan izin/sakit/dispensasi..."></textarea>
                            @error('alasan')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>

                        {{-- LAMPIRAN --}}
                        <div class="col-12">
                            <label class="form-label fw-semibold" for="lampiran">Foto atau berkas bukti</label>
                            <input id="lampiran" type="file" wire:model="lampiran" accept=".jpg,.jpeg,.png,.webp,.pdf"
                                class="form-control @error('lampiran') is-invalid @enderror">
                            <div class="form-text">JPG, PNG, WEBP, atau PDF. Maksimal 5 MB.</div>
                            @error('lampiran')<small class="text-danger">{{ $message }}</small>@enderror
                            <div wire:loading wire:target="lampiran" class="small text-muted mt-1">Mengunggah bukti...
                            </div>
                            @if ($lampiran && method_exists($lampiran, 'getMimeType') &&
                            str_starts_with($lampiran->getMimeType(), 'image/'))
                            <img src="{{ $lampiran->temporaryUrl() }}" alt="Pratinjau bukti" class="mt-2 rounded border"
                                style="max-width:180px; max-height:180px; object-fit:cover;">
                            @endif
                        </div>

                        {{-- TOMBOL SIMPAN --}}
                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-4" @disabled(!$this->bolehInputPiket)>
                                Simpan Pengajuan
                            </button>
                        </div>

                    </div>
                </fieldset>
            </form>

        </div>
    </div>
</div>
