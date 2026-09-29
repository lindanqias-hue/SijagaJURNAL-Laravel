<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Surat Dispensasi {{ $dispensasi->nomor_surat }}</title>
    <style>
        @page {
            margin: 42px 48px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 12px;
        }

        .school {
            text-align: center;
            border-bottom: 2px solid #172033;
            padding-bottom: 14px;
            margin-bottom: 24px;
        }

        .school h1 {
            font-size: 18px;
            margin: 0 0 5px;
        }

        .school p {
            margin: 0;
            color: #52627d;
        }

        h2 {
            text-align: center;
            font-size: 16px;
            text-decoration: underline;
            margin: 20px 0 4px;
        }

        .number {
            text-align: center;
            margin-bottom: 26px;
            font-size: 13px;
        }

        .student {
            border: 2px solid #172033;
            text-align: center;
            padding: 17px;
            margin: 20px 0;
        }

        .student .name {
            font-size: 23px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 5px;
        }

        .student .class {
            font-size: 15px;
            margin-top: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
        }

        td {
            padding: 8px 5px;
            vertical-align: top;
        }

        td:first-child {
            width: 28%;
            color: #52627d;
        }

        .notice {
            border: 1px solid #cbd5e1;
            padding: 12px;
            margin-top: 22px;
        }

        .sign {
            width: 42%;
            margin: 38px 0 0 auto;
            text-align: center;
        }

        .sign strong {
            display: block;
            margin-top: 58px;
            text-decoration: underline;
        }

        .verify {
            margin-top: 26px;
            border-top: 1px solid #cbd5e1;
            padding-top: 12px;
            text-align: center;
            color: #52627d;
        }

        .verify img {
            width: 110px;
            height: 110px;
        }

        .foot {
            font-size: 10px;
        }
    </style>
</head>

<body>
    <header class="school">
        <h1>SURAT DISPENSASI SISWA</h1>
        <p>Dokumen resmi yang disetujui Wakil Kepala Sekolah</p>
    </header>
    <h2>{{ $dispensasi->jenis_surat === 'Sakit' ? 'SURAT KETERANGAN SAKIT' : 'SURAT DISPENSASI' }}</h2>
    <div class="number">Nomor: <strong>{{ $dispensasi->nomor_surat }}</strong></div>
    <p>Dengan ini menerangkan bahwa siswa berikut memperoleh {{ strtolower($dispensasi->jenis_surat ?? 'dispensasi') }}:</p>
    <div class="student">
        <div>NAMA SISWA</div>
        <div class="name">{{ $dispensasi->siswa->nama_siswa ?? '-' }}</div>
        <div class="class">Kelas {{ $dispensasi->kelas->nama_kelas ?? '-' }}</div>
    </div>
    <table>
        <tr>
            <td>Tanggal berlaku</td>
            <td><strong>{{ \Carbon\Carbon::parse($dispensasi->tanggal)->translatedFormat('d F Y') }}</strong></td>
        </tr>
        <tr>
            <td>Waktu berlaku</td>
            <td>{{ $dispensasi->jenis_dispensasi === 'Per Jam' ? substr($dispensasi->jam_mulai, 0, 5) . ' sampai ' . substr($dispensasi->jam_selesai, 0, 5) : 'Sehari penuh' }}</td>
        </tr>
        <tr>
            <td>Jenis surat</td>
            <td>{{ $dispensasi->jenis_surat ?? 'Dispensasi' }}</td>
        </tr>
        @if($dispensasi->jenis_surat !== 'Sakit')
        <tr>
            <td>Keterangan</td>
            <td>{{ $dispensasi->alasan ?: '-' }}</td>
        </tr>
        @endif
    </table>
    <div class="notice">Surat ini hanya berlaku untuk nama siswa dan waktu yang tercantum.</div>
    <div class="sign">Disetujui oleh,<br>Wakil Kepala Sekolah<strong>{{ $dispensasi->wakasek->nama ?? 'Wakil Kepala Sekolah' }}</strong></div>
    <div class="verify">
        <div class="foot">Surat berlaku pada {{ \Carbon\Carbon::parse($dispensasi->tanggal)->format('d/m/Y') }}.</div>
    </div>
</body>

</html>