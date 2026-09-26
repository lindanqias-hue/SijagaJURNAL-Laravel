<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Surat Dispensasi</title>

    <style>
        body {
            margin: 0;
            padding: 20px;
            background: #f3f4f6;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .container {
            max-width: 650px;
            margin: 30px auto;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,.08);
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 18px;
            margin-bottom: 20px;
        }

        .header h2 {
            margin: 0;
            font-size: 22px;
        }

        .header p {
            color: #6b7280;
            margin-top: 6px;
        }

        .status {
            text-align: center;
            margin-bottom: 20px;
        }

        .badge {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }

        .approved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .label {
            color: #6b7280;
            font-size: 14px;
        }

        .value {
            font-weight: 600;
            text-align: right;
        }

        .reason {
            margin-top: 20px;
            padding: 15px;
            background: #f9fafb;
            border-radius: 10px;
        }

        .reason-title {
            font-weight: bold;
            margin-bottom: 8px;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: #2563eb;
            font-weight: 600;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="card">

        <div class="header">
            <h2>Surat Dispensasi Siswa</h2>
            <p>Informasi dispensasi yang telah diproses</p>
        </div>

        <div class="status">

            @if($dispensasi->status === 'Disetujui')

                <span class="badge approved">
                    🟢 Disetujui
                </span>

            @elseif($dispensasi->status === 'Ditolak')

                <span class="badge rejected">
                    🔴 Ditolak
                </span>

            @else

                <span class="badge pending">
                    🟡 Menunggu Persetujuan
                </span>

            @endif

        </div>

        <div class="row">
            <div class="label">Nama Siswa</div>
            <div class="value">
                {{ $dispensasi->siswa->nama_siswa ?? '-' }}
            </div>
        </div>

        <div class="row">
            <div class="label">Kelas</div>
            <div class="value">
                {{ $dispensasi->kelas->nama_kelas ?? '-' }}
            </div>
        </div>

        <div class="row">
            <div class="label">Jenis Dispensasi</div>
            <div class="value">
                {{ $dispensasi->jenis_dispensasi }}
            </div>
        </div>

        <div class="row">
            <div class="label">Keterangan</div>
            <div class="value">
                {{ $dispensasi->alasan ?: '-' }}
            </div>
        </div>

        <div class="row">
            <div class="label">Tanggal</div>
            <div class="value">
                {{ \Carbon\Carbon::parse($dispensasi->tanggal)->translatedFormat('d F Y') }}
            </div>
        </div>

        @if($dispensasi->jenis_dispensasi === 'Per Jam')

            <div class="row">
                <div class="label">Jam Pelajaran</div>

                <div class="value">
                    Jam ke-{{ $dispensasi->jam_ke_mulai }}

                    @if($dispensasi->jam_ke_selesai != $dispensasi->jam_ke_mulai)
                        sampai {{ $dispensasi->jam_ke_selesai }}
                    @endif
                </div>
            </div>

            <div class="row">
                <div class="label">Waktu</div>

                <div class="value">
                    {{ substr($dispensasi->jam_mulai, 0, 5) }}
                    -
                    {{ substr($dispensasi->jam_selesai, 0, 5) }}
                </div>
            </div>

        @else

            <div class="row">
                <div class="label">Waktu</div>
                <div class="value">
                    Sehari penuh
                </div>
            </div>

        @endif

        <div class="row">
            <div class="label">Guru Piket</div>
            <div class="value">
                {{ $dispensasi->guruPiket->nama ?? '-' }}
            </div>
        </div>

        @if($dispensasi->wakasek)

            <div class="row">
                <div class="label">Diproses Oleh</div>
                <div class="value">
                    {{ $dispensasi->wakasek->nama ?? '-' }}
                </div>
            </div>

        @endif

        <div class="reason">

            <div class="reason-title">
                Alasan Dispensasi
            </div>

            <div>
                {{ $dispensasi->alasan }}
            </div>

        </div>

        @if($dispensasi->catatan_wakasek)

            <div class="reason">

                <div class="reason-title">
                    Catatan Wakasek
                </div>

                <div>
                    {{ $dispensasi->catatan_wakasek }}
                </div>

            </div>

        @endif

        <a href="{{ route('dashboard') }}" class="back">
            ← Kembali ke Dashboard
        </a>

    </div>

</div>

</body>
</html>
