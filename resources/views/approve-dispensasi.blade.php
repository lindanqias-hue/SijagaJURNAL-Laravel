<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Approval Dispensasi</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #1d4ed8;
            color: white;
            padding: 22px;
            border-radius: 14px;
            margin-bottom: 15px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 7px 0 0;
            opacity: .9;
        }

        .card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        .judul {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .data {
            margin-bottom: 14px;
        }

        .label {
            color: #6b7280;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .value {
            font-weight: 600;
            font-size: 15px;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .menunggu {
            background: #fef3c7;
            color: #92400e;
        }

        .disetujui {
            background: #dcfce7;
            color: #166534;
        }

        .ditolak {
            background: #fee2e2;
            color: #991b1b;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 9px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            resize: vertical;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        button {
            flex: 1;
            border: none;
            padding: 13px;
            border-radius: 9px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-setujui {
            background: #16a34a;
            color: white;
        }

        .btn-tolak {
            background: #dc2626;
            color: white;
        }

        button:active {
            transform: scale(.98);
        }

        .alert {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .wakasek {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 12px;
            border-radius: 9px;
            margin-bottom: 15px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 12px;
            }

            .header h1 {
                font-size: 21px;
            }

            .buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>Approval Dispensasi</h1>
        <p>Konfirmasi permohonan dispensasi siswa</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            ✅ {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">
            ❌ {{ session('error') }}
        </div>
    @endif

    <div class="wakasek">
        <strong>Wakasek:</strong>
        {{ $wakasek->nama }}
    </div>

    <div class="card">

        <div class="judul">
            📋 Data Dispensasi
        </div>

        <div class="data">
            <div class="label">Nama Siswa</div>
            <div class="value">
                {{ $dispensasi->siswa->nama_siswa ?? '-' }}
            </div>
        </div>

        <div class="data">
            <div class="label">Kelas</div>
            <div class="value">
                {{ $dispensasi->kelas->nama_kelas ?? '-' }}
            </div>
        </div>

        <div class="data">
            <div class="label">Jenis Dispensasi</div>
            <div class="value">
                {{ $dispensasi->jenis_dispensasi }}
            </div>
        </div>

        <div class="data">
            <div class="label">Tanggal</div>
            <div class="value">
                {{ \Carbon\Carbon::parse($dispensasi->tanggal)->translatedFormat('d F Y') }}
            </div>
        </div>

        @if ($dispensasi->jenis_dispensasi === 'Per Jam')
            <div class="data">
                <div class="label">Jam</div>
                <div class="value">
                    Jam ke-{{ $dispensasi->jam_ke_mulai }}

                    @if ($dispensasi->jam_ke_selesai != $dispensasi->jam_ke_mulai)
                        s/d {{ $dispensasi->jam_ke_selesai }}
                    @endif
                </div>
            </div>
        @endif

        <div class="data">
            <div class="label">Alasan</div>
            <div class="value">
                {{ $dispensasi->alasan }}
            </div>
        </div>

        <div class="data">
            <div class="label">Diajukan oleh Guru Piket</div>
            <div class="value">
                {{ $dispensasi->guruPiket->nama ?? '-' }}
            </div>
        </div>

        <div class="data">
            <div class="label">Status</div>

            @if ($dispensasi->status === 'Menunggu Persetujuan')
                <span class="status menunggu">
                    🟡 Menunggu Persetujuan
                </span>
            @elseif ($dispensasi->status === 'Disetujui')
                <span class="status disetujui">
                    🟢 Disetujui
                </span>
            @elseif ($dispensasi->status === 'Ditolak')
                <span class="status ditolak">
                    🔴 Ditolak
                </span>
            @endif
        </div>

        @if ($dispensasi->status !== 'Menunggu Persetujuan')

            <div class="data">
                <div class="label">Diproses oleh</div>
                <div class="value">
                    {{ $dispensasi->wakasek->nama ?? '-' }}
                </div>
            </div>

            <div class="data">
                <div class="label">Catatan Wakasek</div>
                <div class="value">
                    {{ $dispensasi->catatan_wakasek ?? '-' }}
                </div>
            </div>

        @endif

    </div>

    @if ($dispensasi->status === 'Menunggu Persetujuan')

        <div class="card">

            <div class="judul">
                ✍️ Keputusan Wakasek
            </div>

            <form
                method="POST"
                action="{{ route('approve-dispensasi.setujui', [
                    'token' => $dispensasi->token,
                    'wakasek' => $wakasek->id_pengguna,
                ]) }}"
                onsubmit="return confirm('Apakah Anda yakin ingin menyetujui dispensasi ini?')"
            >
                @csrf

                <div class="data">
                    <div class="label">
                        Catatan
                    </div>

                    <textarea
                        name="catatan_wakasek"
                        placeholder="Tulis catatan jika diperlukan..."
                    ></textarea>
                </div>

                <div class="buttons">

                    <button
                        type="submit"
                        class="btn-setujui"
                    >
                        ✓ SETUJUI
                    </button>

                </div>
            </form>

            <form
                method="POST"
                action="{{ route('approve-dispensasi.tolak', [
                    'token' => $dispensasi->token,
                    'wakasek' => $wakasek->id_pengguna,
                ]) }}"
                onsubmit="return confirm('Apakah Anda yakin ingin menolak dispensasi ini?')"
            >
                @csrf

                <input
                    type="hidden"
                    name="catatan_wakasek"
                    id="catatan-tolak"
                >

                <div class="buttons">

                    <button
                        type="submit"
                        class="btn-tolak"
                        onclick="
                            const catatan = document.querySelector('textarea[name=catatan_wakasek]').value;
                            if (!catatan.trim()) {
                                alert('Catatan wajib diisi jika menolak.');
                                return false;
                            }
                            document.getElementById('catatan-tolak').value = catatan;
                        "
                    >
                        ✕ TOLAK
                    </button>

                </div>

            </form>

        </div>

    @endif

</div>

</body>
</html>